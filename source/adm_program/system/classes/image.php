<?php
/******************************************************************************
 * Diese Factory-Klasse erzeugt automatisch das entsprechende Bildobjekt aufgrund
 * eines uebergebenen Bildpfades
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Das Bildobjekt erhaelt man ueber folgenden Aufruf im Code
 *
 * $new_image = Image::getImageObject("/pfad_zum_bild/bild.jpg");
 *
 *****************************************************************************/

class Image
{
    // Methode prueft, welcher Bildtyp (JPG,PNG,GIF) vorhanden ist und gibt das
    // entsprechende Objekt zurueck
    function createImageObject($pathAndFilename)
    {
        if(file_exists($pathAndFilename))
        {
            $imageProperties = getimagesize($pathAndFilename);
            //print_r(getimagesize($pathAndFilename)); exit();
            
            if($imageProperties['mime'] == "image/jpeg")
            {
                require_once("image_jpg.php");
                return new ImageJPG($pathAndFilename);
            }
            elseif($imageProperties['mime'] == "image/png")
            {
                require_once("image_png.php");
                return new ImagePNG($pathAndFilename);
            }
            else
            {
                return NULL;
            }
        }
        return NULL;
    }
}
?>