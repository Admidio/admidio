<?php
namespace Admidio\SSO\Service;


use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\SSO\Entity\Key;
use Admidio\Infrastructure\Exception;


class KeyService {
    // Constants to indicate signing/encryption usage    
    public const USAGE_OIDC_SIGNING = 'oidc_signing';
    public const USAGE_SAML_SIGNING = 'saml_signing';
    public const USAGE_SAML_ENCRYPTION = 'saml_encryption';
    public const CREATE_DEFAULT_KEY_VALUE = '__create_default_key__';
    private Database $db;

    public function __construct(Database $db) {
        $this->db           = $db;
    }

    /**
     * Create a key object for the given UUID. Cryptographic keys belong to one organization,
     * so the key is only read if it belongs to the current organization. The UUID alone must
     * never be sufficient to address a key of a different organization.
     *
     * @param string $keyUUID UUID of the cryptographic key. If empty, an empty key object is returned.
     * @return Key Key object; still a new record if the UUID is empty or does not belong to this organization.
     * @throws Exception
     */
    public function createKeyObject(string $keyUUID = ''): Key {
        global $gCurrentOrgId;

        $ssoKey = new Key($this->db);

        if ($keyUUID !== '') {
            $ssoKey->readDataByColumns(array(
                'key_org_id' => $gCurrentOrgId,
                'key_uuid' => $keyUUID
            ));
        }

        return $ssoKey;
    }

    /**
     * Read an existing cryptographic key of the current organization.
     *
     * @param string $keyUUID UUID of the cryptographic key.
     * @return Key Key object of the current organization.
     * @throws Exception SYS_SSO_KEY_NOT_FOUND
     */
    public function getKeyFromUUID(string $keyUUID): Key {
        $ssoKey = $this->createKeyObject($keyUUID);

        if ($ssoKey->isNewRecord()) {
            throw new Exception('SYS_SSO_KEY_NOT_FOUND');
        }

        return $ssoKey;
    }

    /**
     * Return an array of configured cryptographic keys.
     *
     * @param bool $activeOnly Only return active keys.
     * @param string|null $usage Only return keys that are usable for this purpose.
     *
     * @return array
     * @throws Exception
     */
   public function getKeysData(bool $activeOnly = false, ?string $usage = NULL) {
        global $gCurrentOrgId;
        $sql = 'SELECT key_id, key_uuid, key_org_id, key_name, key_algorithm, key_certificate, key_expires_at, key_is_active
                      FROM ' . TBL_SSO_KEYS . '
                  WHERE key_org_id = ?
                     ' . ($activeOnly ? ' and key_is_active = TRUE ' : '') . '
                  ORDER BY key_name';
        $keysList = $this->db->getArrayFromSql($sql, array($gCurrentOrgId));

        if ($usage === null) {
            return $keysList;
        }

        $usableKeys = array();

        foreach ($keysList as $keyData) {
            try {
                $this->getUsableKey((int) $keyData['key_id'], $usage);
                $usableKeys[] = $keyData;
            } catch (Exception $exception) {
                // The key is not offered for this purpose.
            }
        }

        return $usableKeys;
    }

    /**
     * Load and validate a key for a specific SSO purpose.
     *
     * @throws Exception
     */
    public function getUsableKey(int $keyId, string $usage): Key {
        global $gCurrentOrgId;

        if ($keyId <= 0) {
            throw new Exception('SYS_SSO_KEY_NOT_FOUND');
        }

        $key = new Key($this->db, $keyId);

        if ($key->isNewRecord() || (int) $key->getValue('key_org_id') !== $gCurrentOrgId) {
            throw new Exception('SYS_SSO_KEY_NOT_FOUND');
        }

        if (!(bool) $key->getValue('key_is_active')) {
            throw new Exception('SYS_SSO_KEY_INACTIVE');
        }

        $this->validateExpiration($key);
        $this->validateKeyMaterial($key, $usage);
        $this->validateUsage($key, $usage);

        return $key;
    }

