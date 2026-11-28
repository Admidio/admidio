<?php
/**
 ***********************************************************************************************
 * Health endpoint for the Docker healthcheck and for external monitors.
 *
 * The endpoint is always available, needs no settings and answers with a short text:
 *
 * | Condition                                                    | Answer                     |
 * |--------------------------------------------------------------|----------------------------|
 * | no config.php                                                | 200 not installed          |
 * | maintenance mode (adm_my_files/maintenance.json exists)      | 200 maintenance            |
 * | connection to the database or a simple query fails           | 503 fail                   |
 * | the tables of Admidio don't exist                            | 200 not installed          |
 * | data folder is not writable                                  | 503 fail                   |
 * | version of the database differs from the file version, or    | 200 update required        |
 * | the last update was not completed                            |                            |
 * | otherwise                                                    | 200 ok                     |
 *
 * Only "PHP, database and data folder work" is reported as healthy. States that need an administrator
 * in the browser (installation, update) stay "200", otherwise an orchestrator would not route traffic to
 * the container and the administrator could not reach the installation or update page. A monitor can
 * alert on the text "update required".
 *
 * The script starts no session, sets no cookie and writes nothing to the database, so a monitor that
 * calls it every few seconds doesn't show up as "visitors online". It deliberately doesn't load the
 * complete bootstrap (logger, request parameter handling, shutdown handler), so a probe stays cheap.
 * The reason of a failure is written to the PHP error log, not to the Admidio log, because that one is
 * written into the data folder that is checked here.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Database;

/**
 * Sends the short answer of the endpoint and ends the script.
 * @param int $statusCode HTTP status code
 * @param string $body Text of the answer
 */
function admHealthResponse(int $statusCode, string $body): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Robots-Tag: noindex');
    echo $body;
    exit();
}

$rootPath = dirname(__DIR__);

if (!is_file($rootPath . '/adm_my_files/config.php')) {
    admHealthResponse(200, 'not installed');
}

require_once($rootPath . '/adm_my_files/config.php');

// Only the files that are needed for the check are loaded, not the complete bootstrap.
require_once($rootPath . '/system/bootstrap/init_globals.php');
require_once($rootPath . '/system/bootstrap/constants.php');

// A planned maintenance (e.g. a core update) is not a failure. See bootstrap.php for the file.
if (is_file(ADMIDIO_PATH . FOLDER_DATA . '/maintenance.json')) {
    admHealthResponse(200, 'maintenance');
}

try {
    require_once(ADMIDIO_PATH . '/vendor/autoload.php');
    // helper functions that the database layer uses (only function definitions, no side effects)
    require_once(ADMIDIO_PATH . FOLDER_SYSTEM . '/bootstrap/function.php');

    // The database layer writes to $gLogger, but the real logger would write into the data folder
    $gLogger = new \Psr\Log\NullLogger();
    $gDb = Database::createDatabaseInstance();

    if ($gDb->queryPrepared('SELECT 1', array(), false) === false) {
        throw new RuntimeException('The database did not answer a simple query.');
    }
} catch (Throwable $exception) {
    error_log('Admidio HEALTH: ' . $exception->getMessage());
    admHealthResponse(503, 'fail');
}

// The same check as in common.php: an empty database is not installed yet. MySQL throws an exception if the
// table doesn't exist, PostgreSQL returns an empty array.
try {
    $tablesExist = !empty($gDb->getTableColumns(TBL_SESSIONS));
} catch (Throwable $exception) {
    $tablesExist = false;
}
if (!$tablesExist) {
    admHealthResponse(200, 'not installed');
}

try {
    if (!is_dir(ADMIDIO_PATH . FOLDER_DATA) || !is_writable(ADMIDIO_PATH . FOLDER_DATA)) {
        throw new RuntimeException('The data folder is not writable.');
    }

    // The version is read directly, Component::checkDatabaseVersion() needs the language object and writes to the log.
    $statement = $gDb->queryPrepared(
        'SELECT com_version, com_beta, com_update_completed
           FROM ' . TBL_COMPONENTS . '
          WHERE com_type = ?
            AND com_name_intern = ?',
        array('SYSTEM', 'CORE')
    );
    $component = $statement->fetch();
} catch (Throwable $exception) {
    error_log('Admidio HEALTH: ' . $exception->getMessage());
    admHealthResponse(503, 'fail');
}

if (!is_array($component)) {
    // the tables exist, but the system component is missing: the installation or an update was not finished
    admHealthResponse(200, 'update required');
}

$databaseVersion = $component['com_version'] . ((int) $component['com_beta'] > 0 ? '-Beta.' . (int) $component['com_beta'] : '');
$fileVersion = ADMIDIO_VERSION . (ADMIDIO_VERSION_BETA > 0 ? '-Beta.' . ADMIDIO_VERSION_BETA : '');
$updateCompleted = in_array($component['com_update_completed'], array(true, 1, '1', 't', 'true'), true);

if (!$updateCompleted || version_compare($databaseVersion, $fileVersion) !== 0) {
    admHealthResponse(200, 'update required');
}

admHealthResponse(200, 'ok');
