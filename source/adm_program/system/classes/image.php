<?php
/******************************************************************************
 * Diese Klasse verwaltet Bilder und bietet Methoden zum Anpassen dieser
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
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
 * rotate($direction = "right")
 *                  - dreht das Bild um 90° in eine Richtung ("left"/"right")
 * scaleLargerSide($new_max_size)
 *                  - skaliert die laengere Seite des Bildes auf den uebergebenen Pixelwert
 * scale($photo_x_size, $photo_y_size, $aspect_ratio = true)
 *                  - das Bild wird in einer vorgegebenen maximalen Laenge/Hoehe skaliert
 * delete()         - entfernt das Bild aus dem Speicher
 *
 *****************************************************************************/

class Image
{
    private $imagePath;
    private $imageType     = null;
    public  $imageResource = false;
    public  $imageWidth    = 0;
    public  $imageHeight   = 0;
    
    public function __construct($pathAndFilename = '')
    {
        if(strlen($pathAndFilename) > 0)
        {
            $this->setImageFromPath($pathAndFilename);
        }
    }

    // Methode setzt den Pfad zum Bild und liest Bildinformationen ein
    public function setImageFromPath($pathAndFilename)
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
    // imageData : String with binary image data
    public function setImageFromData($imageData)
    {
        $this->imageResource = imagecreatefromstring($imageData);
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
    
    private function createResource($pathAndFilename)
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
    public function copyToFile($imageResource = null, $pathAndFilename = '', $quality = 95)
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
    public function copyToBrowser($imageResource = null, $quality = 95)
    {
        if($imageResource == null)
        {
            $imageResource = $this->imageResource;
        }
        
        switch ($this->imageType)
        {
            case IMAGETYPE_JPEG:
                echo imagejpeg($imageResource, null, $quality);
                break;

            case IMAGETYPE_PNG:
                echo imagepng($imageResource);
                break;
        }
    }     
    
    // gibt den Mime-Type (image/png) des Bildes zurueck
    public function getMimeType()
    {
        return image_type_to_mime_type($this->imageType);
    }

    // setzt den Image-Type des Bildes neu
    public function setImageType($imageType)
    {
        switch ($imageType)
        {
            case 'jpeg':
                $this->imageType = IMAGETYPE_JPEG;
                break;

            case 'png':
                $this->imageType = IMAGETYPE_PNG;
                break;
        }
    }

    // Methode dreht das Bild um 90° in eine Richtung
    // direction : 'right' o. 'left' Richtung, in die gedreht wird
    public function rotate($direction = 'right')
    {
        // nur bei gueltigen Uebergaben weiterarbeiten
        if(($direction == 'left' || $direction == 'right'))
        {
            // Erzeugung neues Bild
            $photo_rotate = imagecreatetruecolor($this->imageHeight, $this->imageWidth);

            //kopieren der Daten in neues Bild
            for($y = 0; $y < $this->imageHeight; $y++)
            {
                for($x = 0; $x < $this->imageWidth; $x++)
                {
                    if($direction == 'right')
                    {
                        imagecopy($photo_rotate, $this->imageResource, $this->imageHeight - $y - 1, $x, $x, $y, 1,1 );
                    }
                    elseif($direction == 'left')
                    {
                        imagecopy($photo_rotate, $this->imageResource, $y, $this->imageWidth - $x - 1, $x, $y, 1,1 );
                    }
                }
            }

            //speichern
            $this->copyToFile($photo_rotate);

            //Loeschen des Bildes aus Arbeitsspeicher
            imagedestroy($photo_rotate);
        }
    }
    
    // Methode skaliert die laengere Seite des Bildes auf den uebergebenen Pixelwert
    // die andere Seite wird dann entsprechend dem Seitenverhaeltnis zurueckgerechnet
    public function scaleLargerSide($new_max_size)
    {
        // Errechnung Seitenverhaeltnis
        $seitenverhaeltnis = $this->imageWidth / $this->imageHeight;
            
        if($this->imageWidth >= $this->imageHeight)
        {
            // x-Seite soll scalliert werden
            $photo_x_size = $new_max_size;
            $photo_y_size = round($new_max_size / $seitenverhaeltnis);
        }
        else
        {
            // y-Seite soll scalliert werden
            $photo_x_size = round($new_max_size * $seitenverhaeltnis);
            $photo_y_size = $new_max_size;
        }
        $this->scale($photo_x_size, $photo_y_size, false);
    }

    // das Bild wird in einer vorgegebenen maximalen Laenge/Hoehe skaliert
    // new_x_size   : Anzahl Pixel auf die die X-Seite maximal veraendert werden soll
    // new_y_size   : Anzahl Pixel auf die die Y-Seite maximal veraendert werden soll
    // aspect_ratio : das aktuelle Seitenverhaeltnis des Bildes wird belassen,
    //                dadurch kann eine Seite kleiner werden als die Angabe vorsieht
    public function scale($photo_x_size, $photo_y_size, $aspect_ratio = true)
    {
        if($aspect_ratio == true)
        {
            if($photo_x_size < $this->imageWidth || $photo_y_size < $this->imageHeight)
            {
                if($this->imageWidth / $this->imageHeight > $photo_x_size / $photo_y_size)
                {
                    // auf max. Breite scalieren
                    $new_width  = $photo_x_size;
                    $new_height = round($photo_x_size / ($this->imageWidth / $this->imageHeight));
                }
                else
                {
                    // auf max. Hoehe scalieren
                    $new_width  = round($photo_y_size * ($this->imageWidth / $this->imageHeight));
                    $new_height = $photo_y_size;
                }
                $this->scale($new_width, $new_height, false);
            }
        }
        else
        {
            //Speicher zur Bildbearbeitung bereit stellen, erst ab php5 noetig
            @ini_set('memory_limit', '50M');

            // Erzeugung neues Bild
            $resized_user_photo = imagecreatetruecolor($photo_x_size, $photo_y_size);

            //kopieren der Daten in neues Bild
            imagecopyresampled($resized_user_photo, $this->imageResource, 0, 0, 0, 0, $photo_x_size, $photo_y_size, $this->imageWidth, $this->imageHeight);

            // nun die internen Bilddaten updaten
            imagedestroy($this->imageResource);
            $this->imageResource = $resized_user_photo;
            $this->imageWidht    = $photo_x_size;
            $this->imageHeight   = $photo_y_size;
        }
    }

    // entfernt das Bild aus dem Speicher
    public function delete()
    {
    	imagedestroy($this->imageResource);
    	$this->imageResource = null;
    	$this->imagePath = '';
    }
}
?>