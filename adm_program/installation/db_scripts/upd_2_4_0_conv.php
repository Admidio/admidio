<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.4.0
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(ADMIDIO_PATH . FOLDER_CLASSES . '/tableusers.php');

// drop foreign keys to delete index
if($gDbType === 'mysql')
{
    $sql = 'ALTER TABLE '.TBL_USERS.' DROP FOREIGN KEY '.$g_tbl_praefix.'_FK_USR_ORG_REG';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_MEMBERS.' DROP FOREIGN KEY '.$g_tbl_praefix.'_FK_MEM_ROL';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_MEMBERS.' DROP FOREIGN KEY '.$g_tbl_praefix.'_FK_MEM_USR';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_MEMBERS.' DROP INDEX IDX_MEM_ROL_USR_ID';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.$g_tbl_praefix.'_folder_roles DROP INDEX FLR_FOL_FK';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.$g_tbl_praefix.'_folder_roles DROP INDEX FLR_ROL_FK';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_ROLE_DEPENDENCIES.' DROP INDEX RLD_ROL_PARENT_FK';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_PREFERENCES.' DROP INDEX PRF_ORG_FK';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_MEMBERS.' DROP INDEX MEM_ROL_FK';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_USER_DATA.' DROP INDEX USD_USR_FK';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_SESSIONS.' MODIFY COLUMN ses_session_id varchar(255) NOT NULL';
    $gDb->query($sql, false);
    $sql = 'UPDATE '.TBL_ROLES.' SET rol_default_registration = 1
             WHERE rol_id IN (SELECT CAST(prf_value AS unsigned integer)
                                FROM '.TBL_PREFERENCES.' WHERE prf_name = \'profile_default_role\' )';
    $gDb->query($sql, false);
    $sql = 'DELETE FROM '.TBL_PREFERENCES.' WHERE prf_name = \'profile_default_role\'';
    $gDb->query($sql, false);

}
else
{
    $sql = 'ALTER TABLE '.TBL_USERS.' DROP CONSTRAINT '.$g_tbl_praefix.'_FK_USR_ORG_REG';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_MEMBERS.' DROP CONSTRAINT '.$g_tbl_praefix.'_FK_MEM_ROL';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_MEMBERS.' DROP CONSTRAINT '.$g_tbl_praefix.'_FK_MEM_USR';
    $gDb->query($sql, false);
    $sql = 'DROP INDEX IDX_MEM_ROL_USR_ID';
    $gDb->query($sql, false);
    $sql = 'DROP INDEX FLR_FOL_FK';
    $gDb->query($sql, false);
    $sql = 'DROP INDEX FLR_ROL_FK';
    $gDb->query($sql, false);
    $sql = 'DROP INDEX RLD_ROL_PARENT_FK';
    $gDb->query($sql, false);
    $sql = 'DROP INDEX PRF_ORG_FK';
    $gDb->query($sql, false);
    $sql = 'DROP INDEX MEM_ROL_FK';
    $gDb->query($sql, false);
    $sql = 'DROP INDEX USD_USR_FK';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_SESSIONS.' ALTER COLUMN ses_session_id SET NOT NULL';
    $gDb->query($sql, false);
    $sql = 'UPDATE '.TBL_ROLES.' SET rol_default_registration = 1
             WHERE rol_id IN (SELECT CAST(prf_value AS integer)
                                FROM '.TBL_PREFERENCES.'
                               WHERE prf_name = \'profile_default_role\' )';
    $gDb->query($sql, false);
    $sql = 'DELETE FROM '.TBL_PREFERENCES.' WHERE prf_name = \'profile_default_role\'';
    $gDb->query($sql, false);
}

$sql = 'ALTER TABLE '.TBL_USERS.' DROP COLUMN usr_reg_org_shortname';
$gDb->query($sql, false);

// create foreign keys and new index
$sql = 'ALTER TABLE '.$g_tbl_praefix.'_members ADD CONSTRAINT '.$g_tbl_praefix.'_FK_MEM_ROL FOREIGN KEY (mem_rol_id)
      REFERENCES '.$g_tbl_praefix.'_roles (rol_id) ON DELETE RESTRICT ON UPDATE RESTRICT';
$gDb->query($sql, false);
$sql = 'ALTER TABLE '.$g_tbl_praefix.'_members ADD CONSTRAINT '.$g_tbl_praefix.'_FK_MEM_USR FOREIGN KEY (mem_usr_id)
      REFERENCES '.$g_tbl_praefix.'_users (usr_id) ON DELETE RESTRICT ON UPDATE RESTRICT';
$gDb->query($sql, false);

$sql = 'CREATE INDEX IDX_'.$g_tbl_praefix.'_MEM_ROL_USR_ID ON '.TBL_MEMBERS.' (mem_rol_id, mem_usr_id)';
$gDb->query($sql);

$sql = 'UPDATE '.TBL_ROLES.' SET rol_webmaster = 1
         WHERE rol_name = \''.$gL10n->get('SYS_ADMINISTRATOR').'\' ';
$gDb->query($sql);

 // convert <br /> to a normal line feed
$emailText = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/', chr(13).chr(10), $gL10n->get('SYS_SYSMAIL_REFUSE_REGISTRATION'));

// create new system user
$systemUser = new TableAccess($gDb, TBL_USERS, 'usr');
$systemUser->setValue('usr_login_name', $gL10n->get('SYS_SYSTEM'));
$systemUser->setValue('usr_valid', '0');
$systemUser->setValue('usr_timestamp_create', DATETIME_NOW);
$systemUser->save(false); // no registered user -> UserIdCreate couldn't be filled

$sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name_intern = \'LAST_NAME\'';
$pdoStatement = $gDb->query($sql);
$usfRow = $pdoStatement->fetch();

$sql = 'INSERT INTO '.TBL_USER_DATA.' (usd_usf_id, usd_usr_id, usd_value)
             VALUES ('.$usfRow['usf_id'].', '.$systemUser->getValue('usr_id').', \''.$gL10n->get('SYS_SYSTEM').'\')';
$gDb->query($sql);

$sql = 'UPDATE '.TBL_MEMBERS.' SET mem_usr_id_create = '. $systemUser->getValue('usr_id'). '
                                 , mem_timestamp_create = \''.DATETIME_NOW.'\'';
$gDb->query($sql);

$sql = 'UPDATE '.TBL_MEMBERS.' SET mem_usr_id_create = '. $systemUser->getValue('usr_id'). '
                                 , mem_timestamp_create = \''.DATETIME_NOW.'\'';
$gDb->query($sql);

// write data for every organization
$sql = 'SELECT * FROM '.TBL_ORGANIZATIONS.' ORDER BY org_id DESC';
$orgaStatement = $gDb->query($sql);

while($rowOrga = $orgaStatement->fetch())
{
    $sql = 'INSERT INTO '.TBL_TEXTS.' (txt_org_id, txt_name, txt_text)
                 VALUES ('.$rowOrga['org_id'].', \'SYSMAIL_REFUSE_REGISTRATION\', \''.$emailText.'\')';
    $gDb->query($sql);
}

// Make all Profilefilds deletable, except FIRSTNAME, LASTNAME, EMAIL
$sql = 'UPDATE '.TBL_USER_FIELDS.'
           SET usf_system = 0
         WHERE usf_name LIKE \'SYS_ADDRESS\'
            OR usf_name LIKE \'SYS_POSTCODE\'
            OR usf_name LIKE \'SYS_CITY\'
            OR usf_name LIKE \'SYS_COUNTRY\'
            OR usf_name LIKE \'SYS_BIRTHDAY\'
            OR usf_name LIKE \'SYS_WEBSITE\'';
$gDb->query($sql);
