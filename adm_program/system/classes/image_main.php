<?php
/******************************************************************************
 * Diese Klasse ist die Elternklasse fuer saemtliche Bildbearbeitungen mit
 * verschiedenen Formaten
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Folgende Methoden stehen zur Verfuegung:
 *
 * setImage($pathAndFilename)
 *                  - setzt den Pfad zum Bild und liest Bildinformationen ein
 * resize($new_x_size, $new_y_size, $seitenveraehltnis_beibehalten = true, $enlarge = false)
 *                  - veraendert die Bildgroesse
 *
 *****************************************************************************/

class ImageMain
{
    var $imagePath;
    var $imageType;
    var $imageProperties = array();

    // Methode setzt den Pfad zum Bild und liest Bildinformationen ein
    function setImage($pathAndFilename)
    {
        if(file_exists($pathAndFilename))
        {
            $this->imagePath = $pathAndFilename;
            $this->imageProperties = getimagesize($this->imagePath);
            return true;
        }
        return false;
    }

    // Methode veraendert die Bildgroesse
    // new_x_size : Anzahl Pixel auf die die X-Seite veraendert werden soll
    // new_y_size : Anzahl Pixel auf die die Y-Seite veraendert werden soll
    // seitenverhaeltnis_beibehalten : das aktuelle Seitenverhaeltnis des Bildes wird belassen,
    //                                 dadurch kann eine Seite kleiner werden als die Angabe vorsieht
    // enlarge    : das Bild wird ggf. vergroessert (Qualtitaetsverlust)
    function resize($new_x_size, $new_y_size, $seitenveraehltnis_beibehalten = true, $enlarge = false)
    {
        // schauen, ob das Bild von der Groesse geaendert werden muss
        if($this->imageProperties[0] > $new_x_size
        || $this->imageProperties[1] > $new_y_size
        || $enlarge == true)
        {
            //Speicher zur Bildbearbeitung bereit stellen, erst ab php5 noetig
            @ini_set('memory_limit', '50M');

            //Errechnung Seitenverhaeltnis
            $seitenverhaeltnis = $this->imageProperties[0] / $this->imageProperties[1];
            
            if($seitenveraehltnis_beibehalten == true)
            {
                //x-Seite soll scalliert werden
                if(($this->imageProperties[0]/$new_x_size) >= ($this->imageProperties[1]/$new_y_size))
                {
                    $photo_x_size = $new_x_size;
                    $photo_y_size = round($new_x_size / $seitenverhaeltnis);
                }

                //y-Seite soll scalliert werden
                if(($this->imageProperties[0] / $new_x_size) < ($this->imageProperties[1] / $new_y_size))
                {
                    $photo_x_size = round($new_y_size * $seitenverhaeltnis);
                    $photo_y_size = $new_y_size;
                }
            }
            else
            {
                $photo_x_size = $new_x_size;
                $photo_x_size = $new_y_size;
            }

            // Erzeugung neues Bild
            $resized_user_photo = imagecreatetruecolor($photo_x_size, $photo_y_size);

            //Aufrufen des Originalbildes
            $original_image = $this->create();

            //kopieren der Daten in neues Bild
            imagecopyresampled($resized_user_photo, $original_image, 0, 0, 0, 0, $photo_x_size, $photo_y_size, $this->imageProperties[0], $this->imageProperties[1]);

            $this->copyToFile($resized_user_photo);
            imagedestroy($resized_user_photo);
        }        
    }
}
?>