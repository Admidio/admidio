<?php

namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Entity\ScopeEntity;

class ScopeRepository implements ScopeRepositoryInterface
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    /**
     * Returns a ScopeEntity if the scope can be processed by the OAuth server.
     */
    public function getScopeEntityByIdentifier($identifier): ?ScopeEntity
    {
        if (!is_string($identifier) || $identifier === '') {
            return null;
        }

        // If we return null for a scope that is not handled, the oauth server will throw an error.
        // Since the oidc spec specifies that scopes that are not handled should be ignored, we return a new ScopeEntity instead.
        // This way, the scope will be ignored and not throw an error.
        // This is a workaround for the fact that the oauth2-server library does not support ignoring unknown scopes.
        return new ScopeEntity($identifier);
    }

    /**
     * Validates requested scopes against allowed scopes.
     */
    public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $client, ?string $userId = null, ?string $authCodeId = null): array
    {
        // First check that the 'openid' scope is included, as it is required by the spec!
        $requestedScopes = array_map(
            static fn (ScopeEntity $scope): string => $scope->getIdentifier(),
            $scopes
        );

        if ($grantType === 'authorization_code'
            && !in_array(OIDCClient::SCOPE_OPENID, $requestedScopes, true)
        ) {
            throw OAuthServerException::invalidRequest('scope', 'The openid scope is required.');
        }

        // Then check the requested scopes against the allowed and filter them correspondingly
        $allowedScopes = OIDCClient::getSupportedScopes();

        if ($client instanceof OIDCClient) {
            $allowedScopes = $client->getAllowedScopes();
        }

        $validScopes = array();
        foreach ($scopes as $scope) {
            $scopeID = $scope->getIdentifier();
            if (in_array($scopeID, $allowedScopes, true) && !array_key_exists($scopeID, $validScopes)) {
                $validScopes[$scopeID] = $scope;
            }
        }
        return array_values($validScopes);
    }    
}
