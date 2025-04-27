<?php
namespace Admidio\SSO\Service;

use Admidio\Preferences\Entity\Preferences;
use Admidio\SSO\Entity\SSOClient;
use LightSaml\Builder\Profile\Metadata\MetadataProfileBuilder;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use LightSaml\Model\Protocol\AuthnRequest;
use LightSaml\Model\Protocol\LogoutRequest;
use LightSaml\Model\Protocol\LogoutResponse;
use LightSaml\Model\Protocol\Response;
use LightSaml\Model\Protocol\SamlMessage;
use LightSaml\Model\Protocol\AttributeQuery;
use LightSaml\Model\Assertion\Assertion;
use LightSaml\Model\Assertion\Subject;
use LightSaml\Model\Assertion\NameID;
use LightSaml\Model\Assertion\AttributeStatement;
use LightSaml\Model\Assertion\Attribute;
use LightSaml\Model\Assertion\EncryptedAssertionWriter;
use LightSaml\Model\Assertion\EncryptedAssertionReader;
use LightSaml\SamlConstants;
use LightSaml\Context\Profile\ProfileContext;
use LightSaml\Credential\X509Certificate;
use LightSaml\Credential\KeyHelper;
use LightSaml\Binding\HttpRedirectBinding;
use LightSaml\Binding\HttpPostBinding;
use LightSaml\Model\Metadata\EntityDescriptor;
use LightSaml\Model\Metadata\KeyDescriptor;
use LightSaml\Model\Metadata\IdpSsoDescriptor;
use LightSaml\Model\Metadata\SingleSignOnService;
use LightSaml\Model\Metadata\SingleLogoutService;
use LightSaml\Model\Context\SerializationContext;
use LightSaml\Model\XmlDSig\SignatureWriter;
use RobRichards\XMLSecLibs\XMLSecurityKey;

use Exception;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Preferences\ValueObject\SettingsManager;
use Admidio\Users\Entity\User;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\UI\Presenter\PagePresenter;

use Admidio\SSO\Entity\SAMLClient;
use Admidio\SSO\Entity\Key;


class SAMLService extends SSOService {
    private $idpEntityId;
    private $ssoUrl;
    private $sloUrl;
    private $metadataUrl;

    public function __construct(Database $db, User $currentUser) {
        global $gSettingsManager;

        parent::__construct($db, $currentUser);
        $this->columnPrefix = 'smc';
        $this->table = TBL_SAML_CLIENTS;

        $this->idpEntityId = $gSettingsManager->get('sso_saml_entity_id');
        $this->ssoUrl      = ADMIDIO_URL . FOLDER_MODULES . '/sso/index.php/saml/sso';
        $this->sloUrl      = ADMIDIO_URL . FOLDER_MODULES . '/sso/index.php/saml/slo';
        $this->metadataUrl = ADMIDIO_URL . FOLDER_MODULES . '/sso/index.php/saml/metadata';
    }

    protected function getRolesRightName(): string {
        return 'sso_saml_access';
    }

    /**
     * Return the SSO endpoint
     * @return string
     */
    public function getSsoEndpoint() {
        return $this->ssoUrl;
    }
    /**
     * Return the SLO endpoint
     * @return string
     */
    public function getSloEndpoint() {
        return $this->sloUrl;
    }
    /**
     * Return the metadata endpoint
     * @return string
     */
    public function getMetadataUrl() {
        return $this->metadataUrl;
    }

    public function getIdPEntityId() : string {
        return $this->idpEntityId;
    }

    public function initializeClientObject($database): ?SSOClient {
        return new SAMLClient($database);
    }


    public function getSignatureWriter(string $privkeyPEM, X509Certificate $cert) {
        $privateKeyResource = KeyHelper::createPrivateKey($privkeyPEM, '', false, XMLSecurityKey::RSA_SHA256);
        $signatureWriter = new SignatureWriter($cert, $privateKeyResource, XmlSecurityDSig::SHA256);
        return $signatureWriter;
    }

