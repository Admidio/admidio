<?php
/**
 ***********************************************************************************************
 * List of all modules and administration pages of Admidio
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// if config file doesn't exists, than show installation dialog
if (!is_file(dirname(__DIR__) . '/adm_my_files/config.php'))
{
    header('Location: installation/index.php');
    exit();
}

require_once(__DIR__ . '/system/common.php');

$headline = 'Admidio '.$gL10n->get('SYS_OVERVIEW');

// Navigation of the module starts here
$gNavigation->addFirst(CURRENT_URL);

// create html page object
$page = new HtmlPage($headline);

// main menu of the page
$mainMenu = $page->getMenu();

if($gValidLogin)
{
    // show link to own profile
    $mainMenu->addItem(
        'adm_menu_item_my_profile', ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php',
        $gL10n->get('PRO_MY_PROFILE'), 'fa-user'
    );
    // show logout link
    $mainMenu->addItem(
        'adm_menu_item_logout', ADMIDIO_URL . '/adm_program/system/logout.php',
        $gL10n->get('SYS_LOGOUT'), 'fa-sign-out-alt'
    );
}
else
{
    // show login link
    $mainMenu->addItem(
        'adm_menu_item_login', ADMIDIO_URL . '/adm_program/system/login.php',
        $gL10n->get('SYS_LOGIN'), 'fa-key'
    );

    if($gSettingsManager->getBool('registration_enable_module'))
    {
        // show registration link
        $mainMenu->addItem(
            'adm_menu_item_registration', ADMIDIO_URL . FOLDER_MODULES . '/registration/registration.php',
            $gL10n->get('SYS_REGISTRATION'), 'fa-address-card'
        );
    }
}

// display Menu
$page->addHtml($page->showMainMenu());

$page->show();
