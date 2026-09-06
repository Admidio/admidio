<?php
namespace Admidio\SSO\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Admidio\Infrastructure\Database;
use Admidio\Changelog\Entity\LogChanges;

class OIDCClient extends SSOClient implements ClientEntityInterface
{
    public const SCOPE_OPENID = 'openid';
    public const SCOPE_PROFILE = 'profile';
    public const SCOPE_EMAIL = 'email';
    public const SCOPE_ADDRESS = 'address';
    public const SCOPE_PHONE = 'phone';
    public const SCOPE_GROUPS = 'groups';
    public const SCOPE_CUSTOM = 'custom';

    private const SUPPORTED_SCOPES = array(
        self::SCOPE_OPENID,
        self::SCOPE_PROFILE,
        self::SCOPE_EMAIL,
        self::SCOPE_ADDRESS,
        self::SCOPE_PHONE,
        self::SCOPE_GROUPS,
        self::SCOPE_CUSTOM
    );

    private const SUPPORTED_GRANT_TYPES = array(
        'authorization_code',
        'refresh_token'
    );

    /**
     * The subject of an ID token must be unique and must never be reassigned to another
     * person (OpenID Connect Core, section 2). A login name and an e-mail address are
     * neither immutable nor unique in Admidio, so only these two remain.
     */
    private const SUPPORTED_SUBJECT_FIELDS = array(
        'usr_uuid',
        'usr_id'
    );

    public function __construct(Database $database, $client_id = null) {
        parent::__construct($database, 'oidc', TBL_OIDC_CLIENTS, 'ocl', $client_id);
        if ($this->isNewRecord() && empty($client_id)) {
            $this->setValue($this->columnPrefix . '_scope', implode(' ', self::SUPPORTED_SCOPES));
            $this->setValue($this->columnPrefix . '_grant_types', implode(' ', self::SUPPORTED_GRANT_TYPES));
            $this->setValue($this->columnPrefix . '_userid_field', 'usr_uuid');
        }
    }

    /**
     * Return all scopes supported by the Admidio OIDC provider.
     * @return array
     */
    public static function getSupportedScopes(): array
    {
        return self::SUPPORTED_SCOPES;
    }

    /**
     * Return the user fields that may be used as the OIDC subject of a client.
     * @return array<int,string>
     */
    public static function getSupportedSubjectFields(): array
    {
        return self::SUPPORTED_SUBJECT_FIELDS;
    }

    /**
     * Return the optional scopes that can be configured for a client.
     * @return array
     */
    public static function getOptionalScopes(): array
    {
        return array_values(array_diff(self::SUPPORTED_SCOPES, array(self::SCOPE_OPENID)));
    }

    /**
     * Return the scopes enabled for this client.
     * @return array
     */
    public function getAllowedScopes(): array
    {
        $scopes = preg_split(
            '/[,;\s]+/',
            trim($this->getValue($this->columnPrefix . '_scope')),
            -1, PREG_SPLIT_NO_EMPTY
        );

        if ($scopes === false) {
            $scopes = array();
        }

        $scopes = array_values(array_intersect(self::SUPPORTED_SCOPES, $scopes));
        if (!in_array(self::SCOPE_OPENID, $scopes, true)) {
            array_unshift($scopes, self::SCOPE_OPENID);
        }

        return $scopes;
    }

