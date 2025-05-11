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
        // If we return null for a scope that is not handled, the oauth server will throw an error.
        // Since the oidc spec specifies that scopes that are not handled should be ignored, we return a new ScopeEntity instead.
        // This way, the scope will be ignored and not throw an error.
        // This is a workaround for the fact that the oauth2-server library does not support ignoring unknown scopes.
        switch ($identifier) {
            case 'openid':
            case 'profile':
            case 'email':
            case 'address':
            case 'phone':
            // case 'offline_access':
            // case 'groups':
            default: 
                return new ScopeEntity($identifier);
            // default: return null;
        }
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
