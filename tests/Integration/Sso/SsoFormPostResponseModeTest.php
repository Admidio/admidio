<?php
/**
 * Regression coverage for response_mode=form_post at the authorization endpoint.
 *
 * With form_post (OAuth 2.0 Form Post Response Mode) the authorization response goes to the client
 * as a form that the browser posts to the redirect URI, instead of as a redirect with the code in
 * the URL. Errors are sent the same way. Other response modes are refused. See OIDC-18.
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Repository\ClientRepository;
use Admidio\SSO\Service\OIDCService;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use League\OAuth2\Server\Exception\OAuthServerException;
use ReflectionMethod;
use ReflectionProperty;

class SsoFormPostResponseModeTest extends SsoTokenGrantTestCase
{
    private function service(): OIDCService
    {
        global $gCurrentUser;

        return new OIDCService($this->getDatabase(), $gCurrentUser);
    }

    private function authorizationRequest(array $query): ServerRequest
    {
        return (new ServerRequest(array(), array(), 'https://op.example/authorize', 'GET'))->withQueryParams($query);
    }

    private function redirect(string $location): Response
    {
        return (new Response())->withStatus(302)->withHeader('Location', $location);
    }

    private function applyResponseMode(OIDCService $service, array $query, Response $response): \Psr\Http\Message\ResponseInterface
    {
        return (new ReflectionMethod($service, 'applyResponseMode'))->invoke($service, $this->authorizationRequest($query), $response);
    }

    /**
     * @return array<string,string> The hidden fields of the page
     */
    private function fieldsOf(string $html): array
    {
        preg_match_all('/<input type="hidden" name="([^"]*)" value="([^"]*)"/', $html, $matches, PREG_SET_ORDER);
        $fields = array();
        foreach ($matches as $match) {
            $fields[html_entity_decode($match[1])] = html_entity_decode($match[2]);
        }

        return $fields;
    }

    /**
     * @testdox form_post answers the authorization code with a form to the redirect URI
     */
    public function testTheCodeIsPostedToTheRedirectUri(): void
    {
        $response = $this->applyResponseMode($this->service(),
            array('response_mode' => 'form_post', 'redirect_uri' => 'https://client.example/cb'),
            $this->redirect('https://client.example/cb?code=the-code&state=the-state'));

        $html = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Location'));
        $this->assertStringContainsString('<form method="POST" action="https://client.example/cb">', $html);
        $this->assertSame(array('code' => 'the-code', 'state' => 'the-state'), $this->fieldsOf($html));
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('document.forms[0].submit()', $html);
    }

    /**
     * @testdox A query in the registered redirect URI stays in the address and is not posted
     */
    public function testTheQueryOfTheRedirectUriStaysInTheAddress(): void
    {
        $response = $this->applyResponseMode($this->service(),
            array('response_mode' => 'form_post', 'redirect_uri' => 'https://client.example/cb?dummy1=lorem&dummy2=ipsum'),
            $this->redirect('https://client.example/cb?dummy1=lorem&dummy2=ipsum&code=the-code&state=s'));

        $html = (string) $response->getBody();
        $this->assertStringContainsString('action="https://client.example/cb?dummy1=lorem&amp;dummy2=ipsum"', $html);
        $this->assertSame(array('code' => 'the-code', 'state' => 's'), $this->fieldsOf($html));
    }

    /**
     * @testdox form_post sends an error response the same way
     */
    public function testAnErrorIsPostedToo(): void
    {
        $response = $this->applyResponseMode($this->service(),
            array('response_mode' => 'form_post', 'redirect_uri' => 'https://client.example/cb'),
            $this->redirect('https://client.example/cb?error=access_denied&error_description=No+way&state=s'));

        $this->assertSame(
            array('error' => 'access_denied', 'error_description' => 'No way', 'state' => 's'),
            $this->fieldsOf((string) $response->getBody())
        );
    }

    /**
     * The state is chosen by the client and arrives in the page, so it must not break out of it.
     *
     * @testdox Values in the form are escaped
     */
    public function testValuesAreEscaped(): void
    {
        $state = '"><script>alert(1)</script>';
        $response = $this->applyResponseMode($this->service(),
            array('response_mode' => 'form_post', 'redirect_uri' => 'https://client.example/cb'),
            $this->redirect('https://client.example/cb?code=c&state=' . urlencode($state)));

        $html = (string) $response->getBody();
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertSame($state, $this->fieldsOf($html)['state']);
    }

    /**
     * @testdox Without a redirect_uri parameter the only registered redirect URI is the address
     */
    public function testTheRegisteredRedirectUriIsUsedWhenNoneIsNamed(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        $service = $this->service();
        (new ReflectionProperty($service, 'clientRepository'))->setValue($service, new ClientRepository($this->getDatabase()));
        $redirectUri = (string) $client->getRedirectUri();

        $response = $this->applyResponseMode($service,
            array('response_mode' => 'form_post', 'client_id' => $client->getIdentifier()),
            $this->redirect($redirectUri . '?code=the-code'));

        $this->assertStringContainsString('action="' . $redirectUri . '"', (string) $response->getBody());
    }

    /**
     * @dataProvider otherModesProvider
     * @testdox A request with ${mode} is answered with a redirect
     */
    public function testOtherModesStayRedirects(?string $mode): void
    {
        $query = array('redirect_uri' => 'https://client.example/cb');
        if ($mode !== null) {
            $query['response_mode'] = $mode;
        }
        $redirect = $this->redirect('https://client.example/cb?code=the-code');

        $this->assertSame($redirect, $this->applyResponseMode($this->service(), $query, $redirect));
    }

    /**
     * @return array<string,array{0:string|null}>
     */
    public static function otherModesProvider(): array
    {
        return array('no response_mode' => array(null), 'response_mode=query' => array('query'));
    }

    /**
     * @testdox The discovery document announces form_post
     */
    public function testDiscoveryAnnouncesFormPost(): void
    {
        $modes = $this->service()->getDiscoveryConfiguration()['response_modes_supported'];

        $this->assertSame(array('query', 'form_post'), $modes);
    }

    /**
     * @dataProvider supportedModesProvider
     * @testdox The authorization endpoint accepts ${mode}
     */
    public function testSupportedModesAreAccepted(?string $mode): void
    {
        $this->validate($mode);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string,array{0:string|null}>
     */
    public static function supportedModesProvider(): array
    {
        return array('no response_mode' => array(null), 'query' => array('query'), 'form_post' => array('form_post'));
    }

    /**
     * Another mode would put the code where the client does not expect it, so the request is refused
     * with an error that goes to the verified redirect URI of the client.
     *
     * @testdox The authorization endpoint refuses response_mode=fragment
     */
    public function testAnUnsupportedModeIsRefused(): void
    {
        try {
            $this->validate('fragment');
            $this->fail('response_mode=fragment should have been refused.');
        } catch (OAuthServerException $exception) {
            $this->assertSame('invalid_request', $exception->getErrorType());
            $this->assertTrue($exception->hasRedirect(), 'The error goes to the redirect URI of the client.');
        }
    }

    private function validate(?string $mode): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        $query = array(
            'response_type' => 'code',
            'client_id' => $client->getIdentifier(),
            'redirect_uri' => (string) $client->getRedirectUri(),
        );
        if ($mode !== null) {
            $query['response_mode'] = $mode;
        }

        (new ReflectionMethod($this->codeGrant, 'rejectUnsupportedRequest'))
            ->invoke($this->codeGrant, $this->authorizationRequest($query));
    }
}
