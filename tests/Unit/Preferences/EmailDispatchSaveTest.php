<?php
/**
 * Saving the panel of the email dispatch.
 *
 * The form object of the panel, the session and the database are replaced by stand-ins; what is
 * checked is what the panel stores in the preferences.
 */

namespace Admidio\Tests\Unit\Preferences;

use Admidio\Infrastructure\Exception;
use Admidio\Preferences\Service\PreferencesService;
use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthLanguageDouble;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthLoggerDouble;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthSettingsDouble;
use InvalidArgumentException;

class EmailDispatchSaveTest extends AdmidioTestCase
{
    private const GLOBALS = array('gSettingsManager', 'gCurrentSession', 'gDb', 'gL10n', 'gLogger');

    private array $savedGlobals = array();

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::GLOBALS as $name) {
            $this->savedGlobals[$name] = $GLOBALS[$name] ?? null;
        }
        $GLOBALS['gL10n'] = new SmtpOAuthLanguageDouble();
        $GLOBALS['gLogger'] = new SmtpOAuthLoggerDouble();

        // the form object returns what was posted, as validate() does for valid input
        $GLOBALS['gCurrentSession'] = new class {
            public function getFormObject(string $csrfToken): object
            {
                return new class {
                    public function validate(array $formData): array
                    {
                        return $formData;
                    }
                };
            }

            public function reloadAllSessions(): void
            {
            }
        };
        $GLOBALS['gDb'] = new class {
            public function startTransaction(): void
            {
            }

            public function endTransaction(): void
            {
            }

            public function rollback(): void
            {
            }
        };
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

    /** The preferences of an organization whose Microsoft 365 account is connected. */
    private function connectedAccount(): SmtpOAuthSettingsDouble
    {
        return $GLOBALS['gSettingsManager'] = new SmtpOAuthSettingsDouble(array(
            'mail_send_method' => 'SMTP_OAUTH',
            'mail_number_recipients' => '50',
            'mail_smtp_host' => 'smtp.office365.com',
            'mail_smtp_port' => '587',
            'mail_smtp_user' => 'info@example.org',
            'mail_smtp_oauth_provider' => 'microsoft',
            'mail_smtp_oauth_grant_type' => 'authorization_code',
            'mail_smtp_oauth_tenant_id' => 'contoso.onmicrosoft.com',
            'mail_smtp_oauth_client_id' => 'client-id',
            'mail_smtp_oauth_client_secret' => 'stored-secret',
            'mail_smtp_oauth_refresh_token' => 'refresh-token-1'
        ));
    }

    /**
     * @param array<string,string> $posted Fields that differ from saving the stored app again with an empty secret field.
     */
    private function save(array $posted): SmtpOAuthSettingsDouble
    {
        $settings = $this->connectedAccount();
        $this->post($posted);

        return $settings;
    }

    /**
     * @param array<string,string> $posted Fields that differ from saving the stored app again with an empty secret field.
     */
    private function post(array $posted): void
    {
        (new PreferencesService())->save('email_dispatch', $posted + array(
            'adm_csrf_token' => 'csrf-token',
            'mail_send_method' => 'SMTP_OAUTH',
            'mail_number_recipients' => '50',
            'mail_smtp_host' => 'smtp.office365.com',
            'mail_smtp_port' => '587',
            'mail_smtp_user' => 'info@example.org',
            'mail_smtp_oauth_provider' => 'microsoft',
            'mail_smtp_oauth_grant_type' => 'authorization_code',
            'mail_smtp_oauth_tenant_id' => 'contoso.onmicrosoft.com',
            'mail_smtp_oauth_client_id' => 'client-id',
            'mail_smtp_oauth_client_secret' => ''
        ));
    }

    public function testAnEmptySecretFieldKeepsTheStoredSecret(): void
    {
        $settings = $this->save(array());

        $this->assertSame('stored-secret', $settings->values['mail_smtp_oauth_client_secret'], 'the stored secret is never shown, so the field is always empty');
    }

    public function testANewSecretReplacesTheStoredOne(): void
    {
        $settings = $this->save(array('mail_smtp_oauth_client_secret' => 'new-secret'));

        $this->assertSame('new-secret', $settings->values['mail_smtp_oauth_client_secret']);
    }

    /** The button next to the field removes the secret at once, as preferences.php does it. */
    public function testTheButtonRemovesTheStoredSecret(): void
    {
        $settings = $this->connectedAccount();

        (new PreferencesService())->setEditablePreference('mail_smtp_oauth_client_secret', '');

        $this->assertSame('', $settings->values['mail_smtp_oauth_client_secret']);
        $this->assertSame('refresh-token-1', $settings->values['mail_smtp_oauth_refresh_token'], 'a public client stays connected');
    }

    /**
     * @dataProvider anotherApp
     */
    public function testTheRefreshTokenOfAnotherAppIsRemoved(string $name, string $value): void
    {
        $settings = $this->save(array($name => $value));

        $this->assertSame('', $settings->values['mail_smtp_oauth_refresh_token']);
    }

    public static function anotherApp(): array
    {
        return array(
            'another provider' => array('mail_smtp_oauth_provider', 'google'),
            'another grant type' => array('mail_smtp_oauth_grant_type', 'client_credentials'),
            'another tenant' => array('mail_smtp_oauth_tenant_id', 'fabrikam.onmicrosoft.com'),
            'another client' => array('mail_smtp_oauth_client_id', 'other-client-id')
        );
    }

    public function testSavingTheSameAppKeepsTheRefreshToken(): void
    {
        $settings = $this->save(array('mail_smtp_oauth_client_secret' => 'new-secret'));

        $this->assertSame('refresh-token-1', $settings->values['mail_smtp_oauth_refresh_token'], 'a new secret of the same app keeps the account connected');
    }

    public function testARejectedSaveKeepsTheAccountConnected(): void
    {
        $settings = $this->connectedAccount();

        try {
            $this->post(array('mail_smtp_oauth_client_id' => 'other-client-id', 'mail_number_recipients' => '1e3'));
            $this->fail('The number of recipients was expected to be rejected.');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame('client-id', $settings->values['mail_smtp_oauth_client_id']);
        $this->assertSame('refresh-token-1', $settings->values['mail_smtp_oauth_refresh_token'], 'nothing of the panel was saved, so the account still belongs to it');
    }

    public function testClientCredentialsNeedTheTenant(): void
    {
        $settings = $this->connectedAccount();

        try {
            $this->post(array('mail_smtp_oauth_grant_type' => 'client_credentials', 'mail_smtp_oauth_tenant_id' => ''));
            $this->fail('The empty tenant was expected to be rejected.');
        } catch (Exception $exception) {
            $this->assertSame('SYS_FIELD_EMPTY(SYS_SMTP_OAUTH_TENANT_ID)', $exception->getMessage(), 'Microsoft issues app tokens only within the tenant');
        }

        $this->assertSame('authorization_code', $settings->values['mail_smtp_oauth_grant_type'], 'nothing of the panel was saved');
    }

    /**
     * @dataProvider withoutTenant
     */
    public function testOtherwiseTheTenantMayBeEmpty(array $posted): void
    {
        $settings = $this->save($posted + array('mail_smtp_oauth_tenant_id' => ''));

        $this->assertSame('', $settings->values['mail_smtp_oauth_tenant_id']);
    }

    public static function withoutTenant(): array
    {
        return array(
            'authorization code for every account' => array(array()),
            'Google with the hidden grant type of Microsoft' => array(array('mail_smtp_oauth_provider' => 'google', 'mail_smtp_oauth_grant_type' => 'client_credentials')),
            'SMTP with password and the hidden grant type' => array(array('mail_send_method' => 'SMTP', 'mail_smtp_oauth_grant_type' => 'client_credentials'))
        );
    }

    public function testTheCommandLineRemovesTheRefreshTokenOfAnotherAppToo(): void
    {
        $settings = $this->connectedAccount();

        (new PreferencesService())->setEditablePreference('mail_smtp_oauth_provider', 'google');

        $this->assertSame('', $settings->values['mail_smtp_oauth_refresh_token'], 'config:set must not send the Microsoft token to Google');
    }

    /**
     * @dataProvider requiredForOAuth
     */
    public function testSendingWithOAuthNeedsServerMailboxAndApp(string $name, string $label): void
    {
        $settings = $this->connectedAccount();

        try {
            $this->post(array($name => ''));
            $this->fail('The empty field was expected to be rejected.');
        } catch (Exception $exception) {
            $this->assertSame('SYS_FIELD_EMPTY(' . $label . ')', $exception->getMessage());
        }

        $this->assertSame('refresh-token-1', $settings->values['mail_smtp_oauth_refresh_token'], 'nothing of the panel was saved');
    }

    public static function requiredForOAuth(): array
    {
        return array(
            'server' => array('mail_smtp_host', 'SYS_SMTP_HOST'),
            'mailbox' => array('mail_smtp_user', 'SYS_SMTP_USER'),
            'app' => array('mail_smtp_oauth_client_id', 'SYS_SMTP_OAUTH_CLIENT_ID')
        );
    }

    public function testGoogleIsSavedWithTheSignInEvenIfApplicationAccessWasChosenBefore(): void
    {
        $settings = $this->save(array('mail_smtp_oauth_provider' => 'google', 'mail_smtp_oauth_grant_type' => 'client_credentials'));

        $this->assertSame('authorization_code', $settings->values['mail_smtp_oauth_grant_type'], 'the form hides the access for Google');
    }

    public function testAClientSecretWithSpecialCharactersIsSavedAsEntered(): void
    {
        $settings = $this->save(array('mail_smtp_oauth_client_secret' => 'a<b>c&amp;d"e'));

        $this->assertSame('a<b>c&amp;d"e', $settings->values['mail_smtp_oauth_client_secret']);
    }

    /**
     * config:set and config:import go through the same validation, so switching to OAuth 2.0 on the
     * command line cannot leave the mails without what they need.
     */
    public function testTheCommandLineCannotSwitchToOAuthWithoutItsSettings(): void
    {
        $settings = $this->connectedAccount();
        $settings->values['mail_send_method'] = 'SMTP';
        $settings->values['mail_smtp_host'] = '';

        try {
            (new PreferencesService())->setEditablePreference('mail_send_method', 'SMTP_OAUTH');
            $this->fail('The missing server was expected to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('mail_smtp_host', $exception->getMessage());
        }

        $this->assertSame('SMTP', $settings->values['mail_send_method']);
    }

    public function testTheCommandLineSwitchesToOAuthOnceEverythingIsSet(): void
    {
        $settings = $this->connectedAccount();
        $settings->values['mail_send_method'] = 'SMTP';
        $service = new PreferencesService();

        $service->setEditablePreference('mail_send_method', 'SMTP_OAUTH');

        $this->assertSame('SMTP_OAUTH', $settings->values['mail_send_method']);
        $this->assertFalse($service->smtpOAuthConnectionWasReset());
    }

    public function testTheCommandLineCannotEmptyASettingThatOAuthNeeds(): void
    {
        $this->connectedAccount();

        $this->expectException(InvalidArgumentException::class);
        (new PreferencesService())->setEditablePreference('mail_smtp_oauth_client_id', '');
    }

    public function testTheCommandLineIsToldThatTheConnectionWasReset(): void
    {
        $this->connectedAccount();
        $service = new PreferencesService();

        $service->setEditablePreference('mail_smtp_oauth_client_id', 'other-client-id');

        $this->assertTrue($service->smtpOAuthConnectionWasReset());
    }

    public function testAnImportOfAnotherTenantResetsTheConnection(): void
    {
        $settings = $this->connectedAccount();
        $service = new PreferencesService();

        $service->importConfiguration(array(
            'schema' => 'admidio-preferences',
            'version' => 1,
            'preferences' => array('mail_smtp_oauth_tenant_id' => 'fabrikam.onmicrosoft.com')
        ));

        $this->assertSame('', $settings->values['mail_smtp_oauth_refresh_token']);
        $this->assertTrue($service->smtpOAuthConnectionWasReset());
    }
}
