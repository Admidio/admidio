<?php

namespace Admidio\Infrastructure;

use Admidio\Infrastructure\Utils\SecurityUtils;
use PHPMailer\PHPMailer\OAuthTokenProvider;

/**
 * @brief Access tokens for sending emails over SMTP with OAuth 2.0 (XOAUTH2)
 *
 * PHPMailer asks this class for the credentials when it logs in to the SMTP server. Microsoft 365
 * issues the access token either to the registered app itself (client credentials) or, like Google,
 * to the mailbox user who signed in once (authorization code). That sign-in leaves a refresh token in
 * the preferences, which is replaced whenever the provider issues a new one.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SmtpOAuth implements OAuthTokenProvider
{
    private const PROVIDERS = array(
        'microsoft' => array(
            'authorizationUrl' => 'https://login.microsoftonline.com/#TENANT#/oauth2/v2.0/authorize',
            'tokenUrl' => 'https://login.microsoftonline.com/#TENANT#/oauth2/v2.0/token',
            'scope' => 'https://outlook.office.com/SMTP.Send offline_access',
            'authorizationParameters' => array()
        ),
        'google' => array(
            'authorizationUrl' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'tokenUrl' => 'https://oauth2.googleapis.com/token',
            'scope' => 'https://mail.google.com/',
            'authorizationParameters' => array('access_type' => 'offline', 'prompt' => 'consent')
        )
    );
    private const MICROSOFT_APPLICATION_SCOPE = 'https://outlook.office365.com/.default';
    private const SESSION_KEY = 'smtp_oauth_authorization';
    private const SIGN_IN_LIFETIME = 600;
    private const SIGN_INS_KEPT = 5;

    /**
     * @var array<string,array{token:string,expiry:int}> The access tokens of this request, so that
     *      every Email of the request uses the same one. The key describes the app and the mailbox.
     */
    private static array $accessTokens = array();

    /**
     * The address to which the provider returns after the sign-in. It must be registered in the app
     * at the provider. It has no query string, because Microsoft accepts none for personal accounts.
     * @return string Absolute URL of the callback page
     */
    public static function getRedirectUri(): string
    {
        return ADMIDIO_URL . FOLDER_MODULES . '/preferences/smtp_oauth_callback.php';
    }

    /**
     * Returns the XOAUTH2 credentials for the login at the SMTP server. An access token is only
     * requested if this request has none yet or if it expires within the next minute.
     * @return string Base64 encoded XOAUTH2 credentials
     * @throws Exception
     */
    public function getOauth64(): string
    {
        global $gSettingsManager;

        $user = $gSettingsManager->getString('mail_smtp_user');
        $key = implode('|', array(
            $gSettingsManager->getString('mail_smtp_oauth_provider'),
            $gSettingsManager->getString('mail_smtp_oauth_grant_type'),
            $gSettingsManager->getString('mail_smtp_oauth_tenant_id'),
            $gSettingsManager->getString('mail_smtp_oauth_client_id'),
            $user
        ));

        if ((self::$accessTokens[$key]['expiry'] ?? 0) <= time()) {
            self::$accessTokens[$key] = $this->requestAccessToken();
        }

        return base64_encode('user=' . $user . "\001auth=Bearer " . self::$accessTokens[$key]['token'] . "\001\001");
    }

    /**
     * Starts the sign-in of the mailbox user at the provider. The state and the PKCE verifier of the
     * sign-in are kept in the session until the provider returns to the redirect URI.
     * @return string URL of the sign-in page of the provider
     * @throws Exception
     */
    public function getAuthorizationUrl(): string
    {
        global $gSettingsManager, $gCurrentUserId, $gCurrentOrgId;

        $clientId = $gSettingsManager->getString('mail_smtp_oauth_client_id');
        if ($clientId === '') {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_SSO_CLIENT_ID'));
        }

        $state = bin2hex(random_bytes(32));
        $codeVerifier = self::base64UrlEncode(random_bytes(32));

        // One click may start several sign-ins, because the button is bound again with every
        // preferences panel that is loaded. So each sign-in keeps its own state.
        $signIns = array_slice($_SESSION[self::SESSION_KEY] ?? array(), 1 - self::SIGN_INS_KEPT, null, true);
        $signIns[$state] = array(
            'code_verifier' => $codeVerifier,
            'usr_id' => $gCurrentUserId,
            'org_id' => $gCurrentOrgId,
            'requested_at' => time()
        );
        $_SESSION[self::SESSION_KEY] = $signIns;

        $parameters = array(
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => self::getRedirectUri(),
            'scope' => $this->getProvider()['scope'],
            'state' => $state,
            'code_challenge' => self::base64UrlEncode(hash('sha256', $codeVerifier, true)),
            'code_challenge_method' => 'S256'
        );
        if ($gSettingsManager->getString('mail_smtp_user') !== '') {
            $parameters['login_hint'] = $gSettingsManager->getString('mail_smtp_user');
        }

        return $this->getUrl('authorizationUrl') . '?' . http_build_query($parameters + $this->getProvider()['authorizationParameters']);
    }

    /**
     * Completes the sign-in when the provider returned to the redirect URI and stores the refresh
     * token of the mailbox user.
     * @param array<string,mixed> $response The query parameters as the provider sent them: the state
     *        and the authorization code, or an error and its description.
     * @throws Exception
     */
    public function handleAuthorizationResponse(array $response): void
    {
        global $gSettingsManager, $gCurrentUserId, $gCurrentOrgId;

        $state = self::getParameter($response, 'state');
        $signIn = $_SESSION[self::SESSION_KEY][$state] ?? null;

        if (!is_array($signIn)
            || $signIn['usr_id'] !== $gCurrentUserId
            || $signIn['org_id'] !== $gCurrentOrgId
            || $signIn['requested_at'] < time() - self::SIGN_IN_LIFETIME) {
            throw new Exception('SYS_OAUTH_SIGN_IN_EXPIRED');
        }
        unset($_SESSION[self::SESSION_KEY][$state]);

        $error = self::getParameter($response, 'error');
        if ($error !== '') {
            throw self::requestFailed(self::getErrorReason($error, self::getParameter($response, 'error_description')));
        }

        $token = $this->requestToken(array(
            'grant_type' => 'authorization_code',
            'code' => self::getParameter($response, 'code'),
            'redirect_uri' => self::getRedirectUri(),
            'code_verifier' => $signIn['code_verifier']
        ));

        if (!is_string($token['refresh_token'] ?? null) || $token['refresh_token'] === '') {
            throw new Exception('SYS_OAUTH_NO_REFRESH_TOKEN');
        }

        $gSettingsManager->set('mail_smtp_oauth_refresh_token', $token['refresh_token']);
    }

    /**
     * @return array{token:string,expiry:int} The access token and when it should no longer be used.
     * @throws Exception
     */
    private function requestAccessToken(): array
    {
        global $gSettingsManager;

        if ($gSettingsManager->getString('mail_smtp_oauth_provider') === 'microsoft'
            && $gSettingsManager->getString('mail_smtp_oauth_grant_type') === 'client_credentials') {
            $token = $this->requestToken(array('grant_type' => 'client_credentials', 'scope' => self::MICROSOFT_APPLICATION_SCOPE));
        } else {
            // another session may already have stored a newer refresh token
            $refreshToken = $gSettingsManager->getString('mail_smtp_oauth_refresh_token', true);
            if ($refreshToken === '') {
                throw new Exception('SYS_SMTP_OAUTH_NOT_CONNECTED', array('SYS_MAIL_DISPATCH'));
            }

            $parameters = array('grant_type' => 'refresh_token', 'refresh_token' => $refreshToken);
            if ($gSettingsManager->getString('mail_smtp_oauth_provider') === 'microsoft') {
                // Microsoft issues the access token for the resource of the requested scope
                $parameters['scope'] = self::PROVIDERS['microsoft']['scope'];
            }
            $token = $this->requestToken($parameters);

            if (is_string($token['refresh_token'] ?? null) && $token['refresh_token'] !== '' && $token['refresh_token'] !== $refreshToken) {
                $gSettingsManager->set('mail_smtp_oauth_refresh_token', $token['refresh_token']);
            }
        }

        return array('token' => $token['access_token'], 'expiry' => time() + (int)($token['expires_in'] ?? 3600) - 60);
    }

    /**
     * Sends a request to the token endpoint of the provider with the credentials of the app.
     * @param array<string,string> $parameters The parameters of the grant.
     * @return array<string,mixed> The answer of the provider, which contains an access token.
     * @throws Exception
     */
    private function requestToken(array $parameters): array
    {
        global $gSettingsManager;

        $parameters['client_id'] = $gSettingsManager->getString('mail_smtp_oauth_client_id');
        // an app without secret (public client) signs in with PKCE alone and must not send an empty one
        if ($gSettingsManager->getString('mail_smtp_oauth_client_secret') !== '') {
            $parameters['client_secret'] = $gSettingsManager->getString('mail_smtp_oauth_client_secret');
        }

        $response = $this->sendTokenRequest($this->getUrl('tokenUrl'), $parameters);
        $token = json_decode($response['body'], true);

        if ($response['status'] !== 200 || !is_array($token) || !is_string($token['access_token'] ?? null) || $token['access_token'] === '') {
            $reason = 'HTTP ' . $response['status'];
            if (is_array($token) && is_string($token['error'] ?? null)) {
                $reason = self::getErrorReason($token['error'], is_string($token['error_description'] ?? null) ? $token['error_description'] : '');
            }
            throw self::requestFailed($reason);
        }

        return $token;
    }

    /**
     * Sends the POST request to the token endpoint.
     * @param string $url URL of the token endpoint.
     * @param array<string,string> $parameters The form parameters of the request.
     * @return array{status:int, body:string} The HTTP status and the body of the answer.
     * @throws Exception
     */
    protected function sendTokenRequest(string $url, array $parameters): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($parameters),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15
        ));
        $body = curl_exec($curl);

        if ($body === false) {
            throw self::requestFailed(curl_error($curl));
        }

        return array('status' => (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'body' => $body);
    }

    /**
     * @return array<string,mixed> The endpoints and the scope of the configured provider.
     */
    private function getProvider(): array
    {
        global $gSettingsManager;

        return self::PROVIDERS[$gSettingsManager->getString('mail_smtp_oauth_provider')];
    }

    /**
     * @param string $type authorizationUrl or tokenUrl
     * @return string The URL of the configured provider, for Microsoft within the configured tenant.
     */
    private function getUrl(string $type): string
    {
        global $gSettingsManager;

        $tenant = $gSettingsManager->getString('mail_smtp_oauth_tenant_id');

        return str_replace('#TENANT#', rawurlencode($tenant !== '' ? $tenant : 'common'), $this->getProvider()[$type]);
    }

    /**
     * @return string The parameter as the provider sent it, or an empty string if it is missing or no text.
     */
    private static function getParameter(array $response, string $name): string
    {
        return is_string($response[$name] ?? null) ? $response[$name] : '';
    }

    /**
     * @param string $reason The reason as the provider or cURL reported it.
     * @return Exception The error for the admin, with the reason encoded for the HTML output.
     */
    private static function requestFailed(string $reason): Exception
    {
        return new Exception('SYS_OAUTH_REQUEST_FAILED', array(SecurityUtils::encodeHTML($reason)));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @return string The error code of the provider with the first line of its description, because
     *                Microsoft adds a trace and a correlation id in further lines.
     */
    private static function getErrorReason(string $error, string $description): string
    {
        $description = (string)strtok($description, "\r\n");

        return $error . ($description !== '' ? ': ' . $description : '');
    }
}
