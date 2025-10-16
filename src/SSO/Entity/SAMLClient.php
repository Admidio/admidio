<?php
namespace Admidio\SSO\Entity;

use Admidio\Infrastructure\Database;

class SAMLClient extends SSOClient 
{
    public function __construct(Database $database, $client_id = null) {
        parent::__construct($database, 'saml', TBL_SAML_CLIENTS, 'smc', $client_id);
    }

}
