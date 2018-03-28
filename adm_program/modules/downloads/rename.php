<?php
/**
 ***********************************************************************************************
 * Rename a file or a folder of download module
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * folder_id    :  Id of the folder that should be renamed
 * file_id      :  Id of the file that should be renamed
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'int');
$getFileId   = admFuncVariableIsValid($_GET, 'file_id',   'int');

// set headline of the script
if($getFileId > 0)
{
    $headline = $gL10n->get('DOW_EDIT_FILE');
}
else
{
    $headline = $gL10n->get('DOW_EDIT_FOLDER');
}

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('enable_download_module'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$gNavigation->addUrl(CURRENT_URL, $headline);

if(isset($_SESSION['download_request']))
{
    $formValues = strStripSlashesDeep($_SESSION['download_request']);
    unset($_SESSION['download_request']);
}
else
{
    $formValues['new_name'] = null;
    $formValues['new_description'] = null;
}

// check the rights of the current folder
// user must be administrator or must have the right to upload files
$targetFolder = new TableFolder($gDb, $getFolderId);

if (!$targetFolder->hasUploadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$originalName    = '';
$fileType        = '';
$createUserId    = 0;
$createTimestamp = '';

try
{
    if ($getFileId)
    {
        // get recordset of current file from database
        $file = new TableFile($gDb);
        $file->getFileForDownload($getFileId);

        $originalName    = pathinfo($file->getValue('fil_name'), PATHINFO_FILENAME);
        $fileType        = pathinfo($file->getValue('fil_name'), PATHINFO_EXTENSION);
        $createUserId    = $file->getValue('fil_usr_id');
        $createTimestamp = $file->getValue('fil_timestamp');

        if ($formValues['new_name'] === null)
        {
            $formValues['new_name'] = $originalName;
        }

        if ($formValues['new_description'] === null)
        {
            $formValues['new_description'] = $file->getValue('fil_description');
        }

    }
    else
    {
        // get recordset of current folder from databases
        $folder = new TableFolder($gDb);
        $folder->getFolderForDownload($getFolderId);

        $originalName    = $folder->getValue('fol_name');
        $createUserId    = $folder->getValue('fol_usr_id');
        $createTimestamp = $folder->getValue('fol_timestamp');

        if ($formValues['new_name'] == null)
        {
            $formValues['new_name'] = $originalName;
        }

        if ($formValues['new_description'] == null)
        {
            $formValues['new_description'] = $folder->getValue('fol_description');
        }
    }
}
catch(AdmException $e)
{
    $e->showHtml();
    // => EXIT
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$downloadRenameMenu = $page->getMenu();
$downloadRenameMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// create html form
$form = new HtmlForm('edit_download_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/downloads/download_function.php', array('mode' => '4', 'folder_id' => $getFolderId, 'file_id' => $getFileId)), $page);
if ($getFileId)
{
    $form->addInput(
        'file_type', $gL10n->get('DOW_FILE_TYPE'), $fileType,
        array('property' => HtmlForm::FIELD_DISABLED, 'class' => 'form-control-small')
    );
}
$form->addInput(
    'previous_name', $gL10n->get('DOW_PREVIOUS_NAME'), $originalName,
    array('property' => HtmlForm::FIELD_DISABLED)
);
$form->addInput(
    'new_name', $gL10n->get('DOW_NEW_NAME'), $formValues['new_name'],
    array('maxLength' => 255, 'property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdLabel' => 'DOW_FILE_NAME_RULES')
);
$form->addMultilineTextInput(
    'new_description', $gL10n->get('SYS_DESCRIPTION'), $formValues['new_description'], 4,
    array('maxLength' => 255)
);
$form->addSubmitButton(
    'btn_rename', $gL10n->get('SYS_SAVE'),
    array('icon' => THEME_URL.'/icons/disk.png', 'class' => ' col-sm-offset-3')
);
$form->addHtml(admFuncShowCreateChangeInfoById($createUserId, $createTimestamp));

$page->addHtml($form->show());
$page->show();
