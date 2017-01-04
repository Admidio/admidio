<?php
/**
 ***********************************************************************************************
 * Init Global Variables
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// if there is no debug flag in config.php than set debug to false
if(!isset($gDebug) || !$gDebug)
{
    $gDebug = 0;
}

// create database object and establish connection to database
if(!isset($gDbType))
{
    $gDbType = 'mysql';
}

// default prefix is set to 'adm' because of compatibility to old versions
if(!isset($g_tbl_praefix))
{
    $g_tbl_praefix = 'adm';
}

if (!isset($g_adm_port))
{
    $g_adm_port = null;
}

// set default password-hash algorithm
if (!isset($gPasswordHashAlgorithm))
{
    $gPasswordHashAlgorithm = 'DEFAULT';
}

// set default timezone that could be defined in the config.php
if(!isset($gTimezone))
{
    $gTimezone = 'Europe/Berlin';
}
