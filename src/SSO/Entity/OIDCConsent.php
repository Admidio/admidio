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
}