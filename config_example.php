<?php
/******************************************************************************
 * Configuration file of Admidio
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!! I M P O R T A N T !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * This is an example file. Don't use it with your production environment. 
 * Our installation wizard will create a specific file with your custom 
 * preferences.
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 *****************************************************************************/

// Select your database system for example 'mysql' or 'postgresql'
$gDbType = 'mysql';

// Table prefix for Admidio-Tables in database
// Example: 'adm'
$g_tbl_praefix = 'adm';

// Access to the database of the MySQL-Server
$g_adm_srv = 'URL_to_your_MySQL-Server';	// Server
$g_adm_usr = 'Username';               		// User
$g_adm_pw  = 'Password';                   	// Password
$g_adm_db  = 'Databasename';              	// Database

// URL to this Admidio installation 
// Example: 'http://www.admidio.org/example'
$g_root_path = 'http://www.your-website.de/admidio';

// Short description of the organization that is running Admidio
// This short description must correspond to your input in the installation wizard !!!
// Example: 'ADMIDIO'
// Maximun of 10 characters !!!
$g_organization = 'Shortcut'; 

?>