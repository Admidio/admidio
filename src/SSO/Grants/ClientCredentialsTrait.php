<?php

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 */

namespace Admidio\SSO\Grants;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Reading of the client credentials that every grant of the token endpoint shares, so that a
 * client authenticates the same way for each grant type.
 */
trait ClientCredentialsTrait
{
    /**
     * WORKAROUND for clients using basic http authorization and clientIDs that 
     * contain colons... The OIDC spec (rather: basic auth spec) does not allow
     * usernames with colon, but the URL contains a colon and is used by many 
     * OpenID RPs. -> Disregard the spec if the credentials string starts with 
     * http: or https: and treat the first colon as part of the username!
     * 
     * Retrieve HTTP Basic Auth credentials with the Authorization header
     * of a request. First index of the returned array is the username,
     * second is the password (so list() will work). If the header does
     * not exist, or is otherwise an invalid HTTP Basic header, return
     * [null, null].
     *
     * @return array{0:non-empty-string,1:string}|array{0:null,1:null}
     */
    protected function getBasicAuthCredentials(ServerRequestInterface $request): array
    {
        [$username, $password] = parent::getBasicAuthCredentials($request);
        // OAuth 2.0 requires client ID and secret to be URL-encoded before the base64 encoding
        // (RFC 6749, section 2.3.1), which league does not undo. Other clients send them raw.
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

        if (($username == "http" || $username == "https") && 
            str_starts_with($password, "//") &&
            str_contains($password, ':') ) {
            [$username2, $password] = explode(':', $password, 2);
            $username = $username . ':' . $username2;
        }
        return [$username, $password];
    }
}
