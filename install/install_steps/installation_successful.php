<?php
/**
 ***********************************************************************************************
 * Installation step: installation_successful
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\UI\View\InstallationPresenter;

if (basename($_SERVER['SCRIPT_FILENAME']) === 'start_installation.php') {
    exit('This page may not be called directly!');
}

// show dialog with success notification
$page = new InstallationPresenter('adm_installation_successful', $gL10n->get('INS_INSTALLATION'));
$page->addTemplateFile('installation.successful.tpl');
$page->addJavascript('$("#adm_next_page").focus();', true);
$page->show();
