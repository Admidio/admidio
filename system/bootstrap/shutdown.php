<?php
/**
 ***********************************************************************************************
 * Admidio Shutdown
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Utils\FileSystemUtils;

if (basename($_SERVER['SCRIPT_FILENAME']) === 'shutdown.php') {
    exit('This page may not be called directly!');
}

function admShutdown()
{
    global $gLogger, $gDb;

    // A transaction that is still open here never reaches the database: PDO rolls it back when the
    // connection closes. Admidio itself calls rollback() nowhere, an exception simply leaves the
    // transaction behind, so this is where the work that was waiting for that commit is told that
    // it will not come.
    if ($gDb instanceof Database && $gDb->isInTransaction()) {
        $gDb->runAfterRollbackCallbacks();
    }

    $gLogger->info('SHUTDOWN', array(
        'execution_time' => getExecutionTime(SCRIPT_START_TIME),
        'memory_usage' => FileSystemUtils::getHumanReadableBytes(memory_get_peak_usage())
    ));
}

register_shutdown_function('admShutdown');
