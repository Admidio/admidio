<?php

namespace Admidio\SSO\Service;

use LightSaml\Binding\HttpRedirectBinding;
use LightSaml\Error\LightSamlBindingException;
use LightSaml\SamlConstants;

/**
 * The HTTP-Redirect binding of LightSAML, with a limit for the inflated message.
 *
 * LightSAML calls `gzinflate()` without a limit, and the SAML endpoints are reachable without a login: a
 * few kilobytes of deflate can expand to gigabytes and exhaust the memory of PHP. A SAML message is a
 * small XML document (a signed LogoutRequest has a few kilobytes), so the inflated size is limited.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SAMLBoundedRedirectBinding extends HttpRedirectBinding
{
    /** Longest SAMLRequest/SAMLResponse parameter that is accepted, base64 encoded and deflated */
    public const MAX_ENCODED_LENGTH = 65536;

    /** Largest message after inflation */
    public const MAX_INFLATED_LENGTH = 1048576;

    /**
     * @param string $msg
     * @param string $encoding
     * @throws LightSamlBindingException
     */
    protected function decodeMessageString($msg, $encoding)
    {
        if ($encoding !== SamlConstants::ENCODING_DEFLATE) {
            throw new LightSamlBindingException(sprintf("Unknown encoding '%s'", $encoding));
        }

        $compressed = base64_decode((string) $msg, true);
        if ($compressed === false) {
            throw new LightSamlBindingException('The SAML message is not valid base64.');
        }

        // With a length PHP stops inflating at that size; a message that is longer ends up as false
        $inflated = @gzinflate($compressed, self::MAX_INFLATED_LENGTH + 1);
        if ($inflated === false || strlen($inflated) > self::MAX_INFLATED_LENGTH) {
            throw new LightSamlBindingException('The SAML message cannot be inflated or is too large.');
        }

        return $inflated;
    }
}
