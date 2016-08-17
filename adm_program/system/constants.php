<?php
/**
 ***********************************************************************************************
 * Constants that will be used within Admidio
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'constants.php')
{
    exit('This page may not be called directly!');
}

// !!! Please do not edit these version numbers !!!
define('MIN_PHP_VERSION', '5.3.7');

define('ADMIDIO_VERSION_MAIN', 3);
define('ADMIDIO_VERSION_MINOR', 2);
define('ADMIDIO_VERSION_PATCH', 0);
define('ADMIDIO_VERSION_BETA', 1);
define('ADMIDIO_VERSION', ADMIDIO_VERSION_MAIN . '.' . ADMIDIO_VERSION_MINOR . '.' . ADMIDIO_VERSION_PATCH);

if(ADMIDIO_VERSION_BETA > 0)
{
    define('ADMIDIO_VERSION_TEXT', ADMIDIO_VERSION . ' Beta ' . ADMIDIO_VERSION_BETA);
}
else
{
    define('ADMIDIO_VERSION_TEXT', ADMIDIO_VERSION);
}

// different paths
define('SERVER_PATH', substr(__FILE__, 0, strpos(__FILE__, 'adm_program')-1));
if(isset($g_root_path) && strpos($_SERVER['SCRIPT_FILENAME'], '/adm_') !== false)
{
    // current called url (only this way possible, because SSL-Proxies couldn't be read with _SERVER parameter)
    define('CURRENT_URL', $g_root_path . substr($_SERVER['SCRIPT_FILENAME'],
            strrpos($_SERVER['SCRIPT_FILENAME'], '/adm_')) . '?' . $_SERVER['QUERY_STRING']);
}
else
{
    define('CURRENT_URL', 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
}

// set default timezone that could be defined in the config.php
if(!isset($gTimezone))
{
    $gTimezone = 'Europe/Berlin';
}

date_default_timezone_set($gTimezone);

// date and time for use in scripts
define('DATE_NOW', date('Y-m-d', time()));
define('DATETIME_NOW', date('Y-m-d H:i:s', time()));

// Defines for all database tables
define('TBL_ANNOUNCEMENTS',      $g_tbl_praefix . '_announcements');
define('TBL_AUTO_LOGIN',         $g_tbl_praefix . '_auto_login');
define('TBL_CATEGORIES',         $g_tbl_praefix . '_categories');
define('TBL_COMPONENTS',         $g_tbl_praefix . '_components');
define('TBL_DATE_ROLE',          $g_tbl_praefix . '_date_role');
define('TBL_DATES',              $g_tbl_praefix . '_dates');
define('TBL_FILES',              $g_tbl_praefix . '_files');
define('TBL_FOLDERS',            $g_tbl_praefix . '_folders');
define('TBL_GUESTBOOK',          $g_tbl_praefix . '_guestbook');
define('TBL_GUESTBOOK_COMMENTS', $g_tbl_praefix . '_guestbook_comments');
define('TBL_IDS',                $g_tbl_praefix . '_ids');
define('TBL_INVENT',             $g_tbl_praefix . '_invent');
define('TBL_INVENT_DATA',        $g_tbl_praefix . '_invent_data');
define('TBL_INVENT_FIELDS',      $g_tbl_praefix . '_invent_fields');
define('TBL_LINKS',              $g_tbl_praefix . '_links');
define('TBL_LIST_COLUMNS',       $g_tbl_praefix . '_list_columns');
define('TBL_LISTS',              $g_tbl_praefix . '_lists');
define('TBL_MEMBERS',            $g_tbl_praefix . '_members');
define('TBL_MESSAGES',           $g_tbl_praefix . '_messages');
define('TBL_MESSAGES_CONTENT',   $g_tbl_praefix . '_messages_content');
define('TBL_ORGANIZATIONS',      $g_tbl_praefix . '_organizations');
define('TBL_PHOTOS',             $g_tbl_praefix . '_photos');
define('TBL_PREFERENCES',        $g_tbl_praefix . '_preferences');
define('TBL_REGISTRATIONS',      $g_tbl_praefix . '_registrations');
define('TBL_ROLE_DEPENDENCIES',  $g_tbl_praefix . '_role_dependencies');
define('TBL_ROLES',              $g_tbl_praefix . '_roles');
define('TBL_ROLES_RIGHTS',       $g_tbl_praefix . '_roles_rights');
define('TBL_ROLES_RIGHTS_DATA',  $g_tbl_praefix . '_roles_rights_data');
define('TBL_ROOMS',              $g_tbl_praefix . '_rooms');
define('TBL_SESSIONS',           $g_tbl_praefix . '_sessions');
define('TBL_TEXTS',              $g_tbl_praefix . '_texts');
define('TBL_USERS',              $g_tbl_praefix . '_users');
define('TBL_USER_DATA',          $g_tbl_praefix . '_user_data');
define('TBL_USER_FIELDS',        $g_tbl_praefix . '_user_fields');
define('TBL_USER_LOG',           $g_tbl_praefix . '_user_log');

// constants for column rol_leader_rights
define('ROLE_LEADER_NO_RIGHTS', 0);
define('ROLE_LEADER_MEMBERS_ASSIGN', 1);
define('ROLE_LEADER_MEMBERS_EDIT', 2);
define('ROLE_LEADER_MEMBERS_ASSIGN_EDIT', 3);

// Password settings
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_GEN_LENGTH', 16);
define('PASSWORD_GEN_CHARS', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
define('PASSWORD_MIN_STRENGTH', 2);
