<?php
/**
 ***********************************************************************************************
 * Configuration file of Admidio
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!! I M P O R T A N T !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * This is an example file. Don't use it with your production environment.
 * Our installation wizard will create a specific file with your custom
 * preferences.
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 ***********************************************************************************************
 */

// Select your database system for example 'mysql' or 'postgresql'
$gDbType = 'mysql';

// Table prefix for Admidio-Tables in database
// Example: 'adm'
$g_tbl_praefix = 'adm';

// Access to the database of the MySQL-Server
$g_adm_srv = 'URL_to_your_MySQL-Server';    // Server
$g_adm_usr = 'Username';                    // User
$g_adm_pw  = 'Password';                    // Password
$g_adm_db  = 'Databasename';                // Database

// URL to this Admidio installation
// Example: 'http://www.admidio.org/example'
$g_root_path = 'http://www.your-website.de/admidio';

// Short description of the organization that is running Admidio
// This short description must correspond to your input in the installation wizard !!!
// Example: 'ADMIDIO'
// Maximum of 10 characters !!!
$g_organization = 'Shortcut';

// The name of the timezone in which your organization is located.
// This must be one of the strings that are defined here https://secure.php.net/manual/en/timezones.php
// Example: 'Europe/Berlin'
$gTimezone = 'Europe/Berlin';

// If this flag is set = 1 then you must enter your loginname and password
// for an update of the Admidio database to a new version of Admidio.
// For a more comfortable and easy update you can set this preference = 0.
$gLoginForUpdate = 1;
