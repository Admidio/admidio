<?php
namespace Admidio\SSO\Service;


use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Users\Entity\User;
use Admidio\SSO\Entity\Key;
use Admidio\Infrastructure\Exception;


class KeyService {
    private Database $db;
    private User $currentUser;

    public function __construct(Database $db) {
        $this->db           = $db;
    }

    /**
     * Return an array of all configured cryptographic key data.
     * @param bool $activeOnly If true, only active key uuids are returned
     * @return array Returns an array with all key data for list view.
     */
    public function getKeysData(bool $activeOnly = false) {
        global $gCurrentOrgId;
        $sql = 'SELECT key_id, key_uuid, key_org_id, key_name, key_algorithm, key_certificate, key_expires_at, key_is_active
                      FROM ' . TBL_SSO_KEYS . '
                  WHERE key_org_id = ?
                     ' . ($activeOnly ? ' and key_is_active = 1 ' : '') . '
                  ORDER BY key_name';
        $keysList = $this->db->getArrayFromSql($sql, array($gCurrentOrgId));
        return $keysList;
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

    public function generateKey(string $algorithm) : array {
        $config = $this->setupKeyConfig($algorithm);

        $key = openssl_pkey_new($config);
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

    public function exportToPkcs12(string $keyUUID, string $password = '') {
        global $gL10n;

        $ssoKey = new Key($this->db);
        $ssoKey->readDataByUuid($keyUUID);

        if (empty($keyUUID)) {
            throw new Exception('SYS_SSO_KEY_EXPORT_FAILURE', array($gL10n->get('SYS_ERROR_UUID_MISSING')));
        }
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
        
        // Send the PKCS#12 file as a download to the browser (All errors were already handled with an exception, which caused a JSON response!)
        $filename = FileSystemUtils::getSanitizedPathEntry($name);
        header('Content-Type: application/x-pkcs12');
        header('Content-Disposition: attachment; filename="' . $filename . '.p12"');
        header('Content-Length: ' . strlen($pkcs12));
        echo $pkcs12;
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
        global $gCurrentSession, $gCurrentOrgId;

        // check form field input and sanitized it from malicious content
        $keyEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $keyEditForm->validate($_POST);

        $ssoKey = new Key($this->db);
        if (!empty($keyUUID)) {
            $ssoKey->readDataByUuid($keyUUID);
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
                $privateKey = $ssoKey->getValue('key_private');
                $privateKey = openssl_pkey_get_private($privateKey);
                
                $csrData = [
                    "countryName" => $formValues['cert_country'],
                    "stateOrProvinceName" => $formValues['cert_state'],
                    "localityName" => $formValues['cert_locality'],
                    "organizationName" => $formValues['cert_org'],
                    "organizationalUnitName" => $formValues['cert_orgunit'],
                    "commonName" => $formValues['cert_common_name'], // Your IdP domain
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

                // write form values in menu object
                foreach ($formValues as $key => $value) {
                    if (str_starts_with($key, 'key_')) {
                        $ssoKey->setValue($key, $value);
                    }
                }
                $ssoKey->setValue('key_org_id', $gCurrentOrgId);
        }

        $ssoKey->save();
    }


    public function exportCertificate(string $keyUUID) {
        $ssoKey = new Key($this->db);
        $ssoKey->readDataByUuid($keyUUID);
        $certificate = $ssoKey->getValue('key_certificate');
        $filename = FileSystemUtils::getSanitizedPathEntry($ssoKey->getValue('key_name'));

        // Set headers for file download
        header('Content-Type: application/x-pem-file');
        header('Content-Disposition: attachment; filename="' . $filename . '_Certificate.pem"');
        header('Content-Length: ' . strlen($certificate));

        // Output the certificate contents
        echo $certificate;
        exit;
    }
}
