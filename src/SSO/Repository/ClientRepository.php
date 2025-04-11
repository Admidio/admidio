<?php
namespace Admidio\SSO\Repository;

use Admidio\SSO\Service\OIDCService;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;

use Admidio\Infrastructure\Database;
use Admidio\SSO\Entity\OIDCClient;


/**
 * Class ClientRepository, implements ClientRepositoryInterface
 *  - Handles access to the client information stored in adm_oidc_client
 */
class ClientRepository implements ClientRepositoryInterface {
    protected Database $db;
    public function __construct(Database $database) {
        $this->db = $database;
    }

    public function getClientEntity($clientIdentifier): ?ClientEntityInterface {
        $client = new OIDCClient($this->db, $clientIdentifier);
        if ($client->isNewRecord()) 
            return null;
        else {
            OIDCService::setClient($client);
            return $client;
        }
    }
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool {
        $client = new OIDCClient($this->db, $clientIdentifier);
        if ($client->isNewRecord()) 
            return false;
        else {
            return password_verify($clientSecret, $client->getValue($client->getColumnPrefix() . '_client_secret'));
        }
    }

    public function isRedirectUriAllowed($uri)
    {
        $client = new OIDCClient($this->db);
        $client->readDataByColumns([$client->getColumnPrefix() . '_redirect_uri' => $uri]);
        return !$client->isNewRecord();
    }
};
