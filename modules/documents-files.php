<?php
/**
 ***********************************************************************************************
 * Show content of the documents module and handle the navigation
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode : list          - Show all documents and files of the current folder
 *     new_folder       - Show form to create a new folder
 *     new_folder_save  - Save the data of the new folder form
 *     folder_rename    - Show form to edit the current folder or create a new folder
 *     folder_rename_save - Save the data of the folder edit form
 *     folder_delete    - Delete the selected folder with all files and subfolders
 *     file_rename      - Show form to edit the current folder or create a new folder
 *     file_rename_save - Save the data of the file edit form
 *     move             - Move a file or folder to another folder
 *     move_save        - Move a file or folder to another folder
 *     copy             - Show form to copy a file to another folder
 *     copy_save        - Copy a file to another folder
 *     copy_check       - Return as JSON the name that a copy of a file would get in a folder
 *     permissions      - Show form to set the permissions of the current folder
 *     permissions_save - Save the data of the permissions form
 *     add              - Add unmanaged file or folder to database
 *     download         - Download a file
 *     tree             - Return the visible subfolders of a folder as JSON for the folder tree
 * folder_uuid        : UUID of the folder that should be shown, edited or saved
 * file_uuid          : UUID of the file that should be edited
 * with_path          : Could be set in **tree** mode. If set to true, then the response contains the UUIDs of the folders
 *                       from the root folder to the requested folder.
 * name               : Name of un unmanaged file or folder that should be added to the database
 * view               : Could be set in **download+* mode. If set to true, then the file output will directly be shown in the browser.
 ***********************************************************************************************
 */

use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Documents\Service\DocumentsService;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\DocumentsPresenter;

