<?php
/**
 * The OAuth 2.0 tokens for sending emails over SMTP.
 *
 * The token endpoint of the provider is replaced by ScriptedSmtpOAuth, which records every request
 * and answers with what the test scripted, and the preferences by SmtpOAuthSettingsDouble.
 */

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\Exception;
use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\Tests\Unit\Infrastructure\Support\ScriptedSmtpOAuth;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthLanguageDouble;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthLoggerDouble;
use Admidio\Tests\Unit\Infrastructure\Support\SmtpOAuthSettingsDouble;

class SmtpOAuthTest extends AdmidioTestCase
{
    private const GLOBALS = array('gSettingsManager', 'gL10n', 'gLogger', 'gCurrentUserId', 'gCurrentOrgId');
    private const REDIRECT_URI = 'https://admidio.test/modules/preferences/smtp_oauth_callback.php';

    private array $savedGlobals = array();
    private mixed $savedSession = null;
    private SmtpOAuthSettingsDouble $settings;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/constants.php';

        foreach (self::GLOBALS as $name) {
            $this->savedGlobals[$name] = $GLOBALS[$name] ?? null;
        }
        $this->savedSession = $_SESSION['smtp_oauth_authorization'] ?? null;
        unset($_SESSION['smtp_oauth_authorization']);
        ScriptedSmtpOAuth::forgetAccessTokens();

        $GLOBALS['gL10n'] = new SmtpOAuthLanguageDouble();
        $GLOBALS['gLogger'] = new SmtpOAuthLoggerDouble();
        $GLOBALS['gCurrentUserId'] = 7;
        $GLOBALS['gCurrentOrgId'] = 1;
        $this->configure();
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
        unset($_SESSION['smtp_oauth_authorization']);
        if ($this->savedSession !== null) {
            $_SESSION['smtp_oauth_authorization'] = $this->savedSession;
        }
        ScriptedSmtpOAuth::forgetAccessTokens();

