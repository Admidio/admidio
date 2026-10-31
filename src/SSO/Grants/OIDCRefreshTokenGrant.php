<?php

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 */

namespace Admidio\SSO\Grants;

use League\OAuth2\Server\Grant\RefreshTokenGrant;

/**
 * Refresh token grant that authenticates the client the same way as the authorization code
 * grant, so that a client with a URL as client ID can also refresh its tokens.
 */
class OIDCRefreshTokenGrant extends RefreshTokenGrant
{
    use ClientCredentialsTrait;
}
