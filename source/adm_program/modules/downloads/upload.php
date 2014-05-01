<?php
/******************************************************************************
 * Upload new files
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * folder_id : Id of the current folder where the files should be uploaded
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'numeric', null, true);

$headline = $gL10n->get('DOW_UPLOAD_FILE');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//nur von eigentlicher OragHompage erreichbar
if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) != 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $gHomepage));
}

//maximaler Fileupload fuer das Downloadmodul muss groesser 0 sein
if ($gPreferences['max_file_upload_size'] == 0) {

    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editDownloadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

//pruefen ob in den aktuellen Servereinstellungen ueberhaupt file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
}

$gNavigation->addUrl(CURRENT_URL, $headline);

if(isset($_SESSION['download_request']))
{
   $form_values = strStripSlashesDeep($_SESSION['download_request']);
   unset($_SESSION['download_request']);
}
else
{
   $form_values['new_name'] = null;
   $form_values['new_description'] = null;
}

try
{
    // get recordset of current folder from databse
    $folder = new TableFolder($gDb);
    $folder->getFolderForDownload($getFolderId);
}
catch(AdmException $e)
{
	$e->showHtml();
}

$parentFolderName = $folder->getValue('fol_name');

// create html page object
$page = new HtmlPage();

// show back link
$page->addHtml($gNavigation->getHtmlBackButton());

// show headline of module
$page->addHeadline($headline);

// show form
$form = new HtmlForm('upload_files_form', $g_root_path.'/adm_program/modules/downloads/download_function.php?mode=1&amp;folder_id='.$getFolderId, $page, true);
$form->addDescription($gL10n->get('DOW_UPLOAD_TO_FOLDER', $parentFolderName));
$form->addFileUpload('add_files', $gL10n->get('DOW_CHOOSE_FILE'), ($gPreferences['max_file_upload_size'] * 1024), true, $gL10n->get('DOW_UPLOAD_ANOTHER_FILE'), false);
$form->addSubmitButton('btn_upload', $gL10n->get('SYS_UPLOAD'), THEME_PATH.'/icons/page_white_upload.png');

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();

?>