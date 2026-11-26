<?php

namespace Admidio\Infrastructure;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\Preferences\ValueObject\SettingsManager;
use PHPMailer\PHPMailer\OAuthTokenProvider;

/**
 * @brief Access tokens for sending emails over SMTP with OAuth 2.0 (XOAUTH2)
 *
 * PHPMailer asks this class for the credentials when it logs in to the SMTP server. Microsoft 365
 * issues the access token either to the registered app itself (client credentials) or, like Google,
 * to the mailbox user who signed in once (authorization code). That sign-in leaves a refresh token in
 * the preferences, which is replaced whenever the provider issues a new one.
 *
 * **Providers.** Microsoft 365 and Google are built in. A plugin adds another provider with the typed
 * filter **smtp_oauth_providers**; the entry has the shape of the ones in PROVIDERS:
 * ```
 * Hooks::addFilter(SmtpOAuth::HOOK_PROVIDERS, function (array $providers): array {
 *     $providers['zoho'] = array(
 *         'name' => 'PLG_ZOHO_MAIL',
 *         'authorizationUrl' => 'https://accounts.{plg_zoho_region}/oauth/v2/auth',
 *         'tokenUrl' => 'https://accounts.{plg_zoho_region}/oauth/v2/token',
 *         'scope' => 'ZohoMail.messages.CREATE',
 *         'authorizationParameters' => array('access_type' => 'offline', 'prompt' => 'consent'),
 *         'smtp' => array('host' => 'smtp.zoho.eu', 'port' => 587, 'secure' => 'tls'),
 *         // declared like the settings in a plugin.json; the preference must be registered
 *         'settings' => array('plg_zoho_region' => array('type' => 'enum', 'label' => 'PLG_ZOHO_REGION',
 *             'values' => array('zoho.eu', 'zoho.com'), 'fallback' => 'zoho.eu'))
 *     );
 *     return $providers;
 * });
 * ```
 * A placeholder {name} in the URLs is replaced by the value of that preference, or by its
 * **fallback** while it is empty. The email preferences show the settings of a provider with the
 * same controls as the generated settings form of a plugin.
 *
 * **Actions.** smtp_oauth_connected and smtp_oauth_disconnected (provider, organization),
 * smtp_oauth_token_refreshed (provider, organization, whether the refresh token was replaced) and
 * smtp_oauth_refresh_failed (provider, organization, reason) let an administrator monitor the
 * connection.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SmtpOAuth implements OAuthTokenProvider
{
    public const HOOK_PROVIDERS = 'smtp_oauth_providers';
    public const HOOK_CONNECTED = 'smtp_oauth_connected';
    public const HOOK_DISCONNECTED = 'smtp_oauth_disconnected';
    public const HOOK_TOKEN_REFRESHED = 'smtp_oauth_token_refreshed';
    public const HOOK_REFRESH_FAILED = 'smtp_oauth_refresh_failed';

    public const GRANT_AUTHORIZATION_CODE = 'authorization_code';
    public const GRANT_CLIENT_CREDENTIALS = 'client_credentials';

    /**
     * The preferences that identify the app at the provider. A refresh token was issued to exactly
     * this app, so it is removed when one of them changes. The settings of the providers belong to
     * this list as well, see getIdentitySettingNames().
     */
    private const IDENTITY_SETTINGS = array('mail_smtp_oauth_provider', 'mail_smtp_oauth_grant_type', 'mail_smtp_oauth_client_id');

    private const PROVIDERS = array(
        'microsoft' => array(
            'name' => 'SYS_MICROSOFT_365',
            'authorizationUrl' => 'https://login.microsoftonline.com/{mail_smtp_oauth_tenant_id}/oauth2/v2.0/authorize',
            'tokenUrl' => 'https://login.microsoftonline.com/{mail_smtp_oauth_tenant_id}/oauth2/v2.0/token',
            'scope' => 'https://outlook.office.com/SMTP.Send offline_access',
            // Microsoft issues the access token of a refresh for the resource of the requested scope
            'refreshWithScope' => true,
            'authorizationParameters' => array(),
            'clientCredentialsScope' => 'https://outlook.office365.com/.default',
            'smtp' => array('host' => 'smtp.office365.com', 'port' => 587, 'secure' => 'tls'),
            'settings' => array(
                'mail_smtp_oauth_tenant_id' => array(
                    'type' => 'string',
                    'label' => 'SYS_SMTP_OAUTH_TENANT_ID',
                    'description' => 'SYS_SMTP_OAUTH_TENANT_ID_DESC',
                    // every account may sign in at the common endpoint, but app tokens are only issued in a tenant
                    'fallback' => 'common',
                    'requiredFor' => array(self::GRANT_CLIENT_CREDENTIALS)
                )
            )
        ),
        'google' => array(
            'name' => 'SYS_GOOGLE',
            'authorizationUrl' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'tokenUrl' => 'https://oauth2.googleapis.com/token',
            'scope' => 'https://mail.google.com/',
            'refreshWithScope' => false,
            'authorizationParameters' => array('access_type' => 'offline', 'prompt' => 'consent'),
            'clientCredentialsScope' => '',
            'smtp' => array('host' => 'smtp.gmail.com', 'port' => 587, 'secure' => 'tls'),
            'settings' => array()
        )
    );
    private const SESSION_KEY = 'smtp_oauth_authorization';
    private const SIGN_IN_LIFETIME = 600;
    private const SIGN_INS_KEPT = 5;
    private const MAX_RESPONSE_SIZE = 65536;

    /**
     * @var array<string,array{token:string,expiry:int}> The access tokens of this request, so that
     *      every Email of the request uses the same one. The key describes the organization, the app
     *      and the mailbox.
     */
    private static array $accessTokens = array();

    /**
     * @var SettingsManager The preferences of the organization whose mails are sent. A test may pass a
     *      stand-in with the same methods.
     */
    protected $settings;

    /**
     * @param SettingsManager|null $settings The preferences of the organization whose mails are sent,
     *        by default those of the current organization. Email passes its own, so the login always
     *        uses the account of the organization whose SMTP server it connects to.
     */
    public function __construct($settings = null)
    {
        global $gSettingsManager;

        $this->settings = $settings ?? $gSettingsManager;
    }

    /**
     * The address to which the provider returns after the sign-in. It must be registered in the app
     * at the provider. It has no query string, because Microsoft accepts none for personal accounts.
     * It is the same for every organization; the organization of a sign-in is kept in the session.
     * @return string Absolute URL of the callback page
     */
    public static function getRedirectUri(): string
    {
        return ADMIDIO_URL . FOLDER_MODULES . '/preferences/smtp_oauth_callback.php';
    }

    /**
     * The providers that can be chosen, the built-in ones and those that plugins added with the
     * filter smtp_oauth_providers. An entry a plugin got wrong is left out and logged.
     * @return array<string,array<string,mixed>> The providers by their ID, each with all keys of PROVIDERS.
     */
    public static function getProviders(): array
    {
        global $gLogger;

        $providers = array();
        foreach (Hooks::applyTypedFilters(self::HOOK_PROVIDERS, self::PROVIDERS) as $id => $provider) {
            $problem = self::getProviderProblem((string)$id, $provider);
            if ($problem !== '') {
                $gLogger?->warning('SMTP OAuth: provider "' . $id . '" is ignored: ' . $problem);
                continue;
            }
            $providers[(string)$id] = $provider + array(
                'refreshWithScope' => false,
                'authorizationParameters' => array(),
                'clientCredentialsScope' => '',
                'smtp' => array(),
                'settings' => array()
            );
        }

        return $providers;
    }

    /**
     * The preferences whose change removes the stored refresh token, because the token was issued
     * to the app and the directory they describe.
     * @return array<int,string>
     */
    public static function getIdentitySettingNames(): array
    {
        $names = self::IDENTITY_SETTINGS;
        foreach (self::getProviders() as $provider) {
            $names = array_merge($names, array_keys($provider['settings']));
        }

        return array_values(array_unique($names));
    }

    /**
     * The preferences that sending with OAuth 2.0 needs but that are empty in the given target state.
     * The preferences, the CLI and the configuration import check the same rules with this method.
     * @param callable(string):string $valueOf Returns the value a preference will have.
     * @return array<int,string> The names of the missing preferences, or the name of the provider or
     *         grant type preference if the chosen one cannot be used.
     */
    public static function getMissingSettings(callable $valueOf): array
    {
        $providers = self::getProviders();
        $providerId = $valueOf('mail_smtp_oauth_provider');
        if (!isset($providers[$providerId])) {
            return array('mail_smtp_oauth_provider');
        }
        $provider = $providers[$providerId];

        $grantType = $valueOf('mail_smtp_oauth_grant_type');
        if ($grantType === self::GRANT_CLIENT_CREDENTIALS && $provider['clientCredentialsScope'] === '') {
            return array('mail_smtp_oauth_grant_type');
        }

        $missing = array();
        // for client credentials the mailbox is named in the XOAUTH2 login, for a sign-in it is the login hint
        foreach (array('mail_smtp_host', 'mail_smtp_port', 'mail_smtp_user', 'mail_smtp_oauth_client_id') as $name) {
            if (trim($valueOf($name)) === '' || ($name === 'mail_smtp_port' && (int)$valueOf($name) <= 0)) {
                $missing[] = $name;
            }
        }
        foreach ($provider['settings'] as $name => $setting) {
            $required = !empty($setting['required']) || in_array($grantType, (array)($setting['requiredFor'] ?? array()), true);
            if ($required && trim($valueOf($name)) === '') {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * @param string $name A preference of the email dispatch or of a provider.
     * @return string The translation ID of its label, or the name of a preference that has none.
     */
    public static function getSettingLabel(string $name): string
    {
        $labels = array(
            'mail_smtp_host' => 'SYS_SMTP_HOST',
            'mail_smtp_port' => 'SYS_SMTP_PORT',
            'mail_smtp_user' => 'SYS_SMTP_USER',
            'mail_smtp_oauth_provider' => 'SYS_SMTP_OAUTH_PROVIDER',
            'mail_smtp_oauth_grant_type' => 'SYS_SMTP_OAUTH_GRANT_TYPE',
            'mail_smtp_oauth_client_id' => 'SYS_SMTP_OAUTH_CLIENT_ID'
        );
        if (isset($labels[$name])) {
            return $labels[$name];
        }
        foreach (self::getProviders() as $provider) {
            if (isset($provider['settings'][$name]['label'])) {
                return (string)$provider['settings'][$name]['label'];
            }
        }

        return $name;
    }

    /**
     * @return bool **true** if a mailbox user has signed in and the refresh token is stored.
     */
    public function isConnected(): bool
    {
        return $this->settings->getString('mail_smtp_oauth_refresh_token') !== '';
    }

    /**
     * @return bool **true** if Admidio has to sign in as the mailbox user, **false** if the app gets
     *              its tokens with its own credentials.
     * @throws Exception
     */
    public function needsSignIn(): bool
    {
        return $this->getGrantType() === self::GRANT_AUTHORIZATION_CODE;
    }

    /**
     * Returns the XOAUTH2 credentials for the login at the SMTP server. An access token is only
     * requested if this request has none yet or if it expires within the next minute.
     * @return string Base64 encoded XOAUTH2 credentials
     * @throws Exception
     */
    public function getOauth64(): string
    {
        $user = $this->settings->getString('mail_smtp_user');
        $key = implode('|', array_merge(
            array((string)$this->getOrganizationId()),
            array_map(fn (string $name): string => $this->settings->getString($name), self::IDENTITY_SETTINGS),
            array_map(fn (string $name): string => $this->getSettingValue($name), array_keys($this->getProvider()['settings'])),
            array($user)
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
        global $gCurrentUserId, $gCurrentOrgId, $gCurrentOrganization;

        $clientId = $this->settings->getString('mail_smtp_oauth_client_id');
        if ($clientId === '') {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_SMTP_OAUTH_CLIENT_ID'));
        }
        if (!$this->needsSignIn()) {
            throw new Exception('SYS_SMTP_OAUTH_NO_SIGN_IN_NEEDED');
        }
        $provider = $this->getProvider();

        $state = bin2hex(random_bytes(32));
        $codeVerifier = self::base64UrlEncode(random_bytes(32));

        // Each started sign-in keeps its own state, so that a second tab or a retry can still be
        // completed. Only the newest few are kept.
        $signIns = array_slice($_SESSION[self::SESSION_KEY] ?? array(), 1 - self::SIGN_INS_KEPT, null, true);
        $signIns[$state] = array(
            'code_verifier' => $codeVerifier,
            'usr_id' => $gCurrentUserId,
            'org_id' => $gCurrentOrgId,
            'org_name' => isset($gCurrentOrganization) ? (string)$gCurrentOrganization->getValue('org_longname') : '',
            'requested_at' => time()
        );
        $_SESSION[self::SESSION_KEY] = $signIns;

        $parameters = array(
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => self::getRedirectUri(),
            'scope' => $provider['scope'],
            'state' => $state,
            'code_challenge' => self::base64UrlEncode(hash('sha256', $codeVerifier, true)),
            'code_challenge_method' => 'S256'
        );
        if ($this->settings->getString('mail_smtp_user') !== '') {
            $parameters['login_hint'] = $this->settings->getString('mail_smtp_user');
        }

        return $this->getUrl('authorizationUrl') . '?' . http_build_query($parameters + $provider['authorizationParameters']);
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
        global $gCurrentUserId, $gCurrentOrgId;

        $state = self::getParameter($response, 'state');
        $signIn = $_SESSION[self::SESSION_KEY][$state] ?? null;

        // The callback URL is the same for all organizations, so the reason is told as exactly as
        // possible: the administrator can do something about each of them.
        if (!is_array($signIn) || $signIn['usr_id'] !== $gCurrentUserId) {
            throw new Exception('SYS_OAUTH_SIGN_IN_UNKNOWN');
        }
        // the code of the provider can only be redeemed once, so the sign-in is used up in any case
        unset($_SESSION[self::SESSION_KEY][$state]);
        if ($signIn['requested_at'] < time() - self::SIGN_IN_LIFETIME) {
            throw new Exception('SYS_OAUTH_SIGN_IN_EXPIRED');
        }
        if ($signIn['org_id'] !== $gCurrentOrgId) {
            throw new Exception('SYS_OAUTH_SIGN_IN_OTHER_ORGANIZATION', array(SecurityUtils::encodeHTML((string)($signIn['org_name'] ?? ''))));
        }

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

        $refreshToken = self::getNewRefreshToken($token);
        if ($refreshToken === null) {
            throw new Exception('SYS_OAUTH_NO_REFRESH_TOKEN');
        }

        $this->settings->set('mail_smtp_oauth_refresh_token', $refreshToken);
        Hooks::doActionCatchErrors(self::HOOK_CONNECTED, $this->settings->getString('mail_smtp_oauth_provider'), $this->getOrganizationId());
    }

    /**
     * Connects the account with a refresh token that was issued elsewhere, for an installation that
     * is only administered on the command line. The token is redeemed once, so that a wrong one is
     * never stored.
     * @param string $refreshToken A refresh token of the configured app for the mailbox user.
     * @throws Exception
     */
    public function connectWithRefreshToken(string $refreshToken): void
    {
        $refreshToken = trim($refreshToken);
        if (!$this->needsSignIn()) {
            throw new Exception('SYS_SMTP_OAUTH_NO_SIGN_IN_NEEDED');
        }
        if ($refreshToken === '') {
            throw new Exception('SYS_SMTP_OAUTH_NOT_CONNECTED', array('SYS_MAIL_DISPATCH'));
        }

        $token = $this->requestToken($this->getRefreshParameters($refreshToken));
        $this->settings->set('mail_smtp_oauth_refresh_token', self::getNewRefreshToken($token) ?? $refreshToken);
        Hooks::doActionCatchErrors(self::HOOK_CONNECTED, $this->settings->getString('mail_smtp_oauth_provider'), $this->getOrganizationId());
    }

    /**
     * Removes the refresh token, so no mail is sent with the account of the mailbox user any more.
     * @throws Exception
     */
    public function disconnect(): void
    {
        $this->settings->set('mail_smtp_oauth_refresh_token', '');
        Hooks::doActionCatchErrors(self::HOOK_DISCONNECTED, $this->settings->getString('mail_smtp_oauth_provider'), $this->getOrganizationId());
    }

    /**
     * @return array{token:string,expiry:int} The access token and when it should no longer be used.
     * @throws Exception
     */
    private function requestAccessToken(): array
    {
        $providerId = $this->settings->getString('mail_smtp_oauth_provider');

        try {
            if ($this->needsSignIn()) {
                $token = $this->lockRefreshToken(function (string $refreshToken) use ($providerId): array {
                    if ($refreshToken === '') {
                        throw new Exception('SYS_SMTP_OAUTH_NOT_CONNECTED', array('SYS_MAIL_DISPATCH'));
                    }

                    $token = $this->requestToken($this->getRefreshParameters($refreshToken));
                    $newRefreshToken = self::getNewRefreshToken($token);
                    $replaced = $newRefreshToken !== null && $newRefreshToken !== $refreshToken;
                    if ($replaced) {
                        $this->storeRefreshToken($newRefreshToken);
                    }
                    Hooks::doActionCatchErrors(self::HOOK_TOKEN_REFRESHED, $providerId, $this->getOrganizationId(), $replaced);

                    return $token;
                });
            } else {
                $token = $this->requestToken(array('grant_type' => 'client_credentials', 'scope' => $this->getProvider()['clientCredentialsScope']));
            }
        } catch (Exception $exception) {
            Hooks::doActionCatchErrors(self::HOOK_REFRESH_FAILED, $providerId, $this->getOrganizationId(), $exception->getMessage());
            throw $exception;
        }

        return array('token' => $token['access_token'], 'expiry' => time() + (int)($token['expires_in'] ?? 3600) - 60);
    }

    /**
     * Runs the refresh while no other process can refresh with the same token. A provider that
     * replaces the refresh token with every refresh (Microsoft) could otherwise get the same token
     * from two processes, and the one that stores last would store the older answer. The row of the
     * refresh token is locked until the transaction ends; inside a transaction of the caller that is
     * when the caller commits.
     * @param callable(string):array $refresh Gets the stored refresh token and returns the answer of the provider.
     * @return array<string,mixed> What the callback returned.
     * @throws Exception
     */
    protected function lockRefreshToken(callable $refresh): array
    {
        global $gDb;

        $gDb->startTransaction();
        try {
            $sql = 'SELECT prf_value
                      FROM ' . TBL_PREFERENCES . '
                     WHERE prf_org_id = ? -- $this->getOrganizationId()
                       AND prf_name   = \'mail_smtp_oauth_refresh_token\'
                       FOR UPDATE';
            $refreshToken = $gDb->queryPrepared($sql, array($this->getOrganizationId()))->fetchColumn();

            return $refresh(is_string($refreshToken) ? $refreshToken : '');
        } finally {
            // nothing was written if the refresh failed, so the transaction is ended in any case
            $gDb->endTransaction();
        }
    }

    /**
     * Stores the refresh token the provider issued instead of the used one. The access token is valid
     * either way, so the mail is still sent if that fails, but the next refresh may need a new sign-in.
     */
    private function storeRefreshToken(string $refreshToken): void
    {
        global $gLogger;

        try {
            $this->settings->set('mail_smtp_oauth_refresh_token', $refreshToken);
        } catch (\Throwable $exception) {
            $gLogger?->warning(
                'SMTP OAuth: the refresh token the provider replaced could not be stored. If the provider has '
                . 'invalidated the old one, the account has to be connected again.',
                array('exception' => $exception->getMessage())
            );
        }
    }

    /**
     * @return array<string,string> The parameters that redeem a refresh token.
     * @throws Exception
     */
    private function getRefreshParameters(string $refreshToken): array
    {
        $provider = $this->getProvider();
        $parameters = array('grant_type' => 'refresh_token', 'refresh_token' => $refreshToken);
        if ($provider['refreshWithScope']) {
            $parameters['scope'] = $provider['scope'];
        }

        return $parameters;
    }

    /**
     * Sends a request to the token endpoint of the provider with the credentials of the app.
     * @param array<string,string> $parameters The parameters of the grant.
     * @return array<string,mixed> The answer of the provider, which contains an access token.
     * @throws Exception
     */
    private function requestToken(array $parameters): array
    {
        $parameters['client_id'] = $this->settings->getString('mail_smtp_oauth_client_id');
        // an app without secret (public client) signs in with PKCE alone and must not send an empty one
        if ($this->settings->getString('mail_smtp_oauth_client_secret') !== '') {
            $parameters['client_secret'] = $this->settings->getString('mail_smtp_oauth_client_secret');
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
     * Sends the POST request to the token endpoint, with the policy that Admidio applies to every
     * request the server sends: HTTPS only, no redirects, and the connection pinned to the public
     * address that was checked.
     * @param string $url URL of the token endpoint.
     * @param array<string,string> $parameters The form parameters of the request.
     * @return array{status:int, body:string} The HTTP status and the body of the answer.
     * @throws Exception
     */
    protected function sendTokenRequest(string $url, array $parameters): array
    {
        if (!function_exists('curl_init')) {
            throw self::requestFailed('The PHP extension curl is not installed.');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw self::requestFailed('Could not initialize the request.');
        }

        $body = '';
        $tooLarge = false;
        curl_setopt_array($curl, SecurityUtils::getOutboundRequestCurlOptions($url) + array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($parameters),
            CURLOPT_HTTPHEADER => array('Accept: application/json'),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_SIZE) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            }
        ));

        $result = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        // no curl_close(): it has done nothing since PHP 8.0, the handle is freed with the variable

        if ($tooLarge) {
            throw self::requestFailed('The answer is larger than ' . self::MAX_RESPONSE_SIZE . ' bytes.');
        }
        if ($result === false) {
            throw self::requestFailed($error);
        }

        return array('status' => $status, 'body' => $body);
    }

    /**
     * @return string The grant type that is used: client credentials only if the provider offers them.
     * @throws Exception
     */
    private function getGrantType(): string
    {
        if ($this->settings->getString('mail_smtp_oauth_grant_type') === self::GRANT_CLIENT_CREDENTIALS
            && $this->getProvider()['clientCredentialsScope'] !== '') {
            return self::GRANT_CLIENT_CREDENTIALS;
        }

        return self::GRANT_AUTHORIZATION_CODE;
    }

    /**
     * @return array<string,mixed> The endpoints and the scope of the configured provider.
     * @throws Exception if the provider is not available, e.g. because its plugin was deactivated.
     */
    private function getProvider(): array
    {
        $providerId = $this->settings->getString('mail_smtp_oauth_provider');
        $providers = self::getProviders();
        if (!isset($providers[$providerId])) {
            throw new Exception('SYS_SMTP_OAUTH_PROVIDER_UNAVAILABLE', array(SecurityUtils::encodeHTML($providerId)));
        }

        return $providers[$providerId];
    }

    /**
     * @return string The value of a setting of the provider, or its fallback while it is empty.
     * @throws Exception
     */
    private function getSettingValue(string $name): string
    {
        $value = $this->settings->has($name) ? $this->settings->getString($name) : '';

        return $value !== '' ? $value : (string)($this->getProvider()['settings'][$name]['fallback'] ?? '');
    }

    /**
     * @param string $type authorizationUrl or tokenUrl
     * @return string The URL of the configured provider with the values of its settings.
     * @throws Exception
     */
    private function getUrl(string $type): string
    {
        $replacements = array();
        foreach (array_keys($this->getProvider()['settings']) as $name) {
            $replacements['{' . $name . '}'] = rawurlencode($this->getSettingValue($name));
        }

        return strtr($this->getProvider()[$type], $replacements);
    }

    private function getOrganizationId(): int
    {
        return method_exists($this->settings, 'getOrganizationId') ? $this->settings->getOrganizationId() : 0;
    }

    /**
     * @param mixed $provider An entry of the filter smtp_oauth_providers.
     * @return string What is wrong with the entry, or an empty string.
     */
    private static function getProviderProblem(string $id, mixed $provider): string
    {
        if (!preg_match('/^[a-z0-9_]+$/', $id)) {
            return 'the ID may only contain lowercase letters, digits and underscores';
        }
        if (!is_array($provider)) {
            return 'the entry is no array';
        }
        foreach (array('name', 'authorizationUrl', 'tokenUrl', 'scope') as $key) {
            if (!is_string($provider[$key] ?? null) || $provider[$key] === '') {
                return 'the key "' . $key . '" is missing';
            }
        }
        foreach (array('authorizationUrl', 'tokenUrl') as $key) {
            if (!str_starts_with($provider[$key], 'https://')) {
                return 'the ' . $key . ' must be an HTTPS URL';
            }
        }
        foreach (array_keys((array)($provider['settings'] ?? array())) as $name) {
            if (!PreferenceDefinitions::isSupported((string)$name)) {
                return 'the setting "' . $name . '" is not a registered preference';
            }
        }

        return '';
    }

    /**
     * @return string|null The refresh token of the answer, if the provider issued one.
     */
    private static function getNewRefreshToken(array $token): ?string
    {
        return is_string($token['refresh_token'] ?? null) && $token['refresh_token'] !== '' ? $token['refresh_token'] : null;
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
