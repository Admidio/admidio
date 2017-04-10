<?php
/**
 ***********************************************************************************************
 * Klasse um htaccessFiles anzulegen
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Htaccess
 * Diese Klasse dient dazu ein .htaccessFile zu erstellen.
 * Ein Ordner kann ueber diese Klasse mit einem htaccess-File geschuetzt werden.
 * Von aussen ist dann kan Zugriff mehr erlaubt.
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe des
 * Ordnerspfades:
 * $htaccess = new Htaccess($folderpath);
 *
 *
 * The following functions are available:
 *
 * protectFolder()      - Platziert ein htaccess-File im übergebenen Ordner
 * unprotectFolder()    - Löscht das htaccess-File im übergebenen Ordner
 */
class Htaccess
{
    protected $folderPath;

    /**
     * @param string $folderPath
     */
    public function __construct($folderPath)
    {
        $this->folderPath = $folderPath;
    }

    /**
     * Protect the passed folder
     * @return bool Returns true if protection is enabled
     */
    public function protectFolder()
    {
        if (is_dir($this->folderPath) && !is_file($this->folderPath.'/.htaccess'))
        {
            $file = fopen($this->folderPath.'/.htaccess', 'w+b');

            if (!$file)
            {
                return false;
            }

            fwrite($file, "Order deny,allow\n");
            fwrite($file, "Deny from all\n");
            return fclose($file);
        }
        return true;
    }

    /**
     * Entfernt den Ordnerschutz (loeschen der htaccessDatei)
     * @return bool Returns true if protection is disabled
     */
    public function unprotectFolder()
    {
        if (is_dir($this->folderPath) && is_file($this->folderPath.'/.htaccess'))
        {
            return @unlink($this->folderPath.'/.htaccess', 'w+');
        }
        return true;
    }
}
