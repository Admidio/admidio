<?php
/**
 * Shared setup for the single sign-on regression tests.
 *
 * The SSO tests need clients, keys and tokens that exist in the database, not doubles: the
 * boundaries they cover are organization scoping and access rights, and both are decided by
 * what the queries actually select.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Roles\Entity\RolesRights;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Entity\SAMLClient;

trait SsoClientFixture
{
    /**
     * A suffix that keeps the names and identifiers of one test apart from every other run.
     */
    protected function suffix(): string
    {
        return bin2hex(random_bytes(6));
    }

    /**
     * Create an OIDC client whose access is restricted to the given roles.
     *
     * @param string $suffix Unique suffix for the client name and identifier.
     * @param array<int,int> $roleIds Roles that are allowed to use the client, empty for everybody.
     * @param int|null $organizationId Organization of the client, by default the current one.
     * @return OIDCClient The saved client.
     */
    protected function createRestrictedClient(string $suffix, array $roleIds, ?int $organizationId = null): OIDCClient
    {
        global $gCurrentOrgId;

        if ($organizationId === null) {
            $organizationId = (int) $gCurrentOrgId;
        }

        $client = new OIDCClient($this->getDatabase());
        $client->setValue('ocl_org_id', $organizationId);
        $client->setValue('ocl_client_id', 'regression-' . $suffix);
        $client->setValue('ocl_client_name', 'Regression client ' . $suffix);
        $client->setValue('ocl_client_secret', password_hash('secret-' . $suffix, PASSWORD_DEFAULT));
        $client->setValue('ocl_redirect_uri', 'https://client.example/' . $suffix . '/callback');
        $client->setValue('ocl_userid_field', 'usr_uuid');
        $client->setValue('ocl_enabled', 1);
        $client->save();

        $this->grantClientAccess('sso_oidc_access', (int) $client->getValue('ocl_id'), $roleIds);

        // Re-read, so that the roles rights object of the client knows the roles that were granted.
        $reloaded = new OIDCClient($this->getDatabase());
        $reloaded->readDataById((int) $client->getValue('ocl_id'));

        return $reloaded;
    }

    /**
     * Create a SAML client whose access is restricted to the given roles.
     *
     * @param string $suffix Unique suffix for the client name and entity ID.
     * @param array<int,int> $roleIds Roles that are allowed to use the client, empty for everybody.
     * @param int|null $organizationId Organization of the client, by default the current one.
     * @return SAMLClient The saved client.
     */
    protected function createSamlClient(string $suffix, array $roleIds = array(), ?int $organizationId = null): SAMLClient
    {
        global $gCurrentOrgId;

        if ($organizationId === null) {
            $organizationId = (int) $gCurrentOrgId;
        }

        $client = new SAMLClient($this->getDatabase());
        $client->setValue('smc_org_id', $organizationId);
        $client->setValue('smc_client_id', 'https://sp.example/' . $suffix);
        $client->setValue('smc_client_name', 'Regression SP ' . $suffix);
        $client->setValue('smc_acs_url', 'https://sp.example/' . $suffix . '/acs');
        $client->setValue('smc_x509_certificate', '');
        $client->setValue('smc_userid_field', 'usr_uuid');
        $client->setValue('smc_enabled', 1);
        $client->save();

        $this->grantClientAccess('sso_saml_access', (int) $client->getValue('smc_id'), $roleIds);

        $reloaded = new SAMLClient($this->getDatabase());
        $reloaded->readDataById((int) $client->getValue('smc_id'));

        return $reloaded;
    }

    /**
     * Restrict the access of a client to a set of roles, through the production rights object.
     *
     * @param string $rightName Internal name of the right, sso_oidc_access or sso_saml_access.
     * @param int $clientId Internal id of the client.
     * @param array<int,int> $roleIds Roles that may use the client.
     */
    protected function grantClientAccess(string $rightName, int $clientId, array $roleIds): void
    {
        if (count($roleIds) === 0) {
            return;
        }

        $rights = new RolesRights($this->getDatabase(), $rightName, $clientId);
        $rights->saveRoles($roleIds);
    }

    /**
     * Write a token of a user for a client, the way the OAuth server does it.
     *
     * @param OIDCClient $client Client the token was issued to.
     * @param int $userId User the token was issued for.
     * @param string $table Token table.
     * @param string $prefix Column prefix of that table.
     * @param string $plainToken Plaintext identifier, generated when it is not given.
     * @param string $expiresAt Expiry timestamp of the token.
     * @return string The stored hash of the token, which is what the rows are addressed by.
     */
    protected function createToken(OIDCClient $client, int $userId, string $table, string $prefix,
        string $plainToken = '', string $expiresAt = ''): string
    {
        if ($plainToken === '') {
            $plainToken = bin2hex(random_bytes(16));
        }
        if ($expiresAt === '') {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        }

        $tokenHash = hash('sha256', $plainToken);

        $this->getDatabase()->queryPrepared(
            'INSERT INTO ' . $table . ' (' . $prefix . '_usr_id, ' . $prefix . '_ocl_id, '
                . $prefix . '_token, ' . $prefix . '_scope, ' . $prefix . '_expires_at, '
                . $prefix . '_revoked)
             VALUES (?, ?, ?, ?, ?, ?)',
            array($userId, (int) $client->getValue('ocl_id'), $tokenHash, 'openid profile', $expiresAt, 0)
        );

        return $tokenHash;
    }
}
