<?php
namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ClientTrait;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;


class ClientEntity extends Entity implements ClientEntityInterface {

    public function __construct(Database $database, string $client_id = '') {
        parent::__construct($database, TBL_OIDC_CLIENTS, 'ocl');
        if (!empty($client_id)) {
            $this->readDataByColumns([$this->columnPrefix . '_client_id' => $client_id]);
        }
    }
    public function getIdentifier(): string {
        return $this->getValue('ocl_client_id')??'';
    }

    public function getName(): string {
        return $this->getValue('ocl_client_name')??'';
    }

    public function getRedirectUri(): string
    {
        return $this->getValue('ocl_redirect_uri')??'';
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
