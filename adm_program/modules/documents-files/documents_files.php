<?php
/**
 ***********************************************************************************************
 * Show a list of all folders and files
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * folder_uuid : UUID of the current folder that should be shown
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

try {
    unset($_SESSION['documents_files_request']);

    // Initialize and check the parameters
    $getFolderUUID = admFuncVariableIsValid($_GET, 'folder_uuid', 'string');

    // Check if module is activated
    if (!$gSettingsManager->getBool('documents_files_module_enabled')) {
        $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
        // => EXIT
    }

    // get recordset of current folder from database
    $currentFolder = new TableFolder($gDb);
    $currentFolder->getFolderForDownload($getFolderUUID);

    // set headline of the script
    if ($currentFolder->getValue('fol_fol_id_parent') == null) {
        $headline = $gL10n->get('SYS_DOCUMENTS_FILES');
    } else {
        $headline = $gL10n->get('SYS_DOCUMENTS_FILES').' - '.$currentFolder->getValue('fol_name');
    }

    if ($getFolderUUID !== '') {
        // add URL to navigation stack
        $gNavigation->addUrl(CURRENT_URL, $currentFolder->getValue('fol_name'));
    } else {
        // Navigation of the module starts here
        $gNavigation->addStartUrl(CURRENT_URL, $gL10n->get('SYS_DOCUMENTS_FILES'), 'fa-file-download');
    }

    $getFolderUUID = $currentFolder->getValue('fol_uuid');

    // create html page object
    $page = new ModuleDocumentsFiles('admidio-documents-files', $headline);

    if ($currentFolder->hasUploadRight()) {
        // upload only possible if upload filesize > 0
        if ($gSettingsManager->getInt('documents_files_max_upload_size') > 0) {
            // show links for upload, create folder and folder configuration
            $page->addPageFunctionsMenuItem(
                'menu_item_documents_upload_files',
                $gL10n->get('SYS_UPLOAD_FILES'),
                SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/file_upload.php', array('module' => 'documents_files', 'uuid' => $getFolderUUID)),
                'fa-upload'
            );

            $page->addPageFunctionsMenuItem(
                'menu_item_documents_create_folder',
                $gL10n->get('SYS_CREATE_FOLDER'),
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/folder_new.php', array('folder_uuid' => $getFolderUUID)),
                'fa-plus-circle'
            );

            if ($currentFolder->getValue('fol_fol_id_parent') > 0) {
                $page->addPageFunctionsMenuItem(
                    'menu_item_documents_edit_folder',
                    $gL10n->get('SYS_EDIT_FOLDER'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/rename.php', array('folder_uuid' => $getFolderUUID)),
                    'fa-edit'
                );
            }
        }

        if ($gCurrentUser->adminDocumentsFiles()) {
            $page->addPageFunctionsMenuItem(
                'menu_item_documents_permissions',
                $gL10n->get('SYS_PERMISSIONS'),
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/documents-files/folder_config.php', array('folder_uuid' => $getFolderUUID)),
                'fa-lock'
            );
        }
    }

    $page->readData($getFolderUUID);
    $page->createContentList();
    $page->show();
} catch (AdmException | Exception | SmartyException $e) {
    $gMessage->show($e->getMessage());
}
