<?php
namespace Admidio\SSO\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;

class OIDCConsent extends Entity
{
    public function __construct(Database $database, int $id = 0)
    {
        parent::__construct($database, TBL_OIDC_CONSENTS, 'oco', $id);
    }

    public function readDataByUserAndClient(int $organizationID, int $userID, int $clientID): bool
    {
        return $this->readDataByColumns(
            array(
                'oco_org_id' => $organizationID,
                'oco_usr_id' => $userID,
                'oco_ocl_id' => $clientID
            )
        );
    }

    public function coversScopes(array $scopes): bool
    {
        $storedScopes = preg_split('/\s+/', trim($this->getValue('oco_scopes')));

        return count(array_diff($scopes, $storedScopes)) === 0;
    }

    /**
     * Whether this consent was given for the claim release policy of this fingerprint.
     *
     * The scopes alone do not describe what a client receives, because the claim mapping of
     * the client decides which profile fields a scope releases. A consent that was stored
     * before the fingerprint existed does not describe a policy at all and never matches, so
     * the user is asked once more.
     */
    public function matchesReleasePolicy(string $policyHash): bool
    {
        $storedHash = (string) $this->getValue('oco_policy_hash');

        return $storedHash !== '' && hash_equals($storedHash, $policyHash);
    }
}