<?php
/**
 ***********************************************************************************************
 * Rename a file or a folder of download module
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * folder_uuid :  UUID of the folder that should be renamed
 * file_uuid   :  UUID of the file that should be renamed
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getFolderUuid = admFuncVariableIsValid($_GET, 'folder_uuid', 'string');
$getFileUuid   = admFuncVariableIsValid($_GET, 'file_uuid', 'string');

// set headline of the script
if ($getFileUuid !== '') {
    $headline = $gL10n->get('SYS_EDIT_FILE');
} else {
    $headline = $gL10n->get('SYS_EDIT_FOLDER');
}

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('documents_files_enable_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$gNavigation->addUrl(CURRENT_URL, $headline);

if (isset($_SESSION['documents_files_request'])) {
    $formValues = SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['documents_files_request']));
    unset($_SESSION['documents_files_request']);
} else {
    $formValues['new_name'] = null;
    $formValues['new_description'] = null;
}

try {
    // check the rights of the current folder
    // user must be administrator or must have the right to upload files
    $targetFolder = new TableFolder($gDb);
    $targetFolder->getFolderForDownload($getFolderUuid);
} catch (AdmException $e) {
    $e->showHtml();
    // => EXIT
}

if (!$targetFolder->hasUploadRight()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$originalName    = '';
$fileType        = '';
$createUserId    = 0;
$createTimestamp = '';

try {
    if ($getFileUuid !== '') {
        // get recordset of current file from database
        $file = new TableFile($gDb);
        $file->getFileForDownload($getFileUuid);

        $originalName    = pathinfo($file->getValue('fil_name'), PATHINFO_FILENAME);
        $fileType        = pathinfo($file->getValue('fil_name'), PATHINFO_EXTENSION);
        $createUserId    = (int) $file->getValue('fil_usr_id');
        $createTimestamp = $file->getValue('fil_timestamp');

        if ($formValues['new_name'] === null) {
            $formValues['new_name'] = $originalName;
        }

        if ($formValues['new_description'] === null) {
            $formValues['new_description'] = $file->getValue('fil_description');
        }
    } else {
        // main folder should not be renamed
        if ($targetFolder->getValue('fol_fol_id_parent') === '') {
            $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
            // => EXIT
        }

        // read folder data to rename the folder
        $originalName    = $targetFolder->getValue('fol_name');
        $createUserId    = (int) $targetFolder->getValue('fol_usr_id');
        $createTimestamp = $targetFolder->getValue('fol_timestamp');

        if ($formValues['new_name'] == null) {
            $formValues['new_name'] = $originalName;
        }

        if ($formValues['new_description'] == null) {
            $formValues['new_description'] = $targetFolder->getValue('fol_description');
        }
    }
} catch (AdmException $e) {
    $e->showHtml();
    // => EXIT
}

// create html page object
$page = new HtmlPage('admidio-documents-files-rename', $headline);

// create html form
$form = new HtmlForm('edit_download_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files_function.php', array('mode' => '4', 'folder_uuid' => $getFolderUuid, 'file_uuid' => $getFileUuid)), $page);
if ($getFileUuid !== '') {
    $form->addInput(
        'file_type',
        $gL10n->get('SYS_FILE_TYPE'),
        $fileType,
        array('property' => HtmlForm::FIELD_DISABLED, 'class' => 'form-control-small')
    );
}
$form->addInput(
    'previous_name',
    $gL10n->get('SYS_PREVIOUS_NAME'),
    $originalName,
    array('property' => HtmlForm::FIELD_DISABLED)
);
$form->addInput(
    'new_name',
    $gL10n->get('SYS_NEW_NAME'),
    $formValues['new_name'],
    array('maxLength' => 255, 'property' => HtmlForm::FIELD_REQUIRED, 'helpTextIdLabel' => 'SYS_FILE_NAME_RULES')
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
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);
$form->addHtml(admFuncShowCreateChangeInfoById($createUserId, $createTimestamp));

$page->addHtml($form->show());
$page->show();