    /**
     * Return the grant types enabled for this client.
     * @return array<int,string>
     */
    public function getAllowedGrantTypes(): array
    {
        $configuredGrantTypes = preg_split(
            '/\s+/',
            trim((string) $this->getValue($this->columnPrefix . '_grant_types', 'database')),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (!is_array($configuredGrantTypes)) {
            return array();
        }

        return array_values(array_intersect($configuredGrantTypes, self::SUPPORTED_GRANT_TYPES));
    }

    public function getRedirectUri(): string
    {
        return $this->getValue($this->columnPrefix . '_redirect_uri', 'database')??'';
    }

    public function requiresPKCE(): bool
    {
        return (bool) $this->getValue($this->columnPrefix . '_require_pkce');
    }

    /**
     * Return the registered post-logout redirect URIs.
     *
     * One URI is stored per line. Empty lines are ignored.
     *
     * @return array<int,string>
     */
    public function getPostLogoutRedirectUris(): array
    {
        $value = (string) $this->getValue($this->columnPrefix . '_post_logout_redirect_uris', 'database');

        $uris = preg_split('/\R/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        if ($uris === false) {
            return array();
        }

        return array_values(array_unique(array_filter(
            array_map('trim', $uris),
            static fn (string $uri): bool => $uri !== ''
        )));
    }

    /**
     * Check whether the client may send the user to the given URI after a logout.
     *
     * A registered URI without a "*" has to match exactly, as required by OpenID Connect
     * RP-Initiated Logout. A registered URI may use "*" as a placeholder for any part of the path
     * or of the query string, which allows applications that append varying parameters (e.g. the
     * user's language) to be registered with a single entry.
     *
     * @param string $uri The post-logout redirect URI requested by the client.
     * @return bool Returns **true** if the URI may be used for the redirect after the logout.
     */
    public function isPostLogoutRedirectUriAllowed(string $uri): bool
    {
        foreach ($this->getPostLogoutRedirectUris() as $registeredUri) {
            if (!str_contains($registeredUri, '*')) {
                if ($registeredUri === $uri) {
                    return true;
                }
            } elseif (self::matchesPostLogoutRedirectUriPattern($registeredUri, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a post-logout redirect URI against a registered URI containing at least one "*".
     *
     * Scheme, host and port must be given without a placeholder and must match exactly, so that a
     * pattern can never match a different site. Without that restriction an entry like
     * "https://example.org*" would also allow "https://example.org.attacker.example/", which would
     * turn the logout endpoint into an open redirect. The placeholder therefore only applies to the
     * part after the host, and it does not match line breaks.
     *
     * @param string $pattern The registered URI, containing at least one "*".
     * @param string $uri The post-logout redirect URI requested by the client.
     * @return bool Returns **true** if the requested URI matches the registered URI.
     */
    private static function matchesPostLogoutRedirectUriPattern(string $pattern, string $uri): bool
    {
        $patternParts = parse_url($pattern);
        $uriParts = parse_url($uri);
        if (!is_array($patternParts) || !is_array($uriParts)) {
            return false;
        }

        // Credentials in a redirect URI are used to disguise the actual target, so neither the
        // registered nor the requested URI may contain them.
        foreach (array('user', 'pass') as $part) {
            if (isset($patternParts[$part]) || isset($uriParts[$part])) {
                return false;
            }
        }

        // Scheme and host are compared case-insensitively, as they are case-insensitive by RFC 3986.
        foreach (array('scheme', 'host') as $part) {
            $patternValue = (string) ($patternParts[$part] ?? '');
            $uriValue = (string) ($uriParts[$part] ?? '');
            if ($patternValue === '' || $uriValue === ''
                || str_contains($patternValue, '*')
                || strcasecmp($patternValue, $uriValue) !== 0
            ) {
                return false;
            }
        }

        if (($patternParts['port'] ?? null) !== ($uriParts['port'] ?? null)) {
            return false;
        }

        $patternPath = self::getUriPathQueryFragment($patternParts);
        if (!str_contains($patternPath, '*')) {
            // The only placeholder was part of the host, which is never accepted.
            return false;
        }

        $regex = '#^' . str_replace('\*', '.*', preg_quote($patternPath, '#')) . '$#';
        return preg_match($regex, self::getUriPathQueryFragment($uriParts)) === 1;
    }

    /**
     * Reassemble everything after the host of a URI parsed by parse_url().
     *
     * @param array<string,mixed> $uriParts Result of parse_url().
     * @return string Path, query string and fragment of the URI.
     */
    private static function getUriPathQueryFragment(array $uriParts): string
    {
        $path = (string) ($uriParts['path'] ?? '');
        if (isset($uriParts['query'])) {
            $path .= '?' . $uriParts['query'];
        }
        if (isset($uriParts['fragment'])) {
            $path .= '#' . $uriParts['fragment'];
        }

        return $path;
    }

    public function getFrontChannelLogoutUri(): string
    {
        return trim((string) $this->getValue($this->columnPrefix . '_frontchannel_logout_uri', 'database'));
    }

    public function isFrontChannelLogoutSessionRequired(): bool
    {
        return (bool) $this->getValue($this->columnPrefix . '_frontchannel_logout_session_required');
    }

    public function getBackChannelLogoutUri(): string
    {
        return trim((string) $this->getValue($this->columnPrefix . '_backchannel_logout_uri', 'database'));
    }

    public function isBackChannelLogoutSessionRequired(): bool
    {
        return (bool) $this->getValue($this->columnPrefix . '_backchannel_logout_session_required');
    }

    public function isConfidential(): bool
    {
        // TODO_RK
        return true;
    }
    
    /**
     * Return whether user consent may be skipped for this client.
     * @return bool
     */
    public function isTrusted(): bool
    {
        return (bool)$this->getValue($this->columnPrefix . '_trusted');
    }

    public function getFieldMappingNoDefault(): bool
    {
        return $this->getFieldMappingCatchall();
    }

    protected function adjustLogEntry(LogChanges $logEntry) :void
    {
        if ($logEntry->getValue('log_field') == $this->columnPrefix . '_client_secret') {
            $logEntry->setValue('log_value_old', '********');
            $logEntry->setValue('log_value_new', '********');
        }
    }

}
