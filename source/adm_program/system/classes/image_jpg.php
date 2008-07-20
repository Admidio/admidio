<?php
/******************************************************************************
 * Diese Klasse dient dazu JPG-Bilder zu verarbeiten
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Neben den Methoden der Elternklasse ImageMain, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * create()         - Es wird eine Bildresource erzeugt und zurueckgegeben
 * copyToFile($image, $pathAndFilename = "", $quality = 95)
 *                  - Methode kopiert die uebergebene Bildresource in die uebergebene Datei bzw. der 
 *                    hinterlegten Datei des Objekts
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/image_main.php");

class ImageJPG extends ImageMain
{
    // Konstruktor
    function ImageJPG($pathAndFilename = "")
    {
        $this->setImage($pathAndFilename);
        $this->imageType = "JPG";
    }
    
    // Es wird eine Bildresource erzeugt und zurueckgegeben
    function create()
    {
        return imagecreatefromjpeg($this->imagePath);
    }
    
    // Methode kopiert die uebergebene Bildresource in die uebergebene Datei bzw. der 
    // hinterlegten Datei des Objekts
    // Rueckgabe: true, falls erfolgreich
    function copyToFile($image, $pathAndFilename = "", $quality = 95)
    {
        if(strlen($pathAndFilename) == 0)
        {
            $pathAndFilename = $this->imagePath;
        }
        return imagejpeg($image, $pathAndFilename, $quality);
    }
}
?>