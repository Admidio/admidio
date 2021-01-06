<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_files
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Diese Klasse dient dazu ein Fileobjekt zu erstellen.
 * Eine Datei kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getFileForDownload($fileId)
 *                         - File mit der uebergebenen ID aus der Datenbank auslesen fuer
 *                           das Downloadmodul. Hier wird auch direkt ueberprueft ob die
 *                           Datei oder der Ordner gesperrt ist.
 * getCompletePathOfFile() - Gibt den kompletten Pfad der Datei zurueck
 */
class TableFile extends TableAccess
{
    /**
     * @var array<string,string> Array with file extensions and the best Font Awesome icon that should be used
     */
    protected $iconFileExtension = array(
        'bmp'  => array('icon' => 'fa-file-image', 'mime-type' => 'image/bmp', 'viewable' => true),
        'gif'  => array('icon' => 'fa-file-image', 'mime-type' => 'image/gif', 'viewable' => true),
        'jpg'  => array('icon' => 'fa-file-image', 'mime-type' => 'image/jpeg', 'viewable' => true),
        'jpeg' => array('icon' => 'fa-file-image', 'mime-type' => 'image/jpeg', 'viewable' => true),
        'png'  => array('icon' => 'fa-file-image', 'mime-type' => 'image/png', 'viewable' => true),
        'svg'  => array('icon' => 'fa-file-image', 'mime-type' => 'image/svg+xml', 'viewable' => true),
        'tiff' => array('icon' => 'fa-file-image', 'mime-type' => 'image/tiff', 'viewable' => true),
        'doc'  => array('icon' => 'fa-file-word', 'mime-type' => 'application/msword', 'viewable' => false),
        'docx' => array('icon' => 'fa-file-word', 'mime-type' => 'application/msword', 'viewable' => false),
        'dot'  => array('icon' => 'fa-file-word', 'mime-type' => 'application/msword', 'viewable' => false),
        'dotx' => array('icon' => 'fa-file-word', 'mime-type' => 'application/msword', 'viewable' => false),
        'odt'  => array('icon' => 'fa-file-word', 'mime-type' => 'application/vnd.oasis.opendocument.text', 'viewable' => false),
        'csv'  => array('icon' => 'fa-file-excel', 'mime-type' => 'text/comma-separated-values', 'viewable' => false),
        'xls'  => array('icon' => 'fa-file-excel', 'mime-type' => 'application/msexcel', 'viewable' => false),
        'xlsx' => array('icon' => 'fa-file-excel', 'mime-type' => 'application/msexcel', 'viewable' => false),
        'xlt'  => array('icon' => 'fa-file-excel', 'mime-type' => 'application/msexcel', 'viewable' => false),
        'xltx' => array('icon' => 'fa-file-excel', 'mime-type' => 'application/msexcel', 'viewable' => false),
        'ods'  => array('icon' => 'fa-file-excel', 'mime-type' => 'application/vnd.oasis.opendocument.spreadsheet', 'viewable' => false),
        'pps'  => array('icon' => 'fa-file-powerpoint', 'mime-type' => 'application/mspowerpoint', 'viewable' => false),
        'ppsx' => array('icon' => 'fa-file-powerpoint', 'mime-type' => 'application/mspowerpoint', 'viewable' => false),
        'ppt'  => array('icon' => 'fa-file-powerpoint', 'mime-type' => 'application/mspowerpoint', 'viewable' => false),
        'pptx' => array('icon' => 'fa-file-powerpoint', 'mime-type' => 'application/mspowerpoint', 'viewable' => false),
        'odp'  => array('icon' => 'fa-file-powerpoint', 'mime-type' => 'application/vnd.oasis.opendocument.presentation', 'viewable' => false),
        'css'  => array('icon' => 'fa-file-alt', 'mime-type' => 'text/css', 'viewable' => true),
        'log'  => array('icon' => 'fa-file-alt', 'mime-type' => 'text/plain', 'viewable' => true),
        'md'   => array('icon' => 'fa-file-alt', 'mime-type' => 'text/plain', 'viewable' => true),
        'rtf'  => array('icon' => 'fa-file-alt', 'mime-type' => 'text/rtf', 'viewable' => false),
        'sql'  => array('icon' => 'fa-file-alt', 'mime-type' => 'text/plain', 'viewable' => true),
        'txt'  => array('icon' => 'fa-file-alt', 'mime-type' => 'text/plain', 'viewable' => true),
        'pdf'  => array('icon' => 'fa-file-pdf', 'mime-type' => 'application/pdf', 'viewable' => true),
        'gz'   => array('icon' => 'fa-file-archive', 'mime-type' => 'application/gzip', 'viewable' => false),
        'tar'  => array('icon' => 'fa-file-archive', 'mime-type' => 'application/x-tar', 'viewable' => false),
        'zip'  => array('icon' => 'fa-file-archive', 'mime-type' => 'application/zip', 'viewable' => false),
        'avi'  => array('icon' => 'fa-file-video', 'mime-type' => 'video/x-msvideo', 'viewable' => true),
        'flv'  => array('icon' => 'fa-file-video', 'mime-type' => 'video/x-flv', 'viewable' => true),
        'mov'  => array('icon' => 'fa-file-video', 'mime-type' => 'video/quicktime', 'viewable' => true),
        'mp4'  => array('icon' => 'fa-file-video', 'mime-type' => 'video/mp4', 'viewable' => true),
        'mpeg' => array('icon' => 'fa-file-video', 'mime-type' => 'video/mpeg', 'viewable' => true),
        'mpg'  => array('icon' => 'fa-file-video', 'mime-type' => 'video/mpeg', 'viewable' => true),
        'webm' => array('icon' => 'fa-file-video', 'mime-type' => 'video/webm', 'viewable' => true),
        'wmv'  => array('icon' => 'fa-file-video', 'mime-type' => 'video/x-ms-wmv', 'viewable' => true),
        'aac'  => array('icon' => 'fa-file-audio', 'mime-type' => 'audio/aac', 'viewable' => true),
        'midi' => array('icon' => 'fa-file-audio', 'mime-type' => 'audio/x-midi', 'viewable' => true),
        'mp3'  => array('icon' => 'fa-file-audio', 'mime-type' => 'audio/mpeg3', 'viewable' => true),
        'wav'  => array('icon' => 'fa-file-audio', 'mime-type' => 'audio/x-midi', 'viewable' => true),
        'wma'  => array('icon' => 'fa-file-audio', 'mime-type' => 'audio/x-ms-wma', 'viewable' => true)
    );

