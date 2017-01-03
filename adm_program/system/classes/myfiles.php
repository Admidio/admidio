<?php
/**
 ***********************************************************************************************
 * Class manages the AdmMyFiles folder
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class MyFiles
 * This class supports the assignment of rights to every folder of adm_my_files
 * It's easy to create new folders and get detailed error messages if there are
 * problems with folder rights
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * checkSettings()       - method checks if adm_my_files folder has all necessary rights
 * getServerPath()       - returns the current path
 * setSubFolder($folder) - open a folder in the current module folder
 *                         if that folder doesn't exists than it will be created
 */
class MyFiles extends Folder
{
    protected $module;      // name of the module and name of the folder in adm_my_files
    protected $modulePath;  // absolute path of the module
    protected $currentPath; // absolute path that is set with setSubFolder
    protected $webPath;     // the path starts with adm_my_file
    public $errorText;
    public $errorPath;

    /**
     * module name should be the folder name in adm_my_files for this module
     * Example: 'PHOTOS', 'BACKUP', 'DOWNLOAD'
     * @param string $module
     */
    public function __construct($module)
    {
        global $gCurrentOrganization;

        if($module === 'DOWNLOAD')
        {
            $folderName = TableFolder::getRootFolderName();
        }
        else
        {
            $folderName = strtolower($module);
        }

        $this->module      = $module;
        $this->modulePath  = ADMIDIO_PATH . FOLDER_DATA . '/' . $folderName;
        $this->currentPath = ADMIDIO_PATH . FOLDER_DATA . '/' . $folderName;
        $this->webPath     = ADMIDIO_URL . FOLDER_DATA;

        parent::__construct($this->modulePath);
    }

    /**
     * method checks if adm_my_files folder has all necessary rights
     * the method is designed to make as little as possible checks
     *
     * [1] (!@mkdir($dirPath, 0777) && !is_dir($dirPath))
     * This issue is difficult to reproduce, as any of concurrency-related issues. Appears when several
     * processes attempting to create a directory which is not yet existing, but between is_dir() and mkdir()
     * calls another process already managed to create a directory.
     * @return bool if false than check the parameters $errorText, $errorPath
     */
    public function checkSettings()
    {
        if(!is_writable($this->modulePath))
        {
            if(!is_dir($this->modulePath))
            {
                $serverPathAdmMyFiles = ADMIDIO_PATH . FOLDER_DATA;

                if(!is_writable($serverPathAdmMyFiles))
                {
                    if(!is_dir($serverPathAdmMyFiles))
                    {
                        // create folder "adm_my_files"
                        if(!@mkdir($serverPathAdmMyFiles, 0777) && !is_dir($serverPathAdmMyFiles)) // [1]
                        {
                            $this->errorText = 'SYS_FOLDER_NOT_CREATED';
                            $this->errorPath = $this->webPath;
                            return false;
                        }
                    }

                    // set "adm_my_files" writable
                    if(!@chmod($serverPathAdmMyFiles, 0777))
                    {
                        $this->errorText = 'SYS_FOLDER_WRITE_ACCESS';
                        $this->errorPath = $this->webPath;
                        return false;
                    }
                }

                // create module folder
                if(!@mkdir($this->modulePath, 0777) && !is_dir($this->modulePath)) // [1]
                {
                    $this->errorText = 'SYS_FOLDER_NOT_CREATED';
                    $this->errorPath = $this->webPath;
                    return false;
                }

                // set "adm_my_files" writable
                if(!@chmod($this->modulePath, 0777))
                {
                    $this->errorText = 'SYS_FOLDER_WRITE_ACCESS';
                    $this->errorPath = $this->webPath;
                    return false;
                }

                // create ".htaccess" file for folder "adm_my_files"
                if (!is_file($serverPathAdmMyFiles.'/.htaccess'))
                {
                    $protection = new Htaccess($serverPathAdmMyFiles);

                    if (!$protection->protectFolder())
                    {
                        return false;
                    }
                }
            }

            if(!is_writable($this->modulePath))
            {
                // set module folder writable
                if(!@chmod($this->folderWithPath, 0777))
                {
                    $this->errorText = 'SYS_FOLDER_WRITE_ACCESS';
                    $this->errorPath = $this->webPath;
                    return false;
                }
            }
        }

        $this->setFolder($this->modulePath);
        return true;
    }

    /**
     * @return string returns the current path
     */
    public function getServerPath()
    {
        return $this->currentPath;
    }

    /**
     * open a folder in the current module folder
     * if that folder doesn't exists than it will be created
     *
     * [1] (!@mkdir($dirPath, 0777) && !is_dir($dirPath))
     * This issue is difficult to reproduce, as any of concurrency-related issues. Appears when several
     * processes attempting to create a directory which is not yet existing, but between is_dir() and mkdir()
     * calls another process already managed to create a directory.
     * @param string $folder subfolder name
     * @return bool Returns true if folder is successfully created and writable.
     */
    public function setSubFolder($folder)
    {
        if(!admStrIsValidFileName($folder))
        {
            return false;
        }

        $tempServerPath = $this->modulePath.'/'.$folder;
        $tempWebPath    = $this->webPath.'/'.$folder;

        // create folder
        if (!is_dir($tempServerPath))
        {
            if (!@mkdir($tempServerPath, 0777) && !is_dir($tempServerPath)) // [1]
            {
                $this->errorText = 'SYS_FOLDER_NOT_CREATED';
                $this->errorPath = $tempWebPath;
                return false;
            }
        }

        // set folder writable
        if (!is_writable($tempServerPath))
        {
            if (!@chmod($tempServerPath, 0777))
            {
                $this->errorText = 'SYS_FOLDER_WRITE_ACCESS';
                $this->errorPath = $tempWebPath;
                return false;
            }
        }

        $this->currentPath = $tempServerPath;
        $this->webPath     = $tempWebPath;
        return true;
    }
}
