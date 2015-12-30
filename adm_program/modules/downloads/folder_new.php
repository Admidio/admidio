<?php
/**
 ***********************************************************************************************
 * Create new folder
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
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
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'numeric', array('requireValue' => true));

$headline = $gL10n->get('DOW_CREATE_FOLDER');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
// nur von eigentlicher OragHompage erreichbar
if($gCurrentOrganization->getValue('org_shortname')!= $g_organization)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $g_organization));
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editDownloadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
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
    $folder = new TableFolder($gDb);
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
$form = new HtmlForm('new_folder_form', $g_root_path.'/adm_program/modules/downloads/download_function.php?mode=3&amp;folder_id='.$getFolderId, $page);
$form->addInput('new_folder', $gL10n->get('SYS_NAME'), $form_values['new_folder'], array('maxLength' => 255, 'property' => FIELD_REQUIRED));
$form->addMultilineTextInput('new_description', $gL10n->get('SYS_DESCRIPTION'), $form_values['new_description'], 4, array('maxLength' => 4000));
$form->addSubmitButton('btn_create', $gL10n->get('DOW_CREATE_FOLDER'), array('icon' => THEME_PATH.'/icons/folder_create.png', 'class' => ' col-sm-offset-3'));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