    /**
     * Constructor that will create an object of a recordset of the table adm_files.
     * If the id is set than the specific files will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $filId    The recordset of the files with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $filId = 0)
    {
        // read also data of assigned folder
        $this->connectAdditionalTable(TBL_FOLDERS, 'fol_id', 'fil_fol_id');

        parent::__construct($database, TBL_FILES, 'fil', $filId);
    }

    /**
     * Deletes the selected record of the table and the associated file in the file system.
     * After that the class will be initialize.
     * @return bool **true** if no error occurred
     */
    public function delete()
    {
        global $gLogger;

        try
        {
            FileSystemUtils::deleteFileIfExists($this->getFullFilePath());
        }
        catch (\RuntimeException $exception)
        {
            $gLogger->error('Could not delete file!', array('filePath' => $this->getFullFilePath()));
            // TODO
        }

        // Even if the delete won't work, return true, so that the entry of the DB disappears
        return parent::delete();
    }

    /**
     * Gets the absolute path of the folder (with folder-name)
     * @return string
     */
    public function getFullFolderPath()
    {
        return ADMIDIO_PATH . $this->getValue('fol_path') . '/' . $this->getValue('fol_name');
    }

    /**
     * Gets the absolute path of the file
     * @return string Absolute pth of the file
     */
    public function getFullFilePath()
    {
        return $this->getFullFolderPath() . '/' . $this->getValue('fil_name');
    }

