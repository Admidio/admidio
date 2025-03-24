<?php

namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use Admidio\SSO\Entity\AuthCodeEntity;

use Admidio\Infrastructure\Entity\Entity;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    /**
     * Creates a new AuthCode entity.
     */
    public function getNewAuthCode(): AuthCodeEntity
    {
        return new AuthCodeEntity();
    }

    /**
     * Persists a new authorization code to the database.
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCode): void
    {
        $authcode = new Entity($this->db, TABLE_PREFIX . '_auth_codes', 'oac');
        $authcode->setValue('oac_auth_code', $authCode->getIdentifier());
        $authcode->setValue('oac_usr_id', $authCode->getUserIdentifier());
        $authcode->setValue('oac_ocl_id', $authCode->getClient()->getIdentifier());
        $authcode->setValue('oac_redirect_uri', implode(',', $authCode->getRedirectUri()));
        $authcode->setValue('oac_expires_at', $authCode->getExpiryDateTime()->format('Y-m-d H:i:s'));
        $authcode->setValue('oac_revoked', false);
        // $authcode->setValue('oac_scopes', $authCode->getIdentifier());
        $authcode->save();
    }

    /**
     * Revokes an authorization code by marking it as revoked.
     */
    public function revokeAuthCode($codeId): void
    {
        $authcode = new Entity($this->db, TABLE_PREFIX . '_auth_codes', 'oac');
        $authcode->readDataByColumns(['oac_auth_code' => $codeId]);
        if (!$authcode->isNewRecord()) {
            $authcode->setValue('oac_revoked', true);
            $authcode->save();
        }
    }

    /**
     * Checks if an authorization code has been revoked.
     */
    public function isAuthCodeRevoked($codeId): bool
    {
        $authcode = new Entity($this->db, TABLE_PREFIX . '_auth_codes', 'oac');
        $authcode->readDataByColumns(['oac_auth_code' => $codeId]);
        if (!$authcode->isNewRecord()) {
            return (bool)$authcode->getValue('oac_revoked');
        } else {
            return true;
        }

    }
}
