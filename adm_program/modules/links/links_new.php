<?php
/**
 ***********************************************************************************************
 * Create and edit weblinks
 *
 * @copyright 2004-2018 The Admidio Team
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
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getLinkId   = admFuncVariableIsValid($_GET, 'lnk_id',   'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('LNK_WEBLINKS')));

// check if the module is enabled for use
if ((int) $gSettingsManager->get('enable_weblinks_module') === 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// create weblink object
$link = new TableWeblink($gDb);

if($getLinkId > 0)
{
    $link->readDataById($getLinkId);

    // check if the current user could edit this weblink
    if(!$link->isEditable())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}
else
{
    // check if the user has the right to edit at least one category
    if(count($gCurrentUser->getAllEditableCategories('LNK')) === 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

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
    $headline = $gL10n->get('SYS_EDIT_VAR', array($getHeadline));
}
else
{
    $headline = $gL10n->get('SYS_CREATE_VAR', array($getHeadline));
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
$form = new HtmlForm('weblinks_edit_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/links/links_function.php', array('lnk_id' => $getLinkId, 'headline' => $getHeadline, 'mode' => $modeEditOrCreate)), $page);
$form->addInput(
    'lnk_name', $gL10n->get('LNK_LINK_NAME'), noHTML($link->getValue('lnk_name')),
    array('maxLength' => 250, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addInput(
    'lnk_url', $gL10n->get('LNK_LINK_ADDRESS'), $link->getValue('lnk_url'),
    array('maxLength' => 2000, 'property' => HtmlForm::FIELD_REQUIRED)
);
$form->addSelectBoxForCategories(
    'lnk_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'LNK', HtmlForm::SELECT_BOX_MODUS_EDIT,
    array('property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $link->getValue('lnk_cat_id'))
);
$form->addEditor(
    'lnk_description', $gL10n->get('SYS_DESCRIPTION'), $link->getValue('lnk_description'),
    array('height' => '150px')
);
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));

$form->addHtml(admFuncShowCreateChangeInfoById(
    (int) $link->getValue('lnk_usr_id_create'), $link->getValue('lnk_timestamp_create'),
    (int) $link->getValue('lnk_usr_id_change'), $link->getValue('lnk_timestamp_change')
));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
