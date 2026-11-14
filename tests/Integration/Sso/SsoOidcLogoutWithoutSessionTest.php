<?php
/**
 * Regression coverage for an OpenID Connect RP-initiated logout when nobody is logged in.
 *
 * The Admidio session can end in other ways than the logout of the relying party (a logout of
 * another client, the timeout), while the relying party keeps its own session. Its logout request
 * was answered with the error "The current session cannot be used for OpenID Connect logout", so a
 * user of DokuWiki could not log out. There is nothing left to end, so the browser returns to the
 * registered post-logout address.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Session\Entity\Session;
use Admidio\SSO\Service\OIDCService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\UI\Component\Message;
use RuntimeException;

class SsoOidcLogoutWithoutSessionTest extends AdministratorTestCase
{
    use SsoClientFixture;

    private const POST_LOGOUT_URI = 'https://client.example/after-logout';

    /** @var array<string,mixed> */
    private array $previousGlobals = array();
    private ?string $previousQueryString = null;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (array('gCurrentSession', 'gValidLogin') as $name) {
            $this->previousGlobals[$name] = $GLOBALS[$name] ?? null;
        }
        $this->previousQueryString = $_SERVER['QUERY_STRING'] ?? null;

        // A browser without a login: a fresh session that has no user and no external session id
        $GLOBALS['gCurrentSession'] = new Session($this->getDatabase(), COOKIE_PREFIX);
        $GLOBALS['gValidLogin'] = false;
    }

    protected function tearDown(): void
    {
        foreach ($this->previousGlobals as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        if ($this->previousQueryString === null) {
            unset($_SERVER['QUERY_STRING']);
        } else {
            $_SERVER['QUERY_STRING'] = $this->previousQueryString;
        }

        parent::tearDown();
    }

    private function clientWithPostLogoutUri(): string
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        $client->setValue('ocl_post_logout_redirect_uris', self::POST_LOGOUT_URI);
        $client->save();

        return $client->getIdentifier();
    }

    private function logout(array $query): \Psr\Http\Message\ResponseInterface
    {
        global $gCurrentUser;

        $_SERVER['QUERY_STRING'] = http_build_query($query);

        return (new OIDCService($this->getDatabase(), $gCurrentUser))->handleLogoutRequest();
    }

    /**
     * @testdox Without a login the logout returns to the registered post-logout address with the state
     */
    public function testTheBrowserReturnsToTheRelyingParty(): void
    {
        $response = $this->logout(array(
            'client_id' => $this->clientWithPostLogoutUri(),
            'post_logout_redirect_uri' => self::POST_LOGOUT_URI,
            'state' => 'the-state'
        ));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(self::POST_LOGOUT_URI . '?state=the-state', $response->getHeaderLine('Location'));
    }

    /**
     * @testdox Without a login and without a return address the logout is confirmed
     */
    public function testTheLogoutIsConfirmedWithoutAReturnAddress(): void
    {
        $response = $this->logout(array('client_id' => $this->clientWithPostLogoutUri()));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(array('logout' => true), json_decode((string) $response->getBody(), true));
    }

    /**
     * Not being logged in must not turn the endpoint into a redirect to any address. The browser
     * of the user gets an Admidio page, not a JSON document.
     *
     * @testdox Without a login an unregistered post-logout address is still refused, with a page
     */
    public function testAnUnregisteredReturnAddressIsRefused(): void
    {
        $previousMessage = $GLOBALS['gMessage'] ?? null;
        // The page exits the script, so the test double hands its content over instead
        $GLOBALS['gMessage'] = new class extends Message {
            public function show(string $content, string $headline = ''): void
            {
                throw new RuntimeException($content);
            }
        };

        try {
            $this->logout(array(
                'client_id' => $this->clientWithPostLogoutUri(),
                'post_logout_redirect_uri' => 'https://evil.example/phish'
            ));
            $this->fail('The unregistered address must end in an error page.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Unregistered post_logout_redirect_uri', $exception->getMessage());
            $this->assertStringContainsString('evil.example', $exception->getMessage());
        } finally {
            $GLOBALS['gMessage'] = $previousMessage;
        }
    }
}
