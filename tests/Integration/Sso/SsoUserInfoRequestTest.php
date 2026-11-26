<?php
/**
 * Regression coverage for the ways a client may present its access token at the userinfo endpoint.
 *
 * The resource server only reads the Authorization header, so a token in a form-encoded POST
 * body, which RFC 6750, section 2.2, allows, was rejected (oidcc-userinfo-post-body).
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Service\OIDCService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;

class SsoUserInfoRequestTest extends AdministratorTestCase
{
    private array $previousServer = array();
    private array $previousPost = array();

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousServer = $_SERVER;
        $this->previousPost = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->previousServer;
        $_POST = $this->previousPost;
        parent::tearDown();
    }

    /**
     * @testdox An access token in the POST body is passed on as bearer token
     */
    public function testAnAccessTokenInThePostBodyIsUsed(): void
    {
        $request = $this->userInfoRequest('POST', array('access_token' => 'token-from-body'));

        $this->assertSame('Bearer token-from-body', $request->getHeaderLine('authorization'));
    }

    /**
     * @testdox An Authorization header is not replaced by an access token in the POST body
     */
    public function testTheAuthorizationHeaderWins(): void
    {
        $request = $this->userInfoRequest('POST', array('access_token' => 'token-from-body'), 'Bearer token-from-header');

        $this->assertSame('Bearer token-from-header', $request->getHeaderLine('authorization'));
    }

    /**
     * @testdox An access_token parameter of a GET request is not used
     */
    public function testAGetParameterIsNotUsed(): void
    {
        $request = $this->userInfoRequest('GET', array('access_token' => 'token-from-body'));

        $this->assertFalse($request->hasHeader('authorization'));
    }

    /**
     * Build the userinfo request the service sees for the given request globals.
     *
     * @param array<string,string> $post Form-encoded body of the request.
     */
    private function userInfoRequest(string $method, array $post, string $authorization = ''): ServerRequestInterface
    {
        global $gCurrentUser;

        $_SERVER['REQUEST_METHOD'] = $method;
        unset($_SERVER['HTTP_AUTHORIZATION']);
        if ($authorization !== '') {
            $_SERVER['HTTP_AUTHORIZATION'] = $authorization;
        }
        $_POST = $method === 'POST' ? $post : array();

        $service = new OIDCService($this->getDatabase(), $gCurrentUser);
        $getUserInfoRequest = new ReflectionMethod($service, 'getUserInfoRequest');

        return $getUserInfoRequest->invoke($service);
    }
}
