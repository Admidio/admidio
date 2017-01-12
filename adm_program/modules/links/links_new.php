<?php
/**
 ***********************************************************************************************
 * Create and edit weblinks
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * lnk_id    - ID of the weblink that should be edited
 * headline  - Title of the weblink module. This will be shown in the whole module.
 *             (Default) LNK_WEBLINKS
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getLinkId   = admFuncVariableIsValid($_GET, 'lnk_id',   'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('LNK_WEBLINKS')));

// check if the module is enabled for use
if ($gPreferences['enable_weblinks_module'] == 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// Ist ueberhaupt das Recht vorhanden?
if (!$gCurrentUser->editWeblinksRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// Weblinkobjekt anlegen
$link = new TableWeblink($gDb, $getLinkId);

if(isset($_SESSION['links_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $link->setArray($_SESSION['links_request']);
    unset($_SESSION['links_request']);
}

// Html-Kopf ausgeben
if($getLinkId > 0)
{
    $headline = $gL10n->get('SYS_EDIT_VAR', $getHeadline);
}
else
{
    $headline = $gL10n->get('SYS_CREATE_VAR', $getHeadline);
}

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$linksCreateMenu = $page->getMenu();
$linksCreateMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// Html des Modules ausgeben
if($getLinkId > 0)
{
    $modeEditOrCreate = '3';
}
else
{
    $modeEditOrCreate = '1';
}

// show form
$form = new HtmlForm('weblinks_edit_form', ADMIDIO_URL.FOLDER_MODULES.'/links/links_function.php?lnk_id='. $getLinkId. '&amp;headline='. $getHeadline. '&amp;mode='.$modeEditOrCreate, $page);
$form->addInput('lnk_name', $gL10n->get('LNK_LINK_NAME'), $link->getValue('lnk_name'), array('maxLength' => 250, 'property' => FIELD_REQUIRED));
$form->addInput('lnk_url', $gL10n->get('LNK_LINK_ADDRESS'), $link->getValue('lnk_url'), array('maxLength' => 2000, 'property' => FIELD_REQUIRED));
$form->addSelectBoxForCategories('lnk_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'LNK', 'EDIT_CATEGORIES',
                                 array('property' => FIELD_REQUIRED, 'defaultValue' => $link->getValue('lnk_cat_id')));
$form->addEditor('lnk_description', $gL10n->get('SYS_DESCRIPTION'), $link->getValue('lnk_description'), array('height' => '150px'));

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($link->getValue('lnk_usr_id_create'), $link->getValue('lnk_timestamp_create'), $link->getValue('lnk_usr_id_change'), $link->getValue('lnk_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
