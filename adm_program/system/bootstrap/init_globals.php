<?php
/**
 ***********************************************************************************************
 * Init Global Variables
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'init_globals.php') {
    exit('This page may not be called directly!');
}

// if there is no debug flag in config.php than set debug to false
if (!isset($gDebug) || !$gDebug) {
    $gDebug = false;
}

// create database object and establish connection to database
if (!isset($gDbType)) {
    $gDbType = 'mysql'; // DB_ENGINE
}

if (!isset($g_adm_srv)) {
    $g_adm_srv = null; // DB_HOST
}

if (!isset($g_adm_port)) {
    $g_adm_port = null; // DB_PORT
}

if (!isset($g_adm_db)) {
    $g_adm_db = null; // DB_NAME
}

if (!isset($g_adm_usr)) {
    $g_adm_usr = null; // DB_USERNAME
}

if (!isset($g_adm_pw)) {
    $g_adm_pw = null; // DB_PASSWORD
}

// default prefix is set to 'adm' because of compatibility to old versions
if (!isset($g_tbl_praefix)) {
    $g_tbl_praefix = 'adm'; // TABLE_PREFIX
}

// set default password-hash algorithm
if (!isset($gPasswordHashAlgorithm)) {
    $gPasswordHashAlgorithm = 'DEFAULT';
}

// set default timezone that could be defined in the config.php
if (!isset($gTimezone)) {
    $gTimezone = 'Europe/Berlin';
}

// default all cookies will only be set for the subfolder of Admidio
if (!isset($gSetCookieForDomain)) {
    $gSetCookieForDomain = false;
}

// set Force permanent HTTPS redirect
if (!isset($gForceHTTPS)) {
    $gForceHTTPS = false;
}
