<?php
/**
 ***********************************************************************************************
 * Configuration file of Admidio
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!! I M P O R T A N T !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * This is an example file. Don't use it with your production environment.
 * Our installation wizard will create a specific file with your custom
 * preferences.
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 ***********************************************************************************************
 */

// Select your database system between 'mariadb', 'mysql' or 'pgsql'
$gDbType = 'mysql';

// Table prefix for Admidio-Tables in database
// Example: 'adm'
$g_tbl_praefix = 'adm';

// Access to the database
$g_adm_srv  = 'URL_to_your_database_server'; // Server
$g_adm_port = null;                          // Port
$g_adm_db   = 'Database-name';               // Database
$g_adm_usr  = 'Username';                    // User
$g_adm_pw   = 'Password';                    // Password

// URL to this Admidio installation
// Example: 'https://www.admidio.org/example'
$g_root_path = 'https://www.your-website.de/admidio';

// The name of the timezone in which your organization is located.
// This must be one of the strings that are defined here https://www.php.net/manual/en/timezones.php
// Example: 'Europe/Berlin'
$gTimezone = 'Europe/Berlin';
