<?php
/**
 * Saving the panel of the email dispatch.
 *
 * The form object of the panel, the session and the database are replaced by stand-ins; what is
 * checked is what the panel stores in the preferences.
 */

namespace Admidio\Tests\Unit\Preferences;

use Admidio\Preferences\Service\PreferencesService;
use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthSettingsDouble;
use InvalidArgumentException;

class EmailDispatchSaveTest extends AdmidioTestCase
{
    private const GLOBALS = array('gSettingsManager', 'gCurrentSession', 'gDb');

    private array $savedGlobals = array();

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::GLOBALS as $name) {
            $this->savedGlobals[$name] = $GLOBALS[$name] ?? null;
        }

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
        // FormPresenter::validate() posts '0' for the checkboxes, which the panel shows with a stored secret and token
        (new PreferencesService())->save('email_dispatch', $posted + array(
            'adm_csrf_token' => 'csrf-token',
            'mail_send_method' => 'SMTP_OAUTH',
            'mail_number_recipients' => '50',
            'mail_smtp_oauth_provider' => 'microsoft',
            'mail_smtp_oauth_grant_type' => 'authorization_code',
            'mail_smtp_oauth_tenant_id' => 'contoso.onmicrosoft.com',
            'mail_smtp_oauth_client_id' => 'client-id',
            'mail_smtp_oauth_client_secret' => '',
            'mail_smtp_oauth_client_secret_remove' => '0',
            'mail_smtp_oauth_refresh_token_remove' => '0'
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

    public function testTheCheckboxRemovesTheStoredSecret(): void
    {
        $settings = $this->save(array('mail_smtp_oauth_client_secret_remove' => '1'));

        $this->assertSame('', $settings->values['mail_smtp_oauth_client_secret']);
        $this->assertArrayNotHasKey('mail_smtp_oauth_client_secret_remove', $settings->values, 'the checkbox is not a preference');
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

    public function testTheCheckboxDisconnectsTheAccount(): void
    {
        $settings = $this->save(array('mail_smtp_oauth_refresh_token_remove' => '1'));

        $this->assertSame('', $settings->values['mail_smtp_oauth_refresh_token']);
        $this->assertArrayNotHasKey('mail_smtp_oauth_refresh_token_remove', $settings->values, 'the checkbox is not a preference');
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

    public function testTheCommandLineRemovesTheRefreshTokenOfAnotherAppToo(): void
    {
        $settings = $this->connectedAccount();

        (new PreferencesService())->setEditablePreference('mail_smtp_oauth_provider', 'google');

        $this->assertSame('', $settings->values['mail_smtp_oauth_refresh_token'], 'config:set must not send the Microsoft token to Google');
    }
}
