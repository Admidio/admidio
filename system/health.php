<?php
/**
 ***********************************************************************************************
 * Optional health endpoint for the Docker healthcheck and external monitors.
 *
 * The endpoint is disabled by default and answers 404 until $gHealthEndpoint = true is set in
 * config.php (Docker: ADMIDIO_HEALTH_ENDPOINT=1). It does not start a session, does not set a
 * cookie and writes nothing to the database, so a monitor that calls it every few seconds
 * doesn't show up as "visitors online".
 *
 * It checks the connection to the database and the write access to the data folder and answers
 * only with "ok" (200) or "fail" (503). The reason of a failure is written to the PHP error log
 * (not to the Admidio log, because that one is written into the data folder that is checked here).
 * While the maintenance mode is active (adm_my_files/maintenance.json exists, e.g. during an update)
 * the endpoint answers 200 "maintenance", so that an orchestrator doesn't restart the container
 * in the middle of an update.
 *
 * If $gHealthToken is set in config.php (Docker: ADMIDIO_HEALTH_TOKEN), the token must be sent
 * in the header "X-Health-Token". Without or with a wrong token the endpoint answers 404.
 * If $gForceHTTPS is set, requests that don't come from the loopback address are redirected to
 * https before the token is read.
 *
 * The script deliberately doesn't load the complete bootstrap (logger, request parameter handling,
 * shutdown handler), so a probe every few seconds stays cheap and doesn't flood the log.
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
    admHealthResponse(404, 'not found');
}

require_once($rootPath . '/adm_my_files/config.php');

if (empty($gHealthEndpoint)) {
    admHealthResponse(404, 'not found');
}

// Only the files that are needed for the check are loaded, not the complete bootstrap.
require_once($rootPath . '/system/bootstrap/init_globals.php');
require_once($rootPath . '/system/bootstrap/constants.php');

// Force permanent HTTPS redirect, but not for requests from the machine itself (Docker healthcheck on
// http://localhost). The check is done before the token is read, so the token is never sent over http
// to a public address.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
if ($gForceHTTPS && !HTTPS && !in_array($remoteAddress, array('127.0.0.1', '::1'), true)) {
    header('Location: ' . str_replace('http://', 'https://', CURRENT_URL), true, 301);
    exit();
}

if (!empty($gHealthToken)) {
    $sentToken = $_SERVER['HTTP_X_HEALTH_TOKEN'] ?? '';
    if (!is_string($sentToken) || !hash_equals((string) $gHealthToken, $sentToken)) {
        admHealthResponse(404, 'not found');
    }
}

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

    if (!is_dir(ADMIDIO_PATH . FOLDER_DATA) || !is_writable(ADMIDIO_PATH . FOLDER_DATA)) {
        throw new RuntimeException('The data folder is not writable.');
    }
} catch (Throwable $exception) {
    error_log('Admidio HEALTH: ' . $exception->getMessage());
    admHealthResponse(503, 'fail');
}

admHealthResponse(200, 'ok');