        parent::tearDown();
    }

    /**
     * @param array<string,string> $values Preferences that differ from a Microsoft 365 sign-in.
     * @param array<string,string> $database Rows another session changed in the meantime.
     */
    private function configure(array $values = array(), array $database = array()): void
    {
        $this->settings = new SmtpOAuthSettingsDouble($values + array(
            'mail_smtp_user' => 'info@example.org',
            'mail_smtp_oauth_provider' => 'microsoft',
            'mail_smtp_oauth_grant_type' => 'authorization_code',
            'mail_smtp_oauth_tenant_id' => 'contoso.onmicrosoft.com',
            'mail_smtp_oauth_client_id' => 'client-id',
            'mail_smtp_oauth_client_secret' => 'client-secret',
            'mail_smtp_oauth_refresh_token' => 'refresh-token-1'
        ), $database);
        $GLOBALS['gSettingsManager'] = $this->settings;
    }

    /** The PKCE challenge as RFC 7636 defines it, computed independently of the class. */
    private static function s256(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** @return array<string,string> The query parameters of the sign-in URL. */
    private static function queryOf(string $url): array
    {
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    private function startSignIn(ScriptedSmtpOAuth $oauth): string
    {
        return self::queryOf($oauth->getAuthorizationUrl())['state'];
    }

    private function failureOf(callable $call): Exception
    {
        try {
            $call();
        } catch (Exception $exception) {
            return $exception;
        }

        $this->fail('An exception was expected.');
    }

    public function testPhpMailerGetsTheAccessTokenAsXOAuth2Credentials(): void
    {
        $this->configure(array('mail_smtp_oauth_grant_type' => 'client_credentials'));
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'token_type' => 'Bearer', 'expires_in' => 3599));

        $this->assertSame("user=info@example.org\x01auth=Bearer access-1\x01\x01", base64_decode($oauth->getOauth64()));
    }

    public function testClientCredentialsAskForAnAppTokenOfTheTenant(): void
    {
        $this->configure(array('mail_smtp_oauth_grant_type' => 'client_credentials'));
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'expires_in' => 3599));

        $oauth->getOauth64();

        $this->assertCount(1, $oauth->requests);
        $this->assertSame('https://login.microsoftonline.com/contoso.onmicrosoft.com/oauth2/v2.0/token', $oauth->requests[0]['url']);
        $this->assertEquals(array(
            'grant_type' => 'client_credentials',
            'scope' => 'https://outlook.office365.com/.default',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret'
        ), $oauth->requests[0]['parameters']);
        $this->assertSame(array(), $this->settings->writes, 'an app token leaves the stored refresh token alone');
    }

    public function testAPublicClientSendsNoSecret(): void
    {
        $this->configure(array('mail_smtp_oauth_client_secret' => ''));
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'expires_in' => 3599));

        $oauth->getOauth64();

        $this->assertArrayNotHasKey('client_secret', $oauth->requests[0]['parameters'], 'an app without secret signs in with PKCE alone');
    }

    public function testTheTenantCannotLeaveThePathOfTheTokenEndpoint(): void
    {
        $this->configure(array('mail_smtp_oauth_grant_type' => 'client_credentials', 'mail_smtp_oauth_tenant_id' => 'evil.example/x?y#'));
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'expires_in' => 3599));

        $oauth->getOauth64();

        $this->assertSame('https://login.microsoftonline.com/evil.example%2Fx%3Fy%23/oauth2/v2.0/token', $oauth->requests[0]['url']);
    }

    public function testWithoutTenantMicrosoftAcceptsEveryAccount(): void
    {
        $this->configure(array('mail_smtp_oauth_tenant_id' => ''));
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'expires_in' => 3599));

        $oauth->getOauth64();

        $this->assertSame('https://login.microsoftonline.com/common/oauth2/v2.0/token', $oauth->requests[0]['url']);
    }

    public function testTheRefreshTokenIsReadFromTheDatabaseAndNotFromTheSessionCache(): void
    {
        $this->configure(array(), array('mail_smtp_oauth_refresh_token' => 'refresh-token-2'));
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'expires_in' => 3599));

        $oauth->getOauth64();

        $this->assertEquals(array(
            'grant_type' => 'refresh_token',
            'refresh_token' => 'refresh-token-2',
            'scope' => 'https://outlook.office.com/SMTP.Send offline_access',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret'
        ), $oauth->requests[0]['parameters']);
    }

    public function testARefreshTokenThatMicrosoftReplacedIsSaved(): void
    {
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'refresh_token' => 'refresh-token-3', 'expires_in' => 3599));

        $oauth->getOauth64();

        $this->assertSame(array(array('mail_smtp_oauth_refresh_token', 'refresh-token-3')), $this->settings->writes);
    }

    public function testAnUnchangedRefreshTokenIsNotSavedAgain(): void
    {
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'refresh_token' => 'refresh-token-1', 'expires_in' => 3599));

        $oauth->getOauth64();

        $this->assertSame(array(), $this->settings->writes);
    }

    public function testGoogleAlwaysRefreshesTheTokenOfTheSignedInUserWithoutScope(): void
    {
        $this->configure(array('mail_smtp_oauth_provider' => 'google', 'mail_smtp_oauth_grant_type' => 'client_credentials'));
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'expires_in' => 3599));

        $oauth->getOauth64();

        $this->assertSame('https://oauth2.googleapis.com/token', $oauth->requests[0]['url']);
        $this->assertEquals(array(
            'grant_type' => 'refresh_token',
            'refresh_token' => 'refresh-token-1',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret'
        ), $oauth->requests[0]['parameters'], 'Google offers no client credentials and documents no scope for the refresh');
        $this->assertSame(array(), $this->settings->writes, 'Google answers without a new refresh token, the stored one stays');
    }

    public function testTheAccessTokenIsReusedForTheNextLogin(): void
    {
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'expires_in' => 3599));

        $oauth->getOauth64();
        $second = $oauth->getOauth64();

        $this->assertCount(1, $oauth->requests);
        $this->assertStringContainsString('auth=Bearer access-1', base64_decode($second));
    }

    public function testEveryMailOfARequestUsesTheSameAccessToken(): void
    {
        $first = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'refresh_token' => 'refresh-token-2', 'expires_in' => 3599));
        $second = new ScriptedSmtpOAuth();

        $first->getOauth64();
        $credentials = base64_decode($second->getOauth64());

        $this->assertSame(array(), $second->requests, 'each Email has its own provider, but one token serves all mails of a request');
        $this->assertStringContainsString('auth=Bearer access-1', $credentials);
        $this->assertCount(1, $this->settings->writes, 'the refresh token is replaced once and not for every mail');
    }

    public function testAnotherMailboxDoesNotGetTheAccessTokenOfTheFirst(): void
    {
        $first = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-1', 'expires_in' => 3599));
        $first->getOauth64();
        $this->configure(array('mail_smtp_user' => 'board@example.org'));
        $second = (new ScriptedSmtpOAuth())->answerWith(array('access_token' => 'access-2', 'expires_in' => 3599));

        $credentials = base64_decode($second->getOauth64());

        $this->assertCount(1, $second->requests);
        $this->assertSame("user=board@example.org\x01auth=Bearer access-2\x01\x01", $credentials);
    }

    public function testATokenThatExpiresWithinAMinuteIsNotReused(): void
    {
        $oauth = (new ScriptedSmtpOAuth())
            ->answerWith(array('access_token' => 'access-1', 'expires_in' => 30))
            ->answerWith(array('access_token' => 'access-2', 'expires_in' => 3599));

        $oauth->getOauth64();
        $second = $oauth->getOauth64();

        $this->assertCount(2, $oauth->requests, 'the SMTP login could start after the token expired');
        $this->assertStringContainsString('auth=Bearer access-2', base64_decode($second));
    }

    public function testWithoutConnectedAccountNoTokenIsRequested(): void
    {
        $this->configure(array('mail_smtp_oauth_refresh_token' => ''));
        $oauth = new ScriptedSmtpOAuth();

        $exception = $this->failureOf(fn () => $oauth->getOauth64());

        $this->assertSame('SYS_SMTP_OAUTH_NOT_CONNECTED', $exception->getTranslationId());
        $this->assertSame(array(), $oauth->requests);
    }

    public function testTheReasonOfARejectedRequestIsReported(): void
    {
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array(
            'error' => 'invalid_client',
            'error_description' => "AADSTS7000215: Invalid client secret provided.\r\nTrace ID: 0000aaaa\r\nCorrelation ID: bbbb",
            'error_codes' => array(7000215)
        ), 401);

        $exception = $this->failureOf(fn () => $oauth->getOauth64());

        $this->assertSame('SYS_OAUTH_REQUEST_FAILED', $exception->getTranslationId());
        $this->assertSame('SYS_OAUTH_REQUEST_FAILED(invalid_client: AADSTS7000215: Invalid client secret provided.)', $exception->getMessage());
    }

    public function testAnErrorPageIsReportedWithItsHttpStatus(): void
    {
        $oauth = (new ScriptedSmtpOAuth())->answerWithBody('<html><body>Service unavailable</body></html>', 503);

        $exception = $this->failureOf(fn () => $oauth->getOauth64());

        $this->assertSame('SYS_OAUTH_REQUEST_FAILED(HTTP 503)', $exception->getMessage());
    }

    public function testTheProviderCannotPutHtmlIntoTheMessage(): void
    {
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('error' => 'invalid_request', 'error_description' => '<img src=x onerror=alert(1)>'), 400);

        $exception = $this->failureOf(fn () => $oauth->getOauth64());

        $this->assertStringNotContainsString('<img', $exception->getMessage());
        $this->assertStringContainsString('&lt;img', $exception->getMessage());
    }

    public function testAnAnswerWithoutAccessTokenIsAnError(): void
    {
        $oauth = (new ScriptedSmtpOAuth())->answerWith(array('token_type' => 'Bearer', 'expires_in' => 3599));

        $exception = $this->failureOf(fn () => $oauth->getOauth64());

        $this->assertSame('SYS_OAUTH_REQUEST_FAILED', $exception->getTranslationId());
    }

    public function testTheSignInAsksMicrosoftForTheSmtpScopeWithPkce(): void
    {
        $oauth = new ScriptedSmtpOAuth();

        $url = $oauth->getAuthorizationUrl();
        $query = self::queryOf($url);
        $signIn = $_SESSION['smtp_oauth_authorization'][$query['state']] ?? null;

        $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
        $this->assertSame('login.microsoftonline.com', parse_url($url, PHP_URL_HOST));
        $this->assertSame('/contoso.onmicrosoft.com/oauth2/v2.0/authorize', parse_url($url, PHP_URL_PATH));
        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(self::REDIRECT_URI, $query['redirect_uri']);
        $this->assertSame('https://outlook.office.com/SMTP.Send offline_access', $query['scope']);
        $this->assertSame('info@example.org', $query['login_hint']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $query['state']);
        $this->assertIsArray($signIn, 'the sign-in is remembered in the session under its state');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43,128}$/', $signIn['code_verifier']);
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', self::s256('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'), 'this test computes the challenge as RFC 7636 appendix B demands');
        $this->assertSame(self::s256($signIn['code_verifier']), $query['code_challenge']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertArrayNotHasKey('access_type', $query);
        $this->assertSame(array(), $oauth->requests, 'nothing is requested before the user signed in');
    }

    public function testGoogleIsAskedForAnOfflineRefreshToken(): void
    {
        $this->configure(array('mail_smtp_oauth_provider' => 'google'));

        $url = (new ScriptedSmtpOAuth())->getAuthorizationUrl();
        $query = self::queryOf($url);

        $this->assertSame('accounts.google.com', parse_url($url, PHP_URL_HOST));
        $this->assertSame('/o/oauth2/v2/auth', parse_url($url, PHP_URL_PATH));
        $this->assertSame('https://mail.google.com/', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt'], 'Google returns a refresh token only with the consent screen');
    }

    public function testASignInNeedsTheClientId(): void
    {
        $this->configure(array('mail_smtp_oauth_client_id' => ''));

        $exception = $this->failureOf(fn () => (new ScriptedSmtpOAuth())->getAuthorizationUrl());

        $this->assertSame('SYS_FIELD_EMPTY', $exception->getTranslationId());
        $this->assertArrayNotHasKey('smtp_oauth_authorization', $_SESSION);
    }

    public function testStartedSignInsDoNotPileUpInTheSession(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $states = array();
        for ($signIn = 0; $signIn < 20; ++$signIn) {
            $states[] = $this->startSignIn($oauth);
        }

        $kept = array_keys($_SESSION['smtp_oauth_authorization']);
        $this->assertLessThan(20, count($kept));
        $this->assertNotContains($states[0], $kept, 'the oldest sign-in is given up');
        $this->assertContains($states[19], $kept, 'the latest sign-in can be completed');
    }

    public function testTheCodeIsRedeemedWithTheVerifierOfItsSignIn(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $first = $this->startSignIn($oauth);
        $this->startSignIn($oauth);
        $verifier = $_SESSION['smtp_oauth_authorization'][$first]['code_verifier'];
        $oauth->answerWith(array('access_token' => 'access-1', 'refresh_token' => 'refresh-token-new', 'expires_in' => 3599));

        $oauth->handleAuthorizationResponse(array('state' => $first, 'code' => 'the-code'));

        $this->assertSame('https://login.microsoftonline.com/contoso.onmicrosoft.com/oauth2/v2.0/token', $oauth->requests[0]['url']);
        $this->assertEquals(array(
            'grant_type' => 'authorization_code',
            'code' => 'the-code',
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => $verifier,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret'
        ), $oauth->requests[0]['parameters']);
        $this->assertSame(array(array('mail_smtp_oauth_refresh_token', 'refresh-token-new')), $this->settings->writes);
    }

    public function testAnotherStartedSignInCanStillBeCompleted(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $first = $this->startSignIn($oauth);
        $second = $this->startSignIn($oauth);
        $oauth->answerWith(array('access_token' => 'access-1', 'refresh_token' => 'refresh-token-a', 'expires_in' => 3599))
            ->answerWith(array('access_token' => 'access-2', 'refresh_token' => 'refresh-token-b', 'expires_in' => 3599));
        $oauth->handleAuthorizationResponse(array('state' => $first, 'code' => 'code-a'));

        $oauth->handleAuthorizationResponse(array('state' => $second, 'code' => 'code-b'));

        $this->assertSame('refresh-token-b', $this->settings->values['mail_smtp_oauth_refresh_token'], 'a second tab completes its own sign-in');
    }

    public function testAnAnswerToASignInThatWasNeverStartedIsRejected(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $state = $this->startSignIn($oauth);

        $exception = $this->failureOf(fn () => $oauth->handleAuthorizationResponse(array('state' => str_repeat('0', 64), 'code' => 'the-code')));

        $this->assertSame('SYS_OAUTH_SIGN_IN_EXPIRED', $exception->getTranslationId());
        $this->assertSame(array(), $oauth->requests);
        $this->assertSame(array(), $this->settings->writes);
        $this->assertArrayHasKey($state, $_SESSION['smtp_oauth_authorization'], 'a forged answer does not cancel the sign-in that is running');
    }

    public function testAnOldSignInIsRejected(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $state = $this->startSignIn($oauth);
        $_SESSION['smtp_oauth_authorization'][$state]['requested_at'] -= 3600;

        $exception = $this->failureOf(fn () => $oauth->handleAuthorizationResponse(array('state' => $state, 'code' => 'the-code')));

        $this->assertSame('SYS_OAUTH_SIGN_IN_EXPIRED', $exception->getTranslationId());
        $this->assertSame(array(), $oauth->requests);
    }

    public function testASignInCanOnlyBeCompletedOnce(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $state = $this->startSignIn($oauth);
        $oauth->answerWith(array('access_token' => 'access-1', 'refresh_token' => 'refresh-token-new', 'expires_in' => 3599));
        $oauth->handleAuthorizationResponse(array('state' => $state, 'code' => 'the-code'));

        $exception = $this->failureOf(fn () => $oauth->handleAuthorizationResponse(array('state' => $state, 'code' => 'the-code')));

        $this->assertSame('SYS_OAUTH_SIGN_IN_EXPIRED', $exception->getTranslationId());
        $this->assertCount(1, $oauth->requests);
    }

    /**
     * @dataProvider anotherSession
     */
    public function testASignInStartedByAnotherUserOrInAnotherOrganizationIsRejected(string $global, int $value): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $state = $this->startSignIn($oauth);
        $GLOBALS[$global] = $value;

        $exception = $this->failureOf(fn () => $oauth->handleAuthorizationResponse(array('state' => $state, 'code' => 'the-code')));

        $this->assertSame('SYS_OAUTH_SIGN_IN_EXPIRED', $exception->getTranslationId());
        $this->assertSame(array(), $oauth->requests);
    }

    public static function anotherSession(): array
    {
        return array(
            'another user' => array('gCurrentUserId', 8),
            'another organization' => array('gCurrentOrgId', 2)
        );
    }

    public function testTheErrorOfTheProviderIsReportedForAStartedSignIn(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $state = $this->startSignIn($oauth);

        $exception = $this->failureOf(fn () => $oauth->handleAuthorizationResponse(array('state' => $state, 'error' => 'access_denied')));

        $this->assertSame('SYS_OAUTH_REQUEST_FAILED(access_denied)', $exception->getMessage());
        $this->assertSame(array(), $oauth->requests);
    }

    public function testASignInErrorIsReportedLikeAnErrorOfTheTokenEndpoint(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $state = $this->startSignIn($oauth);

        $exception = $this->failureOf(fn () => $oauth->handleAuthorizationResponse(array(
            'state' => $state,
            'error' => 'access_denied',
            'error_description' => "AADSTS65004: User declined to consent to access the app.\r\nTrace ID: 0000aaaa\r\nCorrelation ID: bbbb"
        )));

        $this->assertSame('SYS_OAUTH_REQUEST_FAILED(access_denied: AADSTS65004: User declined to consent to access the app.)', $exception->getMessage());
    }

    public function testTheProviderCannotPutHtmlIntoTheSignInError(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $state = $this->startSignIn($oauth);

        $exception = $this->failureOf(fn () => $oauth->handleAuthorizationResponse(array(
            'state' => $state,
            'error' => 'invalid_request',
            'error_description' => '<img src=x onerror=alert(1)> & "more"'
        )));

        $this->assertSame('SYS_OAUTH_REQUEST_FAILED(invalid_request: &lt;img src=x onerror=alert(1)&gt; &amp; &quot;more&quot;)', $exception->getMessage(), 'encoded exactly once');
    }

    public function testTheCodeIsRedeemedAsTheProviderSentIt(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $state = $this->startSignIn($oauth);
        $oauth->answerWith(array('access_token' => 'access-1', 'refresh_token' => 'refresh-token-new', 'expires_in' => 3599));

        $oauth->handleAuthorizationResponse(array('state' => $state, 'code' => '4/0Ad&b"c\'d'));

        $this->assertSame('4/0Ad&b"c\'d', $oauth->requests[0]['parameters']['code'], 'the code is not HTML encoded');
    }

    public function testAStateThatIsNoTextIsRejected(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $state = $this->startSignIn($oauth);

        $exception = $this->failureOf(fn () => $oauth->handleAuthorizationResponse(array('state' => array($state), 'code' => 'the-code')));

        $this->assertSame('SYS_OAUTH_SIGN_IN_EXPIRED', $exception->getTranslationId());
        $this->assertSame(array(), $oauth->requests);
    }

    public function testTheErrorOfASignInThatWasNeverStartedIsNotShown(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $this->startSignIn($oauth);

        $exception = $this->failureOf(fn () => $oauth->handleAuthorizationResponse(array('state' => 'forged', 'error' => 'Your account is blocked, call +1 555 0100')));

        $this->assertSame('SYS_OAUTH_SIGN_IN_EXPIRED', $exception->getMessage());
    }

    public function testASignInWithoutRefreshTokenConnectsNothing(): void
    {
        $oauth = new ScriptedSmtpOAuth();
        $state = $this->startSignIn($oauth);
        $oauth->answerWith(array('access_token' => 'access-1', 'expires_in' => 3599));

        $exception = $this->failureOf(fn () => $oauth->handleAuthorizationResponse(array('state' => $state, 'code' => 'the-code')));

        $this->assertSame('SYS_OAUTH_NO_REFRESH_TOKEN', $exception->getTranslationId(), 'the admin learns that offline access is missing');
        $this->assertSame(array(), $this->settings->writes);
    }
}
