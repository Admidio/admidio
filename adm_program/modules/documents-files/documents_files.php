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

use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getFolderUUID = admFuncVariableIsValid($_GET, 'folder_uuid', 'uuid');
    $haveGetFolderUUID = ($getFolderUUID !== ''); // When no folder UUID is given, history should be for the whole folder structure. The UUID will be set to default below!

    // Check if module is activated
    if (!$gSettingsManager->getBool('documents_files_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // get recordset of current folder from database
    $currentFolder = new Folder($gDb);
    $currentFolder->getFolderForDownload($getFolderUUID);

    // set headline of the script
    if ($currentFolder->getValue('fol_fol_id_parent') == null) {
        $headline = $gL10n->get('SYS_DOCUMENTS_FILES');
    } else {
        $headline = $gL10n->get('SYS_DOCUMENTS_FILES') . ' - ' . $currentFolder->getValue('fol_name');
    }

    if ($getFolderUUID !== '') {
        // add URL to navigation stack
        $gNavigation->addUrl(CURRENT_URL, $currentFolder->getValue('fol_name'));
    } else {
        // Navigation of the module starts here
        $gNavigation->addStartUrl(CURRENT_URL, $gL10n->get('SYS_DOCUMENTS_FILES'), 'bi-file-earmark-arrow-down-fill');
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
                SecurityUtils::encodeUrl(ADMIDIO_URL . '/adm_program/system/file_upload.php', array('module' => 'documents_files', 'uuid' => $getFolderUUID)),
                'bi-upload'
            );

            $page->addPageFunctionsMenuItem(
                'menu_item_documents_create_folder',
                $gL10n->get('SYS_CREATE_FOLDER'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/folder_new.php', array('folder_uuid' => $getFolderUUID)),
                'bi-plus-circle-fill'
            );

            if ($currentFolder->getValue('fol_fol_id_parent') > 0) {
                $page->addPageFunctionsMenuItem(
                    'menu_item_documents_edit_folder',
                    $gL10n->get('SYS_EDIT_FOLDER'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/rename.php', array('folder_uuid' => $getFolderUUID)),
                    'bi-pencil-square'
                );
            }
        }

        if ($gCurrentUser->adminDocumentsFiles()) {
            $page->addPageFunctionsMenuItem(
                'menu_item_documents_permissions',
                $gL10n->get('SYS_PERMISSIONS'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files/folder_permissions.php', array('folder_uuid' => $getFolderUUID)),
                'bi-shield-lock-fill'
            );
        }
        if ($gSettingsManager->getBool('profile_log_edit_fields')) { // TODO_RK: More fine-grained logging settings
            // show link to view change history
            $page->addPageFunctionsMenuItem(
                'menu_item_folder_change_history',
                $gL10n->get('SYS_CHANGE_HISTORY'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/changelog.php', array('table' => 'folders,files,roles_rights_data', 'uuid' => $haveGetFolderUUID?$getFolderUUID:'')),
                'bi-clock-history'
            );
        }
        
    }

    $page->readData($getFolderUUID);
    $page->createContentList();
    $page->show();
} catch (Exception $e) {
    if ($e->getMessage() === 'LOGIN') {
        require_once(ADMIDIO_PATH . FOLDER_SYSTEM . '/login_valid.php');
    } else {
        $gMessage->show($e->getMessage());
    }
}
