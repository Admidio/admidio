<?php

namespace Admidio\SSO\Service;

use Exception;

/**
 * A SAML message that exceeds the size an endpoint accepts. It is answered with HTTP 413.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SAMLMessageTooLargeException extends Exception
{
}
