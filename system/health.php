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
 * only with "ok" (200) or "fail" (503). The reason of a failure is written to the log.
 *
 * If $gHealthToken is set in config.php (Docker: ADMIDIO_HEALTH_TOKEN), the token must be sent
 * in the header "X-Health-Token". Without or with a wrong token the endpoint answers 404.
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

// The config is loaded before the bootstrap, so that the endpoint can be called with http on localhost
// even if the installation forces https.
require_once($rootPath . '/adm_my_files/config.php');

if (empty($gHealthEndpoint)) {
    admHealthResponse(404, 'not found');
}

if (!empty($gHealthToken)) {
    $sentToken = $_SERVER['HTTP_X_HEALTH_TOKEN'] ?? '';
    if (!is_string($sentToken) || !hash_equals((string) $gHealthToken, $sentToken)) {
        admHealthResponse(404, 'not found');
    }
}

$gForceHTTPS = false;

// bootstrap answers with 503 itself if the maintenance mode is active
require_once($rootPath . '/system/bootstrap/bootstrap.php');

try {
    $gDb = Database::createDatabaseInstance();

    if ($gDb->queryPrepared('SELECT 1', array(), false) === false) {
        throw new RuntimeException('The database did not answer a simple query.');
    }

    if (!is_dir(ADMIDIO_PATH . FOLDER_DATA) || !is_writable(ADMIDIO_PATH . FOLDER_DATA)) {
        throw new RuntimeException('The data folder is not writable.');
    }
} catch (Throwable $exception) {
    $gLogger->error('HEALTH: ' . $exception->getMessage());
    admHealthResponse(503, 'fail');
}

admHealthResponse(200, 'ok');
