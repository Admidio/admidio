<?php
/**
 ***********************************************************************************************
 * Configuration file of Admidio
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Select your database system for example 'mysql' or 'pgsql'
$gDbType = '%DB_TYPE%';

// Table prefix for Admidio-Tables in database
// Example: 'adm'
$g_tbl_praefix = '%PREFIX%';

// Access to the database of the MySQL-Server
$g_adm_srv  = '%SERVER%';      // Server
$g_adm_port = '%PORT%';        // Port
$g_adm_usr  = '%USER%';        // User
$g_adm_pw   = '%PASSWORD%';    // Password
$g_adm_db   = '%DATABASE%';    // Database

// URL to this Admidio installation
// Example: 'https://www.admidio.org/example'
$g_root_path = '%ROOT_PATH%';

// Short description of the organization that is running Admidio
// This short description must correspond to your input in the installation wizard !!!
// Example: 'ADMIDIO'
// Maximum of 10 characters !!!
$g_organization = '%ORGANIZATION%';

// The name of the timezone in which your organization is located.
// This must be one of the strings that are defined here https://secure.php.net/manual/en/timezones.php
// Example: 'Europe/Berlin'
$gTimezone = '%TIMEZONE%';

// If this flag is set = 1 then you must enter your loginname and password
// for an update of the Admidio database to a new version of Admidio.
// For a more comfortable and easy update you can set this preference = 0.
$gLoginForUpdate = 1;
