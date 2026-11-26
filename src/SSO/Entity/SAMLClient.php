<?php
namespace Admidio\SSO\Entity;

use Admidio\Infrastructure\Database;

class SAMLClient extends SSOClient 
{
    public function __construct(Database $database, $client_id = null) {
        parent::__construct($database, 'saml', TBL_SAML_CLIENTS, 'smc', $client_id);
    }

    /**
     * Split a list of URLs, one per line, as it is stored and edited. Empty lines and duplicates are dropped.
     * @param string $text The URLs, separated by line breaks
     * @return array<int,string> The URLs in their order
     */
    public static function splitUrls(string $text): array
    {
        $urls = array();
        foreach (preg_split('/\R/', $text) ?: array() as $line) {
            $line = trim($line);
            if ($line !== '' && !in_array($line, $urls, true)) {
                $urls[] = $line;
            }
        }
        return $urls;
    }

    /**
     * The assertion consumer service URLs the service provider has registered. The first one is the
     * default, the response goes there if the request names none.
     * @return array<int,string>
     */
    public function getAcsUrls(): array
    {
        return self::splitUrls((string) $this->getValue('smc_acs_url'));
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
            'smc_metadata_hash', 'smc_metadata_valid_until', 'smc_metadata_cache_duration', 'smc_metadata_failures', 'smc_metadata_error'
        ));
    }
}
