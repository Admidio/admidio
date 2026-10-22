<?php
/**
 ***********************************************************************************************
 * phpinfo
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;

require_once(__DIR__ . '/common.php');
require(__DIR__ . '/login_valid.php');

try {
    // only administrators are allowed to view phpinfo
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // show php info page
    phpinfo();
} catch (Throwable $e) {
    handleException($e);
}
