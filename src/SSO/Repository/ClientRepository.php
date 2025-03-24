<?php
namespace Admidio\SSO\Repository;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use Admidio\SSO\Entity\ClientEntity;
use Admidio\Infrastructure\Database;


/**
 * Class ClientRepository, implements ClientRepositoryInterface
 *  - Handles access to the client information stored in adm_oauth2_client
 */
class ClientRepository implements ClientRepositoryInterface {
    protected Database $db;
    public function __construct(Database $database) {
        $this->db = $database;
    }

    public function getClientEntity($clientIdentifier): ?ClientEntityInterface {
        $client = new ClientEntity($this->db, $clientIdentifier);
        if ($client->isNewRecord()) 
            return null;
        else
            return $client;
    }
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool {
        $client = new ClientEntity($this->db, $clientIdentifier);
        if ($client->isNewRecord()) 
            return false;
        else {
            return password_verify($clientSecret, $client->getValue('ocl_secret'));
        }
    }

    public function isRedirectUriAllowed($uri)
    {
        $client = new ClientEntity($this->db);
        $client->readDataByColumns(['ocl_redirect_uri' => $uri]);
        return !$client->isNewRecord();
    }
};
