<?php
namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\Entities\ClaimSetEntity;

// Since the 'custom' scope's claims depend on the client-specific 
class IdTokenResponse extends \OpenIDConnectServer\IdTokenResponse
{

    /**
     * @param AccessTokenEntityInterface $accessToken
     * @return array
     */
    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        /** @var OIDCClient */
        $client = $accessToken->getClient();
        // Add the custom scope (client-dependent! to the claims builder, if it does not exist yet)
        if (!$this->claimExtractor->hasClaimSet('custom')) {
            $this->claimExtractor->addClaimSet(
                new ClaimSetEntity('custom', array_keys($client->getFieldMapping()))
            );
        }
        return parent::getExtraParams($accessToken);
    }

    // The issuer in the JWT token MUST be the same as the issuer in the discovery document
    // (https://openid.net/specs/openid-connect-discovery-1_0.html#IssuerDiscovery)
    // The issuer is the URL of the OpenID Provider (OP) that issued the ID token.
    // The OIDC library sets the issuer to the server name only ('https://' . $_SERVER['HTTP_HOST'),
    // so we need to correct this here!

    protected function getBuilder(AccessTokenEntityInterface $accessToken, UserEntityInterface $userEntity)
    {
        global $gSettingsManager;
        $builder = parent::getBuilder($accessToken, $userEntity);
        return $builder->issuedBy( $gSettingsManager->get('sso_oidc_issuer_url'));
    }

}
