<?php
/**
 * The bootstrap constants of a test run, in one place.
 *
 * Admidio reads these as bare constants, so the first definition in the process wins and every
 * later one is ignored. composer test:all runs the unit, the integration and the CLI suite in one
 * process, which means a unit test that invents a value of its own decides what the integration
 * run gets - silently, because a constant that is already defined is not an error. Everything that
 * both a unit test case and tests/bootstrap-admidio.php need is therefore defined here and nowhere
 * else.
 *
 * The file is safe to require more than once and from either side.
 */

require_once __DIR__ . '/env.php';

// FOLDER_DATA has to name the directory of the test run. The environment is loaded here so that a
// unit test that runs before the Admidio bootstrap arrives at the same value it would; without the
// file the defaults of env.php apply, which is what a run without .env.test gets anyway.
$admidioTestEnvFile = dirname(__DIR__) . '/.env.test';
if (is_file($admidioTestEnvFile)) {
    admidioTestLoadEnvironment($admidioTestEnvFile);
}
unset($admidioTestEnvFile);

$admidioTestRoot = dirname(__DIR__);

$admidioTestConstants = array(
    'MIN_PHP_VERSION' => '8.2.0',
    'ADMIDIO_VERSION_MAIN' => 5,
    'ADMIDIO_VERSION_MINOR' => 1,
    'ADMIDIO_VERSION_PATCH' => 0,
    'ADMIDIO_VERSION_BETA' => 0,
    'ADMIDIO_VERSION' => '5.1.0',
    'ADMIDIO_VERSION_TEXT' => '5.1.0',
    'ADMIDIO_HOMEPAGE' => 'https://www.admidio.org/',
    'TABLE_PREFIX' => 'adm',
    'FOLDER_SYSTEM' => '/system',
    'FOLDER_INSTALLATION' => '/install',
    'FOLDER_LIBS' => '/libs',
    'FOLDER_THEMES' => '/themes',
    'FOLDER_MODULES' => '/modules',
    'FOLDER_LANGUAGES' => '/languages',
    'FOLDER_PLUGINS' => '/plugins',
    'ADMIDIO_PATH' => $admidioTestRoot,
    'DATE_MAX' => '9999-12-31',
    'DATE_NOW' => date('Y-m-d'),
    'DATETIME_NOW' => date('Y-m-d H:i:s'),
    'SCRIPT_START_TIME' => microtime(true),
    'DOMAIN' => 'admidio.test',
    'HOST' => 'admidio.test',
    // An installation that serves OIDC has to be reachable over HTTPS, so the test environment
    // describes one: the issuer URL of the default preferences is derived from ADMIDIO_URL and is
    // rejected by OIDCService::assertValidIssuerURL() when it is not an HTTPS URL.
    'SCHEME' => 'https',
    'ADMIDIO_URL' => 'https://admidio.test',
    'ADMIDIO_URL_PATH' => ''
);
// Derived from the scheme exactly as system/bootstrap/constants.php does it, so that code which
// branches on the transport sees the same installation that ADMIDIO_URL describes.
$admidioTestConstants['HTTPS'] = $admidioTestConstants['SCHEME'] === 'https';

foreach ($admidioTestConstants as $admidioTestName => $admidioTestValue) {
    if (!defined($admidioTestName)) {
        define($admidioTestName, $admidioTestValue);
    }
}
unset($admidioTestConstants, $admidioTestName, $admidioTestValue);

// Installation::install() creates ecard_templates, logs, mail_templates and temp below this folder,
// so it has to be the directory of the test run and not the adm_my_files of the checkout, which on
// a developer machine is a real installation.
if (!defined('FOLDER_DATA')) {
    define('FOLDER_DATA', admidioTestDataFolder($admidioTestRoot));
}
if (!defined('FOLDER_TEMP_DATA')) {
    define('FOLDER_TEMP_DATA', FOLDER_DATA . '/temp');
}

unset($admidioTestRoot);
