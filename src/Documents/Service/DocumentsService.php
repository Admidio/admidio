<?php

namespace Admidio\Documents\Service;

use Admidio\Documents\Entity\Folder;
use Admidio\Forum\Entity\Topic;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use DateTime;

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
     */
    public function __construct(Database $database, string $folderUUID = '')
    {
        $this->db = $database;
        $this->folderUUID = $folderUUID;
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
        global $gDb, $gSettingsManager;

        if (!isset($this->folder)) {
            $this->folder = new Folder($gDb);
            $this->folder->readDataByUuid($this->folderUUID);
        }

        $data = array();
        $this->folderContent = array(
            'folders' => $this->folder->getSubfoldersWithProperties(),
            'files'   => $this->folder->getFilesWithProperties()
        );

        foreach($this->folderContent['folders'] as $folder) {
            $data[] = array(
                'folder' => true,
                'uuid' => $folder['fol_uuid'],
                'name' => $folder['fol_name'],
                'description' => (string) $folder['fol_description'],
                'timestamp' => DateTime::createFromFormat('Y-m-d H:i:s', $folder['fol_timestamp'])
                    ->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
                'size' => null,
                'counter' => null,
                'existsInFileSystem' => $folder['fol_exists']
            );
        }

        foreach($this->folderContent['files'] as $file) {
            $data[] = array(
                'folder' => false,
                'uuid' => $file['fil_uuid'],
                'name' => $file['fil_name'],
                'description' => (string) $file['fil_description'],
                'timestamp' => DateTime::createFromFormat('Y-m-d H:i:s', $file['fil_timestamp'])
                    ->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time')),
                'size' => round($file['fil_size'] / 1024). ' kB&nbsp;',
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
                    'size' => round($file['fil_size'] / 1024). ' kB&nbsp;'
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
                       FROM '.TBL_FOLDERS.'
                      WHERE  fol_fol_id_parent = ? -- $folderID ';
        $filesStatement = $this->db->queryPrepared($sqlFiles, array($folderID));

        while($row = $filesStatement->fetch()) {
            $folder = new Folder($this->db, $row['fol_id']);

            if ($folder->hasUploadRight()) {
                $arrAllUploadableFolders[$folder->getValue('fol_uuid')] = $indent.'- '.$folder->getValue('fol_name');

                $arrAllUploadableFolders = $this->findFoldersWithUploadRights($row['fol_id'], $arrAllUploadableFolders, $indent.'&nbsp;&nbsp;&nbsp;');
            }
        }
        return $arrAllUploadableFolders;
    }

    /**
     * Save data from the topic form into the database.
     * @param string $topicUUID UUID if the topic that should be stored within this class
     * @return string UUID of the saved topic.
     * @throws Exception
     */
    public function saveTopic(string $topicUUID = ''): string
    {
        global $gCurrentSession, $gDb;

        // check form field input and sanitized it from malicious content
        $topicEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $topicEditForm->validate($_POST);

        $topic = new Topic($gDb);
        if ($topicUUID !== '') {
            $topic->readDataByUuid($topicUUID);
        }

        // write form values in topic object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'fot_') || str_starts_with($key, 'fop_')) {
                $topic->setValue($key, $value);
            }
        }

        if ($topic->save()) {
            // Notification email for new or changed entries to all members of the notification role
            $topic->sendNotification();
        }

        return $topic->getValue('fot_uuid');
    }
}
