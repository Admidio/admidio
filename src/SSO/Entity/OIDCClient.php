<?php
namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Admidio\Infrastructure\Database;

class OIDCClient extends SSOClient implements ClientEntityInterface
{
    public function __construct(Database $database, $client_id = null) {
        parent::__construct($database, 'oidc', TBL_OIDC_CLIENTS, 'ocl', $client_id);
        if ($this->isNewRecord()) {
            $this->dbColumns[$this->columnPrefix . '_scope'] = 'profile email address phone groups custom';

        }
    }

    /**
     * @return string|null Returns the hook ID of this entity.
     * @see Entity::getHookId()
     */
    public function getHookId(): ?string
    {
        return 'oidc_client';
    }

    /**
     * @return array Returns the columns whose value must not be handed to a hook callback.
     * @see Entity::getSensitiveHookColumns()
     */
    public function getSensitiveHookColumns(): array
    {
        return array('ocl_client_secret');
    }

    public function getRedirectUri(): string
    {
        return $this->getValue($this->columnPrefix . '_redirect_uri', 'database')??'';
    }

    public function isConfidential(): bool
    {
        // TODO_RK
        return true;
    }

    public function getFieldMappingNoDefault(): bool
    {
        return $this->getFieldMappingCatchall();
    }
}
