<?php
namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Admidio\Infrastructure\Database;

class OIDCClient extends SSOClient implements ClientEntityInterface
{
    public function __construct(Database $database, $client_id = null) {
        parent::__construct($database, TBL_OIDC_CLIENTS, 'ocl', $client_id);
        $this->ssoType = 'oidc';
    }

    public function getIdentifier(): string {
        return $this->getValue($this->columnPrefix . '_client_id')??'';
    }

    public function getName(): string {
        return $this->getValue($this->columnPrefix . '_client_name')??'';
    }

    public function getRedirectUri(): string
    {
        return $this->getValue($this->columnPrefix . '_redirect_uri')??'';
    }

    public function isConfidential(): bool
    {
        // TODO_RK
        return true;
    }

    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * In addition to the default ignored columns, don't log fot_views
     *
     * @return true Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(),
        ($this->newRecord)?[$this->columnPrefix.'_name']:[]);
    }
}
