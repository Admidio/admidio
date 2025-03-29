<?php

namespace Admidio\Documents\Service;

use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Roles\Entity\RolesRights;
use DateTime;
use RuntimeException;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class DocumentsService
{
    /**
     * @var Database An object of the class Database for communication with the database
     */
    protected Database $db;
    /**
     * @var string UUID of the folder for which the topics should be filtered.
     */
    protected string $folderUUID = '';
    /**
     * @var Folder Object of the current folder
     */
    protected Folder $folder;
    /**
     * @var array Array with the folders and files of the current folder
     */
    protected array $folderContent = array();

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $folderUUID UUID of the folder for which the documents should be filtered.
     * @throws Exception
     */
    public function __construct(Database $database, string $folderUUID = '')
    {
        $this->db = $database;
        $this->folderUUID = $folderUUID;

        if ($folderUUID !== '') {
            $this->folder = new Folder($this->db);
            $this->folder->readDataByUuid($this->folderUUID);
        }
    }

    /**
     * Send the file to the user for download. Optional the parameter inline could be set and then the file will
     * be shown in the browser.
     * @param string $fileUUID The UUID of the file that should be downloaded.
     * @param bool $inline If set to **true** than the output will be sent inline otherwise as attachment.
     * @return void
     * @throws Exception
     */
    public function downloadFile(string $fileUUID, bool $inline = false): void
    {
        // get recordset of current file from database
        $file = new File($this->db);
        $file->getFileForDownload($fileUUID);

        // get complete path with filename of the file
        $completePath = $file->getFullFilePath();

        // check if the file already exists
        if (!is_file($completePath)) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        // Increment download counter
        $file->setValue('fil_counter', (int)$file->getValue('fil_counter') + 1);
        $file->save();

        // determine filesize
        $fileSize = filesize($completePath);

        if ($inline) {
            $content = 'inline';
        } else {
            $content = 'attachment';
        }

        // Create appropriate header information of the file
        header('Content-Type: ' . $file->getMimeType());
        header('Content-Length: ' . $fileSize);
        header('Content-Disposition: ' . $content . '; filename="' . $file->getValue('fil_name') . '"');

        // necessary for IE, because without it the download with SSL has problems
        header('Cache-Control: private');
        header('Pragma: public');

        // file output
        if ($fileSize > 10 * 1024 * 1024) {
            // file output for large files (> 10MB)
            $chunkSize = 1024 * 1024;
            $handle = fopen($completePath, 'rb');
            while (!feof($handle)) {
                $buffer = fread($handle, $chunkSize);
                echo $buffer;
                ob_flush();
                flush();
            }
            fclose($handle);
        } else {
            // file output for small files (< 10MB)
            readfile($completePath);
        }
    }

    /**
     * Read the data of the folder in an array.
     * The returned array contains arrays with the following information:
     * folder, uuid, name, description, timestamp, size, counter, existsInFileSystem
     * @return array{array{}} Returns an array with an array for each missing folder or file.
     * @throws Exception
     */
    public function findAll(): array
    {
        global $gSettingsManager;

        $data = array();
        $this->folderContent = array(
            'folders' => $this->folder->getSubfoldersWithProperties(),
            'files' => $this->folder->getFilesWithProperties()
        );

        foreach ($this->folderContent['folders'] as $folder) {
            $data[] = array(
                'folder' => true,
                'uuid' => $folder['fol_uuid'],
                'name' => $folder['fol_name'],
                'description' => (string)$folder['fol_description'],
                'timestamp' => DateTime::createFromFormat('Y-m-d H:i:s', $folder['fol_timestamp'])
                    ->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')),
                'size' => null,
                'counter' => null,
                'existsInFileSystem' => $folder['fol_exists']
            );
        }

        foreach ($this->folderContent['files'] as $file) {
            $data[] = array(
                'folder' => false,
                'uuid' => $file['fil_uuid'],
                'name' => $file['fil_name'],
                'description' => (string)$file['fil_description'],
                'timestamp' => DateTime::createFromFormat('Y-m-d H:i:s', $file['fil_timestamp'])
                    ->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')),
                'size' => round($file['fil_size'] / 1024) . ' kB&nbsp;',
                'counter' => $file['fil_counter'],
                'existsInFileSystem' => $file['fil_exists']
            );
        }

        return $data;
    }

    /**
     * Read the data of the folder in an array. Only folders and files that are currently not registered in the
     * database are returned.
     * @return array{array{folder: bool, name: string, size: int}} Returns an array with an array for each missing folder or file.
     * @throws Exception
     */
    public function findUnregisteredFoldersFiles(): array
    {
        if (!isset($this->folder)) {
            $this->folder = new Folder($this->db);
            $this->folder->readDataByUuid($this->folderUUID);
        }

        $unregisteredFoldersFiles = array();
        if (count($this->folderContent) === 0) {
            $this->folderContent = $this->findAll();
        }
        $additionalFolderContent = $this->folder->addAdditionalToFolderContents($this->folderContent);

        if (array_key_exists('additionalFolders', $additionalFolderContent)) {
            foreach ($additionalFolderContent['additionalFolders'] as $folder) {
                $unregisteredFoldersFiles[] = array(
                    'folder' => true,
                    'name' => $folder['fol_name'],
                    'size' => null
                );
            }
        }

        if (array_key_exists('additionalFiles', $additionalFolderContent)) {
            foreach ($additionalFolderContent['additionalFiles'] as $file) {
                $unregisteredFoldersFiles[] = array(
                    'folder' => false,
                    'name' => $file['fil_name'],
                    'size' => round($file['fil_size'] / 1024) . ' kB&nbsp;'
                );
            }
        }

        return $unregisteredFoldersFiles;
    }

    /**
     * Reads recursively all folders where the current user has the right to upload files. The array will represent the
     * nested structure of the folder with an indentation in the folder name.
     * @param string $folderID ID if the folder where the upload rights should be checked.
     * @param array<string,string> $arrAllUploadableFolders Array with all currently read uploadable folders. That
     *                         array will be supplemented with new folders of this method and then returned.
     * @param string $indent The current indent for the visualisation of the folder structure.
     * @return array<string,string> Returns an array with the folder UUID as key and the folder name as value.
     * @throws Exception
     */
    private function findFoldersWithUploadRights(string $folderID, array $arrAllUploadableFolders, string $indent = ''): array
    {
        // read all folders
        $sqlFiles = 'SELECT fol_id
                       FROM ' . TBL_FOLDERS . '
                      WHERE  fol_fol_id_parent = ? -- $folderID ';
        $filesStatement = $this->db->queryPrepared($sqlFiles, array($folderID));

        while ($row = $filesStatement->fetch()) {
            $folder = new Folder($this->db, $row['fol_id']);

            if ($folder->hasUploadRight()) {
                $arrAllUploadableFolders[$folder->getValue('fol_uuid')] = $indent . '- ' . $folder->getValue('fol_name');

                $arrAllUploadableFolders = $this->findFoldersWithUploadRights($row['fol_id'], $arrAllUploadableFolders, $indent . '&nbsp;&nbsp;&nbsp;');
            }
        }
        return $arrAllUploadableFolders;
    }

    /**
     * Reads recursively all folders where the current user has the right to upload files. The method will start at the
     * top documents and files folder of the current organization. The returned array will represent the
     * nested structure of the folder with an indentation in the folder name.
     * @return array<string,string> Returns an array with the folder UUID as key and the folder name as value.
     * @throws Exception
     */
    public function getUploadableFolderStructure(): array
    {
        global $gCurrentOrgId, $gDb, $gL10n;

        // read main documents folder
        $sqlFiles = 'SELECT fol_uuid, fol_id
                       FROM ' . TBL_FOLDERS . '
                      WHERE fol_fol_id_parent IS NULL
                        AND fol_org_id = ? -- $gCurrentOrgId
                        AND fol_type   = \'DOCUMENTS\' ';
        $filesStatement = $gDb->queryPrepared($sqlFiles, array($gCurrentOrgId));

        $row = $filesStatement->fetch();

        $arrAllUploadableFolders = array($row['fol_uuid'] => $gL10n->get('SYS_DOCUMENTS_FILES'));

        return $this->findFoldersWithUploadRights($row['fol_id'], $arrAllUploadableFolders);
    }

    /**
     * Save data from the topic form into the database.
     * @throws Exception
     */
    public function renameFile(string $fileUUID): void
    {
        global $gCurrentSession, $gL10n;

        try {
            // check form field input and sanitized it from malicious content
            $documentsFilesRenameForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $documentsFilesRenameForm->validate($_POST);

            // get recordset of current file from database and throw exception if necessary
            $file = new File($this->db);
            $file->getFileForDownload($fileUUID);

            $oldFile = $file->getFullFilePath();
            $newPath = $file->getFullFolderPath() . '/';
            $newFile = $formValues['adm_new_name'] . '.' . pathinfo($oldFile, PATHINFO_EXTENSION);

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
                $file->setValue('fil_description', $formValues['adm_new_description']);
                $file->save();
            }
        } // exception handling; replace some exception strings with better descriptions
        catch (Exception $e) {
            if ($e->getMessage() === 'SYS_FILENAME_EMPTY') {
                throw new Exception('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NEW_NAME')));
            }
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Save data from the topic form into the database.
     * @throws Exception
     */
    public function renameFolder(): void
    {
        global $gCurrentSession, $gL10n;

        if (!$this->folderUUID) {
            // file UUID and/or folder UUID must be set
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        try {
            // check form field input and sanitized it from malicious content
            $documentsFilesRenameForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $documentsFilesRenameForm->validate($_POST);

            // main folder could not be renamed
            if ($this->folder->getValue('fol_fol_id_parent') === '') {
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            }

            $oldFolder = $this->folder->getFullFolderPath();

            // check if folder already exists in filesystem
            if ($formValues['adm_new_name'] !== $this->folder->getValue('fol_name')
                && is_dir(ADMIDIO_PATH . $this->folder->getValue('fol_path') . '/' . $formValues['adm_new_name'])) {
                throw new Exception('SYS_FOLDER_EXISTS', array($formValues['adm_new_name']));
            } else {
                $oldName = $this->folder->getValue('fol_name');

                if ($formValues['adm_new_name'] !== $this->folder->getValue('fol_name')) {
                    // rename folder in filesystem and database
                    try {
                        FileSystemUtils::moveDirectory($oldFolder, ADMIDIO_PATH . $this->folder->getValue('fol_path') . '/' . $formValues['adm_new_name']);

                        $this->folder->rename($formValues['adm_new_name'], $this->folder->getValue('fol_path'));
                    } catch (RuntimeException $exception) {
                        throw new Exception('SYS_FOLDER_RENAME_ERROR', array($oldName));
                    }
                }

                $this->folder->setValue('fol_description', $formValues['adm_new_description']);
                $this->folder->save();
            }
        } // exception handling; replace some exception strings with better descriptions
        catch (Exception $e) {
            if ($e->getMessage() === 'SYS_FILENAME_EMPTY') {
                throw new Exception('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NEW_NAME')));
            }
            if ($e->getMessage() === 'SYS_FILENAME_INVALID' && $this->folderUUID !== '') {
                throw new Exception('SYS_FOLDER_NAME_INVALID');
            }
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Save data from the topic form into the database.
     * @return string UUID of the created folder.
     * @throws Exception
     */
    public function saveNewFolder(): string
    {
        global $gCurrentSession, $gL10n, $gCurrentOrganization;

        try {
            // check form field input and sanitized it from malicious content
            $documentsFilesFolderNewForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
            $formValues = $documentsFilesFolderNewForm->validate($_POST);

            // Test if the folder already exists in the file system
            if (is_dir($this->folder->getFullFolderPath() . '/' . $formValues['adm_folder_name'])) {
                throw new Exception('SYS_FOLDER_EXISTS', array($formValues['adm_folder_name']));
            } else {
                // create folder
                $error = $this->folder->createFolder($formValues['adm_folder_name']);

                if ($error === null) {
                    $folId = (int)$this->folder->getValue('fol_id');

                    // add folder to database
                    $newFolder = new Folder($this->db);

                    $newFolder->setValue('fol_fol_id_parent', $folId);
                    $newFolder->setValue('fol_type', 'DOCUMENTS');
                    $newFolder->setValue('fol_name', $formValues['adm_folder_name']);
                    $newFolder->setValue('fol_description', $formValues['adm_folder_description']);
                    $newFolder->setValue('fol_path', $this->folder->getFolderPath());
                    $newFolder->setValue('fol_locked', $this->folder->getValue('fol_locked'));
                    $newFolder->setValue('fol_public', $this->folder->getValue('fol_public'));
                    $newFolder->save();

                    // get roles rights of parent folder
                    $rightParentFolderView = new RolesRights($this->db, 'folder_view', $folId);
                    $newFolder->addRolesOnFolder('folder_view', $rightParentFolderView->getRolesIds());
                    $rightParentFolderUpload = new RolesRights($this->db, 'folder_upload', $folId);
                    $newFolder->addRolesOnFolder('folder_upload', $rightParentFolderUpload->getRolesIds());

                    return $newFolder->getValue('fol_uuid');
                } else {
                    // the corresponding folder could not be created
                    throw new Exception($gL10n->get($error['text'], array($error['path'], '<a href="mailto:' . $gCurrentOrganization->getValue('org_email_administrator') . '">', '</a>')));
                }
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

    /**
     * Save permissions of the folder into the database.
     * @throws Exception
     */
    public function savePermissions(): void
    {
        global $gCurrentSession, $gCurrentUser;

        // only users with documents & files administration rights should set new roles rights
        if (!$gCurrentUser->isAdministratorDocumentsFiles()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // check form field input and sanitized it from malicious content
        $documentsFilesFolderPermissionsForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $documentsFilesFolderPermissionsForm->validate($_POST);

        $postIntRolesViewRight   = array_map('intval', $formValues['adm_roles_view_right']);
        $postIntRolesUploadRight = array_map('intval', $formValues['adm_roles_upload_right']);

        // Read current view roles rights of the folder
        $rightFolderView = new RolesRights($this->db, 'folder_view', $this->folder->getValue('fol_id'));
        $rolesFolderView = $rightFolderView->getRolesIds();

        // Read current upload roles rights of the folder
        $rightFolderUpload = new RolesRights($this->db, 'folder_upload', $this->folder->getValue('fol_id'));
        $rolesFolderUpload = $rightFolderUpload->getRolesIds();

        // get new roles and removed roles
        $addUploadRoles = array_diff($postIntRolesUploadRight, $rolesFolderUpload);
        $removeUploadRoles = array_diff($rolesFolderUpload, $postIntRolesUploadRight);

        if (in_array(0, $postIntRolesViewRight, true)) {
            // set flag public for this folder and all child folders
            $this->folder->editPublicFlagOnFolder(true);
            // if all users have access then delete all existing roles
            $this->folder->removeRolesOnFolder('folder_view', $rolesFolderView);
        } else {
            // set flag public for this folder and all child folders
            $this->folder->editPublicFlagOnFolder(false);

            // get new roles and removed roles
            $addViewRoles = array_unique(array_merge(array_diff($postIntRolesViewRight, $rolesFolderView), $postIntRolesUploadRight, $rolesFolderUpload));
            $removeViewRoles = array_diff($rolesFolderView, $postIntRolesViewRight);

            $this->folder->addRolesOnFolder('folder_view', $addViewRoles);
            $this->folder->removeRolesOnFolder('folder_view', $removeViewRoles);

            // upload right should not contain removed view roles
            $removeUploadRoles = array_merge($removeUploadRoles, $removeViewRoles);
        }

        $this->folder->addRolesOnFolder('folder_upload', $addUploadRoles);
        $this->folder->removeRolesOnFolder('folder_upload', $removeUploadRoles);
        $this->folder->save();
    }
}
