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


use Admidio\Infrastructure\Database;

$rootPath = dirname(__DIR__);

// check if installation is necessary
if (is_file($rootPath . '/adm_my_files/config.php')) {
    // load config and init bootstrapping
    require_once($rootPath . '/adm_my_files/config.php');
    require_once($rootPath . '/system/bootstrap/bootstrap.php');

    // check for empty db and redirect to installation wizard
    try {
        $gDb = Database::createDatabaseInstance();
        if (empty($gDb->getTableColumns(TBL_SESSIONS))) {
            // Postgres does not throw an error, but returns an empty array if the session table does not yet exist.
            header('Location: installation.php');
            exit();
        }
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
