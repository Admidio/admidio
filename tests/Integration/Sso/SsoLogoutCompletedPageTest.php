<?php
/**
 * Regression coverage for a single sign-on logout that has nowhere to return to.
 *
 * Without a post_logout_redirect_uri the logout ended on an empty page with only the title
 * "Logout", so the user could not tell whether the logout had worked
 * (oidcc-rp-initiated-logout-no-post-logout-redirect-uri and similar tests ask for a screenshot).
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\Application\Navigation;
use Admidio\Menu\ValueObject\Menu;
use Admidio\Session\Entity\Session;
use Admidio\SSO\Service\SAMLService;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;

class SsoLogoutCompletedPageTest extends AdministratorTestCase
{
    /** @var array<string,mixed> */
    private array $previousGlobals = array();

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    protected function setUp(): void
    {
        parent::setUp();
        // The page is rendered with the theme and the session, like a real Admidio page
        if (!defined('THEME_PATH')) {
            define('THEME_PATH', ADMIDIO_PATH . '/themes/simple');
        }
        if (!defined('THEME_URL')) {
            define('THEME_URL', ADMIDIO_URL . '/themes/simple');
        }
        foreach (array('gCurrentSession', 'gNavigation', 'gMenu', 'gLayoutReduced') as $name) {
            $this->previousGlobals[$name] = $GLOBALS[$name] ?? null;
        }
        $GLOBALS['gCurrentSession'] = new Session($this->getDatabase(), COOKIE_PREFIX);
        $GLOBALS['gNavigation'] = new Navigation();
        $GLOBALS['gMenu'] = new Menu();
        $GLOBALS['gLayoutReduced'] = false;
    }

    protected function tearDown(): void
    {
        foreach ($this->previousGlobals as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        parent::tearDown();
    }

    /**
     * @testdox The logout without a return address ends on a page that confirms the logout
     */
    public function testThePageConfirmsTheLogout(): void
    {
        global $gL10n;

        $body = (string) $this->completedResponse('')->getBody();

        $this->assertStringContainsString($gL10n->get('SYS_LOGOUT_SUCCESSFUL'), $body);
        $this->assertStringNotContainsString('<iframe hidden', $body);
    }

    /**
     * @testdox The page loads the front-channel logout URIs that are still due
     */
    public function testThePageLoadsTheFrontChannelLogouts(): void
    {
        global $gL10n;

        $frames = '<iframe hidden src="https://client.example/frontchannel_logout?iss=x&amp;sid=y"></iframe>';
        $response = $this->completedResponse($frames);
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($gL10n->get('SYS_LOGOUT_SUCCESSFUL'), $body);
        $this->assertStringContainsString($frames, $body);
    }

    private function completedResponse(string $frames): ResponseInterface
    {
        global $gCurrentUser;

        $service = new SAMLService($this->getDatabase(), $gCurrentUser);

        // The page sets an HTTP header, which PHPUnit's own output has made impossible
        set_error_handler(static function (int $errorNumber, string $message): bool {
            return str_starts_with($message, 'Cannot modify header information');
        }, E_WARNING);
        try {
            return (new ReflectionMethod($service, 'createLogoutCompletedResponse'))->invoke($service, $frames);
        } finally {
            restore_error_handler();
        }
    }
}
