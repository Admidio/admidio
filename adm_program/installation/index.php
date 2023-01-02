<?php
/**
 ***********************************************************************************************
 * Redirect the user to installation or update page
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// check if installation is necessary
if (is_file('../../adm_my_files/config.php')) {
    $page = 'update.php';
} else {
    $page = 'installation.php';
}

// redirect to installation or update page
header('Location: ' . $page);
exit();
