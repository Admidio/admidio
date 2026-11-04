<?php
namespace Admidio\SSO\Entity;

use Admidio\Infrastructure\Database;

class SAMLClient extends SSOClient 
{
    public function __construct(Database $database, $client_id = null) {
        parent::__construct($database, 'saml', TBL_SAML_CLIENTS, 'smc', $client_id);
    }

    /**
     * @return string|null Returns the hook ID of this entity.
     * @see Entity::getHookId()
     */
    public function getHookId(): ?string
    {
        return 'saml_client';
    }

    /**
     * What the automatic metadata refresh records about its last check changes with every check and
     * is not a setting of the client, so it is not part of the changelog. The settings the refresh
     * applies from the metadata (endpoints, certificates) are logged like any other change.
     * @return array Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(), array(
            'smc_metadata_checked', 'smc_metadata_next_check', 'smc_metadata_etag', 'smc_metadata_last_modified',
            'smc_metadata_hash', 'smc_metadata_valid_until', 'smc_metadata_failures', 'smc_metadata_error'
        ));
    }
}