try {
    require_once(__DIR__ . '/../system/common.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string',
        array(
            'defaultValue' => 'list',
            'validValues' => array(
                'list',
                'new_folder',
                'new_folder_save',
                'folder_rename',
                'folder_rename_save',
                'folder_delete',
                'file_rename',
                'file_rename_save',
                'file_delete',
                'move',
                'move_save',
                'copy',
                'copy_save',
                'copy_check',
                'permissions',
                'permissions_save',
                'add',
                'download',
                'tree')
        )
    );
    $getFolderUUID = admFuncVariableIsValid($_GET, 'folder_uuid', 'uuid');
    $getFileUUID = admFuncVariableIsValid($_GET, 'file_uuid', 'uuid');
    $getDestinationUUID = admFuncVariableIsValid($_GET, 'destination_uuid', 'uuid');
    $getWithPath = admFuncVariableIsValid($_GET, 'with_path', 'bool', array('defaultValue' => false));

    // Check if the module is activated
    if ($gSettingsManager->getInt('documents_files_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ($gSettingsManager->getInt('documents_files_module_enabled') === 2 && !$gValidLogin) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if (in_array($getMode, array('copy', 'copy_save', 'copy_check'))) {
        // A file can be copied by everybody who is allowed to see it. The right to upload to the destination folder
        // is checked when the file is copied.
        if ($getFileUUID === '') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $file = new File($gDb);
        $file->getFileForDownload($getFileUUID);
        $getFolderUUID = $file->getValue('fol_uuid');

        $folder = new Folder($gDb);
        $folder->getFolderForDownload($getFolderUUID);
    } elseif (!in_array($getMode, array('list', 'download', 'tree'))) {
        if ($getFileUUID !== '') {
            // when file UUID is set then read the folder of that file from database and check the rights
            $file = new File($gDb);
            $file->readDataByUuid($getFileUUID);
            $getFolderUUID = $file->getValue('fol_uuid');
        }
        // check the rights of the current folder
        // user must be administrator or must have the right to upload files
        $folder = new Folder($gDb);
        $folder->getFolderForDownload($getFolderUUID);

        if (!$folder->hasUploadRight()) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    }

    switch ($getMode) {
        case 'list':
            // create an HTML page object
            $page = new DocumentsPresenter($getFolderUUID);
            $page->createList();
            if ($getFolderUUID !== '') {
                $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            } else {
                $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-file-earmark-arrow-down-fill');
            }
            $page->show();
            break;

        case 'new_folder':
            // create an HTML page object
            $page = new DocumentsPresenter($getFolderUUID);
            $page->createFolderNewForm();
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'new_folder_save':
            $documentsService = new DocumentsService($gDb, $getFolderUUID);
            $documentsService->saveNewFolder();

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'folder_rename':
            $page = new DocumentsPresenter($getFolderUUID);
            $page->createFolderRenameForm();
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'folder_rename_save':
            $documentsService = new DocumentsService($gDb, $getFolderUUID);
            $documentsService->renameFolder();

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'folder_delete':
            if ($getFolderUUID === '') {
                // the uuid of the current folder must be set
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            } else {
                SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

                $folder = new Folder($gDb);
                $folder->getFolderForDownload($getFolderUUID);

                $folder->delete();
                echo json_encode(array('status' => 'success'));
            }
            break;

        case 'file_rename':
            $page = new DocumentsPresenter($getFolderUUID);
            $page->createFileRenameForm($getFileUUID);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'file_rename_save':
            $documentsService = new DocumentsService($gDb, $getFolderUUID);
            $documentsService->renameFile($getFileUUID);

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'file_delete':
            if ($getFileUUID === '') {
                // if no file id was set then show error
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            } else {
                SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

                $file = new File($gDb);
                $file->getFileForDownload($getFileUUID);

                $file->delete();
                echo json_encode(array('status' => 'success'));
            }
            break;

        case 'move':
            $page = new DocumentsPresenter($getFolderUUID);
            $page->createFolderFileMoveForm($getFileUUID);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'move_save':
            // check form field input and sanitized it from malicious content
            $documentsFilesMoveForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $documentsFilesMoveForm->validate($_POST);

            if ($formValues['adm_destination_folder_uuid'] === '') {
                throw new Exception('SYS_FIELD_EMPTY', array($gL10n->get('SYS_MOVE_TO')));
            }

            if ($getFileUUID !== '') {
                $file = new File($gDb);
                $file->readDataByUuid($getFileUUID);
                $file->moveToFolder($formValues['adm_destination_folder_uuid']);
            } else {
                $folder = new Folder($gDb);
                $folder->readDataByUuid($getFolderUUID);
                $folder->moveToFolder($formValues['adm_destination_folder_uuid']);
            }

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'copy':
            $page = new DocumentsPresenter($getFolderUUID);
            $page->createFolderFileMoveForm($getFileUUID, true);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'copy_save':
            // check form field input and sanitized it from malicious content
            $documentsFilesMoveForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $documentsFilesMoveForm->validate($_POST);

            if ($formValues['adm_destination_folder_uuid'] === '') {
                throw new Exception('SYS_FIELD_EMPTY', array($gL10n->get('SYS_MOVE_TO')));
            }

            $file->copyToFolder($formValues['adm_destination_folder_uuid'], $formValues['adm_accept_new_name'] === '1');

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'copy_check':
            // the name of the copy in the destination folder, the dialog asks the user if it is a new name
            $destinationFolder = new Folder($gDb);
            $destinationFolder->readDataByUuid($getDestinationUUID);

            if (!$destinationFolder->hasUploadRight()) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            $nameOfCopy = $file->getNameOfCopy($destinationFolder);
            echo json_encode(array(
                'status' => 'success',
                'newName' => $nameOfCopy,
                'nameExists' => $nameOfCopy !== $file->getValue('fil_name'),
                'messageNameExists' => $gL10n->get('SYS_COPY_FILE_NAME_EXISTS', array($file->getValue('fil_name'))),
                'messageNewName' => $gL10n->get('SYS_COPY_FILE_NEW_NAME', array($nameOfCopy))
            ));
            break;

        case 'permissions':
            $page = new DocumentsPresenter($getFolderUUID);
            $page->createFolderPermissionsForm();
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'permissions_save':
            $documentsService = new DocumentsService($gDb, $getFolderUUID);
            $documentsService->savePermissions();

            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'add':
            SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
            $getName = admFuncVariableIsValid($_GET, 'name', 'file');

            // only users with download administration rights should set new roles rights
            if (!$gCurrentUser->isAdministratorDocumentsFiles()) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            // add the file or folder recursively to the database
            $folder = new Folder($gDb);
            $folder->readDataByUuid($getFolderUUID);
            $folder->addFolderOrFileToDatabase($getName);

            // back to the previous page
            $gNavigation->addUrl(CURRENT_URL);
            admRedirect(ADMIDIO_URL . FOLDER_SYSTEM . '/back.php');
            // => EXIT
            break;

        case 'tree':
            // the user must be allowed to see the folder, otherwise an exception is thrown
            $folder = new Folder($gDb);
            $folder->getFolderForDownload($getFolderUUID);

            $documentsService = new DocumentsService($gDb, $folder->getValue('fol_uuid'));
            $response = array('status' => 'success', 'nodes' => $documentsService->findFolderTreeNodes());
            if ($getWithPath) {
                // the tree can open the folders down to this folder
                $response['path'] = $documentsService->findFolderPath();
            }
            echo json_encode($response);
            break;

        case 'download':
            $getView = admFuncVariableIsValid($_GET, 'view', 'bool', array('defaultValue' => false));

            $documentsService = new DocumentsService($gDb);
            try {
                $documentsService->downloadFile($getFileUUID, $getView);
            } catch (Exception $e) {
                // A visitor who isn't logged in should see the login page, like for the link of a folder.
                // After the login the file will be shown if the user has the right to see it.
                if (!$gValidLogin && $e->getMessage() === $gL10n->get('SYS_FOLDER_NO_RIGHTS')) {
                    throw new Exception('LOGIN');
                }
                throw $e;
            }
            break;
    }
} catch (Throwable $e) {
    if ($e->getMessage() === 'LOGIN') {
        require_once(ADMIDIO_PATH . FOLDER_SYSTEM . '/login_valid.php');
    } else {
        handleException($e, in_array($getMode, array('new_folder_save', 'folder_rename_save', 'folder_delete', 'file_rename_save', 'file_delete', 'move_save', 'copy_save', 'copy_check', 'permissions_save', 'tree')));
    }
}
