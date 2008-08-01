<?php
/******************************************************************************
 * Diese Klasse verwaltet Bilder und bietet Methoden zum Anpassen dieser
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Folgende Methoden stehen zur Verfuegung:
 *
 * setImageFromPath($pathAndFilename)
 *                  - setzt den Pfad zum Bild und liest Bildinformationen ein
 * setImageFromData($imageData)
 *                  - liest das Bild aus einem String ein und wird intern als PNG-Bild
 *                    weiter verarbeitet und ausgegeben
 * copyToFile($imageResource = null, $pathAndFilename = "", $quality = 95)
 *                  - kopiert die uebergebene Bildresource in die uebergebene Datei bzw. der 
 *                    hinterlegten Datei des Objekts
 * copyToBrowser($imageResource = null, $quality = 95)
 *                  - gibt das Bild direkt aus, so dass es im Browser dargestellt werden kann
 * getMimeType()    - gibt den Mime-Type (image/png) des Bildes zurueck
 * resize($new_x_size, $new_y_size, $seitenveraehltnis_beibehalten = true, $enlarge = false)
 *                  - veraendert die Bildgroesse
 *
 *****************************************************************************/

class Image
{
    var $imagePath;
    var $imageResource = false;
    var $imageWidth = 0;
    var $imageHeight = 0;
    var $imageType = null;
    
    function Image($pathAndFilename = "")
    {
        if(strlen($pathAndFilename) > 0)
        {
            $this->setImageFromPath($pathAndFilename);
        }
    }

    // Methode setzt den Pfad zum Bild und liest Bildinformationen ein
    function setImageFromPath($pathAndFilename)
    {
        if(file_exists($pathAndFilename))
        {
            $this->imagePath = $pathAndFilename;
            $properties = getimagesize($this->imagePath);
            $this->imageWidth    = $properties[0];
            $this->imageHeight   = $properties[1];
            $this->imageType     = $properties[2];

            if($this->createResource($pathAndFilename))
            {
                return true;
            }
        }
        return false;
    }

    // Methode liest das Bild aus einem String ein und wird intern als PNG-Bild
    // weiter verarbeitet und ausgegeben
    // imageData : String mit den Bilddaten, dieser sollte vorher mit addslashes 
    //             bearbeitet werden, da ansonsten bei der Verarbeitung Daten
    //             verloren gehen und es zu Fehlern kommt
    function setImageFromData($imageData)
    {
        $this->imageResource = imagecreatefromstring(stripslashes($imageData));
        if($this->imageResource !== false)
        {        
            $this->imageWidth    = imagesx($this->imageResource);
            $this->imageHeight   = imagesy($this->imageResource);
            $this->imageType     = IMAGETYPE_PNG;
            return true;
        }
        else
        {
            return false;
        }
    } 
    
    function createResource($pathAndFilename)
    {
        switch ($this->imageType)
        {
            case IMAGETYPE_JPEG:
                $this->imageResource = imagecreatefromjpeg($pathAndFilename);
                break;

            case IMAGETYPE_PNG:
                $this->imageResource = imagecreatefrompng($pathAndFilename);
                break;
        }
        
        if($this->imageResource !== false)
        {
            return true;
        }
        return false;      
    }
    
    // Methode kopiert die uebergebene Bildresource in die uebergebene Datei bzw. der 
    // hinterlegten Datei des Objekts
    // Optional: - eine andere Bild-Resource kann uebergeben werden
    //           - ein andere Datei kann zur Ausgabe angegeben werden
    //           - die Qualitaet kann fuer jpeg-Dateien veraendert werden
    // Rueckgabe: true, falls erfolgreich
    function copyToFile($imageResource = null, $pathAndFilename = "", $quality = 95)
    {
        $returnValue = false;
        
        if(strlen($pathAndFilename) == 0)
        {
            $pathAndFilename = $this->imagePath;
        }
        if($imageResource == null)
        {
            $imageResource = $this->imageResource;
        }
        
        switch ($this->imageType)
        {
            case IMAGETYPE_JPEG:
                $returnValue = imagejpeg($imageResource, $pathAndFilename, $quality);
                break;

            case IMAGETYPE_PNG:
                $returnValue = imagepng($imageResource, $pathAndFilename);
                break;
        }
        
        return $returnValue;
    }
    
    // Methode gibt das Bild direkt aus, so dass es im Browser dargestellt werden kann
    // Optional: - eine andere Bild-Resource kann uebergeben werden
    //           - die Qualitaet kann fuer jpeg-Dateien veraendert werden
    function copyToBrowser($imageResource = null, $quality = 95)
    {
        if($imageResource == null)
        {
            $imageResource = $this->imageResource;
        }
        
        switch ($this->imageType)
        {
            case IMAGETYPE_JPEG:
                echo imagejpeg($imageResource, $quality);
                break;

            case IMAGETYPE_PNG:
                echo imagepng($imageResource);
                break;
        }
    }     
    
    // gibt den Mime-Type (image/png) des Bildes zurueck
    function getMimeType()
    {
        return image_type_to_mime_type($this->imageType);
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
        if($this->imageWidth  > $new_x_size
        || $this->imageHeight > $new_y_size
        || $enlarge == true)
        {
            //Speicher zur Bildbearbeitung bereit stellen, erst ab php5 noetig
            @ini_set('memory_limit', '50M');

            //Errechnung Seitenverhaeltnis
            $seitenverhaeltnis = $this->imageWidth / $this->imageHeight;
            
            if($seitenveraehltnis_beibehalten == true)
            {
                //x-Seite soll scalliert werden
                if(($this->imageWidth / $new_x_size) >= ($this->imageHeight / $new_y_size))
                {
                    $photo_x_size = $new_x_size;
                    $photo_y_size = round($new_x_size / $seitenverhaeltnis);
                }

                //y-Seite soll scalliert werden
                if(($this->imageWidth / $new_x_size) < ($this->imageHeight / $new_y_size))
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

            //kopieren der Daten in neues Bild
            imagecopyresampled($resized_user_photo, $this->imageResource, 0, 0, 0, 0, $photo_x_size, $photo_y_size, $this->imageWidth, $this->imageHeight);

            // nun die interne Bildresource updaten
            imagedestroy($this->imageResource);
            $this->imageResource = $resized_user_photo;
            
            // falls das Bild im Dateisystem abgelegt ist, dann auch dort abspeichern
            if(strlen($this->imagePath) > 0)
            {
                $this->copyToFile($resized_user_photo);
            }
        }        
    }
}
?>