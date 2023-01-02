<?php
/**
 ***********************************************************************************************
 * index.php for backward compatibility to Admidio 3
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
// if config file doesn't exists, than show installation dialog
if (!is_file(__DIR__ . '/../adm_my_files/config.php')) {
    header('Location: adm_program/installation/index.php');
    exit();
}

require_once(__DIR__ . '/system/common.php');

// if parameter gHomepage doesn't exists then show default page
admRedirect(ADMIDIO_URL . '/adm_program/overview.php');
// => EXIT
