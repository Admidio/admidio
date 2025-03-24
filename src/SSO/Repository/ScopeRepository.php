<?php

namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Admidio\SSO\Entity\ScopeEntity;
use Admidio\Infrastructure\Entity\Entity;

class ScopeRepository implements ScopeRepositoryInterface
{
    private $db;

    public function __construct($databaseConnection)
    {
        $this->db = $databaseConnection;
    }

    /**
     * Returns a ScopeEntity if the scope exists in the database.
     */
    public function getScopeEntityByIdentifier($identifier): ?ScopeEntity
    {
        $scopes = new Entity($this->db, TABLE_PREFIX . '_oauth_scopes', 'osc');
        $scopes->readDataByColumns(['osc_scope_id' => $identifier]);
        if (!$scopes->isNewRecord()) {
            return new ScopeEntity($scopes->getValue('osc_scope_id'), $scopes->getValue('osc_description'));
        }
        return null;
    }

    /**
     * Validates requested scopes against allowed scopes.
     */
    public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $client, string $userId = null, ?string $authCodeId = null): array
    {
        $validScopes = [];

        foreach ($scopes as $scope) {
            $validScope = $this->getScopeEntityByIdentifier($scope->getIdentifier());
            if ($validScope !== null) {
                $validScopes[] = $validScope;
            }
        }

        return $validScopes;
    }
}
