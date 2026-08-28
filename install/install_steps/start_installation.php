<?php
/**
 ***********************************************************************************************
 * Installation step: start_installation
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;

if (basename($_SERVER['SCRIPT_FILENAME']) === 'start_installation.php') {
    exit('This page may not be called directly!');
}

// Check if configuration file exists. This file must be copied to the base folder of the Admidio installation.
if (!is_file($configPath)) {
    throw new Exception('INS_CONFIGURATION_FILE_NOT_FOUND', array('config.php'));
}

// first check if session is filled (if installation was aborted then this is not filled)
// if previous dialogs were filled then check if the settings are equal to config file
if (isset($_SESSION['table_prefix'])
    && ($_SESSION['db_type'] !== DB_TYPE
        || $_SESSION['db_host'] !== DB_HOST
        || $_SESSION['db_port'] !== InstallationConfig::normalizePort(DB_PORT)
        || $_SESSION['db_name'] !== DB_NAME
        || $_SESSION['db_username'] !== DB_USERNAME
        || $_SESSION['db_password'] !== DB_PASSWORD
        || $_SESSION['table_prefix'] !== TABLE_PREFIX)) {
    throw new Exception('INS_DATA_DO_NOT_MATCH', array('config.php'));
}

// create the database and all its data with the values that were collected by the wizard
Installation::install($db, InstallationConfig::fromInstallerSession($_SESSION, ADMIDIO_URL));

// delete session data
session_unset();
session_destroy();

echo json_encode(array(
    'status' => 'success',
    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_INSTALLATION . '/installation.php', array('step' => 'installation_successful'))));
exit();
