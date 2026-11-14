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
     * Retrieve HTTP Basic Auth credentials with the Authorization header of a request, with
     * the client ID and secret interpreted as described in
     * {@see ClientCredentials::normalizeBasicCredentials()}. First index of the returned array
     * is the username, second is the password (so list() will work). If the header does not
     * exist, or is otherwise an invalid HTTP Basic header, return [null, null].
     *
     * @return array{0:non-empty-string,1:string}|array{0:null,1:null}
     */
    protected function getBasicAuthCredentials(ServerRequestInterface $request): array
    {
        [$username, $password] = parent::getBasicAuthCredentials($request);

        return ClientCredentials::normalizeBasicCredentials($username, $password);
    }
}
