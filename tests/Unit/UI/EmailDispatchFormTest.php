<?php
/**
 * The form of the email dispatch preferences.
 *
 * The presenter is created without its constructor, because the form reads nothing but the
 * preferences, the session and the theme - building the page itself would need a database. The real
 * form presenter and the real templates of the theme render the form.
 */

namespace Admidio\Tests\Unit\UI;

use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthLanguageDouble;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthSettingsDouble;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\UI\Presenter\PreferencesPresenter;
use ReflectionClass;
use ReflectionProperty;

class EmailDispatchFormTest extends AdmidioTestCase
{
    private const GLOBALS = array('gSettingsManager', 'gL10n', 'gCurrentOrganization', 'gCurrentSession', 'gDebug', 'gValidLogin');

    private array $savedGlobals = array();

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/constants.php';

        foreach (self::GLOBALS as $name) {
            $this->savedGlobals[$name] = $GLOBALS[$name] ?? null;
        }

        $GLOBALS['gL10n'] = new SmtpOAuthLanguageDouble();
        $GLOBALS['gCurrentOrganization'] = new class {
            public function getValue(string $column, string $format = ''): string
            {
                return 'Example Club';
            }
        };
        $GLOBALS['gCurrentSession'] = new class {
            public function getCsrfToken(): string
            {
                return 'csrf-token';
            }

            public function addFormObject(object $form): void
            {
            }
        };
        $GLOBALS['gDebug'] = false;
        $GLOBALS['gValidLogin'] = true;
    }

    protected function tearDown(): void
    {
        foreach ($this->savedGlobals as $name => $value) {
            if ($value === null) {
                unset($GLOBALS[$name]);
            } else {
                $GLOBALS[$name] = $value;
            }
        }

        parent::tearDown();
    }

    /**
     * @param array<string,string> $values Preferences that differ from an organization that sends
     *                                     over SMTP OAuth 2.0 and has nothing stored yet.
     */
    private function render(array $values = array()): string
    {
        $GLOBALS['gSettingsManager'] = new SmtpOAuthSettingsDouble($values + array(
            'mail_send_method' => 'SMTP_OAUTH',
            'mail_sender_mode' => '1',
            'mail_sender_email' => 'noreply@example.org',
            'mail_sender_name' => 'Example Club',
            'mail_send_to_all_addresses' => '0',
            'mail_sending_mode' => '0',
            'mail_recipients_with_roles' => '1',
            'mail_into_to' => '0',
            'mail_number_recipients' => '50',
            'mail_smtp_host' => 'smtp.office365.com',
            'mail_smtp_port' => '587',
            'mail_smtp_secure' => 'tls',
            'mail_smtp_auth' => '1',
            'mail_smtp_authentication_type' => '',
            'mail_smtp_user' => 'info@example.org',
            'mail_smtp_password' => '',
            'mail_smtp_oauth_provider' => 'microsoft',
            'mail_smtp_oauth_grant_type' => 'authorization_code',
            'mail_smtp_oauth_tenant_id' => 'contoso.onmicrosoft.com',
            'mail_smtp_oauth_client_id' => 'client-id',
            'mail_smtp_oauth_client_secret' => '',
            'mail_smtp_oauth_refresh_token' => ''
        ));

        $presenter = (new ReflectionClass(PreferencesPresenter::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(PagePresenter::class, 'smarty'))->setValue($presenter, PagePresenter::createSmartyObject());

        return $presenter->createEmailDispatchForm();
    }

    public function testTheStoredClientSecretAndRefreshTokenNeverReachThePage(): void
    {
        $html = $this->render(array('mail_smtp_oauth_client_secret' => 'stored-client-secret', 'mail_smtp_oauth_refresh_token' => 'stored-refresh-token'));

        $this->assertStringNotContainsString('stored-client-secret', $html);
        $this->assertStringNotContainsString('stored-refresh-token', $html);
        $this->assertStringContainsString('SYS_SMTP_OAUTH_CLIENT_SECRET_STORED', $html, 'the placeholder tells that a secret is stored');
        $this->assertStringContainsString('autocomplete="new-password"', $html, 'the browser must not fill in the password of the login');
        $this->assertStringContainsString('SYS_SMTP_OAUTH_CONNECTED', $html);
    }

    /**
     * Removing the secret and disconnecting act at once like connecting, instead of being checkboxes
     * that only act when the form is saved. None of them is a field of the form.
     */
    public function testTheAccountActionsAreButtonsOutsideTheForm(): void
    {
        $html = $this->render(array('mail_smtp_oauth_client_secret' => 'stored-client-secret', 'mail_smtp_oauth_refresh_token' => 'stored-refresh-token'));

        foreach (array('smtp_oauth_secret_remove', 'smtp_oauth_disconnect', 'smtp_oauth_authorize') as $mode) {
            $this->assertSame(1, preg_match('/<button[^>]*id="adm_' . $mode . '"[^>]*>/', $html, $button), $mode);
            $this->assertStringContainsString('mode=' . $mode, $button[0]);
            $this->assertStringNotContainsString('name=', $button[0]);
        }
        $this->assertStringContainsString('data-needs-saved-settings', $this->buttonOf($html, 'smtp_oauth_authorize'), 'connecting reads the saved settings');
        $this->assertStringNotContainsString('data-needs-saved-settings', $this->buttonOf($html, 'smtp_oauth_disconnect'));
        $this->assertStringNotContainsString('id="mail_smtp_oauth_client_secret_remove"', $html, 'no checkbox is left');
        $this->assertStringNotContainsString('id="mail_smtp_oauth_refresh_token_remove"', $html, 'no checkbox is left');
    }

    public function testWithNothingStoredOnlyConnectingIsOffered(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('id="adm_smtp_oauth_secret_remove"', $html);
        $this->assertStringNotContainsString('id="adm_smtp_oauth_disconnect"', $html);
        $this->assertStringContainsString('id="adm_smtp_oauth_authorize"', $html);
        $this->assertStringContainsString('SYS_SMTP_OAUTH_NOT_CONNECTED_STATUS', $html);
    }

    public function testTheRedirectUriIsTextToCopyAndNeverPosted(): void
    {
        $html = $this->render();

        $this->assertSame(1, preg_match('/<div class="copy-container[^"]*"[^>]*>https:\/\/admidio\.test\/modules\/preferences\/smtp_oauth_callback\.php<\/div>/', $html));
        $this->assertStringNotContainsString('value="https://admidio.test/modules/preferences/smtp_oauth_callback.php"', $html, 'every posted field must be a form element');
    }

    /** The form does not borrow texts of the SSO module, a rewording there must not change this page. */
    public function testTheFormUsesTextsOfItsOwn(): void
    {
        $this->assertStringNotContainsString('SYS_SSO_', $this->render());
    }

    public function testTheProviderComesBeforeTheServerItFillsIn(): void
    {
        $html = $this->render();

        $this->assertLessThan(strpos($html, 'id="mail_smtp_host"'), strpos($html, 'id="mail_smtp_oauth_provider"'));
        $this->assertLessThan(strpos($html, 'id="mail_smtp_host"'), strpos($html, 'name="mail_send_method"'), 'the send method is part of the connection');
        $this->assertStringContainsString('"host":"smtp.office365.com"', $html, 'the script knows the server of each provider');
    }

    public function testTheTenantIsDeclaredByTheProviderAndRenderedLikeAPluginSetting(): void
    {
        $html = $this->render();

        $this->assertSame(1, preg_match('/<input[^>]*id="mail_smtp_oauth_tenant_id"[^>]*value="contoso\.onmicrosoft\.com"/', $html));
        $this->assertStringContainsString('"settings":["mail_smtp_oauth_tenant_id"]', $html);
    }

    private function buttonOf(string $html, string $mode): string
    {
        preg_match('/<button[^>]*id="adm_' . $mode . '"[^>]*>/', $html, $button);

        return $button[0] ?? '';
    }
}
