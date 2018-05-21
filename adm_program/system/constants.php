<?php
/**
 ***********************************************************************************************
 * Constants that will be used within Admidio
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'constants.php')
{
    exit('This page may not be called directly!');
}

define('SCRIPT_START_TIME', microtime(true));

// ##################
// ###  VERSIONS  ###
// ##################

// !!! Please do not edit these version numbers !!!
define('MIN_PHP_VERSION', '5.3.7');

define('ADMIDIO_VERSION_MAIN', 3);
define('ADMIDIO_VERSION_MINOR', 3);
define('ADMIDIO_VERSION_PATCH', 4);
define('ADMIDIO_VERSION_BETA', 0);
define('ADMIDIO_VERSION', ADMIDIO_VERSION_MAIN . '.' . ADMIDIO_VERSION_MINOR . '.' . ADMIDIO_VERSION_PATCH);

if(ADMIDIO_VERSION_BETA > 0)
{
    define('ADMIDIO_VERSION_TEXT', ADMIDIO_VERSION . ' Beta ' . ADMIDIO_VERSION_BETA);
}
else
{
    define('ADMIDIO_VERSION_TEXT', ADMIDIO_VERSION);
}

// ######################
// ###  URLS & PATHS  ###
// ######################

// Admidio Homepage
define('ADMIDIO_HOMEPAGE', 'https://www.admidio.org/');

// BASIC STUFF
// https://secure.php.net/manual/en/reserved.variables.server.php => $_SERVER['HTTPS']
define('SCHEME', parse_url($g_root_path, PHP_URL_SCHEME)); // get SCHEME out of $g_root_path because server doesn't have this info if ssl proxy is used
define('HTTPS', SCHEME === 'https'); // true | false
define('PORT', (int) $_SERVER['SERVER_PORT']); // 443 | 80

$port = (PORT === 80 || PORT === 443) ? '' : ':' . PORT; // :1234

if(isset($_SERVER['HTTP_X_FORWARDED_SERVER']) && $_SERVER['HTTP_X_FORWARDED_SERVER'] !== $_SERVER['HTTP_HOST'])
{
    // if ssl proxy is used than this proxy is the host and the cookie must be set for this
    define('HOST', $_SERVER['HTTP_X_FORWARDED_SERVER'] . $port . '/' . $_SERVER['HTTP_HOST']); // ssl.example.org/my.domain.net
    define('DOMAIN', strstr($_SERVER['HTTP_X_FORWARDED_SERVER'] . $port . ':', ':', true)); // ssl.example.org
}
else
{
    define('HOST', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'] . $port); // www.example.org:1234
    define('DOMAIN', strstr(HOST . ':', ':', true)); // www.example.org | www.myproxy.com
}
define('ADMIDIO_URL_PATH', parse_url($g_root_path, PHP_URL_PATH)); // /subfolder

// PATHS
define('WWW_PATH',     realpath($_SERVER['DOCUMENT_ROOT'])); // /var/www    Will get "SERVER_PATH" in v4.0
define('ADMIDIO_PATH', dirname(dirname(__DIR__))); // /var/www/subfolder
define('CURRENT_PATH', realpath($_SERVER['SCRIPT_FILENAME'])); // /var/www/subfolder/adm_program/index.php

// URLS
define('ADMIDIO_URL', $g_root_path); // https://www.example.org:1234/subfolder | https://www.myproxy.com:1234/www.example.com/subfolder
define('FILE_URL',    SCHEME . '://' . HOST . $_SERVER['SCRIPT_NAME']); // https://www.example.org:1234/subfolder/adm_program/index.php
define('CURRENT_URL', SCHEME . '://' . HOST . $_SERVER['REQUEST_URI']); // https://www.example.org:1234/subfolder/adm_program/index.php?param=value

// FOLDERS
define('FOLDER_DATA', '/adm_my_files');
define('FOLDER_CLASSES', '/adm_program/system/classes');
define('FOLDER_LIBS_SERVER', '/adm_program/libs'); // PHP libs
define('FOLDER_LIBS_CLIENT', '/adm_program/libs'); // JS/CSS libs
define('FOLDER_LANGUAGES', '/adm_program/languages');
define('FOLDER_THEMES', '/adm_themes');
define('FOLDER_MODULES', '/adm_program/modules');
define('FOLDER_PLUGINS', '/adm_plugins');

// ####################
// ###  DATE-STUFF  ###
// ####################

// Define default timezone
date_default_timezone_set($gTimezone);

// date and time for use in scripts
define('DATE_NOW', date('Y-m-d'));
define('DATETIME_NOW', date('Y-m-d H:i:s'));
define('DATE_MAX', '9999-12-31');

// ###################
// ###  DB-CONFIG  ###
// ###################

define('DB_ENGINE', $gDbType);
define('DB_HOST', $g_adm_srv);
define('DB_PORT', $g_adm_port);
define('DB_NAME', $g_adm_db);
define('DB_USERNAME', $g_adm_usr);
define('DB_PASSWORD', $g_adm_pw);

// ###################
// ###  DB-TABLES  ###
// ###################

define('TABLE_PREFIX', $g_tbl_praefix);

define('TBL_ANNOUNCEMENTS',       TABLE_PREFIX . '_announcements');
define('TBL_AUTO_LOGIN',          TABLE_PREFIX . '_auto_login');
define('TBL_CATEGORIES',          TABLE_PREFIX . '_categories');
define('TBL_COMPONENTS',          TABLE_PREFIX . '_components');
define('TBL_DATES',               TABLE_PREFIX . '_dates');
define('TBL_FILES',               TABLE_PREFIX . '_files');
define('TBL_FOLDERS',             TABLE_PREFIX . '_folders');
define('TBL_GUESTBOOK',           TABLE_PREFIX . '_guestbook');
define('TBL_GUESTBOOK_COMMENTS',  TABLE_PREFIX . '_guestbook_comments');
define('TBL_IDS',                 TABLE_PREFIX . '_ids');
define('TBL_LINKS',               TABLE_PREFIX . '_links');
define('TBL_LIST_COLUMNS',        TABLE_PREFIX . '_list_columns');
define('TBL_LISTS',               TABLE_PREFIX . '_lists');
define('TBL_MEMBERS',             TABLE_PREFIX . '_members');
define('TBL_MENU',                TABLE_PREFIX . '_menu');
define('TBL_MESSAGES',            TABLE_PREFIX . '_messages');
define('TBL_MESSAGES_CONTENT',    TABLE_PREFIX . '_messages_content');
define('TBL_ORGANIZATIONS',       TABLE_PREFIX . '_organizations');
define('TBL_PHOTOS',              TABLE_PREFIX . '_photos');
define('TBL_PREFERENCES',         TABLE_PREFIX . '_preferences');
define('TBL_REGISTRATIONS',       TABLE_PREFIX . '_registrations');
define('TBL_ROLE_DEPENDENCIES',   TABLE_PREFIX . '_role_dependencies');
define('TBL_ROLES',               TABLE_PREFIX . '_roles');
define('TBL_ROLES_RIGHTS',        TABLE_PREFIX . '_roles_rights');
define('TBL_ROLES_RIGHTS_DATA',   TABLE_PREFIX . '_roles_rights_data');
define('TBL_ROOMS',               TABLE_PREFIX . '_rooms');
define('TBL_SESSIONS',            TABLE_PREFIX . '_sessions');
define('TBL_TEXTS',               TABLE_PREFIX . '_texts');
define('TBL_USERS',               TABLE_PREFIX . '_users');
define('TBL_USER_DATA',           TABLE_PREFIX . '_user_data');
define('TBL_USER_FIELDS',         TABLE_PREFIX . '_user_fields');
define('TBL_USER_LOG',            TABLE_PREFIX . '_user_log');
define('TBL_USER_RELATIONS',      TABLE_PREFIX . '_user_relations');
define('TBL_USER_RELATION_TYPES', TABLE_PREFIX . '_user_relation_types');

// #####################
// ###  OTHER STUFF  ###
// #####################

// create an installation unique cookie prefix and remove special characters
if(isset($g_adm_db))
{
    $cookiePrefix = 'ADMIDIO_' . $g_organization . '_' . DB_NAME . '_' . TABLE_PREFIX;
}
else
{
    $cookiePrefix = 'ADMIDIO_' . TABLE_PREFIX;
}
define('COOKIE_PREFIX', preg_replace('/\W/', '_', $cookiePrefix));

// constants for column rol_leader_rights
define('ROLE_LEADER_NO_RIGHTS', 0);
define('ROLE_LEADER_MEMBERS_ASSIGN', 1);
define('ROLE_LEADER_MEMBERS_EDIT', 2);
define('ROLE_LEADER_MEMBERS_ASSIGN_EDIT', 3);

// Password settings
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_GEN_LENGTH', 16);
define('PASSWORD_GEN_CHARS', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');

// ####################
// ###  DEPRECATED  ###
// ####################

// TODO deprecated: Remove in Admidio 4.0
define('SERVER_PATH', ADMIDIO_PATH);

$gCookiePraefix = COOKIE_PREFIX;

// Define Constants for PHP 5.3
if (!defined('JSON_UNESCAPED_SLASHES'))
{
    define('JSON_UNESCAPED_SLASHES', 64);
    define('JSON_UNESCAPED_UNICODE', 256);
}
