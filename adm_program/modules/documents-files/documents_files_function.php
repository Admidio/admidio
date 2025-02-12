<?php
/**
 ***********************************************************************************************
 * Various functions for documents & files module
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Parameters:
 *
 * mode   :  create_folder - Create folder
 *           delete_folder - Delete folder
 *           delete_file   - Delete file
 *           permissions   - Save permissions of folder
 *           add    - Add file/folder to database
 *           rename - Rename file/folder
 *           move   - Move file / folder
 * folder_uuid : UUID of the folder in the database
 * file_uuid   : UUID of the file in the database
 * name        : Name of the file/folder that should be added to the database
 ***********************************************************************************************/

use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\RolesRights;

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // Initialize and check the parameters
    $getMode       = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('create_folder', 'delete_folder', 'delete_file', 'add', 'rename', 'move', 'permissions')));
    $getFolderUuid = admFuncVariableIsValid($_GET, 'folder_uuid', 'uuid');
    $getFileUuid   = admFuncVariableIsValid($_GET, 'file_uuid', 'uuid');
    $getName       = admFuncVariableIsValid($_GET, 'name', 'file');

    // check if the module is enabled and disallow access if it's disabled
    if (!$gSettingsManager->getBool('documents_files_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // Check path in adm_my_files and create if necessary
    FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/' . Folder::getRootFolderName());

    // check the rights of the current folder
    // user must be administrator or must have the right to upload files
    $folder = new Folder($gDb);
    $folder->getFolderForDownload($getFolderUuid);

    if (!$folder->hasUploadRight()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // check the CSRF token of the form against the session token
    if (in_array($getMode, array('delete_folder', 'delete_file'))) {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
    }

    // Delete file
    if ($getMode === 'delete_file') {
        if ($getFileUuid !== '') {
            // get recordset of current file from database
            $file = new File($gDb);
            $file->getFileForDownload($getFileUuid);

            $file->delete();
            echo json_encode(array('status' => 'success'));
            exit();
        } else {
            // if no file id was set then show error
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
    }

    // create folder
    elseif ($getMode === 'create_folder') {
        if ($getFolderUuid === '') {
            // Folder UUID is required to create a sub-folder
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        try {
            $newFolderName = admFuncVariableIsValid($_POST, 'new_folder', 'file', array('requireValue' => true));
            $newFolderDescription = admFuncVariableIsValid($_POST, 'new_description', 'string');

            // check form field input and sanitized it from malicious content
            $documentsFilesFolderNewForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $documentsFilesFolderNewForm->validate($_POST);

            // Test if the folder already exists in the file system
            if (is_dir($folder->getFullFolderPath() . '/' . $newFolderName)) {
                throw new Exception('SYS_FOLDER_EXISTS', array($newFolderName));
            } else {
                // create folder
                $error = $folder->createFolder($newFolderName);

                if ($error === null) {
                    $folId = (int) $folder->getValue('fol_id');

                    // add folder to database
                    $newFolder = new Folder($gDb);

                    $newFolder->setValue('fol_fol_id_parent', $folId);
                    $newFolder->setValue('fol_type', 'DOCUMENTS');
                    $newFolder->setValue('fol_name', $newFolderName);
                    $newFolder->setValue('fol_description', $newFolderDescription);
                    $newFolder->setValue('fol_path', $folder->getFolderPath());
                    $newFolder->setValue('fol_locked', $folder->getValue('fol_locked'));
                    $newFolder->setValue('fol_public', $folder->getValue('fol_public'));
                    $newFolder->save();

                    // get roles rights of parent folder
                    $rightParentFolderView = new RolesRights($gDb, 'folder_view', $folId);
                    $newFolder->addRolesOnFolder('folder_view', $rightParentFolderView->getRolesIds());
                    $rightParentFolderUpload = new RolesRights($gDb, 'folder_upload', $folId);
                    $newFolder->addRolesOnFolder('folder_upload', $rightParentFolderUpload->getRolesIds());
                } else {
                    // the corresponding folder could not be created
                    throw new Exception($gL10n->get($error['text'], array($error['path'], '<a href="mailto:' . $gCurrentOrganization->getValue('org_email_administrator') . '">', '</a>')));
                }

                $gNavigation->deleteLastUrl();
                echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
                exit();
            }
        } catch (Exception $e) {
            if ($e->getMessage() === 'SYS_FILENAME_EMPTY') {
                throw new Exception('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME')));
            }
            if ($e->getMessage() === 'SYS_FILENAME_INVALID') {
                throw new Exception('SYS_FOLDER_NAME_INVALID');
            }
            throw new Exception($e->getMessage());
        }
    }

    // rename folder or file
    elseif ($getMode === 'rename') {
        if (!$getFileUuid && !$getFolderUuid) {
            // file UUID and/or folder UUID must be set
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        try {
            $newName = admFuncVariableIsValid($_POST, 'new_name', 'file', array('requireValue' => true));
            $newDescription = admFuncVariableIsValid($_POST, 'new_description', 'string');

            // check form field input and sanitized it from malicious content
            $documentsFilesRenameForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $documentsFilesRenameForm->validate($_POST);

            if ($getFileUuid !== '') {
                // get recordset of current file from database and throw exception if necessary
                $file = new File($gDb);
                $file->getFileForDownload($getFileUuid);

                $oldFile = $file->getFullFilePath();
                $newPath = $file->getFullFolderPath() . '/';
                $newFile = $newName . '.' . pathinfo($oldFile, PATHINFO_EXTENSION);

                // check if file already exists in filesystem
                if ($newFile !== $file->getValue('fil_name') && is_file($newPath . $newFile)) {
                    throw new Exception('SYS_FILE_EXIST', array($newFile));
                } else {
                    $oldName = $file->getValue('fil_name');

                    if ($newFile !== $file->getValue('fil_name')) {
                        // rename file in filesystem and database
                        try {
                            FileSystemUtils::moveFile($oldFile, $newPath . $newFile);
                        } catch (RuntimeException $exception) {
                            throw new Exception('SYS_FILE_RENAME_ERROR', array($oldName));
                        }
                    }

                    $file->setValue('fil_name', $newFile);
                    $file->setValue('fil_description', $newDescription);
                    $file->save();

                    $gNavigation->deleteLastUrl();
                    echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
                    exit();
                }
            } elseif ($getFolderUuid !== '') {
                // main folder could not be renamed
                if ($folder->getValue('fol_fol_id_parent') === '') {
                    throw new Exception('SYS_INVALID_PAGE_VIEW');
                }

                $oldFolder = $folder->getFullFolderPath();
                $newFolder = $newName;

                // check if folder already exists in filesystem
                if ($newFolder !== $folder->getValue('fol_name')
                && is_dir(ADMIDIO_PATH. $folder->getValue('fol_path'). '/'.$newFolder)) {
                    throw new Exception('SYS_FOLDER_EXISTS', array($newFolder));
                } else {
                    $oldName = $folder->getValue('fol_name');

                    if ($newFolder !== $folder->getValue('fol_name')) {
                        // rename folder in filesystem and database
                        try {
                            FileSystemUtils::moveDirectory($oldFolder, ADMIDIO_PATH. $folder->getValue('fol_path'). '/'.$newFolder);

                            $folder->rename($newFolder, $folder->getValue('fol_path'));
                        } catch (RuntimeException $exception) {
                            throw new Exception('SYS_FOLDER_RENAME_ERROR', array($oldName));
                        }
                    }

                    $folder->setValue('fol_description', $newDescription);
                    $folder->save();

                    $gNavigation->deleteLastUrl();
                    echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
                    exit();
                }
            }
        }
        // exception handling; replace some exception strings with better descriptions
        catch (Exception $e) {
            if ($e->getMessage() === 'SYS_FILENAME_EMPTY') {
                throw new Exception('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NEW_NAME')));
            }
            if ($e->getMessage() === 'SYS_FILENAME_INVALID' && $getFolderUuid !== '') {
                throw new Exception('SYS_FOLDER_NAME_INVALID');
            }
            throw new Exception($e->getMessage());
        }
    }

    // delete folder
    elseif ($getMode === 'delete_folder') {
        if ($getFolderUuid === '') {
            // the uuid of the current folder must be set
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        } else {
            $folder->delete();
            echo json_encode(array('status' => 'success'));
            exit();
        }
    }

    // add file / folder to database
    elseif ($getMode === 'add') {
        if ($getFolderUuid === '') {
            // the uuid of the current folder must be set
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // only users with download administration rights should set new roles rights
        if (!$gCurrentUser->administrateDocumentsFiles()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // add the file or folder recursively to the database
        $folder->addFolderOrFileToDatabase($getName);

        // back to previous page
        $gNavigation->addUrl(CURRENT_URL);
        admRedirect(ADMIDIO_URL . '/adm_program/system/back.php');
        // => EXIT
    }

    // save view or upload rights for a folder
    elseif ($getMode === 'permissions') {
        if (!isset($_POST['adm_roles_view_right'])) {
            throw new Exception('SYS_FIELD_EMPTY', array('SYS_VISIBLE_FOR'));
        }
        if (!isset($_POST['adm_roles_upload_right'])) {
            // upload right does not need to be set because documents & files module administrators still
            // have the right, so initialize the parameter
            $_POST['adm_roles_upload_right'] = array();
        }

        if ($getFolderUuid === '' || !is_array($_POST['adm_roles_view_right']) || !is_array($_POST['adm_roles_upload_right'])) {
            // the uuid of the current folder must be set
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // only users with documents & files administration rights should set new roles rights
        if (!$gCurrentUser->administrateDocumentsFiles()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // check form field input and sanitized it from malicious content
        $documentsFilesFolderPermissionsForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $documentsFilesFolderPermissionsForm->validate($_POST);

        $postIntRolesViewRight   = array_map('intval', $_POST['adm_roles_view_right']);
        $postIntRolesUploadRight = array_map('intval', $_POST['adm_roles_upload_right']);

        // Read current view roles rights of the folder
        $rightFolderView = new RolesRights($gDb, 'folder_view', $folder->getValue('fol_id'));
        $rolesFolderView = $rightFolderView->getRolesIds();

        // Read current upload roles rights of the folder
        $rightFolderUpload = new RolesRights($gDb, 'folder_upload', $folder->getValue('fol_id'));
        $rolesFolderUpload = $rightFolderUpload->getRolesIds();

        // get new roles and removed roles
        $addUploadRoles = array_diff($postIntRolesUploadRight, $rolesFolderUpload);
        $removeUploadRoles = array_diff($rolesFolderUpload, $postIntRolesUploadRight);

        if (in_array(0, $postIntRolesViewRight, true)) {
            // set flag public for this folder and all child folders
            $folder->editPublicFlagOnFolder(true);
            // if all users have access then delete all existing roles
            $folder->removeRolesOnFolder('folder_view', $rolesFolderView);
        } else {
            // set flag public for this folder and all child folders
            $folder->editPublicFlagOnFolder(false);

            // get new roles and removed roles
            $addViewRoles = array_unique(array_merge(array_diff($postIntRolesViewRight, $rolesFolderView), $postIntRolesUploadRight, $rolesFolderUpload));
            $removeViewRoles = array_diff($rolesFolderView, $postIntRolesViewRight);

            $folder->addRolesOnFolder('folder_view', $addViewRoles);
            $folder->removeRolesOnFolder('folder_view', $removeViewRoles);

            // upload right should not contain removed view roles
            $removeUploadRoles = array_merge($removeUploadRoles, $removeViewRoles);
        }

        $folder->addRolesOnFolder('folder_upload', $addUploadRoles);
        $folder->removeRolesOnFolder('folder_upload', $removeUploadRoles);
        $folder->save();

        $gNavigation->deleteLastUrl();
        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    }
    // move file to another folder
    elseif ($getMode === 'move') {
        $destFolderUUID = admFuncVariableIsValid($_POST, 'dest_folder_uuid', 'string', array('requireValue' => true));

        // check form field input and sanitized it from malicious content
        $documentsFilesMoveForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $documentsFilesMoveForm->validate($_POST);

        if ($getFileUuid !== '') {
            $file = new File($gDb);
            $file->readDataByUuid($getFileUuid);
            $file->moveToFolder($destFolderUUID);
        } else {
            $folder = new Folder($gDb);
            $folder->readDataByUuid($getFolderUuid);
            $folder->moveToFolder($destFolderUUID);
        }

        $gNavigation->deleteLastUrl();
        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    }
} catch (Exception | Exception | RuntimeException | UnexpectedValueException $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
