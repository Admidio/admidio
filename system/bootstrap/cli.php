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
 * used instead of the organization configured through $g_organization. The optional variable
 * $cliConfigFile may contain the path of a configuration file that replaces adm_my_files/config.php.
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
$configFile = isset($cliConfigFile) && $cliConfigFile !== ''
    ? $cliConfigFile
    : $rootPath . '/adm_my_files/config.php';

if (!is_file($configFile)) {
    throw new RuntimeException('Admidio configuration file ' . $configFile . ' was not found.');
}

// the install commands write their configuration file to the same place the bootstrap reads it
define('ADMIDIO_CONFIG_FILE', $configFile);

require_once $rootPath . '/system/bootstrap/cli-request.php';

/*
 * Some Admidio config.php files select environment-specific database settings through HTTP_HOST.
 * A CLI process has no request host, so provide a deterministic value before loading config.php.
 */
admCliRequestVariables($rootPath, admCliRequestHost(isset($cliHost) ? $cliHost : ''));

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
admCliRequestVariablesFromUrl($rootPath, $g_root_path);

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
