<?php

declare(strict_types=1);

namespace AdmidioCoreUpdater;

use RuntimeException;
use Throwable;

/**
 * An error the updater reports to the administrator.
 *
 * The message is either a text key of the updater (SYS_CORE_UPDATE_*) with its parameters, which
 * the page translates with Texts, or an English diagnostic for a situation in which no texts can
 * be read yet - before the configuration is loaded, for example.
 *
 * A text key tells the administrator what happened in plain words. Why exactly - which entry of an
 * archive, which field of a descriptor - is the English **detail**, which goes into the log and the
 * command line output.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class UpdaterException extends RuntimeException
{
    /**
     * @param string $message A text key (SYS_CORE_UPDATE_*) or an English diagnostic.
     * @param array<int,string> $params The parameters of a text key.
     * @param Throwable|null $previous
     * @param string $detail An English diagnostic for the log.
     */
    public function __construct(
        string $message,
        private readonly array $params = array(),
        ?Throwable $previous = null,
        private readonly string $detail = ''
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * A failure with a text key for the administrator and an English diagnostic for the log.
     * @param string $key
     * @param string $detail
     * @param array<int,string> $params
     * @return self
     */
    public static function withDetail(string $key, string $detail, array $params = array()): self
    {
        return new self($key, $params, null, $detail);
    }

    /**
     * The English diagnostic, or an empty string.
     * @return string
     */
    public function getDetail(): string
    {
        return $this->detail;
    }

    /**
     * @return array<int,string>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * The message for the administrator, translated if it is a text key.
     * @param Texts|null $texts
     * @return string
     */
    public function getText(?Texts $texts): string
    {
        return ($texts !== null && Texts::isKey($this->getMessage()))
            ? $texts->get($this->getMessage(), $this->params)
            : $this->getMessage();
    }
}
