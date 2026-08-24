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
use Admidio\SSO\Entity\SAMLLogoutTransaction;
use Admidio\SSO\Entity\Key;
use Admidio\SSO\Service\KeyService;

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

    /**
     * Validate SAML-specific client settings before saving them.
     * @param array $formValues
     * @param SSOClient $client
     * @return void
     * @throws Exception
     */
    protected function saveCustomClientSettings(array &$formValues, SSOClient $client) {
        if (!empty($formValues['smc_encrypt_assertions'])) {
            // This method checks whether all requirements for encryption are fulfilled and
            // returns the certificate, which can be ignored here
            $this->getClientEncryptionCertificate($formValues['smc_x509_certificate']??'');
        }
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

    public function initializeClientObject(Database $database): ?SSOClient {
        return new SAMLClient($database);
    }

    /**
     * Returns an associative array with labels and links for the static IdP configuration data
     * (metadata/discovery URL, SSO/SLO endpoints, etc.).
     * @return array Associative arry, the keys will be the displayed labels, each entry has the form
     *     ['value' => 'linkHTML', 'id' => 'uniqueIDinForm', 'style' => 'additionalCSSstyles']
     *   where the 'style' key is optional, but 'value' and 'id' are required.
     */
    public function getStaticSettings() : array {
        global $gSettingsManager, $gL10n;

        // Load Certificate PEM
        $idpCertPem = '';
        $signatureKeyID = (int) $gSettingsManager->get('sso_saml_signing_key');

        if ($signatureKeyID > 0) {
            $keyService = new KeyService($this->db);
            try {
                $signatureKey = $keyService->getUsableKey($signatureKeyID, KeyService::USAGE_SAML_SIGNING);
                $idpCertPem = (string) $signatureKey->getValue('key_certificate');
            } catch (Exception $exception) {
                // The settings form must remain accessible so that an
                // administrator can replace an invalid key.
                $idpCertPem = '';
            }
        }

        $metaURL = $this->getMetadataUrl();
        $staticSettings = array(
            $gL10n->get('SYS_SSO_SAML_METADATA_URL') => ['value' => '<a href="' . $metaURL . '">' . $metaURL . '</a>', 'id' => 'metadata_URL'],
            $gL10n->get('SYS_SSO_SAML_SSO_ENDPOINT') => ['value' => $this->getSsoEndpoint(), 'id' => 'SSO_endpoint'],
            $gL10n->get('SYS_SSO_SAML_SLO_ENDPOINT') => ['value' => $this->getSloEndpoint(),'id' => 'SLO_endpoint'],
            $gL10n->get('SYS_SSO_KEY_CERTIFICATE')   => ['value' => $idpCertPem,  'id' => 'wrapper_certificate', 'style' => 'white-space: pre-wrap; word-wrap: break-word; background-color: #f8f9fa;
                    border: 1px solid #ced4da; padding: 0.375rem 0.75rem; font-family: monospace; width: 100%;
                    max-height: 120px; overflow: auto; border-radius: 0.375rem; font-size: smaller;']
        );
        return $staticSettings;
    }



    public function getSignatureWriter(string $privkeyPEM, X509Certificate $cert) {
        $privateKeyResource = KeyHelper::createPrivateKey($privkeyPEM, '', false, XMLSecurityKey::RSA_SHA256);
        $signatureWriter = new SignatureWriter($cert, $privateKeyResource, XmlSecurityDSig::SHA256);
        return $signatureWriter;
    }

    /**
     * Load and validate the certificate used to encrypt assertions for a SAML client.
     * @param string $certificatePEM
     * @return X509Certificate
     * @throws Exception
     */
    private function getClientEncryptionCertificate(string $certificatePEM): X509Certificate {
        global $gL10n;

        if (trim($certificatePEM) === '') {
            throw new Exception($gL10n->get('SYS_SSO_SAML_ENCRYPTION_KEY_MISSING'));
        }

        $certificateResource = openssl_x509_read($certificatePEM);
        if ($certificateResource === false) {
            throw new Exception($gL10n->get('SYS_SSO_SAML_ENCRYPTION_CERTIFICATE_INVALID'));
        }

        $certificateData = openssl_x509_parse($certificateResource);
        if ($certificateData === false
            || !isset($certificateData['validFrom_time_t'])
            || !isset($certificateData['validTo_time_t'])
            || time() < $certificateData['validFrom_time_t']
            || time() > $certificateData['validTo_time_t']
        ) {
            throw new Exception($gL10n->get('SYS_SSO_SAML_ENCRYPTION_CERTIFICATE_INVALID'));
        }

        $publicKey = openssl_pkey_get_public($certificateResource);
        if ($publicKey === false) {
            throw new Exception($gL10n->get('SYS_SSO_SAML_ENCRYPTION_CERTIFICATE_INVALID'));
        }

        $publicKeyDetails = openssl_pkey_get_details($publicKey);
        if (
            $publicKeyDetails === false
            || $publicKeyDetails['type'] !== OPENSSL_KEYTYPE_RSA
        ) {
            throw new Exception($gL10n->get('SYS_SSO_SAML_ENCRYPTION_CERTIFICATE_INVALID'));
        }

        try {
            $certificate = new X509Certificate();
            $certificate->loadPem($certificatePEM);

            return $certificate;
        } catch (\Throwable $exception) {
            throw new Exception(
                $gL10n->get('SYS_SSO_SAML_ENCRYPTION_CERTIFICATE_INVALID'),
                0,
                $exception
            );
        }
    }
    
    /**
     * Encrypt an assertion with the configured client certificate.
     * @param Assertion $assertion
     * @param SAMLClient $client
     * @return EncryptedAssertionWriter
     * @throws Exception
     */
    protected function encryptAssertion(Assertion $assertion, SAMLClient $client, bool $encryptAssertionRequired) {
        global $gL10n;
        try {
            // If no encryption certificate is set, the following method throws an exception!
            $SPcert = $this->getClientEncryptionCertificate($client->getValue('smc_x509_certificate'));
            $key = KeyHelper::createPublicKey($SPcert);

            $encryptedAssertion = new EncryptedAssertionWriter();
            $encryptedAssertion->encrypt($assertion, $key);

            return $encryptedAssertion;
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new Exception($gL10n->get('SYS_SSO_SAML_ENCRYPTION_FAILED'), 0, $exception);
        }
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

    /**
     * Load and validate the keys used by the SAML identity provider.
     *
     * @return array
     * @throws Exception
     */
    public function getKeysCertificates(): array
    {
        global $gSettingsManager;

        $keyService = new KeyService($this->db);

        $signatureKey = $keyService->getUsableKey((int) $gSettingsManager->get('sso_saml_signing_key'), KeyService::USAGE_SAML_SIGNING);

        $idpPrivateKeyPem = (string) $signatureKey->getValue('key_private');

        $idpCert = new X509Certificate();
        $idpCert->loadPem((string) $signatureKey->getValue('key_certificate'));

        $idpCertEnc = null;
        $encryptionKeyId = (int) $gSettingsManager->get('sso_saml_encryption_key');

        if ($encryptionKeyId > 0) {
            $encryptionKey = $keyService->getUsableKey($encryptionKeyId, KeyService::USAGE_SAML_ENCRYPTION);

            $idpCertEnc = new X509Certificate();
            $idpCertEnc->loadPem((string) $encryptionKey->getValue('key_certificate'));
        }

        // Return everything as a named array
        return ['idpPrivateKey' => $idpPrivateKeyPem, 'idpCert' => $idpCert, 'idpCertEnc' => $idpCertEnc];
    }

    public function handleMetadataRequest(): void
    {
        header('Content-Type: application/xml');
        echo $this->getMetadataXml();
    }

    public function getMetadataXml(): string {
        global $gSettingsManager;
        if ($gSettingsManager->get('sso_saml_enabled') !== '1') {
            throw new Exception("SSO SAML is not enabled");
        }

        $keys = $this->getKeysCertificates();

        $entityId = $this->getIdPEntityId();
        $ssoUrl = $this->getSsoEndpoint();
        $sloUrl = $this->getSloEndpoint();
        $metadataUrl = $this->getMetadataUrl();

        if (!$entityId) {
            throw new Exception("SAML IDP settings are not configured properly: The SAML Entity ID is missing");
        }
        if (!$ssoUrl) {
            throw new Exception("SAML IDP settings are not configured properly: The Single-Sign-On URL is missing");
        }
        if (!$keys['idpCert']) {
            throw new Exception("SAML IDP settings are not configured properly: The IdP certificate is missing");
        }
        if (!$keys['idpPrivateKey']) {
            throw new Exception("SAML IDP settings are not configured properly: The IdP private key is missing");
        }


        $entityDescriptor = new EntityDescriptor();
        $entityDescriptor->setID(\LightSaml\Helper::generateID());
        $entityDescriptor->setEntityID($entityId);

        // Create IDP SSO Descriptor
        $idpDescriptor = new IDPSSODescriptor();
        $idpDescriptor->setWantAuthnRequestsSigned($gSettingsManager->getBool('sso_saml_want_requests_signed'));
        $idpDescriptor->setProtocolSupportEnumeration(SamlConstants::PROTOCOL_SAML2);

        // Add KeyDescriptor for signing
        if ($keys['idpCert'] !== null) {
            $keyDescriptor = new KeyDescriptor();
            $keyDescriptor->setUse(KeyDescriptor::USE_SIGNING);
            $keyDescriptor->setCertificate($keys['idpCert']);
            $idpDescriptor->addKeyDescriptor($keyDescriptor);
        }

        // Advertise an encryption key only when one is configured.
        if ($keys['idpCertEnc'] !== null) {
            $keyDescriptor = new KeyDescriptor();
            $keyDescriptor->setUse(KeyDescriptor::USE_ENCRYPTION);
            $keyDescriptor->setCertificate($keys['idpCertEnc']);
            $idpDescriptor->addKeyDescriptor($keyDescriptor);
        }

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

        // Create metadata XML
        $context = new SerializationContext();
        $entityDescriptor->serialize($context->getDocument(), $context);

        return $context->getDocument()->saveXML();
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
        if ($request instanceof LogoutRequest) {
            $response->setDestination($client->getValue('smc_slo_url'));
        } else {
            // Always use the registered ACS URL, never the request's ACS URL
            $response->setDestination($client->getValue('smc_acs_url'));
        }
        $response->setRelayState($request->getRelayState());


        $issuer = new \LightSaml\Model\Assertion\Issuer($this->getIdPEntityId());
        $response->setIssuer($issuer);

        if ($this->shouldSignProtocolResponses($client)) {
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
     * @param SAMLClient $client The SAML client configuration
     * @param SamlMessage $message The SAML message (or assertion) that should be validated
     * @param bool $required Whether a signature is required. If set to false, the function will return false if no
     *                       signature is present, otherwise it will return an error message.
     * @return bool true upon success, error message otherwise
     * @throws \Admidio\Infrastructure\Exception
     * @throws Exception
     */
    public function validateSignature(SAMLClient $client, SamlMessage $message, bool $required = false): bool
    {
        global $gL10n;
        $certPem = $client->getValue('smc_x509_certificate');
        if (!$certPem) {
            // Client has no cert configured...
            $SPcert = null;
            if ($required) {
                throw new Exception($gL10n->get('SYS_SSO_SAML_SIGNATURE_KEY_MISSING'));
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
                throw new Exception($gL10n->get('SYS_SSO_SAML_SIGNATURE_MISSING'));
            } else {
                return false;
            }
        }

        try {
            $ok = $signatureReader->validate($key);
            if ($ok) {
                return true;
            } else {
                throw new Exception($gL10n->get('SYS_SSO_SAML_SIGNATURE_FAILED'));
            }
        } catch (Exception) {
            throw new Exception($gL10n->get('SYS_SSO_SAML_SIGNATURE_FAILED'));
        }
    }

    /**
     * Process the NameID policy of an authentication request.
     *
     * Admidio currently supports only the unspecified NameID format. If no
     * format is requested, the supported unspecified format is used.
     *
     * @param AuthnRequest $request
     * @param string $serviceProviderEntityID
     * @return array
     * @throws \InvalidArgumentException
     */
    private function processNameIDPolicy(AuthnRequest $request, string $serviceProviderEntityID): array {
        $nameIDFormat = SamlConstants::NAME_ID_FORMAT_UNSPECIFIED;
        $spNameQualifier = null;
        $nameIDPolicy = $request->getNameIDPolicy();

        if ($nameIDPolicy === null) {
            return array(
                'format' => $nameIDFormat,
                'spNameQualifier' => $spNameQualifier
            );
        }

        $requestedFormat = $nameIDPolicy->getFormat();

        if (!empty($requestedFormat) && $requestedFormat !== SamlConstants::NAME_ID_FORMAT_UNSPECIFIED) {
            throw new \InvalidArgumentException(
                'The SAML client requested the unsupported NameID format "' . $requestedFormat . '".'
            );
        }

        $requestedSPNameQualifier = $nameIDPolicy->getSPNameQualifier();

        if (!empty($requestedSPNameQualifier)) {
            if (!hash_equals($serviceProviderEntityID, $requestedSPNameQualifier)) {
                throw new \InvalidArgumentException(
                    'The SAML client requested the unsupported SPNameQualifier "' . $requestedSPNameQualifier . '".'
                );
            }
            $spNameQualifier = $requestedSPNameQualifier;
        }

        // AllowCreate controls whether a new federated identifier may be
        // established. Admidio returns an existing user field in unspecified
        // format and does not create a persistent identifier here.
        return array(
            'format' => $nameIDFormat,
            'spNameQualifier' => $spNameQualifier
        );
    }


    /**
     * Validate the destination and issue time of an incoming SAML request.
     *
     * @param SAMLClient $client
     * @param SamlMessage $request
     * @param string $expectedDestination
     * @return void
     * @throws Exception
     */
    private function validateRequestContext(SAMLClient $client, SamlMessage $request, string $expectedDestination): void 
    {
        $destination = $request->getDestination();
        $requestIsSigned = $request->getSignature() !== null;

        // The HTTP Redirect and HTTP POST bindings require signed messages
        // to contain the endpoint to which the message was sent.
        if ($requestIsSigned && empty($destination)) {
            throw new Exception('The signed SAML request does not contain a destination.');
        }

        // If Destination is supplied, it must identify this exact endpoint.
        if (!empty($destination) && !hash_equals($expectedDestination, $destination)) {
            throw new Exception(
                'The destination in the SAML request ("' . $destination . '") '
                . 'does not match the endpoint at which the request was received.'
            );
        }

        $issueInstant = $request->getIssueInstantTimestamp();
        if (!is_int($issueInstant) || $issueInstant <= 0) {
            throw new Exception('The SAML request does not contain a valid issue instant.');
        }

        $allowedClockSkew = (int)($client->getValue('smc_allowed_clock_skew') ?? 0);
        $requestLifetime = (int)($client->getValue('smc_request_lifetime') ?? 300);

        if ($allowedClockSkew < 0) {
            $allowedClockSkew = 0;
        }

        if ($requestLifetime < 1) {
            $requestLifetime = 300;
        }

        $currentTimestamp = time();
        if ($issueInstant > $currentTimestamp + $allowedClockSkew) {
            throw new Exception('The SAML request was issued in the future.');
        }
        if ($issueInstant < $currentTimestamp - $requestLifetime - $allowedClockSkew) {
            throw new Exception('The SAML request has expired.');
        }

    }


    public function handleSSORequest(): void
    {
        global $gCurrentSession, $gCurrentUser, $gSettingsManager, $gL10n, $gProfileFields, $gValidLogin, $gLogger;

        if ($gSettingsManager->get('sso_saml_enabled') !== '1') {
            throw new Exception("SSO SAML is not enabled");
        }

        $request = $this->receiveMessage();
        if (!$request instanceof AuthnRequest) {
            throw new Exception("Invalid request (not an AuthnRequest) in SAMLService->handleSSORequest()");
        }
        $requestIssuer = $request->getIssuer();
        if ($requestIssuer === null || empty($requestIssuer->getValue())) {
            throw new Exception('The SAML AuthnRequest has no issuer.');
        }

        // Load the SAML client data (entityID is in the request issuer)
        $entityIdClient = $requestIssuer->getValue();
        $client = $this->getClientFromID($entityIdClient);

        try {
            if (!$client->isEnabled()) {
                throw new Exception("Client \"" . $client->getIdentifier() . "\" is disabled. Login is no possible.");
            }

            // The global metadata flag and the per-client flag both mean that
            // an AuthnRequest signature is mandatory.
            $signatureRequired = $gSettingsManager->getBool('sso_saml_want_requests_signed')
                || (bool) $client->getValue('smc_require_auth_signed');

            if ($signatureRequired
                || $request->getSignature() !== null
                || $client->getValue('smc_validate_signatures')
            ) {
                $this->validateSignature($client, $request, $signatureRequired);
            }

            $this->validateRequestContext($client, $request, $this->ssoUrl);
            $nameIDPolicy = $this->processNameIDPolicy($request, $entityIdClient);

            $cancelAuthentication = admFuncVariableIsValid($_GET, 'sso_cancel', 'bool', array('defaultValue' => false));

            if ($cancelAuthentication) {
                unset($_SESSION['login_forward_url'], $_SESSION['login_forward_url_post']);
                $this->errorResponse(SamlConstants::STATUS_RESPONDER, $gL10n->get('SYS_SSO_LOGIN_CANCELLED'), $request, $client);
                return;
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

            // Validate ACS URL against registered client configuration
            $registeredACS = $client->getValue('smc_acs_url');
            if (!empty($clientACS) && $clientACS !== $registeredACS) {
                throw new Exception(
                    'The AssertionConsumerServiceURL in the AuthnRequest ("' . $clientACS . '") ' .
                    'does not match the registered ACS URL for this client. ' .
                    'Possible assertion theft attempt.'
                );
            }

            // If no ACS URL in request, fall back to the registered one
            if (empty($clientACS)) {
                $clientACS = $registeredACS;
            }

            $issuer = new \LightSaml\Model\Assertion\Issuer($this->getIdPEntityId());
            $login = $this->currentUser->getValue($client->getValue('smc_userid_field'))??'';

            // Set up validity periods for the assertions and confirmationData -> Use allowed clock skew and assertion lifetime
            $issueInstant = new \DateTime();
            $notBefore = (clone $issueInstant)->sub(new \DateInterval('PT' . ($client->getValue('smc_allowed_clock_skew')??300) . 'S'));
            $notOnOrAfter = (clone $issueInstant)->add(new \DateInterval('PT' . ($client->getValue('smc_assertion_lifetime')??600) . 'S'));

            // Assertion validity and session validity serve different purposes.
            // SessionNotOnOrAfter tells the service provider when the session
            // established from this assertion must end.
            $sessionLifetime = $gSettingsManager->getInt('logout_minutes') * 60;
            if ($sessionLifetime <= 0) {
                throw new Exception('The configured login session lifetime is invalid.');
            }
            $sessionNotOnOrAfter = (clone $issueInstant)->add(new \DateInterval('PT' . $sessionLifetime . 'S'));

            $statusSuccess = new \LightSaml\Model\Protocol\Status(
                new \LightSaml\Model\Protocol\StatusCode(SamlConstants::STATUS_SUCCESS));

            $response = new Response();
            $response->setStatus($statusSuccess);
            $response->setID(id: 'ID' . \LightSaml\Helper::generateID());
            $response->setIssueInstant($issueInstant);
            $response->setDestination($clientACS);
            $response->setIssuer($issuer);
            $response->setInResponseTo($requestId);
            $response->setRelayState($request->getRelayState());
            $assertion = new Assertion();

            // Create SubjectConfirmationData
            $subjectConfirmationData = new \LightSaml\Model\Assertion\SubjectConfirmationData();
            $subjectConfirmationData
                ->setRecipient($clientACS) // Required recipient URL
                ->setNotOnOrAfter($notOnOrAfter)
                ->setInResponseTo($requestId); // ID of the AuthnRequest (optional but recommended)

            // Create SubjectConfirmation (Bearer method)
            $subjectConfirmation = new \LightSaml\Model\Assertion\SubjectConfirmation();
            $subjectConfirmation
                ->setMethod(SamlConstants::CONFIRMATION_METHOD_BEARER) // Bearer confirmation method
                ->setSubjectConfirmationData($subjectConfirmationData);

            $subject = new Subject();
            $nameID = new NameID($login, $nameIDPolicy['format']);
            if ($nameIDPolicy['spNameQualifier'] !== null) {
                $nameID->setSPNameQualifier($nameIDPolicy['spNameQualifier']);
            }
            $subject->setNameID($nameID);
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

            $sessionIndex = bin2hex(random_bytes(32));
            $authenticationTimestamp = (int) $gCurrentSession->getValue('ses_authentication_time', 'U');

            if ($authenticationTimestamp <= 0) {
                throw new Exception('The current Admidio session has no valid authentication time.');
            }

            $authnInstant = (new \DateTime())->setTimestamp($authenticationTimestamp);

            $assertion->addItem(
                (new \LightSaml\Model\Assertion\AuthnStatement())
                    ->setAuthnInstant($authnInstant)
                    ->setSessionIndex($sessionIndex)
                    ->setSessionNotOnOrAfter($sessionNotOnOrAfter)
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


            // HTTP-POST SSO assertions are always signed. The legacy
            // smc_sign_assertions setting now controls only the additional
            // response-level signature.
            $keys = $this->getKeysCertificates();
            $signResponse = (bool) $client->getValue('smc_sign_assertions');
            $assertion->setSignature($this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert']));

            // IF required, encrypt the assertion
            $encryptAssertionRequired = (bool)$client->getValue('smc_encrypt_assertions');

            if ($encryptAssertionRequired) {
                $assertionEnc = $this->encryptAssertion($assertion, $client, $encryptAssertionRequired);
                $response->addEncryptedAssertion($assertionEnc);
            } else {
                $response->addAssertion($assertion);
            }

            if ($signResponse) {
                $response->setSignature($this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert']));
            }

            $messageContext = new \LightSaml\Context\Profile\MessageContext();
            $messageContext->setMessage($response);

            $binding = new HttpPostBinding();
            $httpResponse = $binding->send($messageContext);

            $this->saveSessionParticipant($client, (int) $gCurrentUser->getValue('usr_id'), 
                (string) $nameID->getValue(), (string) $nameID->getFormat(), $nameID->getSPNameQualifier(),
                $sessionIndex, $authnInstant, $sessionNotOnOrAfter);

            print $httpResponse->getContent();
        } catch (\InvalidArgumentException $exception) {
            $gLogger->error($exception->getMessage());
            $this->errorResponse(
                array(SamlConstants::STATUS_REQUESTER, SamlConstants::STATUS_INVALID_NAME_ID_POLICY),
                $gL10n->get('SYS_SSO_SAML_NAME_ID_POLICY_INVALID'),
                $request,
                $client
            );
        } catch (Exception $e) {
            $gLogger->error(
                'Could not process the SAML request.',
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            );
            $this->errorResponse(SamlConstants::STATUS_RESPONDER, 'The SAML request could not be processed.', $request, $client);
        }
    }

    /**
     * Persist the SAML session that has been established for a client.
     *
     * @throws Exception
     */
    private function saveSessionParticipant(SAMLClient $client, int $userId,
        string $nameID, string $nameIDFormat, ?string $nameIDSPNameQualifier, string $sessionIndex,
        \DateTimeInterface $authnInstant, \DateTimeInterface $expiresAt): void
    {
        global $gCurrentOrgId, $gCurrentSession;

        $participantService = new SAMLSessionParticipantService($this->db);
        $participantService->removeExpiredParticipants();

        $externalSessionId = (string) $gCurrentSession->getValue('ses_external_session_id');

        if ($externalSessionId === '') {
            throw new Exception('The current Admidio session has no external session identifier.');
        }

        $participantService->persistParticipant(
            $gCurrentOrgId, $userId, $externalSessionId, (int) $client->getValue('smc_id'),
            $nameID, $nameIDFormat, $nameIDSPNameQualifier,
            $sessionIndex, $authnInstant, $expiresAt
        );
    }


    /**
     * Resolve an SP-initiated LogoutRequest to the persisted SAML participant.
     *
     * @return array<string,mixed>|null
     * @throws Exception
     */
    private function findActiveSessionParticipant(LogoutRequest $request, int $clientId): ?array
    {
        global $gCurrentOrgId;

        $sessionIndex = (string) ($request->getSessionIndex() ?? '');
        if ($sessionIndex === '') {
            return null;
        }

        $participantService = new SAMLSessionParticipantService($this->db);
        $participants = $participantService->getParticipantsByClientAndSessionIndex(
            $gCurrentOrgId, $clientId, $sessionIndex);

        foreach ($participants as $participant) {
            if ($this->logoutRequestMatchesParticipant($request, $participant)) {
                return $participant;
            }
        }

        return null;
    }

    /**
     * Check whether a LogoutRequest identifies the stored SAML participant.
     */
    private function logoutRequestMatchesParticipant(LogoutRequest $request, array $participant): bool 
    {
        $requestNameID = $request->getNameID();

        if ($requestNameID === null) {
            return false;
        }

        if (!hash_equals((string) $participant['ssp_name_id'],(string) $requestNameID->getValue())) {
            return false;
        }

        $storedFormat = (string) $participant['ssp_name_id_format'];
        $requestFormat = (string) $requestNameID->getFormat();

        if ($requestFormat !== '' && !hash_equals($storedFormat, $requestFormat)) {
            return false;
        }

        $storedSPNameQualifier = (string) ($participant['ssp_name_id_sp_name_qualifier'] ?? '');
        $requestSPNameQualifier = (string) ($requestNameID->getSPNameQualifier() ?? '');

        if (!hash_equals($storedSPNameQualifier,$requestSPNameQualifier)) {
            return false;
        }

        $requestSessionIndex = (string) ($request->getSessionIndex() ?? '');

        if ($requestSessionIndex === '' || !hash_equals((string) $participant['ssp_session_index'], $requestSessionIndex)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Handle incoming SAML LogoutRequest and LogoutResponse messages.
     * @throws Exception
     */
    public function handleSLORequest(): void
    {
        global $gSettingsManager;
        if ($gSettingsManager->get('sso_saml_enabled') !== '1') {
            throw new Exception('SSO SAML is not enabled');
        }

        $this->removeExpiredLogoutTransactions();
        (new SAMLSessionParticipantService($this->db))->removeExpiredParticipants();

        $message = $this->receiveMessage();

        if ($message instanceof LogoutRequest) {
            $this->handleIncomingLogoutRequest($message);
            return;
        }
        if ($message instanceof LogoutResponse) {
            $this->handleIncomingLogoutResponse($message);
            return;
        }
        throw new Exception('Invalid request in SAMLService->handleSLORequest().');
    }

    /**
     * Start an SP-initiated logout transaction.
     * @throws Exception
     */
    private function handleIncomingLogoutRequest(LogoutRequest $request): void
    {
        global $gCurrentOrgId, $gLogger;

        $issuer = $request->getIssuer();
        if ($issuer === null || empty($issuer->getValue())) {
            throw new Exception('The SAML LogoutRequest has no issuer.');
        }

        $initiatorEntityId = $issuer->getValue();
        $initiatorClient = $this->getClientFromID($initiatorEntityId);

        try {
            $this->validateSLOClient($initiatorClient, $request);

            $initiatorClientId = (int) $initiatorClient->getValue('smc_id');
            $initiatorParticipant = $this->findActiveSessionParticipant($request, $initiatorClientId);

            if ($initiatorParticipant === null) {
                throw new Exception('The LogoutRequest does not match an active SAML session.');
            }

            $externalSessionId = (string) $initiatorParticipant['ssp_external_session_id'];
            $participantService = new SAMLSessionParticipantService($this->db);
            $participants = $participantService->getParticipants($gCurrentOrgId, $externalSessionId);
            $pendingClients = array();

            foreach ($participants as $participant) {
                $participantId = (int) $participant['ssp_id'];
                $participantClientId = (int) $participant['ssp_client_id'];

                if ($participantId === (int) $initiatorParticipant['ssp_id']) {
                    continue;
                }

                $pendingClients[] = array(
                    'participantId' => $participantId,
                    'clientId' => $participantClientId,
                    'nameId' => (string) $participant['ssp_name_id'],
                    'nameIdFormat' => (string) $participant['ssp_name_id_format'],
                    'nameIdSPNameQualifier' => $participant['ssp_name_id_sp_name_qualifier'],
                    'sessionIndex' => (string) $participant['ssp_session_index']
                );
            }

            $transaction = new SAMLLogoutTransaction($this->db);
            $transaction->initialize($gCurrentOrgId, $initiatorClientId,
                (int) $initiatorParticipant['ssp_id'], $request->getID(), $request->getRelayState(), $pendingClients);
            $transaction->save();

            /*
             * All information required for downstream LogoutRequests is now
             * persisted. Invalidate the Admidio session identified by the
             * persisted SAML participant, not whichever browser session happens
             * to be making this request.
             */
            $this->performLocalLogout($externalSessionId);

            $this->continueLogoutTransaction($transaction);
        } catch (Exception $exception) {
            $gLogger->error($exception->getMessage());
            $this->sendLogoutResponse($initiatorClient, $request->getID(), $request->getRelayState(), SamlConstants::STATUS_RESPONDER);
        }
    }

    private function isPartialLogoutStatus(\LightSaml\Model\Protocol\Status $status): bool 
    {
        $statusCode = $status->getStatusCode();

        if ($statusCode === null) {
            return false;
        }

        $nestedStatusCode = $statusCode->getStatusCode();

        return $nestedStatusCode !== null
            && $nestedStatusCode->getValue() === SamlConstants::STATUS_PARTIAL_LOGOUT;
    }

    /**
     * Handle and correlate a LogoutResponse from a service provider.
     * @throws Exception
     */
    private function handleIncomingLogoutResponse(LogoutResponse $response): void
    {
        global $gLogger;

        $transactionToken = (string) $response->getRelayState();

        if ($transactionToken === '') {
            throw new Exception('The SAML LogoutResponse has no logout transaction RelayState.');
        }

        $transaction = new SAMLLogoutTransaction($this->db);

        if (!$transaction->readDataByToken($transactionToken)
            || (int) $transaction->getValue('slt_org_id') !== $gCurrentOrgId
        ) {
            throw new Exception('The SAML LogoutResponse references an unknown logout transaction.');
        }

        if ($transaction->isExpired()) {
            $transaction->delete();
            throw new Exception('The SAML logout transaction has expired.');
        }

        $currentParticipantId = $transaction->getCurrentParticipantId();
        $currentClientId = $transaction->getCurrentClientId();
        $currentRequestId = $transaction->getCurrentRequestId();

        if ($currentParticipantId <= 0 || $currentClientId <= 0 || $currentRequestId === '') {
            throw new Exception('The SAML logout transaction has no pending request.');
        }

        $client = new SAMLClient($this->db, $currentClientId);

        try {
            $issuer = $response->getIssuer();

            if ($issuer === null|| !hash_equals($client->getIdentifier(), (string) $issuer->getValue())) {
                throw new Exception('The LogoutResponse issuer does not match the expected client.');
            }

            $this->validateSLOClient($client, $response);

            if (!hash_equals($currentRequestId, (string) $response->getInResponseTo())) {
                throw new Exception('The LogoutResponse does not match the pending LogoutRequest.');
            }

            $status = $response->getStatus();

            if ($status !== null && $status->isSuccess() && !$this->isPartialLogoutStatus($status)) {
                /*
                * Only a successful correlated LogoutResponse confirms that the SP
                * session represented by this participant has ended.
                */
                (new SAMLSessionParticipantService($this->db))->deleteParticipant($currentParticipantId);
            } else {
                $transaction->setPartialLogout(true);

                $statusMessage = $status?->getStatusMessage();

                $gLogger->warning(
                    'The SAML client "' . $client->getIdentifier()
                    . '" returned an unsuccessful LogoutResponse'
                    . ($statusMessage === null || $statusMessage === ''
                        ? '.'
                        : ': ' . $statusMessage)
                );
            }

            $transaction->setCurrentRequest(null, null, null);
            $transaction->save();

            $this->continueLogoutTransaction($transaction);
        } catch (Exception $exception) {
            $gLogger->error($exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Continue with the next active SP participant or finish the logout.
     *
     * @throws Exception
     */
    private function continueLogoutTransaction(SAMLLogoutTransaction $transaction): void 
    {
        $pendingClients = $transaction->getPendingClients();

        while (count($pendingClients) > 0) {
            $pendingClient = array_shift($pendingClients);
            $transaction->setPendingClients($pendingClients);

            $participantId = (int) ($pendingClient['participantId'] ?? 0);
            $clientId = (int) ($pendingClient['clientId'] ?? 0);
            $nameID = (string) ($pendingClient['nameId'] ?? '');
            $nameIDFormat = (string) ($pendingClient['nameIdFormat'] ?? '');
            $nameIDSPNameQualifier = $pendingClient['nameIdSPNameQualifier'] ?? null;
            $sessionIndex = (string) ($pendingClient['sessionIndex'] ?? '');

            if ($participantId <= 0 || $clientId <= 0
                || $nameID === '' || $nameIDFormat === '' || $sessionIndex === ''
            ) {
                $transaction->setPartialLogout(true);
                $transaction->save();
                continue;
            }

            $client = new SAMLClient($this->db, $clientId);

            if (!$client->isEnabled() || empty($client->getValue('smc_slo_url'))) {
                /*
                * Keep the participant because no successful logout has been
                * confirmed for that SP.
                */
                $transaction->setPartialLogout(true);
                $transaction->save();
                continue;
            }


            $this->sendLogoutRequest(
                $transaction,
                $participantId,
                $client,
                $nameID,
                $nameIDFormat,
                is_string($nameIDSPNameQualifier) ? $nameIDSPNameQualifier : null,
                $sessionIndex
            );
            return;
        }

        $initiatorClient = new SAMLClient($this->db, $transaction->getInitiatorClientId());

        $status = $transaction->hasPartialLogout()
            ? SamlConstants::STATUS_PARTIAL_LOGOUT
            : SamlConstants::STATUS_SUCCESS;

        $initiatorRequestId = $transaction->getInitiatorRequestId();
        $initiatorRelayState = $transaction->getInitiatorRelayState();
        $initiatorParticipantId = $transaction->getInitiatorParticipantId();

        /*
        * The initiating SP requested logout and receives the final response,
        * so its participant record no longer represents an active session.
        */
        if ($initiatorParticipantId > 0) {
            (new SAMLSessionParticipantService($this->db))->deleteParticipant($initiatorParticipantId);
        }

        $transaction->delete();

        $this->sendLogoutResponse($initiatorClient, $initiatorRequestId, $initiatorRelayState, $status);
    }

    private function shouldSignProtocolResponses(SAMLClient $client): bool
    {
        return (bool) $client->getValue('smc_sign_assertions')
            || (bool) $client->getValue('smc_require_auth_signed')
            || (bool) $client->getValue('smc_validate_signatures');
    }

    /**
     * Send a front-channel LogoutRequest to the next service provider.
     *
     * @throws Exception
     */
    private function sendLogoutRequest(SAMLLogoutTransaction $transaction, int $participantId, 
        SAMLClient $client, string $nameID, string $nameIDFormat, ?string $nameIDSPNameQualifier, string $sessionIndex): void 
    {
        $sloUrl = trim((string) $client->getValue('smc_slo_url'));

        if ($sloUrl === '') {
            throw new Exception('The SAML client has no logout service URL.');
        }

        $requestId = 'ID' . \LightSaml\Helper::generateID();

        $logoutRequest = new LogoutRequest();
        $logoutRequest->setIssuer(
            new \LightSaml\Model\Assertion\Issuer($this->getIdPEntityId())
        );
        $logoutRequest->setID($requestId);
        $logoutRequest->setIssueInstant(new \DateTime());
        $logoutNameID = new NameID($nameID, $nameIDFormat);
        if ($nameIDSPNameQualifier !== null && $nameIDSPNameQualifier !== '') {
            $logoutNameID->setSPNameQualifier($nameIDSPNameQualifier);
        }
        $logoutRequest->setNameID($logoutNameID);
        $logoutRequest->setSessionIndex($sessionIndex);
        $logoutRequest->setDestination($sloUrl);
        $logoutRequest->setRelayState($transaction->getValue('slt_token'));

        $keys = $this->getKeysCertificates();

        if ($this->shouldSignProtocolResponses($client)) {
            $logoutRequest->setSignature(
                $this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert'])
            );
        }

        /*
        * Persist correlation data before returning the redirect. The next HTTP
        * request is independent of the destroyed Admidio login session.
        */
        $transaction->setCurrentRequest($participantId, (int) $client->getValue('smc_id'), $requestId);
        $transaction->save();

        $messageContext = new \LightSaml\Context\Profile\MessageContext();
        $messageContext->setMessage($logoutRequest);

        $binding = new HttpRedirectBinding();
        $httpResponse = $binding->send($messageContext, $sloUrl);

        $this->emitSAMLResponse($httpResponse);
    }

    /**
     * Send the final LogoutResponse to the initiating service provider.
     *
     * @throws Exception
     */
    private function sendLogoutResponse(SAMLClient $client, string $inResponseTo, ?string $relayState, string $statusCode): void 
    {
        $sloUrl = trim((string) $client->getValue('smc_slo_url'));

        if ($sloUrl === '') {
            throw new Exception('The initiating SAML client has no logout service URL.');
        }

        $logoutResponse = new LogoutResponse();
        $logoutResponse->setIssuer(
            new \LightSaml\Model\Assertion\Issuer($this->getIdPEntityId())
        );
        $logoutResponse->setID('ID' . \LightSaml\Helper::generateID());
        $logoutResponse->setIssueInstant(new \DateTime());
        $logoutResponse->setDestination($sloUrl);
        $logoutResponse->setInResponseTo($inResponseTo);
        $logoutResponse->setRelayState($relayState);

        if ($statusCode === SamlConstants::STATUS_PARTIAL_LOGOUT) {
            $statusCodeObject = new \LightSaml\Model\Protocol\StatusCode(SamlConstants::STATUS_SUCCESS);
            $statusCodeObject->setStatusCode(
                new \LightSaml\Model\Protocol\StatusCode(SamlConstants::STATUS_PARTIAL_LOGOUT)
            );
        } else {
            $statusCodeObject = new \LightSaml\Model\Protocol\StatusCode($statusCode);
        }

        $logoutResponse->setStatus(new \LightSaml\Model\Protocol\Status($statusCodeObject));

        $keys = $this->getKeysCertificates();

        if ($this->shouldSignProtocolResponses($client)) {
            $logoutResponse->setSignature(
                $this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert'])
            );
        }

        $messageContext = new \LightSaml\Context\Profile\MessageContext();
        $messageContext->setMessage($logoutResponse);

        $binding = new HttpRedirectBinding();
        $httpResponse = $binding->send($messageContext, $sloUrl);

        $this->emitSAMLResponse($httpResponse);
    }

    /**
     * Validate a SAML SLO message and its client.
     *
     * @throws Exception
     */
    private function validateSLOClient(SAMLClient $client, SamlMessage $message): void 
    {
        if (!$client->isEnabled()) {
            throw new Exception('Client "' . $client->getIdentifier() . '" is disabled. Logout is not possible.');
        }

        if ($client->getValue('smc_require_auth_signed') || $client->getValue('smc_validate_signatures')) {
            $this->validateSignature($client, $message, (bool) $client->getValue('smc_require_auth_signed'));
        }

        $this->validateRequestContext($client, $message, $this->sloUrl);
    }

    /**
     * Destroy the local Admidio login after all required SAML user information
     * has been persisted in the logout transaction.
     *
     * @throws Exception
     */
    private function performLocalLogout(string $externalSessionId): void
    {
        global $gCurrentUser, $gDb, $gMenu, $g_organization;
        global $gCurrentOrganization, $gCurrentOrgId, $gCurrentSession;
        global $gProfileFields, $gSettingsManager, $gValidLogin;

        if ($externalSessionId === '') {
            throw new Exception('The SAML logout target has no external session identifier.');
        }

        $oidcService = new OIDCService($gDb, $gCurrentUser);
        $oidcLogoutNotificationService = new OIDCLogoutNotificationService($gDb, $oidcService->getIssuerURL());

        // A SAML front-channel transaction is already in progress. Send
        // OIDC back-channel notifications now. Keep participant records because
        // front-channel-only OIDC clients have not been notified by this flow.
        $oidcLogoutNotificationService->notifySession($gCurrentOrgId, $externalSessionId, false);

        $currentExternalSessionId = $gValidLogin
            ? (string) $gCurrentSession->getValue('ses_external_session_id')
            : '';
        $isCurrentSession = $currentExternalSessionId !== ''
            && hash_equals($currentExternalSessionId, $externalSessionId);

        if (!$isCurrentSession) {
            // The LogoutRequest can arrive without the browser that owns the
            // target Admidio session. Invalidate that persisted session and its
            // auto-login record without touching an unrelated current session.
            $sql = 'SELECT ses_session_id
                      FROM ' . TBL_SESSIONS . '
                     WHERE ses_org_id = ?
                       AND ses_external_session_id = ?';
            $statement = $this->db->queryPrepared($sql, array($gCurrentOrgId, $externalSessionId));

            while ($session = $statement->fetch()) {
                $this->db->queryPrepared(
                    'DELETE FROM ' . TBL_AUTO_LOGIN . '
                           WHERE atl_org_id = ?
                             AND atl_session_id = ?',
                    array($gCurrentOrgId, $session['ses_session_id'])
                );
            }

            $this->db->queryPrepared(
                'UPDATE ' . TBL_SESSIONS . '
                    SET ses_usr_id = NULL,
                        ses_authentication_time = NULL,
                        ses_authentication_methods = NULL
                  WHERE ses_org_id = ?
                    AND ses_external_session_id = ?',
                array($gCurrentOrgId, $externalSessionId)
            );
            return;
        }

        $gValidLogin = false;
        $gCurrentSession->logout();

        if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) !== 0
            && $g_organization !== ''
        ) {
            $gCurrentOrganization->readDataByColumns(array('org_shortname' => $g_organization));

            $gProfileFields->readProfileFields($gCurrentOrgId);

            $gCurrentSession->setValue('ses_org_id', $gCurrentOrgId);
            $gCurrentSession->save();

            $gSettingsManager = new SettingsManager($gDb, $gCurrentOrgId);
        }

        $gCurrentUser->clear();
        $gMenu->initialize();
    }

    /**
     * Remove abandoned SAML logout transactions.
     *
     * @throws Exception
     */
    private function removeExpiredLogoutTransactions(): void
    {
        $sql = '
            DELETE FROM ' . TBL_SAML_LOGOUT_TRANSACTIONS . '
            WHERE slt_expires_at < CURRENT_TIMESTAMP';

        $this->db->queryPrepared($sql);
    }

    /**
     * Forward the generated Symfony response to the browser.
     */
    private function emitSAMLResponse(\Symfony\Component\HttpFoundation\Response $response): void 
    {
        http_response_code($response->getStatusCode());

        foreach ($response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }

        echo $response->getContent();
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
            throw new Exception("Invalid request (not an AttributeQuery) in SAMLService->handleAttributeQuery()");
        }


        // Load the SAML client data (entityID is in $request->issuer->getValue())
        $clientACS = $request->getAssertionConsumerServiceURL();
        $entityIdClient = $request->getIssuer()->getValue();
        $client = $this->getClientFromID($entityIdClient);

        try{
            if (!$client->isEnabled()) {
                throw new Exception("Client \"" . $client->getIdentifier() . "\" is disabled. Query is no possible.");
            }
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
            $gLogger->error(
                'Could not process the SAML request.',
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            );
            $this->errorResponse(SamlConstants::STATUS_RESPONDER, 'The SAML request could not be processed.', $request, $client);
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

            foreach ($client->getMappedRoleMemberships($user) as $r) {
                $att->addAttributeValue($r);
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
