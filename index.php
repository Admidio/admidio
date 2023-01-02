<?php
/**
 ***********************************************************************************************
 * Set the correct startpage for Admidio
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
// if config file doesn't exists, than show installation dialog
if (!is_file(__DIR__ . '/adm_my_files/config.php')) {
    if (is_file(__DIR__ . '/config.php')) {
        // show update hint if config of Admidio 1 or 2 was found
        exit('<div style="color: #cc0000;">Old Admidio version 1.x or 2.x config file detected! Please update first to the latest version 3 of Admidio and after that you can perform an update to version 4!<br /><br />Please view <a href="https://www.admidio.org/dokuwiki/doku.php?id=de:2.0:update_von_2.x_auf_3.x">our documentation</a>.</div>');
    } else {
        // no config file found than show installation dialog
        header('Location: adm_program/installation/index.php');
        exit();
    }
}

require_once(__DIR__ . '/adm_program/system/common.php');

if (isset($gHomepage)) {
    admRedirect($gHomepage);
// => EXIT
} else {
    // if parameter gHomepage doesn't exists then show default page
    admRedirect(ADMIDIO_URL . '/adm_program/overview.php');
    // => EXIT
}