    /**
     * Check whether a key is usable for a specific SSO purpose.
     */
    public function isKeyUsable(int $keyId, string $usage): bool {
        try {
            $this->getUsableKey($keyId, $usage);
            return true;
        } catch (Exception $exception) {
            return false;
        }
    }

    /**
     * Validate the expiry date stored for a key.
     *
     * @throws Exception
     */
    private function validateExpiration(Key $key): void
    {
        $expiresAtValue = $key->getValue('key_expires_at');

        if ($expiresAtValue === '' || $expiresAtValue === null) {
            return;
        }

        $expiresAt = $this->createDateFromFormValue((string) $expiresAtValue);

        if ($expiresAt < new \DateTimeImmutable('today')) {
            throw new Exception('SYS_SSO_KEY_EXPIRED');
        }
    }

    /**
     * Validate all stored key material and ensure that it belongs together.
     *
     * @throws Exception
     */
    private function validateKeyMaterial(Key $key, string $usage): void {
        $privateKeyPem = (string) $key->getValue('key_private');
        $publicKeyPem = (string) $key->getValue('key_public');
        $certificatePem = (string) $key->getValue('key_certificate');

        if ($privateKeyPem === '') {
            throw new Exception('SYS_SSO_PRIVATE_KEY_MISSING');
        }
        if ($publicKeyPem === '') {
            throw new Exception('SYS_SSO_PUBLIC_KEY_MISSING');
        }
        $certificateRequired = in_array($usage, [self::USAGE_SAML_SIGNING, self::USAGE_SAML_ENCRYPTION], true);
        if ($certificateRequired && $certificatePem === '') {
            throw new Exception('SYS_SSO_CERTIFICATE_MISSING');
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new Exception('SYS_SSO_PRIVATE_KEY_INVALID');
        }

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            throw new Exception('SYS_SSO_PUBLIC_KEY_INVALID');
        }

        $privateKeyDetails = openssl_pkey_get_details($privateKey);
        $publicKeyDetails = openssl_pkey_get_details($publicKey);

        if (
            $privateKeyDetails === false
            || $publicKeyDetails === false
            || !isset($privateKeyDetails['key'], $publicKeyDetails['key'])
        ) {
            throw new Exception('SYS_SSO_KEY_INVALID');
        }

        if ($this->normalizePublicKey($privateKeyDetails['key']) !== $this->normalizePublicKey($publicKeyDetails['key'])
            || $privateKeyDetails['type'] !== $publicKeyDetails['type']) {
            throw new Exception('SYS_SSO_KEY_PAIR_MISMATCH');
        }

        if ($certificatePem !== '') {
            $certificate = openssl_x509_read($certificatePem);
            
            if ($certificate === false) {
                throw new Exception('SYS_SSO_CERTIFICATE_INVALID');
            }
                
            if (!openssl_x509_check_private_key($certificate, $privateKey)) {
                throw new Exception('SYS_SSO_CERTIFICATE_KEY_MISMATCH');
            }
            $certificateData = openssl_x509_parse($certificate);
    
            if ($certificateData === false) {
                throw new Exception('SYS_SSO_CERTIFICATE_INVALID');
            }
    
            $currentTimestamp = time();
    
            if (isset($certificateData['validFrom_time_t'])
                && $currentTimestamp < (int) $certificateData['validFrom_time_t']
            ) {
                throw new Exception('SYS_SSO_CERTIFICATE_NOT_YET_VALID');
            }
    
            if (isset($certificateData['validTo_time_t'])
                && $currentTimestamp > (int) $certificateData['validTo_time_t']
            ) {
                throw new Exception('SYS_SSO_CERTIFICATE_EXPIRED');
            }
        }


