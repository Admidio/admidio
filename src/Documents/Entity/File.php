<?php
namespace Admidio\Documents\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Email;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Changelog\Entity\LogChanges;

/**
 * @brief Class manages access to database table adm_files
 *
 * With the given ID a file object is created from the data in the database table **adm_files**.
 * The class will handle the communication with the database and give easy access to the data. New
 * file could be created or existing file could be edited. Special properties of
 * data like save urls, checks for evil code or timestamps of last changes will be handled within this class.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class File extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_files.
     * If the id is set than the specific files will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $filId The recordset of the files with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $filId = 0)
    {
        // read also data of assigned folder
        $this->connectAdditionalTable(TBL_FOLDERS, 'fol_id', 'fil_fol_id');

        parent::__construct($database, TBL_FILES, 'fil', $filId);
    }

    /**
     * Check if the file extension of the current file format is allowed for upload and the
     * documents and files module.
     * @return bool Return true if the file extension is allowed to be used within Admidio.
     * @throws Exception
     */
    public function allowedFileExtension(): bool
    {
        return FileSystemUtils::allowedFileExtension($this->getValue('fil_name', 'database'));
    }

    /**
     * Deletes the selected record of the table and the associated file in the file system.
     * After that the class will be initialized.
     * @return bool **true** if no error occurred
     * @throws Exception
     */
    public function delete(): bool
    {
        global $gLogger;

        try {
            FileSystemUtils::deleteFileIfExists($this->getFullFilePath());
        } catch (\RuntimeException $exception) {
            $gLogger->error('Could not delete file!', array('filePath' => $this->getFullFilePath()));
            // TODO
        }

        // Even if delete won't work, return true, so that the entry of the DB disappears
        return parent::delete();
    }

    /**
     * Gets the absolute path of the folder (with folder-name)
     * @return string Returns the folder path of the current file.
     * @throws Exception
     */
    public function getFullFolderPath(): string
    {
        return ADMIDIO_PATH . $this->getValue('fol_path', 'database') . '/' . $this->getValue('fol_name', 'database');
    }

    /**
     * Gets the absolute path of the file
     * @return string Returns the folder path with the file name of the current file.
     * @throws Exception
     */
    public function getFullFilePath(): string
    {
        return $this->getFullFolderPath() . '/' . $this->getValue('fil_name', 'database');
    }

    /**
     * Get the extension of the file
     * @return string Extension of the file e.g. 'pdf' or 'jpg'
     * @throws Exception
     */
    public function getFileExtension(): string
    {
        return strtolower(pathinfo($this->getValue('fil_name'), PATHINFO_EXTENSION));
    }

    /**
     * Reads the file recordset from database table **adm_folders** and throws an Exception
     * if the user has no right to see the corresponding folder or the file id doesn't exist.
     * @param string $fileUuid The UUID of the file.
     * @return true Returns **true** if everything is ok otherwise an Exception is thrown.
     * @throws Exception SYS_FOLDER_NO_RIGHTS
     * @throws Exception
     *                      SYS_INVALID_PAGE_VIEW
     */
    public function getFileForDownload(string $fileUuid): bool
    {
        global $gCurrentUser;

        $this->readDataByUuid($fileUuid);

        // Check if a dataset is found
        if ((int)$this->getValue('fil_id') === 0) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // If current user has download-admin-rights => allow
        if ($gCurrentUser->adminDocumentsFiles()) {
            return true;
        }

        // If file is locked (and no download-admin-rights) => throw exception
        if ($this->getValue('fil_locked')) {
            $this->clear();
            throw new Exception('SYS_FOLDER_NO_RIGHTS');
        }

        // If folder is public (and file is not locked) => allow
        if ($this->getValue('fol_public')) {
            return true;
        }

        // check if user has a membership in a role that is assigned to the current folder
        $folderViewRolesObject = new RolesRights($this->db, 'folder_view', (int)$this->getValue('fol_id'));

        if ($folderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships())) {
            return true;
        }

        $this->clear();
        throw new Exception('SYS_FOLDER_NO_RIGHTS');
    }

    /**
     * Get the relevant icon for the current file
     * @return string Returns the name of the icon
     * @throws Exception
     */
    public function getIcon(): string
    {
        return FileSystemUtils::getFileIcon($this->getValue('fil_name'));
    }

    /**
     * Get the MIME type of the current file e.g. 'image/jpeg'
     * @return string MIME type of the current file
     * @throws Exception
     */
    public function getMimeType(): string
    {
        return FileSystemUtils::getFileMimeType($this->getValue('fil_name'));
    }

    /**
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @throws Exception
     */
    public function getValue(string $columnName, string $format = '')
    {
        $value = parent::getValue($columnName, $format);

        // getValue transforms & to html chars. This must be undone.
        if ($columnName === 'fil_name') {
            $value = htmlspecialchars_decode($value);
        }

        return $value;
    }

    /**
     * Check if the current file format could be viewed within a browser.
     * @return bool Return true if the file could be viewed in the browser otherwise false.
     * @throws Exception
     */
    public function isViewableInBrowser(): bool
    {
        return FileSystemUtils::isViewableFileInBrowser($this->getValue('fil_name'));
    }

    /**
     * Move this file to the folder that is set with the parameter $destFolderUUID. The method
     * will check if the user has the right to upload files to that folder and then move the file
     * within the file system and the database structure.
     * @param string $destFolderUUID UUID of the destination folder to which this file is to be moved.
     * @return void
     * @throws Exception
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws Exception
     */
    public function moveToFolder(string $destFolderUUID)
    {
        $folder = new Folder($this->db);
        $folder->readDataByUuid($destFolderUUID);

        if ($folder->hasUploadRight()) {
            FileSystemUtils::moveFile($this->getFullFilePath(), $folder->getFullFolderPath() . '/' . $this->getValue('fil_name'));

            $this->setValue('fil_fol_id', $folder->getValue('fol_id'));
            $this->save();
        }
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore, the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * For new records the user and timestamp will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        if ($this->newRecord) {
            $this->setValue('fil_timestamp', DATETIME_NOW);
            $this->setValue('fil_usr_id', $GLOBALS['gCurrentUserId']);
        }

        $returnCode = parent::save($updateFingerPrint);

        // read data to fill folder information to the object
        if ($this->newRecord) {
            $this->readDataById($this->getValue('fil_id'));
            $this->newRecord = true;
        }

        return $returnCode;
    }

    /**
     * Send a notification email that a new file was uploaded or an existing file was changed
     * to all members of the notification role. This role is configured within the global preference
     * **system_notifications_role**. The email contains the file name, the name of the current user,
     * the timestamp and the url to the folder of the file.
     * @return bool Returns **true** if the notification was sent
     * @throws Exception 'SYS_EMAIL_NOT_SEND'
     * @throws Exception
     */
    public function sendNotification(): bool
    {
        global $gCurrentOrganization, $gCurrentUser, $gSettingsManager, $gL10n;

        if ($gSettingsManager->getBool('system_notifications_new_entries')) {
            $notification = new Email();

            $message = $gL10n->get('SYS_FILE_CREATED_TITLE', array($gCurrentOrganization->getValue('org_longname'))) . '<br /><br />'
                . $gL10n->get('SYS_FILE') . ': ' . $this->getValue('fil_name') . '<br />'
                . $gL10n->get('SYS_FOLDER') . ': ' . $this->getValue('fol_name') . '<br />'
                . $gL10n->get('SYS_CREATED_BY') . ': ' . $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME') . '<br />'
                . $gL10n->get('SYS_CREATED_AT') . ': ' . date($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time')) . '<br />'
                . $gL10n->get('SYS_URL') . ': ' . ADMIDIO_URL . FOLDER_MODULES . '/documents-files/documents_files.php?folder_uuid=' . $this->getValue('fol_uuid') . '<br />';
            return $notification->sendNotification(
                $gL10n->get('SYS_FILE_CREATED_TITLE', array($gCurrentOrganization->getValue('org_longname'))),
                $message
            );
        }
        return false;
    }

    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * Some tables contain columns _usr_id_create, timestamp_create, etc. We do not want
     * to log changes to these columns.
     * The folder table also contains fol_usr_id and fol_timestamp. We also don't want to log
     * download counter increases...
     *
     * @return true Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(), ['fil_counter', 'fil_usr_id', 'fil_timestamp']);
    }
    /**
     * Adjust the changelog entry for this db record: Add the parent fold as a related object
     * 
     * @param LogChanges $logEntry The log entry to adjust
     * 
     * @return void
     */
    protected function adjustLogEntry(LogChanges $logEntry) {
        $folEntry = new Folder($this->db, $this->getValue('fil_fol_id'));
        $logEntry->setLogRelated($folEntry->getValue('fol_uuid'), $folEntry->getValue('fol_name'));
    }
}
