<?php
/**
 * The preferences of the email dispatch with OAuth 2.0.
 */

namespace Admidio\Tests\Unit\Preferences;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\SmtpOAuth;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\Tests\Support\AdmidioTestCase;
use InvalidArgumentException;

class MailPreferenceDefinitionsTest extends AdmidioTestCase
{
    public function testSmtpOAuthCanBeChosenAsSendMethodTogetherWithWhatItNeeds(): void
    {
        $normalized = PreferenceDefinitions::normalizeValues(array(
            'mail_send_method' => 'SMTP_OAUTH',
            'mail_smtp_host' => 'smtp.office365.com',
            'mail_smtp_port' => '587',
            'mail_smtp_user' => 'info@example.org',
            'mail_smtp_oauth_provider' => 'microsoft',
            'mail_smtp_oauth_grant_type' => 'authorization_code',
            'mail_smtp_oauth_client_id' => 'client-id'
        ));

        $this->assertSame('SMTP_OAUTH', $normalized['mail_send_method']);
    }

    public function testSmtpOAuthIsNotAcceptedWithoutWhatItNeeds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mail_smtp_host, mail_smtp_user, mail_smtp_oauth_client_id');

        PreferenceDefinitions::normalize('mail_send_method', 'SMTP_OAUTH');
    }

    public function testAProviderOfAPluginCanBeChosen(): void
    {
        Hooks::addFilter(SmtpOAuth::HOOK_PROVIDERS, function (array $providers): array {
            $providers['zoho'] = array('name' => 'Zoho', 'authorizationUrl' => 'https://accounts.zoho.eu/oauth/v2/auth',
                'tokenUrl' => 'https://accounts.zoho.eu/oauth/v2/token', 'scope' => 'ZohoMail.messages.CREATE');
            return $providers;
        });

        try {
            $this->assertSame('zoho', PreferenceDefinitions::normalize('mail_smtp_oauth_provider', 'zoho'));
            $this->assertSame(array('microsoft', 'google', 'zoho'), PreferenceDefinitions::definition('mail_smtp_oauth_provider')['values']);
        } finally {
            Hooks::reset();
        }
    }

    public function testTheRefreshTokenIsKeptAwayFromTheConfigurationCommands(): void
    {
        $this->assertContains('mail_smtp_oauth_refresh_token', PreferenceDefinitions::internalNames(), 'Admidio maintains it, config:* refuses it');
        $this->assertTrue(PreferenceDefinitions::isSensitive('mail_smtp_oauth_refresh_token'), 'the changelog masks it');
    }

    public function testTheClientSecretIsMaskedLikeThePasswordOfBasicAuth(): void
    {
        $this->assertTrue(PreferenceDefinitions::isSensitive('mail_smtp_oauth_client_secret'));
    }

    public function testEveryDefinitionIsWellFormed(): void
    {
        $this->assertSame(array(), PreferenceDefinitions::coverageProblems());
    }
}
