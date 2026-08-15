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

    public function isPostLogoutRedirectUriAllowed(string $uri): bool
    {
        return in_array($uri, $this->getPostLogoutRedirectUris(), true);

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

    protected function adjustLogEntry(LogChanges $logEntry)
    {
        if ($logEntry->getValue('log_field') == $this->columnPrefix . '_client_secret') {
            $logEntry->setValue('log_value_old', '********');
            $logEntry->setValue('log_value_new', '********');
        }
    }

}
