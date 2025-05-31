<?php

namespace Admidio\SSO\Entity;

use Admidio\SSO\Service\OIDCService;
use League\OAuth2\Server\Entities\TokenInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Users\Entity\User;
use Admidio\SSO\Entity\UserEntity;


class TokenEntity extends Entity implements TokenInterface
{
    /**
     * @var ScopeEntityInterface[]
     */
    protected array $scopes = [];

    protected ?OIDCClient $client = null;
    protected ?UserEntity $user = null;

    public function __construct(Database $database, string $tableName = '', string $columnPrefix = '', string $tokenId = '') {
        global $gProfileFields;
        parent::__construct($database, $tableName, $columnPrefix);
        if (!empty($tokenId)) {
            $this->readDataByColumns([$this->columnPrefix . '_token' => $tokenId]);
        }
    }

    public function deleteExpiredTokens() {
        $sql = '
          DELETE FROM ' . $this->tableName . '
          WHERE ' . $this->columnPrefix . '_expires_at < NOW()';
        $this->db->queryPrepared($sql);
    }
          

    public function save(bool $updateFingerPrint = true): bool {
        // Convert scope list to a string and store it in the corresponding attribute
        $scopes = array_map(
            fn($scope) => $scope->getIdentifier(),
            $this->getScopes());
        $this->setValue($this->columnPrefix . '_scope', implode(' ',$scopes));

        return parent::save($updateFingerPrint);
    }

    public function readData(string $sqlWhereCondition, array $queryParams = array()): bool {
        global $gProfileFields;

        $retVal = parent::readData($sqlWhereCondition, $queryParams);

        // Read scopes from DB
        $this->scopes = [];
        foreach (explode(' ', $this->getValue($this->columnPrefix . '_scope')??'') as $scope) {
            if (!empty($scope)) {
                $scopeObject = new ScopeEntity($scope);
                $this->addScope($scopeObject);
            }
        }

        // read client and user from DB
        $ocl_id = $this->getValue($this->columnPrefix . '_ocl_id');
        if (!empty($ocl_id)) {
            $this->client = new OIDCClient($this->db);
            $this->client->readDataById($ocl_id);
            OIDCService::setClient($this->client);
        }
        $usr_id = $this->getValue($this->columnPrefix . '_usr_id');
        if (!empty($usr_id)) {
            $this->user = new UserEntity($this->db, $gProfileFields, $this->client, $usr_id);
        }

        return $retVal;
    }

    /**
     * Convert the access token entity to a serialized JSON format.
     */
    public function jsonSerialize()
    {
        // TODO_RK
        return [
            'identifier'   => $this->getIdentifier(),
            'expiry_date'  => $this->getExpiryDateTime()->format('Y-m-d H:i:s'),
            'scopes'       => array_map(fn($scope) => $scope->getIdentifier(), $this->getScopes()),
            'user_id'      => $this->getUserIdentifier(),
            'client_id'    => $this->getClient()->getIdentifier(),
        ];
    }


    /**
     * @return non-empty-string
     */
    public function getIdentifier(): string
    {
        return $this->getValue($this->columnPrefix . '_token');
    }

    /**
     * @param non-empty-string $identifier
     */
    public function setIdentifier(string $identifier): void
    {
        // TODO_RK: Store identifier as a hash!
        $this->setValue($this->columnPrefix . '_token', $identifier);
    }




    /**
     * Associate a scope with the token.
     */
    public function addScope(ScopeEntityInterface $scope): void
    {
        $this->scopes[$scope->getIdentifier()] = $scope;
    }

    /**
     * Return an array of scopes associated with the token.
     *
     * @return ScopeEntityInterface[]
     */
    public function getScopes(): array
    {
        return array_values($this->scopes);
    }

    /**
     * Get the token's expiry date time.
     */
    public function getExpiryDateTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->getValue($this->columnPrefix . '_expires_at', 'Y-m-d H:i:s'));
    }

    /**
     * Set the date time when the token expires.
     */
    public function setExpiryDateTime(\DateTimeImmutable $dateTime): void
    {
        $expiryDate = $dateTime->format('Y-m-d H:i:s');
        $this->setValue($this->columnPrefix . '_expires_at', $expiryDate);
    }

    /**
     * Set the identifier of the user associated with the token.
     *
     * @param non-empty-string $identifier The identifier of the user
     */
    public function setUserIdentifier(string $identifier): void
    {
        global $gProfileFields;
        if ($this->client) {
            $userIDfield = $this->client->getUseridField();
            $this->user = new UserEntity($this->db, $gProfileFields, $this->client);
            $this->user->readDataByUserIDfield($userIDfield, $identifier);
            
            // If no user with that identifier can be found -> thow exception
            if ($this->user->isNewRecord()) { // user with given identifier couldn't be loaded
                throw new OAuthServerException("User '$identifier' not found", 6, 'invalid_user');
            }
            $this->setValue($this->columnPrefix . '_usr_id', $this->user->getValue($this->user->getKeyColumnName()));
        } else {
            throw new OAuthServerException('Client not set in TokenEntity->setUserIdentifier()', 6, 'invalid_client');
        }
    }

    /**
     * Get the token user's identifier.
     *
     * @return non-empty-string|null
     */
    public function getUserIdentifier(): string|null
    {
        if ($this->client === null || $this->user === null) {
            return null;
        }
        $userIDfield = $this->client->getUseridField();
        return $this->user->getValue($userIDfield);
    }

    /**
     * Get the user that the token was issued to.
     */
    public function getUser(): UserEntity
    {
        return $this->user;
    }

    /**
     * Get the client that the token was issued to.
     */
    public function getClient(): ClientEntityInterface
    {
        return $this->client;
    }

    /**
     * Set the client that the token was issued to.
     */
    public function setClient(ClientEntityInterface $client): void
    {
        if (!($client instanceof OIDCClient)) {
            throw new OAuthServerException('Client \'' . $client->getIdentifier() . '\' must be an instance of OIDCClient', 6, 'invalid_client');
        }
        // We cannot be sure that $client is an OIDCClient object, so we create a copy of type OIDCClient with the same identifier
        $this->client = $client;
        if ($this->user) {
            $this->user->setClient($this->client);
        }
        $this->setValue($this->columnPrefix . '_ocl_id', $this->client->getValue($this->client->keyColumnName));
    }

}