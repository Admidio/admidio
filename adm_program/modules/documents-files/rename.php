<?php
/**
 ***********************************************************************************************
 * Rename a file or a folder of documents & files module
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * folder_uuid :  UUID of the folder that should be renamed
 * file_uuid   :  UUID of the file that should be renamed
 ***********************************************************************************************
 */

use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Component\Form;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getFolderUuid = admFuncVariableIsValid($_GET, 'folder_uuid', 'uuid');
    $getFileUuid = admFuncVariableIsValid($_GET, 'file_uuid', 'uuid');

    // set headline of the script
    if ($getFileUuid !== '') {
        $headline = $gL10n->get('SYS_EDIT_FILE');
    } else {
        $headline = $gL10n->get('SYS_EDIT_FOLDER');
    }

    // check if the module is enabled and disallow access if it's disabled
    if (!$gSettingsManager->getBool('documents_files_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    $gNavigation->addUrl(CURRENT_URL, $headline);

    // check the rights of the current folder
    // user must be administrator or must have the right to upload files
    $targetFolder = new Folder($gDb);
    $targetFolder->getFolderForDownload($getFolderUuid);

    if (!$targetFolder->hasUploadRight()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $originalName = '';
    $fileType = '';

    if ($getFileUuid !== '') {
        // get recordset of current file from database
        $file = new File($gDb);
        $file->getFileForDownload($getFileUuid);

        $originalName = pathinfo($file->getValue('fil_name'), PATHINFO_FILENAME);
        $fileType = pathinfo($file->getValue('fil_name'), PATHINFO_EXTENSION);
        $userFileUploaded = new User($gDb, $gProfileFields, $file->getValue('fil_usr_id'));
        $nameUserCreated = $userFileUploaded->getValue('FIRST_NAME') . ' ' . $userFileUploaded->getValue('LAST_NAME');
        $timestampUserCreated = $file->getValue('fil_timestamp');

        $formValues['new_name'] = $originalName;
        $formValues['new_description'] = $file->getValue('fil_description');
    } else {
        // main folder should not be renamed
        if ($targetFolder->getValue('fol_fol_id_parent') === '') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // read folder data to rename the folder
        $originalName = $targetFolder->getValue('fol_name');
        $userFolderCreated = new User($gDb, $gProfileFields, $targetFolder->getValue('fol_usr_id'));
        $nameUserCreated = $userFolderCreated->getValue('FIRST_NAME') . ' ' . $userFolderCreated->getValue('LAST_NAME');
        $timestampUserCreated = $targetFolder->getValue('fol_timestamp');

        $formValues['new_name'] = $originalName;
        $formValues['new_description'] = $targetFolder->getValue('fol_description');
    }

    // create html page object
    $page = new HtmlPage('admidio-documents-files-rename', $headline);

    if ($gSettingsManager->getBool('profile_log_edit_fields')) { // TODO_RK: More fine-grained logging settings
        $isFile = ($getFileUuid !== '');
        // show link to view change history
        $page->addPageFunctionsMenuItem(
            'menu_item_filefolder_change_history',
            $gL10n->get('SYS_CHANGE_HISTORY'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/changelog.php', array('table' => ($isFile?'files':'folders').',roles_rights_data', 'uuid' => $isFile?$getFileUuid:$getFolderUuid)),
            'bi-clock-history'
        );
    }
    
    // create html form
    $form = new Form(
        'adm_edit_download_form',
        'modules/documents-files.rename.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/documents_files_function.php', array('mode' => 'rename', 'folder_uuid' => $getFolderUuid, 'file_uuid' => $getFileUuid)),
        $page
    );
    if ($getFileUuid !== '') {
        $form->addInput(
            'file_type',
            $gL10n->get('SYS_FILE_TYPE'),
            $fileType,
            array('property' => Form::FIELD_DISABLED, 'class' => 'form-control-small')
        );
    }
    $form->addInput(
        'previous_name',
        $gL10n->get('SYS_PREVIOUS_NAME'),
        $originalName,
        array('property' => Form::FIELD_DISABLED)
    );
    $form->addInput(
        'new_name',
        $gL10n->get('SYS_NEW_NAME'),
        $formValues['new_name'],
        array('maxLength' => 255, 'property' => Form::FIELD_REQUIRED, 'helpTextId' => 'SYS_FILE_NAME_RULES')
    );
    $form->addMultilineTextInput(
        'new_description',
        $gL10n->get('SYS_DESCRIPTION'),
        $formValues['new_description'],
        4,
        array('maxLength' => 255)
    );
    $form->addSubmitButton(
        'btn_rename',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
    );

    $page->assignSmartyVariable('nameUserCreated', $nameUserCreated);
    $page->assignSmartyVariable('timestampUserCreated', $timestampUserCreated);
    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
