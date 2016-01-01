<?php
/**
 ***********************************************************************************************
 * Klasse um htaccessFiles anzulegen
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Diese Klasse dient dazu ein .htaccessFile zu erstellen.
 * Ein Ordner kann ueber diese Klasse mit einem htaccess-File geschuetzt werden.
 * Von aussen ist dann kan Zugriff mehr erlaubt.
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe des
 * Ordnerspfades:
 * $htaccess = new Htaccess($folderpath);
 *
 *
 *
 * The following functions are available:
 *
 * protectFolder()      - Platziert ein htaccess-File im übergebenen Ordner
 * unprotectFolder()    - Löscht das htaccess-File im übergebenen Ordner
 *
 *****************************************************************************/
class Htaccess
{
    protected $folderPath;
    protected $htaccessFileExistsAlready = false;
    protected $folderExists              = false;

    /**
     * @param string $folderPathParam
     */
    public function __construct($folderPathParam)
    {
        $this->folderPath = $folderPathParam;

        if (file_exists($this->folderPath))
        {
            $this->folderExists = true;

            if (file_exists($folderPathParam . '/.htaccess'))
            {
                $this->htaccessFileExistsAlready = true;
            }
        }
    }

    /**
     * Schuetzt den uebergebenen Ordner
     */
    public function protectFolder()
    {
        if ($this->folderExists && !$this->htaccessFileExistsAlready)
        {
            $file = fopen($this->folderPath . '/.htaccess', 'w+');
            fwrite($file, "Order deny,allow\n");
            fwrite($file, "Deny from all\n");
            fclose($file);
        }
    }

    /**
     * Entfernt den Ordnerschutz (loeschen der htaccessDatei)
     */
    public function unprotectFolder()
    {
        if ($this->folderExists && $this->htaccessFileExistsAlready)
        {
            @unlink($this->folderPath . '/.htaccess', 'w+');
        }
    }
}
