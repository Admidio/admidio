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
 *     permissions      - Show form to set the permissions of the current folder
 *     permissions_save - Save the data of the permissions form
 *     add              - Add unmanaged file or folder to database
 * folder_uuid        : UUID of the folder that should be shown, edited or saved
 * file_uuid          : UUID of the file that should be edited
 * name               : Name of un unmanaged file or folder that should be added to the database.
 ***********************************************************************************************
 */

use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Documents\Service\DocumentsService;
use Admidio\Infrastructure\Exception;
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
                'permissions',
                'permissions_save',
                'add')
        )
    );
    $getFolderUUID = admFuncVariableIsValid($_GET, 'folder_uuid', 'uuid',
        array(
            'requireValue' => !in_array($getMode, array('list', 'file_delete'))
        )
    );
    $getFileUUID = admFuncVariableIsValid($_GET, 'file_uuid', 'uuid');

    // Check if module is activated
    if (!$gSettingsManager->getBool('documents_files_module_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    switch ($getMode) {
        case 'list':
            // create html page object
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
            // create html page object
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
            $getName = admFuncVariableIsValid($_GET, 'name', 'string');

            // only users with download administration rights should set new roles rights
            if (!$gCurrentUser->administrateDocumentsFiles()) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            // add the file or folder recursively to the database
            $folder = new Folder($gDb);
            $folder->readDataByUuid($getFolderUUID);
            $folder->addFolderOrFileToDatabase($getName);

            // back to previous page
            $gNavigation->addUrl(CURRENT_URL);
            admRedirect(ADMIDIO_URL . '/adm_program/system/back.php');
            // => EXIT
            break;
    }
} catch (Throwable $e) {
    if (in_array($getMode, array('new_folder_save', 'folder_rename_save', 'folder_delete', 'file_rename_save', 'file_delete', 'move_save', 'permissions_save'))) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
