<?php
/**
 ***********************************************************************************************
 * Bootstrap non DB things
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'bootstrap.php')
{
    exit('This page may not be called directly!');
}

$rootPath = dirname(dirname(__DIR__));

// Add init_globals and constants file
// TODO: In future require config.php here
require_once($rootPath . '/adm_program/system/init_globals.php');
require_once($rootPath . '/adm_program/system/constants.php');

// ERROR REPORTING
// http://www.phptherightway.com/#error_reporting
// https://secure.php.net/manual/en/errorfunc.configuration.php
ini_set('error_reporting', '-1');
ini_set('log_errors', '1');

if ($gDebug)
{
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}
else
{
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// check PHP version and show notice if version is too low
if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<'))
{
    exit('<div style="color: #cc0000;">Error: Your PHP version ' . PHP_VERSION . ' does not fulfill
        the minimum requirements for this Admidio version. You need at least PHP ' . MIN_PHP_VERSION . ' or higher.</div>');
}

/**
 * includes WITHOUT database connections
 */
// Add polyfills for backwards compatibility with older PHP versions
require_once(ADMIDIO_PATH . '/adm_program/system/polyfill.php');
// Add Class autoloader
require_once(ADMIDIO_PATH . '/adm_program/system/autoload.php');
// Enable Logging
require_once(ADMIDIO_PATH . '/adm_program/system/logging.php');
// Add shutdown function
require_once(ADMIDIO_PATH . '/adm_program/system/shutdown.php');
// Add some common functions
require_once(ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/htmLawed/htmLawed.php');
require_once(ADMIDIO_PATH . '/adm_program/system/function.php');
require_once(ADMIDIO_PATH . '/adm_program/system/string.php');
// Remove HTML & PHP-Code and escape all quotes from all request parameters
// If debug is on and change is made, log it
require_once(ADMIDIO_PATH . '/adm_program/system/global_request_params.php');

// Force permanent HTTPS redirect
if ($gForceHTTPS && !HTTPS)
{
    $url = str_replace('http://', 'https://', CURRENT_URL);

    $gLogger->notice('REDIRECT: Redirecting permanent to HTTPS!', array('url' => $url, 'statusCode' => 301));

    header('Location: ' . $url, true, 301);
    exit();
}
