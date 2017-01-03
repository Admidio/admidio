<?php
/**
 ***********************************************************************************************
 * Create new folder
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * folder_id : Folder id of the parent folder
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'int', array('requireValue' => true));

$headline = $gL10n->get('DOW_CREATE_FOLDER');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$folder = new TableFolder($gDb, $getFolderId);

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$folder->hasUploadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$gNavigation->addUrl(CURRENT_URL, $headline);

if(isset($_SESSION['download_request']))
{
    $form_values = strStripSlashesDeep($_SESSION['download_request']);
    unset($_SESSION['download_request']);
}
else
{
    $form_values['new_folder'] = null;
    $form_values['new_description'] = null;
}

try
{
    // get recordset of current folder from database
    $folder->getFolderForDownload($getFolderId);
}
catch(AdmException $e)
{
    $e->showHtml();
}

$parentFolderName = $folder->getValue('fol_name');

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$folderNewMenu = $page->getMenu();
$folderNewMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

$page->addHtml('<p class="lead">'.$gL10n->get('DOW_CREATE_FOLDER_DESC', $parentFolderName).'</p>');

// show form
$form = new HtmlForm('new_folder_form', ADMIDIO_URL.FOLDER_MODULES.'/downloads/download_function.php?mode=3&amp;folder_id='.$getFolderId, $page);
$form->addInput('new_folder', $gL10n->get('SYS_NAME'), $form_values['new_folder'], array('maxLength' => 255, 'property' => FIELD_REQUIRED));
$form->addMultilineTextInput('new_description', $gL10n->get('SYS_DESCRIPTION'), $form_values['new_description'], 4, array('maxLength' => 4000));
$form->addSubmitButton('btn_create', $gL10n->get('DOW_CREATE_FOLDER'), array('icon' => THEME_URL.'/icons/folder_create.png', 'class' => ' col-sm-offset-3'));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
