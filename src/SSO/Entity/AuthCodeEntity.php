<?php

namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Database;

class AuthCodeEntity extends TokenEntity implements AuthCodeEntityInterface
{
    public function __construct(Database $database, string $codeId = '') {
        parent::__construct($database, TBL_OIDC_AUTH_CODES, 'oac', $codeId);
    }

    /**
     * Get the redirect URI associated with the auth code.
     */
    public function getRedirectUri(): string|null
    {
        return $this->getValue($this->columnPrefix . '_redirect_uri', 'database');
    }

    /**
     * Set the redirect URI for the auth code.
     */
    public function setRedirectUri($uri): void
    {
        $this->setValue($this->columnPrefix . '_redirect_uri', $uri);
    }

    /**
     * Delete the expired authorization codes, except those that tokens are still linked to. A
     * code that is presented again after it expired has to find its row, so that the tokens it
     * produced can be revoked; the row is purged once its last token has expired.
     */
    public function deleteExpiredTokens(): void
    {
        $this->db->queryPrepared(
            'DELETE FROM ' . $this->tableName . '
              WHERE oac_expires_at < ?
                AND NOT EXISTS (SELECT 1 FROM ' . TBL_OIDC_ACCESS_TOKENS . ' WHERE oat_oac_id = oac_id)
                AND NOT EXISTS (SELECT 1 FROM ' . TBL_OIDC_REFRESH_TOKENS . ' WHERE ort_oac_id = oac_id)',
            array(DATETIME_NOW)
        );
    }
}
