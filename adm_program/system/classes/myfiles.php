<?php
/******************************************************************************
 * Class manages the AdmMyFiles folder
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
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

    // module name should be the folder name in adm_my_files for this module
    // Example: 'PHOTOS' , 'BACKUP', 'DOWNLOAD'
    public function __construct($module)
    {
        global $g_root_path;
        $this->module = $module;
        $this->modulePath   = SERVER_PATH. '/adm_my_files/'. strtolower($module);
        $this->currentPath  = SERVER_PATH. '/adm_my_files/'. strtolower($module);
        $this->webPath      = $g_root_path. '/adm_my_files';
    }

    // method checks if adm_my_files folder has all necessary rights
    // the method is designed to make as little as possible checks
    // Return: true/false - if false than check the parameters $errorText, $errorPath
    public function checkSettings()
    {
        if(is_writeable($this->modulePath) == false)
        {
            if(file_exists($this->modulePath) == false)
            {
                if(is_writeable(SERVER_PATH. '/adm_my_files') == false)
                {
                    if(file_exists(SERVER_PATH. '/adm_my_files') == false)
                    {
                        // create folder adm_my_files
                        if(@mkdir(SERVER_PATH. '/adm_my_files', 0777) == false)
                        {
                            $this->errorText = 'SYS_FOLDER_NOT_CREATED';
                            $this->errorPath = $this->webPath;
                            return 0;
                        }
                    }

                    if(is_writeable(SERVER_PATH. '/adm_my_files') == false)
                    {
                        // set adm_my_files writable
                        if(@chmod(SERVER_PATH. '/adm_my_files', 0777) == false)
                        {
                            $this->errorText = 'SYS_FOLDER_WRITE_ACCESS';
                            $this->errorPath = $this->webPath;
                            return 0;
                        }
                    }
                }

                // create module folder
                if(@mkdir($this->modulePath, 0777))
                {
                    // create htaccess file for folder adm_my_files if necessary
                    if (file_exists(SERVER_PATH. '/adm_my_files/.htaccess') == false)
                    {
                        $protection = new Htaccess(SERVER_PATH. '/adm_my_files');
                        $protection->protectFolder();
                    }
                }
                else
                {
                    $this->errorText = 'SYS_FOLDER_NOT_CREATED';
                    $this->errorPath = $this->webPath;
                    return 0;
                }
            }

            if(is_writeable($this->modulePath) == false)
            {
                // set module folder writable
                if(@chmod($this->folderWithPath, 0777) == false)
                {
                    $this->errorText = 'SYS_FOLDER_WRITE_ACCESS';
                    $this->errorPath = $this->webPath;
                    return 0;
                }
            }
        }

        $this->setFolder($this->modulePath);
        return true;
    }

    // returns the current path
    public function getServerPath()
    {
        return $this->currentPath;
    }

    // open a folder in the current module folder
    // if that folder doesn't exists than it will be created
    public function setSubFolder($folder)
    {
        if(admStrIsValidFileName($folder))
        {
            $tempPath = $this->modulePath. '/'. $folder;
            if(is_writeable($tempPath) == false)
            {
                if(file_exists($tempPath) == false)
                {
                    // create folder
                    if(@mkdir($tempPath, 0777) == false)
                    {
                        $this->errorText = 'SYS_FOLDER_NOT_CREATED';
                        $this->errorPath = $this->webPath.'/'.$folder;
                        return 0;
                    }
                }
            }

            if(is_writeable($tempPath) == false)
            {
                // set folder writable
                if(@chmod($tempPath, 0777) == false)
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
