<?php
/**
 ***********************************************************************************************
 * Class manages the AdmMyFiles folder
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
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
 *
 *****************************************************************************/
class MyFiles extends Folder
{
    protected $module;      // name of the module and name of the folder in adm_my_files
    protected $modulePath;  // absolute path of the module
    protected $currentPath; // absolute path that is set with setSubFolder
    protected $webPath;     // the path starts with adm_my_file
    public $errorText, $errorPath;

    /**
     * module name should be the folder name in adm_my_files for this module
     * Example: 'PHOTOS', 'BACKUP', 'DOWNLOAD'
     * @param string $module
     */
    public function __construct($module)
    {
        global $g_root_path;
        $this->module = $module;
        $this->modulePath  = SERVER_PATH. '/adm_my_files/'. strtolower($module);
        $this->currentPath = SERVER_PATH. '/adm_my_files/'. strtolower($module);
        $this->webPath     = $g_root_path. '/adm_my_files';
    }

    /**
     * method checks if adm_my_files folder has all necessary rights
     * the method is designed to make as little as possible checks
     * @return bool if false than check the parameters $errorText, $errorPath
     */
    public function checkSettings()
    {
        if(!is_writable($this->modulePath))
        {
            if(!file_exists($this->modulePath))
            {
                if(!is_writable(SERVER_PATH. '/adm_my_files'))
                {
                    if(!file_exists(SERVER_PATH. '/adm_my_files'))
                    {
                        // create folder adm_my_files
                        if(!@mkdir(SERVER_PATH. '/adm_my_files', 0777))
                        {
                            $this->errorText = 'SYS_FOLDER_NOT_CREATED';
                            $this->errorPath = $this->webPath;
                            return false;
                        }
                    }

                    if(!is_writable(SERVER_PATH. '/adm_my_files'))
                    {
                        // set adm_my_files writable
                        if(!@chmod(SERVER_PATH. '/adm_my_files', 0777))
                        {
                            $this->errorText = 'SYS_FOLDER_WRITE_ACCESS';
                            $this->errorPath = $this->webPath;
                            return false;
                        }
                    }
                }

                // create module folder
                if(@mkdir($this->modulePath, 0777))
                {
                    // create htaccess file for folder adm_my_files if necessary
                    if (!file_exists(SERVER_PATH. '/adm_my_files/.htaccess'))
                    {
                        $protection = new Htaccess(SERVER_PATH. '/adm_my_files');
                        $protection->protectFolder();
                    }
                }
                else
                {
                    $this->errorText = 'SYS_FOLDER_NOT_CREATED';
                    $this->errorPath = $this->webPath;
                    return false;
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
     * @param string $folder
     * @return int
     */
    public function setSubFolder($folder)
    {
        if(admStrIsValidFileName($folder))
        {
            $tempPath = $this->modulePath. '/'. $folder;
            if(!is_writable($tempPath))
            {
                if(!file_exists($tempPath))
                {
                    // create folder
                    if(!@mkdir($tempPath, 0777))
                    {
                        $this->errorText = 'SYS_FOLDER_NOT_CREATED';
                        $this->errorPath = $this->webPath.'/'.$folder;
                        return 0;
                    }
                }
            }

            if(!is_writable($tempPath))
            {
                // set folder writable
                if(!@chmod($tempPath, 0777))
                {
                    $this->errorText = 'SYS_FOLDER_WRITE_ACCESS';
                    $this->errorPath = $this->webPath.'/'.$folder;
                    return 0;
                }
            }
            $this->currentPath = $tempPath;
            $this->webPath     = $this->webPath.'/'.$folder;
            return 1;
        }
    }
}
