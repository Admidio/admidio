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

use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getFolderUuid = admFuncVariableIsValid($_GET, 'folder_uuid', 'uuid');
    $getFileUuid = admFuncVariableIsValid($_GET, 'file_uuid', 'uuid');

    // check if the module is enabled and disallow access if it's disabled
    if (!$gSettingsManager->getBool('documents_files_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // check the rights of the current folder
    // user must be administrator or must have the right to upload files
    $targetFolder = new Folder($gDb);
    $targetFolder->getFolderForDownload($getFolderUuid);

    if (!$targetFolder->hasUploadRight()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // set headline and description
    if ($getFileUuid !== '') {
        $file = new File($gDb);
        $file->readDataByUuid($getFileUuid);
        $headline = $gL10n->get('SYS_MOVE_FILE');
        $description = $gL10n->get('SYS_MOVE_FILE_DESC', array($file->getValue('fil_name')));
    } else {
        $folder = new Folder($gDb);
        $folder->readDataByUuid($getFolderUuid);
        $headline = $gL10n->get('SYS_MOVE_FOLDER');
        $description = $gL10n->get('SYS_MOVE_FOLDER_DESC', array($folder->getValue('fol_name')));
    }

    $gNavigation->addUrl(CURRENT_URL, $headline);

    $documentsFiles = new ModuleDocumentsFiles('admidio-documents-move-file', $headline);
    $documentsFiles->assignSmartyVariable('description', $description);
    $folders = $documentsFiles->getUploadableFolderStructure();

    // create html form
    $form = new FormPresenter(
        'adm_documents_files_move_file',
        'modules/documents-files.move.tpl',
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/documents_files_function.php', array('mode' => 'move', 'folder_uuid' => $getFolderUuid, 'file_uuid' => $getFileUuid)),
        $documentsFiles
    );
    $form->addSelectBox(
        'dest_folder_uuid',
        $gL10n->get('SYS_MOVE_TO'),
        $folders,
        array(
            'property' => FormPresenter::FIELD_REQUIRED,
            'defaultValue' => $getFolderUuid,
            'showContextDependentFirstEntry' => false
        )
    );
    $form->addSubmitButton(
        'btn_move',
        $gL10n->get('SYS_SAVE'),
        array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
    );

    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);
    $documentsFiles->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
