<?php
/**
 ***********************************************************************************************
 * Bootstrap non DB things
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'bootstrap.php') {
    exit('This page may not be called directly!');
}

$rootPath = dirname(__DIR__, 2);

// Add init_globals and constants file
// TODO: In future require config.php here
require_once($rootPath . '/system/bootstrap/init_globals.php');
require_once($rootPath . '/system/bootstrap/constants.php');

// ERROR REPORTING
// http://www.phptherightway.com/#error_reporting
// https://www.php.net/manual/en/errorfunc.configuration.php
ini_set('error_reporting', '-1');
ini_set('log_errors', '1');

if ($gDebug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// check PHP version and show notice if version is too low
if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    exit('<div style="color: #cc0000;">Error: Your PHP version ' . PHP_VERSION . ' does not fulfill
        the minimum requirements for this Admidio version. You need at least PHP ' . MIN_PHP_VERSION . ' or higher.</div>');
}

// Check maintenance mode before loading the autoloader or other application files.
// The maintenance.json file optionally contains a list of allowed script filenames that are still executed.
// This keeps maintenance mode active even if core files are temporarily unavailable or inconsistent.
$maintenanceFile = ADMIDIO_PATH . FOLDER_DATA . '/maintenance.json';

if (is_file($maintenanceFile)) {
    $maintenanceJson = file_get_contents($maintenanceFile);
    $maintenanceState = is_string($maintenanceJson) ? json_decode($maintenanceJson, true) : null;

    $currentScript = realpath($_SERVER['SCRIPT_FILENAME']);
    if ($currentScript === false) {
        $currentScript = $_SERVER['SCRIPT_FILENAME'];
    }

    $currentScript = str_replace('\\', '/', $currentScript);
    $admidioPath = rtrim(str_replace('\\', '/', ADMIDIO_PATH), '/');
    $relativeScript = '';

    if (str_starts_with($currentScript, $admidioPath . '/')) {
        $relativeScript = substr($currentScript, strlen($admidioPath) + 1);
    }

    $allowedScripts = is_array($maintenanceState)
        && isset($maintenanceState['allowedScripts'])
        && is_array($maintenanceState['allowedScripts'])
        ? $maintenanceState['allowedScripts']
        : array();

    if (!in_array($relativeScript, $allowedScripts, true)) {
        $retryAfter = is_array($maintenanceState) && isset($maintenanceState['retryAfter'])
            ? max(1, (int) $maintenanceState['retryAfter'])
            : 120;
        $title = is_array($maintenanceState) && isset($maintenanceState['title']) && is_string($maintenanceState['title'])
            ? $maintenanceState['title']
            : 'Maintenance';
        $message = is_array($maintenanceState) && isset($maintenanceState['message']) && is_string($maintenanceState['message'])
            ? $maintenanceState['message']
            : 'This Admidio installation is currently in maintenance mode. Please try again later.';

        http_response_code(503);
        header('Retry-After: ' . $retryAfter);
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        exit('<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex,nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . $title . '</title>
</head>
<body>
    <main style="max-width: 48rem; margin: 10vh auto; padding: 2rem; font-family: sans-serif;">
        <h1>' . $title . '</h1>
        <p style="white-space: pre-line;">' . $message . '</p>
    </main>
</body>
</html>');
    }
}

/**
 * includes WITHOUT database connections
 */
// Add Class autoloader
require_once(ADMIDIO_PATH . '/vendor/autoload.php');
// Enable Logging
require_once(ADMIDIO_PATH . FOLDER_SYSTEM . '/bootstrap/logging.php');
// Add shutdown function
require_once(ADMIDIO_PATH . FOLDER_SYSTEM . '/bootstrap/shutdown.php');
// Add some common functions
require_once(ADMIDIO_PATH . FOLDER_SYSTEM . '/bootstrap/function.php');
// Remove HTML & PHP-Code and escape all quotes from all request parameters
// If debug is on and change is made, log it
require_once(ADMIDIO_PATH . FOLDER_SYSTEM . '/bootstrap/global_request_params.php');

// Force permanent HTTPS redirect
if ($gForceHTTPS && !HTTPS) {
    $url = str_replace('http://', 'https://', CURRENT_URL);

    $gLogger->notice('REDIRECT: Redirecting permanent to HTTPS!', array('url' => $url, 'statusCode' => 301));

    header('Location: ' . $url, true, 301);
    exit();
}
