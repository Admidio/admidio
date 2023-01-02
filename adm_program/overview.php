<?php
/**
 ***********************************************************************************************
 * A small overview of all Admidio modules with the integration of Admidio plugins
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// if config file doesn't exists, than show installation dialog
if (!is_file(dirname(__DIR__) . '/adm_my_files/config.php')) {
    header('Location: installation/index.php');
    exit();
}

require_once(__DIR__ . '/system/common.php');

$headline = $gL10n->get('SYS_OVERVIEW');

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline, 'fa-home');

// create html page object and load template file
$page = new HtmlPage('admidio-overview', $headline);
$page->addTemplateFile('overview.tpl');

$page->show();
