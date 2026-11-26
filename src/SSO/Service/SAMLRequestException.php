<?php

namespace Admidio\SSO\Service;

use Exception;
use LightSaml\SamlConstants;

/**
 * A SAML request that cannot be served because of what the requester sent, not because of a
 * fault of Admidio (wrong destination, expired or invalid signature, unknown principal, ...).
 *
 * The SAML endpoints answer it with the status the exception carries (default `Requester`)
 * instead of the generic `Responder`, which stands for an error on the side of the identity
 * provider (SAML Core 3.2.2.2).
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SAMLRequestException extends Exception
{
    /**
     * @param string $message Description for the log and the status message of the response
     * @param string[] $status Top-level status code, optionally followed by the second-level one
     */
    public function __construct(string $message, private readonly array $status = [SamlConstants::STATUS_REQUESTER])
    {
        parent::__construct($message);
    }

    /**
     * @return string[] Top-level status code, optionally followed by the second-level one
     */
    public function getStatus(): array
    {
        return $this->status;
    }
}
