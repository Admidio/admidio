<?php

use Admidio\Components\Entity\Component;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Language;
use Admidio\Organizations\Entity\Organization;

/**
 ***********************************************************************************************
 * Basic bootstrap for Admidio command-line scripts.
 *
 * This initializes the database, organization, settings and language objects required by
 * Admidio core code, but deliberately does not start a PHP/web session or initialize
 * presenters/navigation. Profile fields and the acting user are initialized lazily by the CLI.
 *
 * The optional variable $cliOrganization may contain an organization short name that should be
 * used instead of the organization configured through $g_organization.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

if (PHP_SAPI !== 'cli') {
    exit('This script may only be called from the command line!');
}

$rootPath = dirname(__DIR__, 2);
$configFile = $rootPath . '/adm_my_files/config.php';

if (!is_file($configFile)) {
    throw new RuntimeException('Admidio configuration file adm_my_files/config.php was not found.');
}

/*
 * Some Admidio config.php files select environment-specific database settings through HTTP_HOST.
 * A CLI process has no request host, so provide a deterministic value before loading config.php.
 */
$configHost = isset($cliHost) && $cliHost !== '' ? $cliHost : getenv('ADMIDIO_HOST');
if ($configHost === false || $configHost === '') {
    $configHost = 'localhost';
}

/*
 * The value ends up in HTTP_HOST, SERVER_NAME and REQUEST_URI and is therefore read by config.php
 * and by the URL constants derived in constants.php. Accept only a host name with an optional port
 * so a caller cannot smuggle a path, a scheme or a header break into those values.
 */
if (!preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*(?::\d{1,5})?$/', $configHost)
    && !preg_match('/^\[[0-9A-Fa-f:.]+](?::\d{1,5})?$/', $configHost)) {
    throw new RuntimeException(
        'The host "' . $configHost . '" is not a valid host name. --host and ADMIDIO_HOST expect '
        . 'a host name with an optional port, for example "example.org" or "example.org:8080".'
    );
}

$_SERVER['HTTP_HOST'] = $configHost;
$_SERVER['SERVER_NAME'] = $configHost;
$_SERVER['SERVER_PORT'] = 80;
$_SERVER['DOCUMENT_ROOT'] = $rootPath;
$_SERVER['SCRIPT_FILENAME'] = $rootPath . '/admidio';
$_SERVER['SCRIPT_NAME'] = '/admidio';
$_SERVER['REQUEST_URI'] = '/admidio';

require_once $configFile;

if (isset($cliOrganization) && $cliOrganization !== '') {
    $g_organization = $cliOrganization;
}

// An HTTP redirect has no meaning in a CLI process.
$gForceHTTPS = false;

/*
 * system/bootstrap/constants.php derives URL/path constants from $_SERVER. The values below are
 * only used so the existing non-database bootstrap can run in CLI mode; no HTTP request/session
 * is created.
 */
$rootUrl = parse_url($g_root_path);
$scheme = is_array($rootUrl) && isset($rootUrl['scheme']) ? $rootUrl['scheme'] : 'http';
$host = is_array($rootUrl) && isset($rootUrl['host']) ? $rootUrl['host'] : 'localhost';
$port = is_array($rootUrl) && isset($rootUrl['port'])
    ? (int)$rootUrl['port']
    : ($scheme === 'https' ? 443 : 80);
$urlPath = is_array($rootUrl) && isset($rootUrl['path']) ? rtrim($rootUrl['path'], '/') : '';

$_SERVER['SERVER_PORT'] = $port;
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host;
$_SERVER['DOCUMENT_ROOT'] = $rootPath;
$_SERVER['SCRIPT_FILENAME'] = $rootPath . '/admidio';
$_SERVER['SCRIPT_NAME'] = $urlPath . '/admidio';
$_SERVER['REQUEST_URI'] = $urlPath . '/admidio';

/*
 * Maintenance mode must be manageable even if the database is unavailable and while the regular
 * bootstrap blocks requests. Load only the constants and Composer autoloader needed by the CLI
 * and MaintenanceMode utility for that command.
 */
if (isset($cliSkipDatabase) && $cliSkipDatabase) {
    require_once $rootPath . '/system/bootstrap/init_globals.php';
    require_once $rootPath . '/system/bootstrap/constants.php';
    require_once ADMIDIO_PATH . '/vendor/autoload.php';

    $gValidLogin = false;
    return;
}

require_once $rootPath . '/system/bootstrap/bootstrap.php';

$gValidLogin = false;

$gDb = Database::createDatabaseInstance();

/*
 * The CORE component doubles as the installation probe. Reading it is needed anyway, whereas the
 * previous getTableColumns() check asked the information schema on every single invocation only to
 * find out whether Admidio is installed at all.
 */
$gSystemComponent = new Component($gDb);

try {
    $gSystemComponent->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
} catch (Throwable $exception) {
    throw new RuntimeException(
        'The Admidio database is not installed or cannot be read: ' . $exception->getMessage()
    );
}

if ((int)$gSystemComponent->getValue('com_id') === 0) {
    throw new RuntimeException('The Admidio database is not installed.');
}

if (!isset($g_organization)) {
    $g_organization = '';
}

$gCurrentOrganization = Organization::createDefaultOrganizationObject($gDb, $g_organization);
$gCurrentOrgId = (int)$gCurrentOrganization->getValue('org_id');

if ($gCurrentOrgId === 0) {
    throw new RuntimeException('The configured Admidio organization could not be found.');
}

$gSettingsManager =& $gCurrentOrganization->getSettingsManager();
$gL10n = new Language($gSettingsManager->getString('system_language'));

$gCurrentUserId = 0;
$gCurrentUserUUID = '';
