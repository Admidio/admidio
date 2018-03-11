<?php
/**
 ***********************************************************************************************
 * Klasse um htaccessFiles anzulegen
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
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
    /**
     * @var string
     */
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
        if (is_file($this->folderPath . '/.htaccess'))
        {
            return true;
        }

        try
        {
            FileSystemUtils::createDirectoryIfNotExists($this->folderPath);

            $lines = array(
                'Order deny,allow',
                'Deny from all'
            );
            $data = implode("\n", $lines) . "\n";
            FileSystemUtils::writeFile($this->folderPath . '/.htaccess', $data);
        }
        catch (\RuntimeException $exception)
        {
            return false;
        }

        return true;
    }

    /**
     * Entfernt den Ordnerschutz (loeschen der htaccessDatei)
     * @return bool Returns true if protection is disabled
     */
    public function unprotectFolder()
    {
        try
        {
            FileSystemUtils::deleteFileIfExists($this->folderPath . '/.htaccess');
        }
        catch (\RuntimeException $exception)
        {
            return false;
        }

        return true;
    }
}
