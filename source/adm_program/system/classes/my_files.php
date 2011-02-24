<?php
/******************************************************************************
 * Klasse zum Verwalten des AdmMyFiles-Ordners
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse unterstuetzt bei der Rechtevergabe der einzelnen Ordner des 
 * adm_my_files - Ordners.
 *
 * Neben den Methoden der Elternklasse Folder, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * checkSettings() - Methode prueft, ob der adm_my_files-Ordner des Modules 
 *                   die notwendigen Rechte hat
 *
 *****************************************************************************/

$absolute_path = substr(__FILE__, 0, strpos(__FILE__, 'adm_program')-1);
require_once($absolute_path. '/adm_program/system/classes/folder.php');
require_once($absolute_path. '/adm_program/system/classes/htaccess.php');

class MyFiles extends Folder
{
    protected $module, $modulePath;
    public $errorText, $errorPath;

    // als Modulname sollte der gewuenschte Basisordner in adm_my_files angegeben werden
    // Bsp: 'PHOTOS' , 'BACKUP', 'DOWNLOAD'
    public function __construct($module)
    {
        $this->module = $module;
        $this->modulePath = SERVER_PATH. '/adm_my_files/'. strtolower($module);
    }
     
    // Methode prueft, ob der adm_my_files-Ordner des Modules die notwendigen Rechte hat
    // dieser Teil ist so aufgebaut, dass im Idealfall so wenig Pruefungen
    // als moeglich erfolgen.
    // Rueckgabe: true/false - bei false sind die Klassenvariablen $errorText, $errorPath belegt
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
                        // Ordner adm_my_files anlegen
                        if(@mkdir(SERVER_PATH. '/adm_my_files', 0777) == false)
                        {
                            $this->errorText = 'SYS_FOLDER_NOT_CREATED';
                            $this->errorPath = SERVER_PATH. '/adm_my_files';
                            return 0;
                        }
                    }

                    if(is_writeable(SERVER_PATH. '/adm_my_files') == false)
                    {
                        // Schreibrechte fuer adm_my_files setzen
                        if(@chmod(SERVER_PATH. '/adm_my_files', 0777) == false)
                        {
                            $this->errorText = 'SYS_FOLDER_WRITE_ACCESS';
                            $this->errorPath = SERVER_PATH. '/adm_my_files';
                            return 0;
                        }
                    }
                }

                // Module-Ordner anlegen
                if(@mkdir($this->modulePath, 0777))
                {
                    // ist der my_files-Ordner noch nicht mit htAccess gesichert, so muss diese Datei erst angelegt werden
                    if (file_exists(SERVER_PATH. '/adm_my_files/.htaccess') == false)
                    {
                        $protection = new Htaccess(SERVER_PATH. '/adm_my_files');
                        $protection->protectFolder();
                    }
                }
                else
                {
                    $this->errorText = 'SYS_FOLDER_NOT_CREATED';
                    $this->errorPath = $this->modulePath;
                    return 0;
                }
            }

            if(is_writeable($this->modulePath) == false)
            {
                // Schreibrechte fuer Module-Ordner setzen
                if(@chmod($this->folderWithPath, 0777) == false)
                {
                    $this->errorText = 'SYS_FOLDER_WRITE_ACCESS';
                    $this->errorPath = $this->modulePath;
                    return 0;
                }
            }
        }

        $this->setFolder($this->modulePath);
        return true;      
    }
    
    public function createFolderFromPath($folderPath)
    {
    }
}
?>