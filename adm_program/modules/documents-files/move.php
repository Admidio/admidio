<?php
/**
 ***********************************************************************************************
 * Move a file to another folder of documents & files module
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
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getFolderUuid = admFuncVariableIsValid($_GET, 'folder_uuid', 'string');
$getFileUuid   = admFuncVariableIsValid($_GET, 'file_uuid', 'string');

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('documents_files_module_enabled')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

try {
    // check the rights of the current folder
    // user must be administrator or must have the right to upload files
    $targetFolder = new TableFolder($gDb);
    $targetFolder->getFolderForDownload($getFolderUuid);

    if (!$targetFolder->hasUploadRight()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
} catch (AdmException $e) {
    $e->showHtml();
    // => EXIT
}

// set headline and description
if ($getFileUuid !== '') {
    $file = new TableFile($gDb);
    $file->readDataByUuid($getFileUuid);
    $headline = $gL10n->get('SYS_MOVE_FILE');
    $description = $gL10n->get('SYS_MOVE_FILE_DESC', array($file->getValue('fil_name')));
} else {
    $folder = new TableFolder($gDb);
    $folder->readDataByUuid($getFolderUuid);
    $headline = $gL10n->get('SYS_MOVE_FOLDER');
    $description = $gL10n->get('SYS_MOVE_FOLDER_DESC', array($folder->getValue('fol_name')));
}

$gNavigation->addUrl(CURRENT_URL, $headline);

$documentsFiles = new ModuleDocumentsFiles('admidio-documents-move-file', $headline);
$folders = $documentsFiles->getUploadableFolderStructure();
$documentsFiles->addHtml('<p class="lead admidio-max-with">'.$description.'</p>');

// create html form
$form = new HtmlForm('documents_files_move_file', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files_function.php', array('mode' => '8', 'folder_uuid' => $getFolderUuid, 'file_uuid' => $getFileUuid)), $documentsFiles);
$form->addSelectBox(
    'dest_folder_uuid',
    $gL10n->get('SYS_MOVE_TO'),
    $folders,
    array(
        'property'                       => HtmlForm::FIELD_REQUIRED,
        'defaultValue'                   => $getFolderUuid,
        'showContextDependentFirstEntry' => false
    )
);
$form->addSubmitButton(
    'btn_move',
    $gL10n->get('SYS_SAVE'),
    array('icon' => 'fa-check', 'class' => ' offset-sm-3')
);

$documentsFiles->addHtml($form->show());
$documentsFiles->show();
