<?php
/**
 ***********************************************************************************************
 * Klasse zum vereinfachten Umgang mit Dateiordnern
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Mit dieser Klasse koennen Ordner leichter verwaltet werden. Das rekursive Verschieben,
 * Kopieren, Loeschen uvw. wird unterstuetzt.
 *
 * The following functions are available:
 *
 * setFolder($folderWithPath = '') - Ordner mit zugehoerigem Pfad setzen
 * getFolder()           - Ordner zurueckgeben
 * createFolder($newFolder, $writeable)     - den Ordner ggf. mit Schreibrechten erstellen
 * copy($destinationFolder, $sourceFolder = '')
 *                       - kopiert den kompletten Ordner mit allen Unterordnern und
 *                         Dateien in einen neuen Pfad
 * delete($folder = '')  - der Ordner wird mit allen Unterordnern / Dateien geloescht
 * move($destinationFolder, $sourceFolder = '')
 *                       - verschiebt den kompletten Ordner mit allen Unterordnern
 *                         und Dateien in einen neuen Pfad
 *
 *****************************************************************************/
class Folder
{
    protected $folderWithPath;

    /**
     * @param string $folderWithPath
     */
    public function __construct($folderWithPath = '')
    {
        $this->folderWithPath = '';

        if($folderWithPath !== '' && is_dir($folderWithPath))
        {
            $this->folderWithPath = $folderWithPath;
        }
    }

    /**
     * Ordner mit zugehoerigem Pfad setzen
     * @param string $folderWithPath
     */
    public function setFolder($folderWithPath = '')
    {
        if($folderWithPath !== '' && is_dir($folderWithPath))
        {
            $this->folderWithPath = $folderWithPath;
        }
    }

    /**
     * Ordner zurueckgeben
     * @return string
     */
    public function getFolder()
    {
        return $this->folderWithPath;
    }

    /**
     * den Ordner der Klasse mit Schreibrechten erstellen
     * @param string $newFolder
     * @param bool   $writable
     * @return bool
     */
    public function createFolder($newFolder, $writable)
    {
        $newPath = $this->folderWithPath. '/'. $newFolder;
        $retCode = true;

        // existiert der Ordner noch nicht, dann diesen anlegen
        if(!file_exists($newPath))
        {
            if($writable)
            {
                $retCode = @mkdir($newPath, 0777);
            }
            else
            {
                $retCode = @mkdir($newPath);
            }
        }

        if($writable)
        {
            // der Ordner existiert, aber die Schreibrechte noch nicht
            if(!is_writable($newPath))
            {
                $retCode = @chmod($newPath, 0777);
            }
        }
        return $retCode;
    }

    /**
     * kopiert den kompletten Ordner mit allen Unterordnern und Dateien in einen neuen Pfad
     * destinationFolder : das neue Zielverzeichnis
     * sourceFolder      : der zu kopierende Ordner, falls nicht gefuellt wird der Ordner aus der Klasse genommen
     * @param string $destinationFolder
     * @param string $sourceFolder
     * @return bool
     */
    public function copy($destinationFolder, $sourceFolder = '')
    {
        if($sourceFolder === '')
        {
            $sourceFolder = $this->folderWithPath;
        }

        // erst einmal vom Zielpfad den letzten Ordner absplitten, damit dieser angelegt werden kann
        $newFolder = substr($destinationFolder, strrpos($destinationFolder, '/') + 1);
        $newPath   = substr($destinationFolder, 0, strrpos($destinationFolder, '/'));

        // nun erst einmal den Zielordner erstellen
        $this->setFolder($newPath);
        $b_return = $this->createFolder($newFolder, true);

        if($b_return)
        {
            $dh = @opendir($sourceFolder);
            if($dh)
            {
                while (false !== ($filename = readdir($dh)))
                {
                    if($filename !== '.' && $filename !== '..')
                    {
                        $act_folder_entry = $sourceFolder.'/'.$filename;

                        if(is_dir($act_folder_entry))
                        {
                            // nun Inhalt des entsprechenden Ordners loeschen
                            $this->copy($destinationFolder.'/'.$filename, $act_folder_entry);
                        }
                        else
                        {
                            // die Datei loeschen
                            if(file_exists($act_folder_entry))
                            {
                                if(!copy($act_folder_entry, $destinationFolder.'/'.$filename))
                                {
                                    return false;
                                }
                            }
                        }
                    }
                }
                closedir($dh);
            }
        }
        else
        {
            return false;
        }

        return true;
    }

    /**
     * Deletes the current folder recursive with all files and subfolders.
     * @param string $folder            Name of a folder that should be deleted. Default is always the current folder
     * @param bool   $onlyDeleteContent If set to @b true then only files and folders in the current
     *                                  folder will be deleted. The current folder will not be deleted.
     * @return bool
     */
    public function delete($folder = '', $onlyDeleteContent = false)
    {
        if($folder === '')
        {
            $folder = $this->folderWithPath;
        }

        $dh = @opendir($folder);
        if($dh)
        {
            while (false !== ($filename = readdir($dh)))
            {
                if($filename !== '.' && $filename !== '..')
                {
                    $act_folder_entry = $folder.'/'.$filename;

                    if(is_dir($act_folder_entry))
                    {
                        // deletes the content of the folder
                        $this->delete($act_folder_entry, false);
                    }
                    else
                    {
                        // deletes the file
                        if(file_exists($act_folder_entry))
                        {
                            if(!@unlink($act_folder_entry))
                            {
                                return false;
                            }
                        }
                    }
                }
            }
            closedir($dh);
        }

        if(!$onlyDeleteContent)
        {
            // now delete current folder
            if(!@rmdir($folder))
            {
                return false;
            }
        }
        return true;
    }

    /**
     * verschiebt den kompletten Ordner mit allen Unterordnern und Dateien in einen neuen Pfad
     * destinationFolder : das neue Zielverzeichnis
     * sourceFolder      : der zu verschiebende Ordner, falls nicht gefuellt wird der Ordner aus der Klasse genommen
     * @param string $destinationFolder
     * @param string $sourceFolder
     * @return bool
     */
    public function move($destinationFolder, $sourceFolder = '')
    {
        if($sourceFolder === '')
        {
            $sourceFolder = $this->folderWithPath;
        }

        // erst den kompletten Ordner kopieren und danach im erfolgsfall loeschen
        if($this->copy($destinationFolder, $sourceFolder))
        {
            return $this->delete($sourceFolder);
        }
        return false;
    }
}
