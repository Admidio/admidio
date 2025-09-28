<?php
/**
 ***********************************************************************************************
 * Edit data of database
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Database;

require_once(__DIR__ . '/../adm_my_files/config.php');
require_once(__DIR__ . '/DatabaseDateTimeEdit.php');

if (!isset($gDb)) {
    // connect to database
    try {
        $gDb = Database::createDatabaseInstance();
    } catch (Exception $e) {
        exit('<br />' . $gL10n->get('SYS_DATABASE_NO_LOGIN', array($e->getMessage())));
    }
}

$databaseDateTimeEdit = new DatabaseDateTimeEdit($gDb);
$databaseDateTimeEdit->updateDateTimeField(TBL_ANNOUNCEMENTS, 'ann_timestamp_create', 0, 15, true);
$databaseDateTimeEdit->updateTwoRelativeDateTimeField(TBL_EVENTS, 'dat_begin', 'dat_end', 0, 60);
$databaseDateTimeEdit->updateBirthdays();
$databaseDateTimeEdit->updateDateTimeField(TBL_REGISTRATIONS, 'reg_timestamp', 0, 3);

if ($gDbType === 'pgsql') {
    $sql = 'UPDATE ' . TBL_ROLES . ' r
               SET rol_name = TO_CHAR(e.dat_begin, \'YYYY-MM-DD HH24:MI\') || \' \' || e.dat_headline
              FROM ' . TBL_EVENTS . ' e
             WHERE r.dat_rol_id = e.rol_id
               AND r.dat_rol_id IS NOT NULL ';
} else {
    $sql = 'UPDATE ' . TBL_ROLES . '
          JOIN ' . TBL_EVENTS . ' ON dat_rol_id = rol_id
           SET rol_name = CONCAT(DATE_FORMAT(dat_begin, \'%Y-%m-%d %H:%i\'), \' \', dat_headline)
         WHERE dat_rol_id IS NOT NULL ';
}
$gDb->queryPrepared($sql);
