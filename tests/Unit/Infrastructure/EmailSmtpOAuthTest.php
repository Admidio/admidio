<?php
/**
 * Sending emails over SMTP with OAuth 2.0.
 *
 * Nothing leaves the test: the SMTP server is replaced by SmtpConnectionDouble, which answers like a
 * server that offers XOAUTH2, and the token endpoint by ScriptedSmtpOAuth.
 */

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Email;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\SmtpOAuth;
use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\Tests\Unit\Infrastructure\Support\ScriptedSmtpOAuth;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpConnectionDouble;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthLanguageDouble;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthLoggerDouble;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthSettingsDouble;
use Throwable;

class EmailSmtpOAuthTest extends AdmidioTestCase
{
    private const GLOBALS = array(
        'gSettingsManager', 'gL10n', 'gLogger', 'gDebug', 'gValidLogin', 'gCurrentUser', 'gDisableEmailSending',
        'phpmailer_output_debug'
    );

    private array $savedGlobals = array();
    private SmtpOAuthLoggerDouble $logger;
    private SmtpConnectionDouble $connection;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/constants.php';

        foreach (self::GLOBALS as $name) {
            $this->savedGlobals[$name] = $GLOBALS[$name] ?? null;
            unset($GLOBALS[$name]);
        }
        Hooks::reset();
        ScriptedSmtpOAuth::forgetAccessTokens();

        $GLOBALS['gL10n'] = new SmtpOAuthLanguageDouble();
        $GLOBALS['gLogger'] = $this->logger = new SmtpOAuthLoggerDouble();
        $GLOBALS['gDebug'] = false;
        $GLOBALS['gValidLogin'] = false;
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
        Hooks::reset();
        ScriptedSmtpOAuth::forgetAccessTokens();

