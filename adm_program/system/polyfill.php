<?php
/**
 ***********************************************************************************************
 * Includes the different polyfills
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'polyfill.php')
{
    exit('This page may not be called directly!');
}

// provide forward compatibility with the random_* functions that ship with PHP 7.0
require_once(ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/random_compat/lib/random.php');