    protected function encryptAssertion(Assertion $assertion, SAMLClient $client, bool $encryptAssertionRequired) {
        global $gL10n;
        $certPem = $client->getValue('smc_x509_certificate');
        if (!$certPem) {
            // Client has no cert configured...
            $SPcert = null;
            if ($encryptAssertionRequired) {
                return $gL10n->get('SYS_SSO_SAML_ENCRYPTION_KEY_MISSING');
            } else {
                return false;
            }
        } else {
            $SPcert = new X509Certificate();
            $SPcert->loadPem($certPem);
        }
        $key = KeyHelper::createPublicKey($SPcert);

        $encryptedAssertion = new EncryptedAssertionWriter();
        $encryptedAssertion->encrypt($assertion, $key);
        return $encryptedAssertion;
    }


    protected function receiveMessage() {
        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();

        $bindingFactory = new \LightSaml\Binding\BindingFactory();
        $binding = $bindingFactory->getBindingByRequest($request);

        $messageContext = new \LightSaml\Context\Profile\MessageContext();
        $binding->receive($request, $messageContext);

        $message = $messageContext->getMessage();

        return $messageContext->getMessage();
    }

    public function getKeysCertificates() {
        global $gSettingsManager;

        // Private key and Certificate for signatures
        $signatureKeyID = $gSettingsManager->get('sso_saml_signing_key');
        $signatureKey = new Key($this->db, $signatureKeyID);

        $idpPrivateKeyPem = $signatureKey->getValue('key_private');
        $idpCertPem = $signatureKey->getValue('key_certificate');
        if (!$idpCertPem) {
            $idpCert = null;
        } else {
            $idpCert = new X509Certificate();
            $idpCert->loadPem($idpCertPem);
        }

        // Certificate for Encryption
        $encryptionKeyID = $gSettingsManager->get('sso_saml_encryption_key');
        $encryptionKey = new Key($this->db, $encryptionKeyID);
        $idpCertEncPem = $encryptionKey->getValue('key_certificate');
        if (!$idpCertEncPem) {
            $idpCertEnc = null;
        } else {
            $idpCertEnc = new X509Certificate();
            $idpCertEnc->loadPem($idpCertEncPem);
        }

        // Return everything as a named array
        return ['idpPrivateKey' => $idpPrivateKeyPem, 'idpCert' => $idpCert, 'idpCertEnc' => $idpCertEnc];

    }