    /**
     * Get the extension of the file
     * @return string Extension of the file e.g. 'pdf' or 'jpg'
     */
    public function getFileExtension()
    {
        return strtolower(pathinfo($this->getValue('fil_name'), PATHINFO_EXTENSION));
    }

    /**
     * Reads the file recordset from database table **adm_folders** and throws an AdmException
     * if the user has no right to see the corresponding folder or the file id doesn't exists.
     * @param int $fileId The id of the file.
     * @throws AdmException SYS_FOLDER_NO_RIGHTS
     *                      SYS_INVALID_PAGE_VIEW
     * @return true Returns **true** if everything is ok otherwise an AdmException is thrown.
     */
    public function getFileForDownload($fileId)
    {
        global $gCurrentUser;

        $this->readDataById($fileId);

        // Check if a dataset is found
        if ((int) $this->getValue('fil_id') === 0)
        {
            throw new AdmException('SYS_INVALID_PAGE_VIEW');
        }

        // If current user has download-admin-rights => allow
        if ($gCurrentUser->adminDocumentsFiles())
        {
            return true;
        }

        // If file is locked (and no download-admin-rights) => throw exception
        if ($this->getValue('fil_locked'))
        {
            $this->clear();
            throw new AdmException('SYS_FOLDER_NO_RIGHTS');
        }

        // If folder is public (and file is not locked) => allow
        if ($this->getValue('fol_public'))
        {
            return true;
        }

        // check if user has a membership in a role that is assigned to the current folder
        $folderViewRolesObject = new RolesRights($this->db, 'folder_view', (int) $this->getValue('fol_id'));

        if ($folderViewRolesObject->hasRight($gCurrentUser->getRoleMemberships()))
        {
            return true;
        }

        $this->clear();
        throw new AdmException('SYS_FOLDER_NO_RIGHTS');
    }

    /**
     * Get the relevant Font Awesome icon for the current file
     * @return string Returns the name of the Font Awesome icon
     */
    public function getFontAwesomeIcon()
    {
        $iconFile = 'fa-file';

        if(array_key_exists($this->getFileExtension(), $this->iconFileExtension))
        {
            $iconFile = $this->iconFileExtension[$this->getFileExtension()]['icon'];
        }

        return $iconFile;
    }

    /**
     * Get the MIME type of the current file e.g. 'image/jpeg'
     * @return string MIME type of the current file
     */
    public function getMimeType()
    {
        $mimeType = 'application/octet-stream';

        if(array_key_exists($this->getFileExtension(), $this->iconFileExtension))
        {
            $mimeType = $this->iconFileExtension[$this->getFileExtension()]['mime-type'];
        }

        return $mimeType;
    }

    /**
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with **setValue** than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        $value = parent::getValue($columnName, $format);

        // getValue transforms & to html chars. This must be undone.
        if ($columnName === 'fil_name')
        {
            $value = htmlspecialchars_decode($value);
        }

        return $value;
    }

    /**
     * Check if the current file format could be viewed within a browser.
     * @return bool Return true if the file could be viewed in the browser otherwise false.
     */
    public function isViewableInBrowser()
    {
        $returnCode = false;

        if(array_key_exists($this->getFileExtension(), $this->iconFileExtension))
        {
            $returnCode = $this->iconFileExtension[$this->getFileExtension()]['viewable'];
        }

        return $returnCode;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * For new records the user and timestamp will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentUser;

        if ($this->newRecord)
        {
            $this->setValue('fil_timestamp', DATETIME_NOW);
            $this->setValue('fil_usr_id', (int) $gCurrentUser->getValue('usr_id'));
        }

        return parent::save($updateFingerPrint);
    }
}
