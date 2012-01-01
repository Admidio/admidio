<?php
/******************************************************************************
 * Klasse zum vereinfachten Umgang mit Dateiordnern
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
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

    public function __construct($folderWithPath = '')
    {
        $this->folderWithPath = '';
        if(strlen($folderWithPath) > 0 && is_dir($folderWithPath))
        {
            $this->folderWithPath = $folderWithPath;
        }
    }
    
    // Ordner mit zugehoerigem Pfad setzen
    public function setFolder($folderWithPath = '')
    {
        if(strlen($folderWithPath) > 0 && is_dir($folderWithPath))
        {
            $this->folderWithPath = $folderWithPath;
        }
    }
    
    // Ordner zurueckgeben
    public function getFolder()
    {
        return $this->folderWithPath;
    }
    
    // den Ordner der Klasse mit Schreibrechten erstellen
    public function createFolder($newFolder, $writeable)
    {
		$newPath = $this->folderWithPath. '/'. $newFolder;
		$retCode = true;

        // existiert der Ordner noch nicht, dann diesen anlegen
        if(file_exists($newPath) == false)
        {
            if($writeable)
            {
                $retCode = @mkdir($newPath, 0777);
            }
            else
            {
                $retCode = @mkdir($newPath);
            }
        }

        if($writeable)
        {
            // der Ordner existiert, aber die Schreibrechte noch nicht
            if(is_writeable($newPath) == false)
            {
                $retCode = @chmod($newPath, 0777);
            }
        }
        return $retCode;
    }
    
    // kopiert den kompletten Ordner mit allen Unterordnern und Dateien in einen neuen Pfad
    // destinationFolder : das neue Zielverzeichnis
    // sourceFolder      : der zu kopierende Ordner, falls nicht gefuellt wird der Ordner aus der Klasse genommen
    public function copy($destinationFolder, $sourceFolder = '')
    {
        if(strlen($sourceFolder) == 0)
        {
            $sourceFolder = $this->folderWithPath;
        }

		// erst einmal vom Zielpfad den letzten Ordner absplitten, damit dieser angelegt werden kann
		$newFolder = substr($destinationFolder, strrpos($destinationFolder, '/') + 1);
		$newPath   = substr($destinationFolder, 0, strrpos($destinationFolder, '/'));
		
        // nun erst einmal den Zielordner erstellen
        $this->setFolder($newPath);
        $b_return = $this->createFolder($newFolder, true);
        
        if($b_return == true)
        {
            $dh  = @opendir($sourceFolder);
            if($dh)
            {
                while (false !== ($filename = readdir($dh)))
                {
                    if($filename != '.' && $filename != '..')
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
                                if(copy($act_folder_entry, $destinationFolder.'/'.$filename) == false)
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
        
    // der Ordner wird mit allen Unterordnern / Dateien geloescht
    public function delete($folder = '')
    {
        if(strlen($folder) == 0)
        {
            $folder = $this->folderWithPath;
        }
        
        $dh  = @opendir($folder);
        if($dh)
        {
            while (false !== ($filename = readdir($dh)))
            {
                if($filename != '.' && $filename != '..')
                {
                    $act_folder_entry = $folder.'/'.$filename;

                    if(is_dir($act_folder_entry))
                    {
                        // nun Inhalt des entsprechenden Ordners loeschen
                        $this->delete($act_folder_entry);
                    }
                    else
                    {
                        // die Datei loeschen
                        if(file_exists($act_folder_entry))
                        {
                            if(@unlink($act_folder_entry) == false)
                            {
                                return false;
                            }
                        }
                    }
                }
            }
            closedir($dh);
        }

        // nun noch den aktuellen selber Ordner loeschen
        if(@rmdir($folder) == false)
        {
            return false;
        }
        return true;
    }
    
    // verschiebt den kompletten Ordner mit allen Unterordnern und Dateien in einen neuen Pfad
    // destinationFolder : das neue Zielverzeichnis
    // sourceFolder      : der zu verschiebende Ordner, falls nicht gefuellt wird der Ordner aus der Klasse genommen    
    public function move($destinationFolder, $sourceFolder = '')
    {
        if(strlen($sourceFolder) == 0)
        {
            $sourceFolder = $this->folderWithPath;
        }
        
        // erst den kompletten Ordner kopieren und danach im erfolgsfall loeschen
        if($this->copy($destinationFolder, $sourceFolder) == true)
        {
            return $this->delete($sourceFolder);
        }
        return false;
    }
}
?>