        $this->validateAlgorithmField($key, $privateKeyDetails);
    }

    /**
     * Validate whether a key can be used for the requested protocol operation.
     *
     * @throws Exception
     */
    private function validateUsage(Key $key, string $usage): void {
        $privateKey = openssl_pkey_get_private((string) $key->getValue('key_private'));
        if ($privateKey === false) {
            throw new Exception('SYS_SSO_PRIVATE_KEY_INVALID');
        }

        $keyDetails = openssl_pkey_get_details($privateKey);
        if ($keyDetails === false) {
            throw new Exception('SYS_SSO_KEY_INVALID');
        }

        switch ($usage) {
            case self::USAGE_OIDC_SIGNING:
            case self::USAGE_SAML_SIGNING:
            case self::USAGE_SAML_ENCRYPTION:
                if ($keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
                    throw new Exception('SYS_SSO_RSA_KEY_REQUIRED');
                }
                break;

            default:
                throw new Exception('SYS_SSO_KEY_USAGE_INVALID');
        }
    }

    /**
     * Ensure that the configured algorithm matches the actual key type and size.
     *
     * @param array $keyDetails Result of openssl_pkey_get_details().
     *
     * @throws Exception
     */
    private function validateAlgorithmField(Key $key, array $keyDetails): void {
        $algorithm = (string) $key->getValue('key_algorithm');

        if ($keyDetails['type'] === OPENSSL_KEYTYPE_RSA) {
            if (!str_starts_with($algorithm, 'RSA')) {
                throw new Exception('SYS_SSO_KEY_ALGORITHM_MISMATCH');
            }

            if (
                preg_match('/^RSA-(\d+)$/', $algorithm, $matches) === 1
                && isset($keyDetails['bits'])
                && (int) $matches[1] !== (int) $keyDetails['bits']
            ) {
                throw new Exception('SYS_SSO_KEY_ALGORITHM_MISMATCH');
            }
            return;
        }

        if ($keyDetails['type'] === OPENSSL_KEYTYPE_EC) {
            if (!str_starts_with($algorithm, 'ECDSA')) {
                throw new Exception('SYS_SSO_KEY_ALGORITHM_MISMATCH');
            }
            return;
        }

        throw new Exception('SYS_SSO_KEY_TYPE_UNSUPPORTED');
    }

    /**
     * Normalize a PEM public key before comparing it.
     */
    private function normalizePublicKey(string $publicKey): string
    {
        return preg_replace('/\s+/', '', trim($publicKey));
    }    

    /**
     * Create a date from a localized form value.
     *
     * @param string $value
     * @return \DateTimeImmutable
     * @throws Exception
     */
    private function createDateFromFormValue(string $value): \DateTimeImmutable
    {
        global $gSettingsManager;

        // Create date object and format date_to in English format and system format and push to date range array
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            // check if date_from  has system format
            $date = \DateTimeImmutable::createFromFormat($gSettingsManager->getString('system_date'), $value);
        }
        if ($date === false) {
            throw new Exception('SYS_SSO_KEY_EXPIRATION_INVALID');
        }
        return $date;
    }

    public function setupKeyConfig(string $algorithm) : array {
        $config = ['digest_alg' => 'sha256'];
        switch($algorithm) {
            case "RSA":
            case "RSA-2048":
                $config['private_key_type'] = OPENSSL_KEYTYPE_RSA;
                $config['private_key_bits'] = 2048;
                break;
            case "RSA-3072":
                $config['private_key_type'] = OPENSSL_KEYTYPE_RSA;
                $config['private_key_bits'] = 3072;
                break;
            case "RSA-4096":
                $config['private_key_type'] = OPENSSL_KEYTYPE_RSA;
                $config['private_key_bits'] = 4096;
                break;
            case "RSA-8192":
                $config['private_key_type'] = OPENSSL_KEYTYPE_RSA;
                $config['private_key_bits'] = 8192;
                break;
            case "ECDSA":
            case "ECDSA-256":
                $config['private_key_type'] = OPENSSL_KEYTYPE_EC;
                $config['curve_name'] = 'prime256v1';
                break;
            case "ECDSA-384":
                $config['private_key_type'] = OPENSSL_KEYTYPE_EC;
                $config['curve_name'] = 'secp384r1';
                break;
            case "ECDSA-521":    
                $config['private_key_type'] = OPENSSL_KEYTYPE_EC;
                $config['curve_name'] = 'secp521r1';
                break;
        }
        return $config;
    }

    /**
     * Create a cryptographic key with the default SSO settings.
     * The generated RSA key can be used for SAML signing/encryption and OIDC signing.
     *
     * @return int Database ID of the newly created key.
     * @throws Exception
     */
    public function createDefaultKey(): int
    {
        global $gCurrentOrgId, $gCurrentOrganization;

        $algorithm = 'RSA';
        $expiration = new \DateTime();
        $expiration->modify('+5 years');

        $nextNumber = 1;
        foreach ($this->getKeysData() as $keyData) {
            if (preg_match('/^Default key #(\\d+)$/', (string) $keyData['key_name'], $matches) === 1) {
                $nextNumber = max($nextNumber, (int) $matches[1] + 1);
            }
        }

        $generatedKey = $this->generateKey($algorithm);

        $csrData = array_filter(
            array(
                'organizationName' => (string) $gCurrentOrganization->getValue('org_longname', 'database'),
                'commonName' => ADMIDIO_URL,
                'emailAddress' => (string) $gCurrentOrganization->getValue('org_email_administrator', 'database')
            ),
            static fn(string $value): bool => $value !== ''
        );

        $certificate = $this->generateCertificate(
            $generatedKey['private_key'],
            $csrData,
            $algorithm,
            $expiration->format('Y-m-d')
        );

        $ssoKey = new Key($this->db);
        $ssoKey->setValue('key_org_id', $gCurrentOrgId);
        $ssoKey->setValue('key_name', 'Default key #' . $nextNumber);
        $ssoKey->setValue('key_algorithm', $algorithm);
        $ssoKey->setValue('key_private', $generatedKey['private_key']);
        $ssoKey->setValue('key_public', $generatedKey['public_key']);
        $ssoKey->setValue('key_certificate', $certificate);
        $ssoKey->setValue('key_expires_at', $expiration->format('Y-m-d'));
        $ssoKey->setValue('key_is_active', true);
        $ssoKey->save();

        return (int) $ssoKey->getValue('key_id');
     }
 
    public function generateKey(string $algorithm) : array {
        $config = $this->setupKeyConfig($algorithm);

        $key = openssl_pkey_new($config);
        if ($key === false) {
            throw new Exception(openssl_error_string(), array(openssl_error_string()));
        }
        openssl_pkey_export($key, $privateKey);
        
        $keyDetails = openssl_pkey_get_details($key);
        $publicKey = $keyDetails['key'];
        
        return ['private_key' => $privateKey, 'public_key' => $publicKey, 'key_details' => $keyDetails];
    }

    public function generateCertificate(string $keyPem, array $csrData, string $algorithm, string $expiration) : string {
        $config = $this->setupKeyConfig($algorithm);

        $privateKey = openssl_pkey_get_private($keyPem);
        $csr = openssl_csr_new($csrData, $privateKey, $config);
        if (!$csr) {
            throw new Exception('SYS_SSO_CERTIFICATE_FAILURE', array(openssl_error_string()));
        }
        
        $expiration = \DateTime::createFromFormat('Y-m-d', $expiration);
        $now = new \DateTime();
        $certificate = openssl_csr_sign($csr, null, $privateKey, $now->diff($expiration)->days);
        if (!$certificate) {
            throw new Exception('SYS_SSO_CERTIFICATE_FAILURE', array(openssl_error_string()));
        }
        
        openssl_x509_export($certificate, $certificatePEM);
        return $certificatePEM;
    }

    /**
     * @return array{filename:string,contentType:string,content:string}
     */
    public function getPkcs12ExportData(string $keyUUID, string $password = ''): array
    {
        global $gL10n;

        if (empty($keyUUID)) {
            throw new Exception('SYS_SSO_KEY_EXPORT_FAILURE', array($gL10n->get('SYS_ERROR_UUID_MISSING')));
        }

        // only keys of the current organization may be exported
        $ssoKey = $this->createKeyObject($keyUUID);

        if ($ssoKey->isNewRecord()) {
            throw new Exception('SYS_SSO_KEY_EXPORT_FAILURE', array($gL10n->get('SYS_SSO_KEY_NOT_FOUND')));
        }

        $name = $ssoKey->getValue('key_name');
        $privkeyPem = $ssoKey->getValue('key_private');
        $certPem = $ssoKey->getValue('key_certificate');

        if (empty($privkeyPem) || empty($certPem)) {
            throw new Exception('SYS_SSO_KEY_EXPORT_FAILURE', array($gL10n->get('SYS_SSO_KEY_NOT_FOUND')));
        }

        // Load the private key
        $privateKey = openssl_pkey_get_private($privkeyPem);
        if (!$privateKey) {
            throw new Exception('SYS_SSO_KEY_EXPORT_FAILURE', array(openssl_error_string()));
        }

        // Load the certificate
        $certificate = openssl_x509_read($certPem);
        if (!$certificate) {
            throw new Exception('SYS_SSO_KEY_EXPORT_FAILURE', array(openssl_error_string()));
        }

        // Export the PKCS#12
        $pkcs12 = "";
        openssl_pkcs12_export($certificate, $pkcs12, $privateKey, $password, ["friendly_name" => $name]);

        if (!$pkcs12) {
            throw new Exception('SYS_SSO_KEY_EXPORT_FAILURE', array(openssl_error_string()));
        }

        $filename = FileSystemUtils::getSanitizedPathEntry($name) . '.p12';

        return array(
            'filename' => $filename,
            'contentType' => 'application/x-pkcs12',
            'content' => $pkcs12
        );
    }

    public function exportToPkcs12(string $keyUUID, string $password = ''): void
    {
        $export = $this->getPkcs12ExportData($keyUUID, $password);

        // Send the PKCS#12 file as a download to the browser.
        header('Content-Type: ' . $export['contentType']);
        header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
        header('Content-Length: ' . strlen($export['content']));
        echo $export['content'];
        exit;
    }

    public function extractCertificateInfo($certificatePem) {
        try {
            // Parse the certificate from PEM format
            $certificateResource = openssl_x509_read($certificatePem);
    
            if ($certificateResource === false) {
                throw new Exception("Failed to parse certificate: " . openssl_error_string());
            }
    
            // Extract certificate details
            $certificateDetails = openssl_x509_parse($certificateResource);
    
            if ($certificateDetails === false) {
                throw new Exception("Failed to extract certificate details: " . openssl_error_string());
            }
    
            // Return the certificate details
            return $certificateDetails;
    
        } catch (Exception $e) {
            // Handle errors appropriately (e.g., log, display a message)
            error_log("Certificate processing error: " . $e->getMessage());
            return false; // Or throw the exception, depending on your error handling
        }
    }
    
    
    /**
     * Save data from the SSO key edit form into the database.
     * @param string $keyUUID UUID of the cryptographic key
     * @throws Exception
     */
    public function save(string $keyUUID, string $mode = 'save')
    {
        global $gCurrentSession;

        // check form field input and sanitized it from malicious content
        $keyEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $keyEditForm->validate($_POST);

        $this->saveData($keyUUID, $formValues, $mode);
    }

    /**
     * Save already validated SSO key data.
     *
     * @param string $keyUUID UUID of an existing key or an empty string for a new key.
     * @param array $formValues Validated key and certificate values.
     * @param string $mode One of save, key or cert.
     * @return Key
     * @throws Exception
     */
    public function saveData(string $keyUUID, array $formValues, string $mode = 'save'): Key
    {
        global $gCurrentOrgId;

        // only keys of the current organization may be modified
        $ssoKey = $this->createKeyObject($keyUUID);

        if (!empty($keyUUID) && $ssoKey->isNewRecord()) {
            throw new Exception('SYS_SSO_KEY_NOT_FOUND');
        }

        // If no key or cert exists yet, make sure it is generated
        if ($ssoKey->isNewRecord() || empty($ssoKey->getValue('key_private'))) {
            $mode = 'key';
        } elseif (empty($ssoKey->getValue('key_certificate')) && ($mode != 'key')) {
            $mode = 'cert';
        }

        switch ($mode) {
            case 'key':
                // 1. Create a new key for the selected algorithm
                $key_algorithm = $formValues['key_algorithm'];
                $newKey = $this->generateKey($key_algorithm);
                $ssoKey->setValue('key_algorithm', $key_algorithm);
                $ssoKey->setValue('key_private', $newKey['private_key']);
                $ssoKey->setValue('key_public', $newKey['public_key']);

                // fall-through
            case 'cert':
                unset($formValues['key_algorithm']);
                unset($formValues['key_public']);
                unset($formValues['key_private']);
                
                // 2. Sign the existing or new key for a certificate
                $key_algorithm = $ssoKey->getValue('key_algorithm');
                
                $csrData = [
                    "countryName" => $formValues['cert_country'],
                    "stateOrProvinceName" => $formValues['cert_state'],
                    "localityName" => $formValues['cert_locality'],
                    "organizationName" => $formValues['cert_org'],
                    "organizationalUnitName" => $formValues['cert_orgunit'],
                    "commonName" => $formValues['cert_common_name'],
                    "emailAddress" => $formValues['cert_admin_email'],
                ];

                $certificatePEM = $this->generateCertificate($ssoKey->getValue('key_private'), $csrData, $key_algorithm, $formValues['key_expires_at']);

                $ssoKey->setValue('key_certificate', $certificatePEM);
                $ssoKey->setValue('key_expires_at', $formValues['key_expires_at']);

                // fall-through
            case 'save':
                unset($formValues['key_certificate']);
                unset($formValues['key_expires_at']);

                // 3. Handle all attributes (name, etc.) that do not need the key or cert to be re-generated

                foreach ($formValues as $key => $value) {
                    if (str_starts_with($key, 'key_')) {
                        $ssoKey->setValue($key, $value);
                    }
                }
                $ssoKey->setValue('key_org_id', $gCurrentOrgId);
                break;
            default:
                throw new Exception('Invalid mode for SSO key save.');
        }

        $ssoKey->save();
        return $ssoKey;
    }


    /**
     * @return array{filename:string,contentType:string,content:string}
     * @throws Exception
     */
    public function getCertificateExportData(string $keyUUID): array
    {
        global $gL10n;

        if (empty($keyUUID)) {
            throw new Exception('SYS_SSO_KEY_EXPORT_FAILURE', array($gL10n->get('SYS_ERROR_UUID_MISSING')));
        }

        // only certificates of the current organization may be exported
        $ssoKey = $this->createKeyObject($keyUUID);

        if ($ssoKey->isNewRecord()) {
            throw new Exception('SYS_SSO_KEY_EXPORT_FAILURE', array($gL10n->get('SYS_SSO_KEY_NOT_FOUND')));
        }

        $certificate = (string) $ssoKey->getValue('key_certificate');

        if ($certificate === '') {
            throw new Exception('SYS_SSO_KEY_EXPORT_FAILURE', array($gL10n->get('SYS_SSO_CERTIFICATE_MISSING')));
        }

        $filename = FileSystemUtils::getSanitizedPathEntry($ssoKey->getValue('key_name')) . '_Certificate.pem';

        return array(
            'filename' => $filename,
            'contentType' => 'application/x-pem-file',
            'content' => $certificate
        );
    }

    public function exportCertificate(string $keyUUID): void
    {
        $export = $this->getCertificateExportData($keyUUID);

        // Set headers for file download
        header('Content-Type: ' . $export['contentType']);
        header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
        header('Content-Length: ' . strlen($export['content']));

        // Output the certificate contents
        echo $export['content'];
        exit;
    }
}