        parent::tearDown();
    }

    /**
     * @param array<string,string> $values Preferences that differ from an installation that sends
     *                                     over SMTP with an app token of Microsoft 365.
     */
    private function configure(string $sendMethod, array $values = array()): void
    {
        $GLOBALS['gSettingsManager'] = new SmtpOAuthSettingsDouble($values + array(
            'mail_send_method' => $sendMethod,
            'mail_smtp_host' => 'smtp.example.org',
            'mail_smtp_port' => '587',
            'mail_smtp_secure' => 'tls',
            'mail_smtp_auth' => '1',
            'mail_smtp_authentication_type' => 'LOGIN',
            'mail_smtp_user' => 'info@example.org',
            'mail_smtp_password' => 'basic-secret',
            'mail_smtp_oauth_provider' => 'microsoft',
            'mail_smtp_oauth_grant_type' => 'client_credentials',
            'mail_smtp_oauth_tenant_id' => 'contoso.onmicrosoft.com',
            'mail_smtp_oauth_client_id' => 'client-id',
            'mail_smtp_oauth_client_secret' => 'client-secret',
            'mail_smtp_oauth_refresh_token' => '',
            'mail_sending_mode' => '0',
            'mail_sender_mode' => '2',
            'mail_sender_name' => 'Example Club',
            'mail_sender_email' => 'noreply@example.org',
            'mail_number_recipients' => '50',
            'mail_into_to' => '0',
            'mail_recipients_with_roles' => '0'
        ));
    }

    /** An email to Ann whose SMTP connection and token endpoint are the doubles. */
    private function emailOverTheDoubles(ScriptedSmtpOAuth $oauth): Email
    {
        $email = new Email();
        $email->setSMTPInstance($this->connection = new SmtpConnectionDouble());
        $email->setOAuth($oauth);
        $email->addRecipient('ann@example.org', 'Ann', 'A');
        $email->setSubject('The summer camp');
        $email->setText('See you there.');

        return $email;
    }

    private function failureOf(callable $call): Throwable
    {
        try {
            $call();
        } catch (Throwable $exception) {
            return $exception;
        }

        $this->fail('An exception was expected.');
    }

    public function testOAuthLogsInWithXOAuth2AndNeverWithThePasswordOfBasicAuth(): void
    {
        $this->configure('SMTP_OAUTH');

        $email = new Email();

        $this->assertSame('smtp', $email->Mailer);
        $this->assertSame('smtp.example.org', $email->Host);
        $this->assertSame(587, $email->Port);
        $this->assertSame('tls', $email->SMTPSecure);
        $this->assertTrue($email->SMTPAuth);
        $this->assertSame('XOAUTH2', $email->AuthType);
        $this->assertSame('info@example.org', $email->Username);
        $this->assertSame('', $email->Password, 'PHPMailer falls back to LOGIN or PLAIN if a server offers no XOAUTH2');
        $this->assertInstanceOf(SmtpOAuth::class, $email->getOAuth());
    }

    public function testBasicAuthStillLogsInWithUsernameAndPassword(): void
    {
        $this->configure('SMTP');

        $email = new Email();

        $this->assertSame('smtp', $email->Mailer);
        $this->assertTrue($email->SMTPAuth);
        $this->assertSame('LOGIN', $email->AuthType);
        $this->assertSame('info@example.org', $email->Username);
        $this->assertSame('basic-secret', $email->Password);
        $this->assertNull($email->getOAuth());
    }

    /**
     * @dataProvider xoauth2DebugLines
     */
    public function testTheDebugOutputOfATestMailHidesTheXOAuth2Credentials(string $line, string $credentials): void
    {
        $this->configure('SMTP_OAUTH');
        $email = new Email();
        $email->setDebugMode(true);

        ($email->Debugoutput)($line, 1);
        ($email->Debugoutput)("SERVER -> CLIENT: 235 2.7.0 Authentication successful\r\n", 1);

        $output = $GLOBALS['phpmailer_output_debug'];
        $this->assertStringContainsString('[credentials hidden]', $output);
        $this->assertStringNotContainsString($credentials, $output);
        $this->assertStringContainsString('235 2.7.0 Authentication successful', $output, 'the rest of the conversation stays readable');
    }

    public static function xoauth2DebugLines(): array
    {
        $lines = array();
        $users = array('letter' => 'info@example.org', 'capital' => 'Info@example.org', 'digit' => '1info@example.org', 'umlaut' => 'änne@example.org');

        foreach ($users as $start => $user) {
            $credentials = base64_encode('user=' . $user . "\x01auth=Bearer access-token-1\x01\x01");
            $lines['address with a ' . $start . ', after AUTH XOAUTH2'] = array('CLIENT -> SERVER: AUTH XOAUTH2 ' . $credentials . "\r\n", $credentials);
            $lines['address with a ' . $start . ', on its own line'] = array('CLIENT -> SERVER: ' . $credentials . "\r\n", $credentials);
        }

        return $lines;
    }

    public function testTheLogOfAnOAuthLoginShowsNoCredentials(): void
    {
        $this->configure('SMTP_OAUTH');
        $accessToken = str_repeat('a', 600);
        $email = $this->emailOverTheDoubles((new ScriptedSmtpOAuth())->answerWith(array('access_token' => $accessToken, 'expires_in' => 3599)));
        $email->setDebugMode();

        $this->assertTrue($email->sendEmail());

        $credentials = base64_encode("user=info@example.org\x01auth=Bearer " . $accessToken . "\x01\x01");
        $this->assertContains($credentials . "\r\n", $this->connection->sent, 'a token of the size Microsoft issues is sent on a line of its own');
        $this->assertStringContainsString('[credentials hidden]', $this->logger->messages());
        $this->assertStringNotContainsString($credentials, $this->logger->messages());
    }

    public function testARefusedTokenStopsTheSendingWithTheReasonOfTheProvider(): void
    {
        $this->configure('SMTP_OAUTH');
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('error' => 'invalid_client', 'error_description' => 'AADSTS7000215: Invalid client secret provided.'), 401);
        $email = $this->emailOverTheDoubles($oauth);

        $exception = $this->failureOf(fn () => $email->sendEmail());

        $this->assertInstanceOf(Exception::class, $exception, 'PHPMailer\'s "SMTP authentication error" would hide the reason');
        $this->assertSame('SYS_OAUTH_REQUEST_FAILED', $exception->getTranslationId());
        $this->assertCount(1, $oauth->requests, 'a refused token is not requested again over a new connection');
        $this->assertSame(1, $this->connection->connections);
    }

    public function testARefusedTokenForTheCopyToTheSenderIsReportedWithItsReason(): void
    {
        $this->configure('SMTP_OAUTH');
        $GLOBALS['gValidLogin'] = true;
        $GLOBALS['gCurrentUser'] = new class {
            public function getValue(string $column): string
            {
                return $column === 'EMAIL' ? 'ada@example.org' : 'Ada';
            }
        };
        $oauth = (new ScriptedSmtpOAuth())
            ->answerWith(array('access_token' => 'access-1', 'expires_in' => 30))
            ->answerWith(array('error' => 'invalid_grant', 'error_description' => 'AADSTS70043: The refresh token has expired.'), 400);
        $email = $this->emailOverTheDoubles($oauth);
        $email->sendEmail();

        $exception = $this->failureOf(fn () => $email->sendCopyEmail());

        $this->assertInstanceOf(Exception::class, $exception, 'the copy is sent over a new connection, which needs a new token');
        $this->assertSame('SYS_OAUTH_REQUEST_FAILED', $exception->getTranslationId());
    }

    public function testInSingleModeARefusedTokenStopsTheMailsToAllRecipients(): void
    {
        $this->configure('SMTP_OAUTH', array('mail_sending_mode' => '1'));
        $GLOBALS['gCurrentUser'] = new class {
            public function isAdministrator(): bool
            {
                return true;
            }
        };
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('error' => 'invalid_client'), 401);
        $email = $this->emailOverTheDoubles($oauth);
        $email->addRecipient('bob@example.org', 'Bob', 'B');
        $email->addRecipient('cid@example.org', 'Cid', 'C');

        $exception = $this->failureOf(fn () => $email->sendEmail());

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertSame('SYS_OAUTH_REQUEST_FAILED', $exception->getTranslationId());
        $this->assertCount(1, $oauth->requests, 'one request instead of two for every recipient');
    }
}
