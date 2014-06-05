<?php
/******************************************************************************
 * Rename a file or a folder of download module
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * folder_id    :  Id of the folder that should be renamed
 * file_id      :  Id of the file that should be renamed
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'numeric', 0);
$getFileId   = admFuncVariableIsValid($_GET, 'file_id', 'numeric', 0);

// set headline of the script
if($getFileId > 0)
{
    $headline = $gL10n->get('DOW_EDIT_FILE');
}
else
{
    $headline = $gL10n->get('DOW_EDIT_FOLDER');
}

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

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editDownloadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if ( (!$getFileId && !$getFolderId) OR ($getFileId && $getFolderId) )
{
    //Es muss entweder eine FileID ODER eine FolderId uebergeben werden
    //beides ist auch nicht erlaubt
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
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
    if ($getFileId) 
    {
        // get recordset of current file from databse
        $file = new TableFile($gDb);
        $file->getFileForDownload($getFileId);
        
        $originalName = $file->getValue('fil_name');
    
        if ($form_values['new_name'] == null) 
        {
            $form_values['new_name'] = admFuncGetFilenameWithoutExtension($originalName);
        }
    
        if ($form_values['new_description'] == null) 
        {
            $form_values['new_description'] = $file->getValue('fil_description');
        }
    
    }
    else 
    {
        // get recordset of current folder from databses
        $folder = new TableFolder($gDb);
        $folder->getFolderForDownload($getFolderId);
        
        $originalName = $folder->getValue('fol_name');
    
        if ($form_values['new_name'] == null) 
        {
            $form_values['new_name'] = $originalName;
        }
    
        if ($form_values['new_description'] == null) 
        {
            $form_values['new_description'] = $folder->getValue('fol_description');
        }    
    }
}
catch(AdmException $e)
{
	$e->showHtml();
}

// create html page object
$page = new HtmlPage();

// show back link
$page->addHtml($gNavigation->getHtmlBackButton());

// show headline of module
$page->addHeadline($headline);

// create html form
$form = new HtmlForm('edit_download_form', $g_root_path.'/adm_program/modules/downloads/download_function.php?mode=4&amp;folder_id='.$getFolderId.'&amp;file_id='.$getFileId, $page);
$form->addTextInput('previous_name', $gL10n->get('DOW_PREVIOUS_NAME'), $originalName, 0, FIELD_DISABLED);
$form->addTextInput('new_name', $gL10n->get('DOW_NEW_NAME'), $form_values['new_name'], 255, FIELD_MANDATORY, 'TEXT', 'DOW_FILE_NAME_RULES');
$form->addMultilineTextInput('new_description', $gL10n->get('SYS_DESCRIPTION'), $form_values['new_description'], 4, 255);
$form->addSubmitButton('btn_rename', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png');

$page->addHtml($form->show(false));
$page->show();

?>