<?php
namespace Admidio\SSO\Entity;

use Admidio\Infrastructure\Database;

class SAMLClient extends SSOClient 
{
    public function __construct(Database $database, $client_id = null) {
        parent::__construct($database, 'saml', TBL_SAML_CLIENTS, 'smc', $client_id);
    }

    public function save(bool $updateFingerPrint = true): bool
    {
        if ($this->isNewRecord() && $this->getValue('smc_org_id') === '') {
            $this->setValue('smc_org_id', $GLOBALS['gCurrentOrgId']);
        }

        return parent::save($updateFingerPrint);
    }
}
