<?php
namespace Admidio\Infrastructure\Cli;

use RuntimeException;

/**
 ***********************************************************************************************
 * The command line refused a command because of adm_my_files/cli-config.php.
 *
 * The exception exists so that a refusal is reported as a rejected operation (exit code 5) and not
 * as a failed one, and so that it can be raised before the Admidio bootstrap has run, where
 * Admidio\Infrastructure\Exception cannot translate a message yet.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class CliAccessDeniedException extends RuntimeException
{
}
