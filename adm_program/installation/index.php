<?php
/**
 ***********************************************************************************************
 * Redirect the user to installation or update page
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */


$rootPath = dirname(__DIR__, 2);

// check if installation is necessary
if (is_file($rootPath . '/adm_my_files/config.php')) {
    // load config and init bootstrapping
    require_once($rootPath . '/adm_my_files/config.php');
    require_once($rootPath . '/adm_program/system/bootstrap/bootstrap.php');

    // check for empty db and redirect to installation wizard
    try {
        $gDb = Database::createDatabaseInstance();
        $gDb->getTableColumns(TBL_SESSIONS);
    } catch (\Throwable $t) {
        $page = 'installation.php';
        header('Location: ' . $page);
        exit();
    }

    $page = 'update.php';
} else {
    $page = 'installation.php';
}

// redirect to installation or update page
header('Location: ' . $page);
exit();
