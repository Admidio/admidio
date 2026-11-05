<?php
namespace Admidio\SSO\Repository;

use Throwable;
use Admidio\SSO\Service\OIDCService;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

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
        global $gCurrentOrgId;
        $client = new OIDCClient($this->db);

        $client->readDataByColumns(array(
            'ocl_org_id' => $gCurrentOrgId,
            'ocl_client_id' => $clientIdentifier
        ));
        if ($client->isNewRecord()) {
            return null;
        }
        // A disabled client is returned as well. Throwing here ended in a plain server error; the
        // authorization endpoint refuses it with a page for the user (OIDCService), and the token endpoint
        // does not authenticate it (validateClient() below), which answers invalid_client.
        OIDCService::setClient($client);
        return $client;
    }
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool {
        global $gCurrentOrgId;

        $client = new OIDCClient($this->db);
        $client->readDataByColumns(array(
            'ocl_org_id' => $gCurrentOrgId,
            'ocl_client_id' => $clientIdentifier
        ));
        if ($client->isNewRecord()) {
            return false;
        } elseif (!$client->isEnabled()) {
            return false;
        } elseif ($grantType !== null && !in_array($grantType, $client->getAllowedGrantTypes(), true)) {
            return false;
        } else {
            $storedHash = $client->getValue($client->getColumnPrefix() . '_client_secret');
            if (password_verify((string) $clientSecret, $storedHash)) {
                return true;
            }

            // The secret of an HTTP Basic header is URL-decoded when it is in the encoded form (RFC 6749, section
            // 2.3.1, see ClientCredentials), but a client that sends it raw is just as common, and a raw secret with
            // a "+" is in that form too: "ab+c" arrives as "ab c". The raw value is urlencode() of the decoded one,
            // so it is tried as well. The generated secrets are Base64, they contain "+" in about every second one.
            $rawSecret = urlencode((string) $clientSecret);
            return $rawSecret !== (string) $clientSecret && password_verify($rawSecret, $storedHash);
        }
    }

    public function isRedirectUriAllowed($uri)
    {
        global $gCurrentOrgId;

        $client = new OIDCClient($this->db);
        $client->readDataByColumns(array(
            'ocl_org_id' => $gCurrentOrgId,
            $client->getColumnPrefix() . '_redirect_uri' => $uri
        ));
        return !$client->isNewRecord();
    }
};
