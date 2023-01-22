<?php
/**
 ***********************************************************************************************
 * Configuration file of Admidio
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Select your database system for example 'mysql' or 'pgsql'
$gDbType = '%DB_ENGINE%';

// Access to the database of the SQL-Server
$g_adm_srv  = '%DB_HOST%';     // Host
$g_adm_port = '%DB_PORT%';     // Port
$g_adm_db   = '%DB_NAME%';     // Database-Name
$g_adm_usr  = '%DB_USERNAME%'; // Username
$g_adm_pw   = '%DB_PASSWORD%'; // Password

// Table prefix for Admidio-Tables in database
// Example: 'adm'
$g_tbl_praefix = '%TABLE_PREFIX%';

// URL to this Admidio installation
// Example: 'https://www.admidio.org/example'
$g_root_path = '%ROOT_PATH%';

// The name of the timezone in which your organization is located.
// This must be one of the strings that are defined here https://www.php.net/manual/en/timezones.php
// Example: 'Europe/Berlin'
$gTimezone = '%TIMEZONE%';

// If this flag is set = 1 then you must enter your loginname and password
// for an update of the Admidio database to a new version of Admidio.
// For a more comfortable and easy update you can set this preference = 0.
$gLoginForUpdate = 1;
