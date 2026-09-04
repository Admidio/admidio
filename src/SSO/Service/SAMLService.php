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
use Psr\Http\Message\ResponseInterface;
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

            /*
            * A signature can only be verified when a certificate is stored for the client.
            * The global preference is advertised as WantAuthnRequestsSigned in the IdP
            * metadata and applies to every client that is able to sign, but a client without
            * a certificate cannot, so the global requirement is not enforced for it. Only the
            * per-client flag demands a signature unconditionally, so an explicit
            * misconfiguration is still reported instead of being ignored.
            */
            $clientHasCertificate = trim((string) $client->getValue('smc_x509_certificate')) !== '';
            $globalSignatureRequired = $gSettingsManager->getBool('sso_saml_want_requests_signed');
            $signatureRequired = (bool) $client->getValue('smc_require_auth_signed')
                || ($globalSignatureRequired && $clientHasCertificate);

            if ($globalSignatureRequired && !$clientHasCertificate
                && !(bool) $client->getValue('smc_require_auth_signed')) {
                $gLogger->notice(
                    'Signed AuthnRequests are required globally, but the SAML client has no certificate '
                    . 'configured, so the signature of its requests cannot be verified.',
                    array('client' => $client->getIdentifier())
                );
            }

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

            /*
            * ForceAuthn asks for a fresh authentication even when an Admidio session
            * already exists, IsPassive forbids any interaction with the user. The
            * AuthnRequest is replayed after the login form, so the fact that the fresh
            * login has happened is remembered in the session; without that the replayed
            * request would demand a login again and again.
            */
            $isPassive = (bool) $request->getIsPassive();
            $authenticationRequired = !$gValidLogin
                || ((bool) $request->getForceAuthn() && !$this->hasCompletedReauthentication($request));

            if ($authenticationRequired && $isPassive) {
                $this->errorResponse(
                    array(SamlConstants::STATUS_REQUESTER, SamlConstants::STATUS_NO_PASSIVE),
                    'The request is passive, but the user would have to authenticate.',
                    $request,
                    $client
                );
                return;
            }

            if ($authenticationRequired) {
                $this->rememberReauthenticationRequest($request);
                $this->showSSOLoginForm($client);
                // exit;
            }

            // Check whether the current user has access permissions to the SP client:
            if (!$client->hasAccessRight()) {
                if ($isPassive) {
                    // The user is authenticated but not allowed to use this client, and a
                    // passive request must not be answered with an interactive page.
                    $this->errorResponse(
                        SamlConstants::STATUS_REQUESTER,
                        $gL10n->get('SYS_SSO_LOGIN_MISSING_PERMISSIONS', array($client->readableName())),
                        $request,
                        $client
                    );
                    return;
                }

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
     * Whether the fresh authentication that this AuthnRequest asked for has just happened.
     *
     * The login form returns to the same SSO URL, so the AuthnRequest is processed a second
     * time and still carries ForceAuthn. Without this marker the request would send the user
     * to the login form again. The marker is tied to the request id and is only accepted when
     * the session was authenticated after the request was remembered.
     */
    private function hasCompletedReauthentication(AuthnRequest $request): bool
    {
        global $gCurrentSession;

        $requestId = (string) $request->getID();
        $rememberedRequest = $_SESSION['saml_reauthentication_request'] ?? null;

        if ($requestId === ''
            || !is_array($rememberedRequest)
            || !isset($rememberedRequest['request_id'], $rememberedRequest['requested_at'])
        ) {
            return false;
        }

        if (!hash_equals((string) $rememberedRequest['request_id'], $requestId)) {
            return false;
        }

        if ((int) $gCurrentSession->getValue('ses_authentication_time', 'U') < (int) $rememberedRequest['requested_at']) {
            return false;
        }

        unset($_SESSION['saml_reauthentication_request']);

        return true;
    }

    /**
     * Remember that this AuthnRequest sent the user to the login form for a fresh login.
     */
    private function rememberReauthenticationRequest(AuthnRequest $request): void
    {
        $_SESSION['saml_reauthentication_request'] = array(
            'request_id' => (string) $request->getID(),
            'requested_at' => time()
        );
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

        if ($sessionIndex !== '') {
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

        /*
        * SessionIndex is optional in a LogoutRequest. Without it the request asks to end
        * the sessions of the principal it names, and a service provider that did not keep
        * the NameID of the login names no principal at all. This endpoint only serves the
        * front-channel bindings, so the browser session carrying the request identifies
        * the session that is meant.
        */
        return $this->findCurrentSessionParticipant($request, $clientId);
    }

    /**
     * Resolve the participant of the browser session that sent a LogoutRequest.
     *
     * Used for a LogoutRequest that carries no SessionIndex, where the session to end
     * cannot be addressed by the message itself.
     *
     * @param LogoutRequest $request The incoming LogoutRequest.
     * @param int $clientId ID of the SAML client that sent the request.
     * @return array|null The participant of the current session, or null if there is none.
     */
    private function findCurrentSessionParticipant(LogoutRequest $request, int $clientId): ?array
    {
        global $gCurrentOrgId, $gCurrentSession, $gValidLogin;

        if (!$gValidLogin) {
            return null;
        }

        $externalSessionId = (string) $gCurrentSession->getValue('ses_external_session_id');

        if ($externalSessionId === '') {
            return null;
        }

        $participantService = new SAMLSessionParticipantService($this->db);

        foreach ($participantService->getParticipants($gCurrentOrgId, $externalSessionId) as $participant) {
            if ((int) $participant['ssp_client_id'] !== $clientId) {
                continue;
            }

            // A request that does name a principal must name the one of this session.
            if (!$this->logoutRequestNameIDMatchesParticipant($request, $participant)) {
                continue;
            }

            return $participant;
        }

        return null;
    }

    /**
     * Check whether a LogoutRequest identifies the stored SAML participant.
     */
    private function logoutRequestMatchesParticipant(LogoutRequest $request, array $participant): bool 
    {
        if ($request->getNameID() === null) {
            return false;
        }

        if (!$this->logoutRequestNameIDMatchesParticipant($request, $participant)) {
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
     * Check whether the NameID of a LogoutRequest addresses a session participant.
     *
     * A request that names no principal cannot contradict the participant, so it matches.
     *
     * @param LogoutRequest $request The incoming LogoutRequest.
     * @param array $participant The stored session participant.
     * @return bool Whether the NameID addresses this participant.
     */
    private function logoutRequestNameIDMatchesParticipant(LogoutRequest $request, array $participant): bool
    {
        $requestNameID = $request->getNameID();

        if ($requestNameID === null) {
            return true;
        }

        /*
        * The entity format identifies a SAML entity, not a principal. A service provider
        * that did not keep the NameID of the login sends the entity ID of the identity
        * provider under this format, which says nothing about the session to end.
        */
        if ((string) $requestNameID->getFormat() === SamlConstants::NAME_ID_FORMAT_ENTITY) {
            return true;
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

        return hash_equals($storedSPNameQualifier, $requestSPNameQualifier);
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
            $this->emitPsrResponse($this->handleIncomingLogoutRequest($message));
            return;
        }
        if ($message instanceof LogoutResponse) {
            $this->emitPsrResponse($this->handleIncomingLogoutResponse($message));
            return;
        }
        throw new Exception('Invalid request in SAMLService->handleSLORequest().');
    }

    /**
     * Start an SP-initiated logout transaction.
     * @throws Exception
     */
    private function handleIncomingLogoutRequest(LogoutRequest $request): ResponseInterface
    {
        global $gLogger;

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

            $frontChannelLogoutUris = $this->notifyOIDCClients($externalSessionId);

            $transaction = $this->prepareLogoutTransaction(
                $externalSessionId,
                (int) $initiatorParticipant['ssp_id'],
                array('type' => SAMLLogoutTransaction::COMPLETION_SAML_RESPONSE),
                $initiatorClientId,
                (int) $initiatorParticipant['ssp_id'],
                $request->getID(),
                $request->getRelayState()
            );

            /*
             * All information required for downstream LogoutRequests is now
             * persisted. Invalidate the Admidio session identified by the
             * persisted SAML participant, not whichever browser session happens
             * to be making this request.
             */
            $this->performLocalLogout($externalSessionId);

            return $this->beginLogoutFlow($transaction, $frontChannelLogoutUris);
        } catch (Exception $exception) {
            $gLogger->error($exception->getMessage());
            return $this->createRedirectResponse(
                $this->createLogoutResponseUrl($initiatorClient, $request->getID(),
                    $request->getRelayState(), SamlConstants::STATUS_RESPONDER)
            );
        }
    }

    /**
     * Start a logout that no SAML service provider initiated.
     *
     * Used for a logout triggered inside Admidio and for an OIDC RP-initiated logout, so
     * that those also reach the SAML service providers of the session. The caller stays
     * responsible for ending the Admidio session itself.
     *
     * @param string $externalSessionId Session whose clients should be logged out.
     * @param string|null $returnUrl Where the browser goes once every client was notified.
     * @return ResponseInterface Response that continues the logout in the browser.
     * @throws Exception
     */
    public function startSessionLogout(string $externalSessionId, ?string $returnUrl = null): ResponseInterface
    {
        $completion = ($returnUrl === null || $returnUrl === '')
            ? array('type' => SAMLLogoutTransaction::COMPLETION_DONE)
            : array('type' => SAMLLogoutTransaction::COMPLETION_REDIRECT, 'url' => $returnUrl);

        $frontChannelLogoutUris = $this->notifyOIDCClients($externalSessionId);
        $transaction = $this->prepareLogoutTransaction($externalSessionId, 0, $completion, 0, null, '', null);

        return $this->beginLogoutFlow($transaction, $frontChannelLogoutUris);
    }

    /**
     * Record the SAML clients of a session that still have to be contacted.
     *
     * @param string $externalSessionId Session that is being logged out.
     * @param int $excludeParticipantId Participant that must not be contacted, 0 for none.
     * @param array<string,mixed> $completion What to do once the SAML chain has finished.
     * @return SAMLLogoutTransaction The saved transaction.
     * @throws Exception
     */
    private function prepareLogoutTransaction(string $externalSessionId, int $excludeParticipantId,
        array $completion, int $initiatorClientId = 0, ?int $initiatorParticipantId = null,
        string $initiatorRequestId = '', ?string $initiatorRelayState = null): SAMLLogoutTransaction
    {
        global $gCurrentOrgId, $gSettingsManager;

        if ($externalSessionId === '') {
            throw new Exception('The SAML logout target has no external session identifier.');
        }

        $participantService = new SAMLSessionParticipantService($this->db);
        $pendingClients = array();
        $samlParticipants = $gSettingsManager->get('sso_saml_enabled') === '1'
            ? $participantService->getParticipants($gCurrentOrgId, $externalSessionId)
            : array();

        foreach ($samlParticipants as $participant) {
            $participantId = (int) $participant['ssp_id'];

            if ($excludeParticipantId > 0 && $participantId === $excludeParticipantId) {
                continue;
            }

            $pendingClients[] = array(
                'participantId' => $participantId,
                'clientId' => (int) $participant['ssp_client_id'],
                'nameId' => (string) $participant['ssp_name_id'],
                'nameIdFormat' => (string) $participant['ssp_name_id_format'],
                'nameIdSPNameQualifier' => $participant['ssp_name_id_sp_name_qualifier'],
                'sessionIndex' => (string) $participant['ssp_session_index']
            );
        }

        $transaction = new SAMLLogoutTransaction($this->db);
        $transaction->initialize($gCurrentOrgId, $initiatorClientId, $initiatorParticipantId,
            $initiatorRequestId, $initiatorRelayState, $pendingClients, $completion);
        $transaction->save();

        return $transaction;
    }

    /**
     * Notify the OIDC clients of a session.
     *
     * The back-channel clients are contacted here, server to server. The front-channel
     * clients are only reported back, because they are loaded through the browser.
     *
     * @param string $externalSessionId Session that is being logged out.
     * @return array<int,string> Front-channel logout URIs that still have to be loaded.
     * @throws Exception
     */
    private function notifyOIDCClients(string $externalSessionId): array
    {
        global $gCurrentOrgId, $gDb, $gSettingsManager;

        if ($gSettingsManager->get('sso_oidc_enabled') !== '1') {
            return array();
        }

        $oidcService = new OIDCService($gDb, $this->currentUser);
        $notificationService = new OIDCLogoutNotificationService($gDb, $oidcService->getIssuerURL());

        $frontChannelLogoutUris = $notificationService->notifySession($gCurrentOrgId, $externalSessionId);

        // Every OIDC client of the session has been contacted or is about to be loaded.
        (new OIDCSessionParticipantService($gDb))->deleteParticipants($gCurrentOrgId, $externalSessionId);

        return $frontChannelLogoutUris;
    }

    /**
     * Enter a prepared logout transaction.
     *
     * The OIDC front-channel clients are loaded first, in one page of parallel iframes, and
     * that page then continues into the first step of the SAML chain. Notifying them before
     * the chain matters because the chain sends the browser through one service provider
     * after another, and a logout abandoned halfway would never reach them.
     *
     * @param SAMLLogoutTransaction $transaction The saved transaction to start.
     * @param array<int,string> $frontChannelLogoutUris OIDC clients to load before the chain.
     * @return ResponseInterface The first response of the logout for the browser.
     * @throws Exception
     */
    private function beginLogoutFlow(SAMLLogoutTransaction $transaction, array $frontChannelLogoutUris): ResponseInterface
    {
        global $gDb;

        $nextStep = $this->continueLogoutTransaction($transaction);

        if (count($frontChannelLogoutUris) === 0) {
            return $nextStep;
        }

        /*
        * The iframe page can only continue to a location, so a next step that renders a
        * page of its own has nowhere to hand over to. That only happens for a logout with
        * no destination at all, where stopping at the iframe page is the correct end.
        */
        $continueUrl = $nextStep->getStatusCode() === 302
            ? $nextStep->getHeaderLine('Location')
            : '';

        $oidcService = new OIDCService($gDb, $this->currentUser);
        $notificationService = new OIDCLogoutNotificationService($gDb, $oidcService->getIssuerURL());

        return $notificationService->createFrontChannelResponse(
            $frontChannelLogoutUris,
            $continueUrl === '' ? null : $continueUrl
        );
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
    private function handleIncomingLogoutResponse(LogoutResponse $response): ResponseInterface
    {
        global $gCurrentOrgId, $gLogger;

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

            return $this->continueLogoutTransaction($transaction);
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
    private function continueLogoutTransaction(SAMLLogoutTransaction $transaction): ResponseInterface
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


            return $this->createLogoutRequestRedirect(
                $transaction,
                $participantId,
                $client,
                $nameID,
                $nameIDFormat,
                is_string($nameIDSPNameQualifier) ? $nameIDSPNameQualifier : null,
                $sessionIndex
            );
        }

        return $this->finalizeLogoutTransaction($transaction);
    }

    /**
     * Finish a logout transaction once every SAML service provider has answered.
     *
     * Where the browser goes from here depends on who started the logout: the initiating
     * service provider receives its LogoutResponse, and any other caller gets the URL it
     * asked for.
     *
     * @param SAMLLogoutTransaction $transaction The transaction that has no pending clients left.
     * @return ResponseInterface The final response for the browser.
     * @throws Exception
     */
    private function finalizeLogoutTransaction(SAMLLogoutTransaction $transaction): ResponseInterface
    {
        $completionType = $transaction->getCompletionType();
        $completionUrl = $transaction->getCompletionUrl();
        $partialLogout = $transaction->hasPartialLogout();

        $initiatorClientId = $transaction->getInitiatorClientId();
        $initiatorRequestId = $transaction->getInitiatorRequestId();
        $initiatorRelayState = $transaction->getInitiatorRelayState();
        $initiatorParticipantId = $transaction->getInitiatorParticipantId();

        /*
        * The initiating SP requested the logout and receives the final response,
        * so its participant record no longer represents an active session.
        */
        if ($initiatorParticipantId > 0) {
            (new SAMLSessionParticipantService($this->db))->deleteParticipant($initiatorParticipantId);
        }

        $transaction->delete();

        if ($completionType === SAMLLogoutTransaction::COMPLETION_SAML_RESPONSE && $initiatorClientId > 0) {
            $status = $partialLogout
                ? SamlConstants::STATUS_PARTIAL_LOGOUT
                : SamlConstants::STATUS_SUCCESS;

            $completionUrl = $this->createLogoutResponseUrl(
                new SAMLClient($this->db, $initiatorClientId),
                $initiatorRequestId,
                $initiatorRelayState,
                $status
            );
        }

        if ($completionUrl !== '') {
            return $this->createRedirectResponse($completionUrl);
        }

        return $this->createLogoutCompletedResponse();
    }

    /**
     * Build a 302 response to the given location.
     */
    private function createRedirectResponse(string $url): ResponseInterface
    {
        return (new \Laminas\Diactoros\Response())
            ->withStatus(302)
            ->withHeader('Location', $url)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }

    /**
     * Build the response for a logout that has nowhere to return to.
     */
    private function createLogoutCompletedResponse(): ResponseInterface
    {
        $body = new \Laminas\Diactoros\Stream(fopen('php://temp', 'r+'));
        $body->write(
            '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="referrer" content="no-referrer">'
            . '<title>Logout</title></head><body></body></html>'
        );

        return (new \Laminas\Diactoros\Response())
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache')
            ->withBody($body);
    }

    /**
     * Send a PSR-7 response to the client.
     */
    private function emitPsrResponse(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }

        echo (string) $response->getBody();
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
    private function createLogoutRequestRedirect(SAMLLogoutTransaction $transaction, int $participantId,
        SAMLClient $client, string $nameID, string $nameIDFormat, ?string $nameIDSPNameQualifier, string $sessionIndex): ResponseInterface
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

        // The Single Logout profile requires a LogoutRequest that is sent through the
        // HTTP Redirect or HTTP POST binding to be signed, independently of the
        // signature settings of the client.
        $logoutRequest->setSignature(
            $this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert'])
        );

        /*
        * Persist correlation data before returning the redirect. The next HTTP
        * request is independent of the destroyed Admidio login session.
        */
        $transaction->setCurrentRequest($participantId, (int) $client->getValue('smc_id'), $requestId);
        $transaction->save();

        $messageContext = new \LightSaml\Context\Profile\MessageContext();
        $messageContext->setMessage($logoutRequest);

        return $this->createRedirectResponse($this->buildRedirectBindingUrl($messageContext, $sloUrl));
    }

    /**
     * Send the final LogoutResponse to the initiating service provider.
     *
     * @throws Exception
     */
    private function createLogoutResponseUrl(SAMLClient $client, string $inResponseTo, ?string $relayState, string $statusCode): string
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

        // A LogoutResponse sent through a front-channel binding must be signed as well.
        $logoutResponse->setSignature(
            $this->getSignatureWriter($keys['idpPrivateKey'], $keys['idpCert'])
        );

        $messageContext = new \LightSaml\Context\Profile\MessageContext();
        $messageContext->setMessage($logoutResponse);

        return $this->buildRedirectBindingUrl($messageContext, $sloUrl);
    }

    /**
     * Serialize a SAML message into a URL of the HTTP Redirect binding.
     *
     * The URL is needed as a plain string, because it can also become the continue target of
     * the OIDC front-channel page instead of being sent as a redirect right away.
     *
     * @param \LightSaml\Context\Profile\MessageContext $messageContext The message to send.
     * @param string $destination Endpoint of the service provider.
     * @return string The URL that carries the message.
     * @throws Exception
     */
    private function buildRedirectBindingUrl(\LightSaml\Context\Profile\MessageContext $messageContext,
        string $destination): string
    {
        $binding = new HttpRedirectBinding();
        $httpResponse = $binding->send($messageContext, $destination);
        $url = (string) $httpResponse->headers->get('Location');

        if ($url === '') {
            throw new Exception('The SAML redirect binding did not produce a destination URL.');
        }

        return $url;
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

        /*
        * The Single Logout profile requires signed messages on the front-channel
        * bindings. Admidio can only verify a signature when a certificate is stored for
        * the client, so the signature is mandatory for every client that has one, and
        * for every client whose configuration demands signatures. A client without a
        * certificate has no verifiable identity at all and keeps the previous behaviour.
        */
        $signatureRequired = trim((string) $client->getValue('smc_x509_certificate')) !== ''
            || (bool) $client->getValue('smc_require_auth_signed')
            || (bool) $client->getValue('smc_validate_signatures');

        if ($signatureRequired) {
            $this->validateSignature($client, $message, true);
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

            // Always send the roles attribute, even if the user is not a member of any mapped
            // role. Some service providers (e.g. the DokuWiki SAML plugin) reject an assertion
            // when the configured group attribute is missing entirely, so send it without values.
            $att->setAttributeValue([]);
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
