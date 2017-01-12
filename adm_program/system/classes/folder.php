<?php
/**
 ***********************************************************************************************
 * Klasse zum vereinfachten Umgang mit Dateiordnern
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Folder
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
 */
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
     * @return bool Returns true if given folder is an existing folder
     */
    public function setFolder($folderWithPath = '')
    {
        if($folderWithPath !== '' && is_dir($folderWithPath))
        {
            $this->folderWithPath = $folderWithPath;
            return true;
        }
        return false;
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
     *
     * [1] (!@mkdir($dirPath, 0777) && !is_dir($dirPath))
     * This issue is difficult to reproduce, as any of concurrency-related issues. Appears when several
     * processes attempting to create a directory which is not yet existing, but between is_dir() and mkdir()
     * calls another process already managed to create a directory.
     * @param string $newFolder
     * @param bool   $writable
     * @return bool
     */
    public function createFolder($newFolder, $writable)
    {
        $newPath = $this->folderWithPath.'/'.$newFolder;
        $returnValue = true;

        // existiert der Ordner noch nicht, dann diesen anlegen
        if(!is_dir($newPath))
        {
            if($writable)
            {
                $returnValue = !(!@mkdir($newPath, 0777) && !is_dir($newPath)); // [1] // do NOT simplify to (@mkdir($path, 0777) || is_dir($path))
            }
            else
            {
                $returnValue = !(!@mkdir($newPath) && !is_dir($newPath)); // [1] // do NOT simplify to (@mkdir($path) || is_dir($path))
            }
        }

        // set write permissions for all users everytime because mkdir does not set this on every system
        if($writable && is_dir($newPath))
        {
            // don't check return code because sometimes we get false also if the rights where set to 0777
            @chmod($newPath, 0777);
        }
        return $returnValue;
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

        if (!is_dir($sourceFolder))
        {
            return false;
        }

        // erst einmal vom Zielpfad den letzten Ordner absplitten, damit dieser angelegt werden kann
        $newPath   = substr($destinationFolder, 0, strrpos($destinationFolder, '/'));
        $newFolder = substr($destinationFolder, strrpos($destinationFolder, '/') + 1);

        // nun erst einmal den Zielordner erstellen
        $this->setFolder($newPath);
        if(!$this->createFolder($newFolder, true))
        {
            return false;
        }

        $dirHandle = @opendir($sourceFolder);
        if($dirHandle)
        {
            while (($entry = readdir($dirHandle)) !== false)
            {
                if($entry === '.' || $entry === '..')
                {
                    continue;
                }

                $currentFolderEntry = $sourceFolder.'/'.$entry;
                $destinationEntry   = $destinationFolder.'/'.$entry;

                if(is_dir($currentFolderEntry))
                {
                    // copy the content of the folder
                    $this->copy($destinationEntry, $currentFolderEntry);
                }

                if(is_file($currentFolderEntry))
                {
                    // copy the file
                    if(!copy($currentFolderEntry, $destinationEntry))
                    {
                        return false;
                    }
                }
            }
            closedir($dirHandle);
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

        if (!is_dir($folder))
        {
            return false;
        }

        $dirHandle = @opendir($folder);
        if($dirHandle)
        {
            while (($entry = readdir($dirHandle)) !== false)
            {
                if($entry === '.' || $entry === '..')
                {
                    continue;
                }

                $currentFolderEntry = $folder.'/'.$entry;

                if(is_dir($currentFolderEntry))
                {
                    // deletes the content of the folder
                    $this->delete($currentFolderEntry, false);
                }

                if(is_file($currentFolderEntry))
                {
                    // deletes the file
                    if(!@unlink($currentFolderEntry))
                    {
                        return false;
                    }
                }
            }
            closedir($dirHandle);
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
     * @param string $destFolder   das neue Zielverzeichnis
     * @param string $sourceFolder der zu verschiebende Ordner, falls nicht gefuellt wird der Ordner aus der Klasse genommen
     * @return bool Returns true if the move works successfully
     */
    public function move($destFolder, $sourceFolder = '')
    {
        if($sourceFolder === '')
        {
            $sourceFolder = $this->folderWithPath;
        }

        // First copy the full source folder to destination
        if($this->copy($destFolder, $sourceFolder))
        {
            // If copy was successful delete source folder
            return $this->delete($sourceFolder);
        }
        return false;
    }

    /**
     * Attempts to rename oldname to newname, moving it between directories if necessary.
     * If newname exists, it will be overwritten.
     * @param string $newName The new name of the folder.
     * @return bool Returns @b true on success or @b false on failure.
     */
    public function rename($newName)
    {
        return rename($this->folderWithPath, $newName);
    }
}
