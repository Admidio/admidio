<?php

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Language;

/**
 ***********************************************************************************************
 * Bootstrap for the Admidio command-line commands that install a new Admidio database.
 *
 * install:check and install:run run before there is an installation: normally there is no
 * adm_my_files/config.php, and there is never a database schema, an organization, a settings
 * manager or an acting user. This bootstrap therefore only initializes the constants, the
 * autoloader, the logging and the language, and it never connects to a database.
 *
 * Admidio derives the table names, the time zone and its URL from the configuration file while it
 * is bootstrapping, long before a command is dispatched. If no configuration file exists, those
 * values are taken from the command line instead, and the install commands verify that what they
 * received matches what was bootstrapped here.
 *
 * The following variables are read if the calling script has set them: $cliHost, $cliRootUrl,
 * $cliLanguage, $cliTablePrefix, $cliDbType and $cliTimezone.
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

require_once $rootPath . '/system/bootstrap/cli-request.php';

admCliRequestVariables($rootPath, admCliRequestHost(isset($cliHost) ? $cliHost : ''));

if (is_file($configFile)) {
    // an existing configuration file wins, the new installation has to use the database it defines
    require_once $configFile;
} else {
    if (isset($cliTablePrefix) && $cliTablePrefix !== '') {
        $g_tbl_praefix = $cliTablePrefix;
    }
    if (isset($cliDbType) && $cliDbType !== '') {
        $gDbType = $cliDbType;
    }
    if (isset($cliTimezone) && $cliTimezone !== '') {
        $gTimezone = $cliTimezone;
    }
    if (isset($cliRootUrl) && $cliRootUrl !== '') {
        $g_root_path = rtrim($cliRootUrl, '/');
    }
}

if (!isset($g_root_path) || $g_root_path === '') {
    $g_root_path = 'http://localhost';
}

// An HTTP redirect has no meaning in a CLI process.
$gForceHTTPS = false;

/*
 * ADMIDIO_URL and the other URL constants have to describe the installation that is created, not
 * the machine that the command runs on.
 */
admCliRequestVariablesFromUrl($rootPath, $g_root_path);

require_once $rootPath . '/system/bootstrap/bootstrap.php';

$gL10n = new Language(isset($cliLanguage) && $cliLanguage !== '' ? $cliLanguage : Language::REFERENCE_LANGUAGE);

/*
 * Everything that the installation creates belongs to the new installation and not into its
 * changelog. Beside that the tables of the changelog only exist after the schema was created.
 */
Entity::setLoggingEnabled(false);

$gValidLogin = false;
$gCurrentUserId = 0;
$gCurrentUserUUID = '';
