<?php

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 */

namespace Admidio\SSO\Grants;

/**
 * Interpretation of the client ID and secret of an HTTP Basic header, shared by the grants of
 * the token endpoint and by the introspection and revocation endpoints, so that a client
 * authenticates the same way everywhere.
 */
final class ClientCredentials
{
    /**
     * Undo the URL encoding of client ID and secret and tolerate raw URLs as client ID.
     *
     * OAuth 2.0 requires client ID and secret to be URL-encoded before the base64 encoding
     * (RFC 6749, section 2.3.1), which league does not undo. Other clients send them raw.
     *
     * WORKAROUND for clients using basic http authorization and clientIDs that contain
     * colons: the basic auth spec does not allow usernames with a colon, but a URL contains
     * one and is used as client ID by many OpenID RPs. The spec is disregarded if the
     * credentials start with http: or https: and the first colon is treated as part of the
     * username.
     *
     * @param string|null $username The part before the first colon of the decoded header
     * @param string|null $password The part after the first colon of the decoded header
     * @return array{0:string|null,1:string|null} Client ID and secret
     */
    public static function normalizeBasicCredentials(?string $username, ?string $password): array
    {
        if (!empty($username)) {
            $decoded = urldecode($username);
            if (urlencode($decoded) == $username) {
                $username = $decoded;
            }
        }
        if (!empty($password)) {
            $decoded = urldecode($password);
            if (urlencode($decoded) == $password) {
                $password = $decoded;
            }
        }

        if (($username == 'http' || $username == 'https')
            && is_string($password)
            && str_starts_with($password, '//')
            && str_contains($password, ':')
        ) {
            [$username2, $password] = explode(':', $password, 2);
            $username = $username . ':' . $username2;
        }

        return [$username, $password];
    }
}