    public function handleMetadataRequest() {
        global $gSettingsManager;
        if ($gSettingsManager->get('sso_saml_enabled') !== '1') {
            throw new Exception("SSO SAML is not enabled");
        }

        $keys = $this->getKeysCertificates();

        $entityId = $this->getIdPEntityId();
        $ssoUrl = $this->getSsoEndpoint();
        $sloUrl = $this->getSloEndpoint();
        $metadataUrl = $this->getMetadataUrl();

        if (!$entityId || !$ssoUrl || !$keys['idpCert'] || !$keys['idpPrivateKey']) {
            throw new Exception("SAML IDP settings are not configured properly.");
        }


        $entityDescriptor = new EntityDescriptor();
        $entityDescriptor->setID(\LightSaml\Helper::generateID());
        $entityDescriptor->setEntityID($entityId);

        // Create IDP SSO Descriptor
        $idpDescriptor = new IDPSSODescriptor();
        $idpDescriptor->setWantAuthnRequestsSigned($gSettingsManager->getBool('sso_saml_want_requests_signed'));
        $idpDescriptor->setProtocolSupportEnumeration(SamlConstants::PROTOCOL_SAML2);

        // Add KeyDescriptor for signing
        $keyDescriptor = new KeyDescriptor();
        $keyDescriptor->setUse(KeyDescriptor::USE_SIGNING);
        $keyDescriptor->setCertificate($keys['idpCert']);
        $idpDescriptor->addKeyDescriptor($keyDescriptor);

        // Add KeyDescriptor for encryption
        $keyDescriptor = new KeyDescriptor();
        $keyDescriptor->setUse(KeyDescriptor::USE_ENCRYPTION);
        $keyDescriptor->setCertificate($keys['idpCertEnc']);
        $idpDescriptor->addKeyDescriptor($keyDescriptor);

        // Add NameIDFormats
        $idpDescriptor->addNameIDFormat(SamlConstants::NAME_ID_FORMAT_UNSPECIFIED);

        // Add SingleSignOnService endpoints with different bindings
        $ssoServiceRedirect = new SingleSignOnService();
        $ssoServiceRedirect->setLocation($ssoUrl);
        $ssoServiceRedirect->setBinding(SamlConstants::BINDING_SAML2_HTTP_REDIRECT);
        $idpDescriptor->addSingleSignOnService($ssoServiceRedirect);

        $ssoServicePost = new SingleSignOnService();
        $ssoServicePost->setLocation($ssoUrl);
        $ssoServicePost->setBinding(SamlConstants::BINDING_SAML2_HTTP_POST);
        $idpDescriptor->addSingleSignOnService($ssoServicePost);


        // Add SingleSignOnService endpoints with different bindings
        $sloServiceRedirect = new SingleLogoutService();
        $sloServiceRedirect->setLocation($sloUrl);
        $sloServiceRedirect->setBinding(SamlConstants::BINDING_SAML2_HTTP_REDIRECT);
        $idpDescriptor->addSingleLogoutService($sloServiceRedirect);

        $sloServicePost = new SingleLogoutService();
        $sloServicePost->setLocation($sloUrl);
        $sloServicePost->setBinding(SamlConstants::BINDING_SAML2_HTTP_POST);
        $idpDescriptor->addSingleLogoutService($sloServicePost);



        // Add the IDP Descriptor to EntityDescriptor
        $entityDescriptor->addItem($idpDescriptor);

        // Sign the metadata with private key
        if (!empty($keys['idpPrivateKey']) && !empty($keys['idpCert'])) {
            $entityDescriptor->setSignature($this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert']));
        }

        // Output metadata as XML
        header('Content-Type: application/xml');

        $context = new SerializationContext();
        $entityDescriptor->serialize($context->getDocument(), $context);

        echo $context->getDocument()->saveXML();
    }

    public function errorResponse(string|array $status, $message, $request, $client) {
        if (!is_array($status)) $status = [$status];
        $statusCode = new \LightSaml\Model\Protocol\StatusCode($status[0]);
        if (count($status) > 1) {
            $statusCode->setStatusCode(new \LightSaml\Model\Protocol\StatusCode($status[1]));
        }
        $status = new \LightSaml\Model\Protocol\Status();
        $status->setStatusCode($statusCode);
        $status->setStatusMessage($message);


        $response = new Response();
        $response->setStatus($status);
        $response->setID('ID' . \LightSaml\Helper::generateID());
        $response->setInResponseTo($request->getID());
        $response->setIssueInstant(new \DateTime());
        $response->setDestination($request->getAssertionConsumerServiceURL());


        $issuer = new \LightSaml\Model\Assertion\Issuer($this->getIdPEntityId());
        $response->setIssuer($issuer);

        if ($client->getValue('smc_sign_assertions')) {
            $keys = $this->getKeysCertificates();
            $response->setSignature($this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert']));
        }

        $messageContext = new \LightSaml\Context\Profile\MessageContext();
        $messageContext->setMessage($response);

        $binding = new HttpPostBinding();
        $httpResponse = $binding->send($messageContext);
        print $httpResponse->getContent();
    }

    /**
     * Validate the SAML signature of the message coming from the client.
     * The client's x509 certificate needs to be configured in the client
     * configuration, otherwise validation will fail
     * @param \Admidio\SSO\Entity\SAMLClient $client The SAML client configuration
     * @param SamlMessage $message The SAML message (or assertion) that should be validated
     * @return mixed true upon success, error message otherwise
     */
    public function validateSignature(SAMLClient $client, SamlMessage $message, bool $required = false): bool|string {
        global $gL10n;
        $certPem = $client->getValue('smc_x509_certificate');
        if (!$certPem) {
            // Client has no cert configured...
            $SPcert = null;
            if ($required) {
                return $gL10n->get('SYS_SSO_SAML_SIGNATURE_KEY_MISSING');
            } else {
                return false;
            }
        } else {
            $SPcert = new X509Certificate();
            $SPcert->loadPem($certPem);
        }
        $key = KeyHelper::createPublicKey($SPcert);

        /** @var \LightSaml\Model\XmlDSig\SignatureXmlReader $signatureReader */
        $signatureReader = $message->getSignature();
        if (is_null($signatureReader)) {
            if ($required) {
                return $gL10n->get('SYS_SSO_SAML_SIGNATURE_MISSING');
            } else {
                return false;
            }
        }

        try {
            $ok = $signatureReader->validate($key);
            if ($ok) {
                return true;
            } else {
                return $gL10n->get('SYS_SSO_SAML_SIGNATURE_FAILED');
            }
        } catch (Exception $ex) {
            return $gL10n->get('SYS_SSO_SAML_SIGNATURE_FAILED');
        }
    }


    public function handleSSORequest() {
        global $gCurrentUser, $gCurrentUserId, $rootPath, $gSettingsManager, $gL10n, $gProfileFields, $gValidLogin, $gLogger;
        global $gNavigation;

        if ($gSettingsManager->get('sso_saml_enabled') !== '1') {
            throw new Exception("SSO SAML is not enabled");
        }

        $request = $this->receiveMessage();
        if (!$request instanceof AuthnRequest) {
            throw new Exception("Invalid request (not an AuthnRequest)");
        }
        // Load the SAML client data (entityID is in $request->issuer->getValue())
        $entityIdClient = $request->getIssuer()->getValue();
        $client = $this->getClientFromID($entityIdClient);

        try {
            // Validate signatures. Will throw an exception
            if ($client->getValue('smc_require_auth_signed') || $client->getValue('smc_validate_signatures')) {
                $this->validateSignature($client, $request, $client->getValue('smc_require_auth_signed'));
            }

            if (!$gValidLogin) {
                $this->showSSOLoginForm($client);
                // exit;
            }

            // Check whether the current user has access permissions to the SP client:
            if (!$client->hasAccessRight()) {
                $message = '<div class="alert alert-danger form-alert" style=""><i class="bi bi-exclamation-circle-fill"></i>' .
                    $gL10n->get('SYS_SSO_LOGIN_MISSING_PERMISSIONS', array($client->readableName())) .
                    '</div>';
                $this->showSSOLoginForm($client, $message);
                // Either exit in the showLoginForm or an Exception was triggered => execution won't continue here!

                exit;
            }



            $requestId = $request->getID(); // Extract from incoming AuthnRequest
            $clientACS = $request->getAssertionConsumerServiceURL();
            $issuer = new \LightSaml\Model\Assertion\Issuer($this->getIdPEntityId());
            $login = $this->currentUser->getValue($client->getValue('smc_userid_field'))??'';

            // Set up validity periods for the assertions and confirmationData -> Use allowed clock skew and assertion lifetime
            $issueInstant = new \DateTime();
            $notBefore = (clone $issueInstant)->sub(new \DateInterval('PT' . ($client->getValue('smc_allowed_clock_skew')??300) . 'S'));
            $notOnOrAfter = (clone $issueInstant)->add(new \DateInterval('PT' . ($client->getValue('smc_assertion_lifetime')??600) . 'S'));


            $statusSuccess = new \LightSaml\Model\Protocol\Status(
                new \LightSaml\Model\Protocol\StatusCode(SamlConstants::STATUS_SUCCESS));

            $response = new Response();
            $response->setStatus($statusSuccess);
            $response->setID(id: 'ID' . \LightSaml\Helper::generateID());
            $response->setIssueInstant($issueInstant);
            $response->setDestination($clientACS);
            $response->setIssuer($issuer);
            $response->setInResponseTo($requestId);
            $assertion = new Assertion();

            // Create SubjectConfirmationData
            $subjectConfirmationData = new \LightSaml\Model\Assertion\SubjectConfirmationData();
            $subjectConfirmationData
                ->setRecipient($clientACS) // Required recipient URL
                ->setNotBefore($notBefore)
                ->setNotOnOrAfter($notOnOrAfter)
                ->setInResponseTo($requestId); // ID of the AuthnRequest (optional but recommended)

            // Create SubjectConfirmation (Bearer method)
            $subjectConfirmation = new \LightSaml\Model\Assertion\SubjectConfirmation();
            $subjectConfirmation
                ->setMethod(SamlConstants::CONFIRMATION_METHOD_BEARER) // Bearer confirmation method
                ->setSubjectConfirmationData($subjectConfirmationData);

            $subject = new Subject();
            $subject->setNameID(new NameID($login, SamlConstants::NAME_ID_FORMAT_UNSPECIFIED));
            $subject->addSubjectConfirmation($subjectConfirmation);

            $assertion
                ->setId('ID' . \LightSaml\Helper::generateID())
                ->setIssueInstant($issueInstant)
                ->setIssuer($issuer)
                ->setSubject($subject)
                ->setConditions(
                    (new \LightSaml\Model\Assertion\Conditions())
                    ->setNotBefore($notBefore)
                    ->setNotOnOrAfter($notOnOrAfter)
                    ->addItem(
                        new \LightSaml\Model\Assertion\AudienceRestriction([$entityIdClient])
                    )
                );

            $assertion->addItem(
                (new \LightSaml\Model\Assertion\AuthnStatement())
                ->setAuthnInstant(new \DateTime('-10 MINUTE'))
                ->setSessionIndex(session_id())
                    ->setAuthnContext(
                        (new \LightSaml\Model\Assertion\AuthnContext())
                            ->setAuthnContextClassRef(SamlConstants::AUTHN_CONTEXT_UNSPECIFIED)
                    )
            );

            $attributeStatement = new AttributeStatement();

            $fields = $client->getFieldMapping();
            $fieldsDone = [];
            foreach ($fields as $samlField => $admidioField) {
                $att = $this->getUserAttribute($client, $gCurrentUser, $admidioField, $samlField);
                if ($att->getAllAttributeValues() !== null) {
                    $attributeStatement->addAttribute($att);
                }
                $fieldsDone[] = $admidioField;
            }
            // now loop through all available profile and user fields and add it if catch-all is configured
            if ($client->getFieldMappingCatchall()) {
                $useridFields = [
                    'usr_id'         => $gL10n->get('SYS_SSO_USERID_ID'),
                    'usr_uuid'       => $gL10n->get('SYS_SSO_USERID_UUID'),
                    'usr_login_name' => $gL10n->get('SYS_SSO_USERID_LOGIN'),
                    'fullname'       => $gL10n->get('SYS_NAME')
                ];
                foreach ($useridFields as $field => $friendlyName) {
                    if (in_array($field, $fieldsDone))
                        continue;
                    $att = $this->getUserAttribute($client, $gCurrentUser, $field, $field, $friendlyName);
                    if ($att->getFirstAttributeValue() !== null) {
                        $attributeStatement->addAttribute($att);
                    }
                }
                foreach ($gProfileFields->getProfileFields() as $field) {
                    $fieldname = $field->getValue('usf_name_intern');
                    if ($field->getValue('usf_hidden') == 0 && !in_array($fieldname, $fieldsDone) && !empty($gCurrentUser->getValue($fieldname))) {
                        // NOTE: Nextcloud does not like duplicate friendly names (althouth the SAML2.0 spec says that the friendly name must not be used for formally identifying attributes...)
                        $att = $this->getUserAttribute($client, $gCurrentUser, $fieldname, strtolower($fieldname));
                        if ($att->getFirstAttributeValue() !== null) {
                            $attributeStatement->addAttribute($att);
                        }
                    }
                }
            }


            $assertion->addItem($attributeStatement);


            // Sign the assertion and the whole response!
            $keys = $this->getKeysCertificates();
            $signAssertions = $client->getValue('smc_sign_assertions');

            if ($signAssertions) {
                $assertion->setSignature($this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert']));
            }

            // IF required, encrypt the assertion
            $encryptAssertion = $client->getValue('smc_encrypt_assertions');
            $encryptAssertionRequired = false;
            if ($encryptAssertion) {
                $assertionEnc = $this->encryptAssertion($assertion, $client, $encryptAssertionRequired);
                // If encryption is required, but fails, an exception should be triggered, so assume encryption worked or is not required!
                if (!is_null($assertionEnc)) {
                    $response->addEncryptedAssertion($assertionEnc);
                } else {
                    $response->addAssertion($assertion);
                }

            } else {
                // Finally add the assertion to the response:
                $response->addAssertion($assertion);
            }

            if ($signAssertions) {
                $response->setSignature($this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert']));
            }

            $messageContext = new \LightSaml\Context\Profile\MessageContext();
            $messageContext->setMessage($response);

            $binding = new HttpPostBinding();
            $httpResponse = $binding->send($messageContext);
            print $httpResponse->getContent();
        } catch (Exception $e) {
            $gLogger->error($e->getMessage());
            $this->errorResponse(SamlConstants::STATUS_RESPONDER, $e->getMessage(), $request, $client);
        }
    }


    public function handleSLORequest() {
        global $gCurrentUserId, $gCurrentUser, $gDb, $gMenu, $g_organization, $gLogger;
        global $gSettingsManager, $gCurrentSession, $gCurrentOrganization, $gProfileFields, $gCurrentOrgId, $gValidLogin;

        if ($gSettingsManager->get('sso_saml_enabled') !== '1') {
            throw new Exception("SSO SAML is not enabled");
        }

        $request = $this->receiveMessage();
        if (!$request instanceof LogoutRequest) {
            throw new Exception("Invalid request (not a LogoutRequest)");
        }


        $sessionId = session_id();
        $entityIdClient = $request->getIssuer()->getValue();
        $client = $this->getClientFromID($entityIdClient);

        try {
            // Validate signatures. Will throw an exception
            if ($client->getValue('smc_require_auth_signed') || $client->getValue('smc_validate_signatures')) {
                $this->validateSignature($client, $request, $client->getValue('smc_require_auth_signed'));
            }


            if ($gValidLogin) {
                // Logout will only work if you are logged in...


                /**  1. LOCAL LOGOUT FROM ADMIDIO */

                // If user is logged in, terminate their current session
                $this->db->queryPrepared("DELETE FROM adm_sessions WHERE ses_session_id = ?", [$sessionId]);

                $gValidLogin = false;

                // remove user from session
                $gCurrentSession->logout();

                // if login organization is different to organization of config file then create new session variables
                if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) !== 0 && $g_organization !== '') {
                    // read organization of config file with their preferences
                    $gCurrentOrganization->readDataByColumns(array('org_shortname' => $g_organization));

                    // read new profile field structure for this organization
                    $gProfileFields->readProfileFields($gCurrentOrgId);

                    // save new organization id to session
                    $gCurrentSession->setValue('ses_org_id', $gCurrentOrgId);
                    $gCurrentSession->save();

                    // read all settings from the new organization
                    $gSettingsManager = new SettingsManager($gDb, $gCurrentOrgId);
                }



                /**  2. NOTIFY ALL REGISTERED CLIENTS OF THE LOGOUT */


                // Notify all registered SPs for logout
                foreach ($this->getIds() as $spId) {
                    // Don't send a logout request to the client that initiated the logout request
                    if ($spId != $entityIdClient) {
                        $sp = new SAMLClient($this->db, $spId);
                        $this->sendLogoutRequest($sp, $gCurrentUser);
                    }
                }

                /**  3. clear data from global objects */

                $gCurrentUser->clear();
                $gMenu->initialize();

            }

            $logoutResponse = new LogoutResponse();
            $logoutResponse->setIssuer(new \LightSaml\Model\Assertion\Issuer($this->getIdPEntityId()));
            $logoutResponse->setInResponseTo($request->getID());
            $statusSuccess = new \LightSaml\Model\Protocol\Status(
                new \LightSaml\Model\Protocol\StatusCode(SamlConstants::STATUS_SUCCESS));
            $logoutResponse->setStatus($statusSuccess);
            // Sign the whole response!
            $keys = $this->getKeysCertificates();
            $signAssertions = $client->getValue('smc_sign_assertions');
            if ($signAssertions) {
                $logoutResponse->setSignature($this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert']));
            }
            $messageContext = new \LightSaml\Context\Profile\MessageContext();
            $messageContext->setMessage($logoutResponse);
            $binding = new HttpRedirectBinding();
            $httpResponse = $binding->send($messageContext, $client->getValue('smc_slo_url'));
            print $httpResponse->getContent();
        } catch (Exception $e) {
            $gLogger->error($e->getMessage());
            $this->errorResponse(SamlConstants::STATUS_RESPONDER, $e->getMessage(), $request, $client);
        }
    }

    public function sendLogoutRequest(SAMLClient $client, User $user) {
        $sloUrl = $client->getValue('smc_slo_url');
        $login = $user->getValue($client->getValue('smc_userid_field'))??'';

        if (empty($sloUrl) || $user->isNewRecord() || empty($login)) {
            return;
        }
        $logoutRequest = new LogoutRequest();
        $logoutRequest->setIssuer(new \LightSaml\Model\Assertion\Issuer($this->getIdPEntityId()));
        $logoutRequest->setId(\LightSaml\Helper::generateId());

        $logoutRequest->setNameID(new NameID($login, SamlConstants::NAME_ID_FORMAT_UNSPECIFIED));
        $logoutRequest->setDestination($sloUrl);

        // Sign the request
        $keys = $this->getKeysCertificates();
        $signAssertions = $client->getValue('smc_sign_assertions');
        if ($signAssertions) {
            $logoutRequest->setSignature($this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert']));
        }

        $messageContext = new \LightSaml\Context\Profile\MessageContext();
        $messageContext->setMessage($logoutRequest);

        $binding = new HttpRedirectBinding();
        // $httpResponse = $binding->send($messageContext, $sloUrl);

        // Instead of sending it to the browser, capture the URL
        $request = \Symfony\Component\HttpFoundation\Request::create('/', 'GET');
        $response = $binding->send($messageContext, $sloUrl, $request);
        $redirectUrl = $response->headers->get('Location');

        // Send backchannel request via GET (NOT POST!)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $redirectUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseContent = curl_exec($ch);
        curl_close($ch);

        print $responseContent;

    }

/*
    public function handleAttributeQuery() {
        // TODO: This should work like the Response to an AuthnRequest, just with the requested attributes
        // Unfortunately, the lightsaml library does not provide a way to extract the requested attributes from the AttributeQuery
        // So the code would be quite different, as the request object does not provide nice accessor functions like AuthnRequest!

        global $gSettingsManager, $gCurrentUserId, $rootPath;
        if ($gSettingsManager->get('sso_saml_enabled') !== '1') {
            throw new Exception("SSO SAML is not enabled");
        }

        $request = $this->receiveMessage();
        if (!$request instanceof Message) {
            throw new Exception("Invalid request (not an AttributeQuery)");
        }


        // Load the SAML client data (entityID is in $request->issuer->getValue())
        $clientACS = $request->getAssertionConsumerServiceURL();
        $entityIdClient = $request->getIssuer()->getValue();
        $client = $this->getClientFromID($entityIdClient);

        try{
            if (!$gCurrentUserId) {
                require_once($rootPath . '/system/login_valid.php');
            }
            $response = new Response();
            $issuer = new \LightSaml\Model\Assertion\Issuer($this->getIdPEntityId());
            $response->setIssuer($issuer);

            $attributeStatement = new AttributeStatement();

            foreach ($request->getRequestedAttributes() as $requestedAttribute) {
                $attrName = $requestedAttribute->getName();
                $attrFriendlyName = $requestedAttribute->getFriendlyName();

                $att = $this->getUserAttribute($gCurrentUser, $attrName, $attrFriendlyName);
                if ($att->getFirstAttributeValue() !== null) {
                    $attributeStatement->addAttribute($att);
                }
            }

            // TODO:....


            // $binding = new HttpPostBinding();
            // $binding->send($response, $attributeQuery->getIssuer()->getValue());
            // exit;

        } catch (Exception $e) {
            $gLogger->error($e->getMessage());
            $this->errorResponse(SamlConstants::STATUS_RESPONDER, $e->getMessage(), $request, $client);
        }
    }
*/
    private function getUserAttribute(SAMLClient $client, User $user, string $admidioField, string $samlAttribute, ?string $friendlyName = null) {
        global $gL10n, $gProfileFields;

        // recode $attributeName to admidio field names, but use original $attributeName in response
        $mapping = [
            'urn:oid:0.9.2342.19200300.100.1.1' => 'usr_login_name',
            'urn:oid:2.5.4.3' => 'usr_name',
            'urn:oid:2.5.4.10' => 'EMAIL',
            'urn:oid:2.5.4.11' => 'roles',
        ];
        $field = $mapping[$samlAttribute]??$admidioField;

        $att = new Attribute();

        if ($field == 'usr_name' || $field == 'fullname') {
            $att->setName($samlAttribute);
            $att->setAttributeValue($user->readableName());
//            $att->setFriendlyName($friendlyName ?: $gL10n->get('SYS_NAME'));

        } elseif ($field == 'roles') {
            $att->setName($samlAttribute);
//            $att->setFriendlyName($friendlyName ?: $gL10n->get('SYS_ROLES'));

            // Loop throu all roles of the user. If it is part of the mapping, or catchall is set, append it to the attribute
            $roles = $user->getRoleMemberships();
            $roleMapping = $client->getRoleMapping();
            $allRoles = $client->getRoleMappingCatchall();

            foreach ($roles as $roleId) {
                $samlRolesFound = array_keys($roleMapping, $roleId);
                foreach ($samlRolesFound as $samlRole) {
                    $att->addAttributeValue($samlRole);
                }
                if (empty($samlRolesFound) && $allRoles) {
                    // CATCHALL: Add role with its admidio role name
                    $role = new Role($this->db, $roleId);
                    $att->addAttributeValue($role->getValue('rol_name'));
                }
            }
        } else {
            // User profile fields or user fields
            $att->setName(strtolower($samlAttribute));
            $att->setAttributeValue($user->getValue($field));
/*            $friendlyNames = [
                'usr_login_name' => 'SYS_USERNAME',
                'usr_id' =>         'SYS_SSO_USERID_ID',
                'usr_uuid' =>       'SYS_SSO_USERID_UUID'
            ];
            if (array_key_exists($field, $friendlyNames)) {
                $att->setFriendlyName($friendlyName ?: $gL10n->get($friendlyNames[$field]));
            } else {
                $att->setFriendlyName($friendlyName ?: $gProfileFields->getProperty($field, 'usf_name'));
            }*/
        }
        return $att;
    }
}
