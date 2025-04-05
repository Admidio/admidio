<?php

namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

use Admidio\SSO\Entity\ScopeEntity;


class ScopeRepository implements ScopeRepositoryInterface
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    /**
     * Returns a ScopeEntity if the scope exists in the database.
     */
    public function getScopeEntityByIdentifier($identifier): ?ScopeEntity
    {
        switch ($identifier) {
            case 'openid':
            case 'profile':
            case 'email':
            case 'address':
            case 'phone':
            // case 'offline_access':
            // case 'groups':
            // default: 
                return new ScopeEntity($identifier);
            default: return null;

        }
        // $scope = new ScopeEntity($this->db, $identifier);
        // return (!$scope->isNewRecord()) ? $scope : null;
    }

    /**
     * Validates requested scopes against allowed scopes.
     */
    public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $client, ?string $userId = null, ?string $authCodeId = null): array
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
