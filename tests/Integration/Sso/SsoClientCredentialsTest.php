<?php
/**
 * Regression coverage for the client authentication at the token endpoint.
 *
 * OAuth 2.0 URL-encodes client ID and secret before they go into an HTTP Basic header (RFC 6749,
 * section 2.3.1), which matters for client IDs that are URLs. Only the authorization code grant
 * decoded them, so a client could exchange its code but not refresh its tokens
 * (oidcc-refresh-token: invalid_client).
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Grants\OIDCAuthCodeGrant;
use Admidio\SSO\Grants\OIDCRefreshTokenGrant;
use Admidio\SSO\Repository\AuthCodeRepository;
use Admidio\SSO\Repository\RefreshTokenRepository;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Laminas\Diactoros\ServerRequest;
use League\OAuth2\Server\Grant\AbstractGrant;
use ReflectionMethod;

class SsoClientCredentialsTest extends AdministratorTestCase
{
    private const CLIENT_ID = 'https://client.example/';
    private const CLIENT_SECRET = 'secret:with/special+chars';

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @dataProvider grantAndHeaderProvider
     * @testdox The $grantType grant reads the client credentials of $header
     */
    public function testEveryGrantReadsTheClientCredentials(string $grantType, string $header): void
    {
        $grant = $grantType === 'authorization code'
            ? new OIDCAuthCodeGrant(new AuthCodeRepository($this->getDatabase()), new RefreshTokenRepository($this->getDatabase()), new \DateInterval('PT10M'))
            : new OIDCRefreshTokenGrant(new RefreshTokenRepository($this->getDatabase()));

        $credentials = match ($header) {
            'a URL-encoded Basic header' => urlencode(self::CLIENT_ID) . ':' . urlencode(self::CLIENT_SECRET),
            'a raw Basic header' => self::CLIENT_ID . ':' . self::CLIENT_SECRET,
            default => null
        };
        $request = new ServerRequest(array(), array(), 'https://op.example/token', 'POST');
        if ($credentials === null) {
            $request = $request->withParsedBody(array('client_id' => self::CLIENT_ID, 'client_secret' => self::CLIENT_SECRET));
        } else {
            $request = $request->withHeader('Authorization', 'Basic ' . base64_encode($credentials));
        }

        $this->assertSame(array(self::CLIENT_ID, self::CLIENT_SECRET), $this->clientCredentials($grant, $request));
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function grantAndHeaderProvider(): array
    {
        $cases = array();
        foreach (array('authorization code', 'refresh token') as $grantType) {
            foreach (array('a URL-encoded Basic header', 'a raw Basic header', 'the POST body') as $header) {
                $cases[$grantType . ', ' . $header] = array($grantType, $header);
            }
        }
        return $cases;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function clientCredentials(AbstractGrant $grant, ServerRequest $request): array
    {
        return (new ReflectionMethod($grant, 'getClientCredentials'))->invoke($grant, $request);
    }
}
