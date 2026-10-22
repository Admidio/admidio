<?php

namespace Admidio\SSO\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\SSO\Entity\OIDCClient;
use Admidio\Users\Entity\User;

/**
 * Ends the single sign-on access that a role membership justified.
 *
 * `SSOClient::hasAccessRight()` is evaluated while an authorization request is being processed.
 * Afterwards the tokens issued from it stay usable until they expire, so a user who loses the role
 * that gave them access to a client would keep that access for the lifetime of the refresh token.
 * This service is called whenever a role membership changes and revokes the tokens of every client
 * the user may no longer use.
 *
 * The SAML clients need none of this: an assertion is issued per request and
 * `SAMLService::handleSSORequest()` asks for the access right every single time.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SSOAccessRevocationService
{
    public function __construct(private Database $database)
    {
    }

    /**
     * Revoke the OIDC tokens of every client of the organization that the user may no longer use.
     *
     * The user object is built in the organization that is being judged, because
     * `User::__construct()` takes the organization from `$gCurrentOrgId` and the role memberships
     * are read for that organization.
     *
     * @param int $userId User whose role memberships have just changed.
     * @param int|null $organizationId Organization whose clients are judged, by default the current one.
     * @return array<int,string> Names of the clients whose tokens were revoked.
     * @throws Exception
     */
    public function revokeLostClientAccess(int $userId, ?int $organizationId = null): array
    {
        global $gProfileFields, $gCurrentOrgId, $gLogger;

        if ($userId <= 0) {
            return array();
        }

        if ($organizationId === null) {
            $organizationId = (int) $gCurrentOrgId;
        }

        if ($organizationId <= 0 || $organizationId !== (int) $gCurrentOrgId) {
            // The role memberships of the user can only be read in the current organization.
            return array();
        }

        $sql = 'SELECT ocl_id FROM ' . TBL_OIDC_CLIENTS . '
                 WHERE ocl_org_id = ? -- $organizationId';
        $statement = $this->database->queryPrepared($sql, array($organizationId));
        $clientIds = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);

        if (count($clientIds) === 0) {
            return array();
        }

        $user = new User($this->database, $gProfileFields, $userId);
        if ((int) $user->getValue('usr_id') !== $userId) {
            return array();
        }

        $revokedClients = array();

        foreach ($clientIds as $clientId) {
            $client = new OIDCClient($this->database);
            if (!$client->readDataById((int) $clientId)) {
                continue;
            }

            if ($client->hasAccessRight($user)) {
                continue;
            }

            if ($this->revokeTokensOfClient($userId, (int) $clientId) > 0) {
                $revokedClients[] = (string) $client->getValue('ocl_client_name');
            }
        }

        if (count($revokedClients) > 0 && isset($gLogger)) {
            $gLogger->notice('SSO: The OIDC tokens of a user were revoked because the role membership that gave access ended.',
                array('userId' => $userId, 'organizationId' => $organizationId, 'clients' => $revokedClients));
        }

        return $revokedClients;
    }

    /**
     * Whether any single sign-on client of the organization restricts its access to this role.
     *
     * Almost every membership change happens in a role that no client knows about, so this one
     * cheap query keeps the whole re-evaluation out of the way of the ordinary case.
     *
     * @param int $roleId Role whose memberships are changing.
     * @return bool **true** if at least one OIDC or SAML client names this role in its access rights.
     * @throws Exception
     */
    public function isRoleUsedForClientAccess(int $roleId): bool
    {
        if ($roleId <= 0) {
            return false;
        }

        $sql = 'SELECT COUNT(*) AS count_rights
                  FROM ' . TBL_ROLES_RIGHTS_DATA . '
            INNER JOIN ' . TBL_ROLES_RIGHTS . '
                    ON ror_id = rrd_ror_id
                 WHERE rrd_rol_id = ? -- $roleId
                   AND ror_name_intern IN (?, ?) -- SSO access rights';
        $statement = $this->database->queryPrepared($sql, array($roleId, 'sso_oidc_access', 'sso_saml_access'));

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * The users whose access has to be judged again once this role is gone.
     *
     * Deleting a role removes its memberships with one bulk statement instead of through
     * `Membership::delete()`, so the members have to be collected before that happens.
     *
     * @param int $roleId Role that is about to be deleted.
     * @return array<int,int> User ids of the current members, empty if no client uses the role.
     * @throws Exception
     */
    public function getUsersToRecheckForRole(int $roleId): array
    {
        if (!$this->isRoleUsedForClientAccess($roleId)) {
            return array();
        }

        $sql = 'SELECT DISTINCT mem_usr_id
                  FROM ' . TBL_MEMBERS . '
                 WHERE mem_rol_id = ? -- $roleId';
        $statement = $this->database->queryPrepared($sql, array($roleId));

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN, 0));
    }

    /**
     * Revoke every token and authorization code that this user still holds for this client.
     *
     * @param int $userId User whose tokens are revoked.
     * @param int $clientId Internal id of the OIDC client.
     * @return int Number of rows that were revoked.
     * @throws Exception
     */
    private function revokeTokensOfClient(int $userId, int $clientId): int
    {
        $revoked = 0;

        $tables = array(
            array(TBL_OIDC_ACCESS_TOKENS, 'oat'),
            array(TBL_OIDC_REFRESH_TOKENS, 'ort'),
            array(TBL_OIDC_AUTH_CODES, 'oac')
        );

        foreach ($tables as [$table, $prefix]) {
            $sql = 'UPDATE ' . $table . '
                       SET ' . $prefix . '_revoked = true
                     WHERE ' . $prefix . '_usr_id = ? -- $userId
                       AND ' . $prefix . '_ocl_id = ? -- $clientId
                       AND (' . $prefix . '_revoked = false OR ' . $prefix . '_revoked IS NULL)';
            $statement = $this->database->queryPrepared($sql, array($userId, $clientId));
            $revoked += $statement->rowCount();
        }

        return $revoked;
    }
}
