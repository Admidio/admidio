<?php
/**
 * The preferences of the email dispatch with OAuth 2.0.
 */

namespace Admidio\Tests\Unit\Preferences;

use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\Tests\Support\AdmidioTestCase;

class MailPreferenceDefinitionsTest extends AdmidioTestCase
{
    public function testSmtpOAuthCanBeChosenAsSendMethod(): void
    {
        $this->assertSame('SMTP_OAUTH', PreferenceDefinitions::normalize('mail_send_method', 'SMTP_OAUTH'));
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
