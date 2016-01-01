<?php
/**
 ***********************************************************************************************
 * Diese Klasse verwaltet Bilder und bietet Methoden zum Anpassen dieser
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
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
 * scaleLargerSide($newMaxSize)
 *                  - skaliert die laengere Seite des Bildes auf den uebergebenen Pixelwert
 * scale($newXSize, $newYSize, $aspect_ratio = true)
 *                  - das Bild wird in einer vorgegebenen maximalen Laenge/Hoehe skaliert
 * delete()         - entfernt das Bild aus dem Speicher
 *
 *****************************************************************************/
class Image
{
    private $imagePath;
    private $imageType    = null;
    public $imageResource = null;
    public $imageWidth    = 0;
    public $imageHeight   = 0;

    /**
     * @param string $pathAndFilename
     */
    public function __construct($pathAndFilename = '')
    {
        if($pathAndFilename !== '')
        {
            $this->setImageFromPath($pathAndFilename);
        }
    }

    /**
     * Methode setzt den Pfad zum Bild und liest Bildinformationen ein
     * @param string $pathAndFilename
     * @return bool
     */
    public function setImageFromPath($pathAndFilename)
    {
        if(file_exists($pathAndFilename))
        {
            $this->imagePath = $pathAndFilename;
            $properties = getimagesize($this->imagePath);
            $this->imageWidth  = $properties[0];
            $this->imageHeight = $properties[1];
            $this->imageType   = $properties[2];

            if($this->createResource($pathAndFilename))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * Methode liest das Bild aus einem String ein und wird intern als PNG-Bild weiter verarbeitet und ausgegeben
     * @param string $imageData String with binary image data
     * @return bool
     */
    public function setImageFromData($imageData)
    {
        $this->imageResource = imagecreatefromstring($imageData);

        if($this->imageResource !== false)
        {
            $this->imageWidth  = imagesx($this->imageResource);
            $this->imageHeight = imagesy($this->imageResource);
            $this->imageType   = IMAGETYPE_PNG;
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * @param string $pathAndFilename
     * @return bool
     */
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

    /**
     * Methode kopiert die uebergebene Bildresource in die uebergebene Datei bzw. der hinterlegten Datei des Objekts
     * @param resource $imageResource   eine andere Bild-Resource kann uebergeben werden
     * @param string   $pathAndFilename ein andere Datei kann zur Ausgabe angegeben werden
     * @param int      $quality         die Qualitaet kann fuer jpeg-Dateien veraendert werden
     * @return bool true, falls erfolgreich
     */
    public function copyToFile($imageResource = null, $pathAndFilename = '', $quality = 95)
    {
        $returnValue = false;

        if($pathAndFilename === '')
        {
            $pathAndFilename = $this->imagePath;
        }
        if($imageResource === null)
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

    /**
     * Methode gibt das Bild direkt aus, so dass es im Browser dargestellt werden kann
     * @param resource $imageResource eine andere Bild-Resource kann uebergeben werden
     * @param int      $quality       die Qualitaet kann fuer jpeg-Dateien veraendert werden
     */
    public function copyToBrowser($imageResource = null, $quality = 95)
    {
        if($imageResource === null)
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

    /**
     * gibt den Mime-Type (image/png) des Bildes zurueck
     * @return string
     */
    public function getMimeType()
    {
        return image_type_to_mime_type($this->imageType);
    }

    /**
     * setzt den Image-Type des Bildes neu
     * @param string $imageType
     */
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

    /**
     * Methode dreht das Bild um 90° in eine Richtung
     * @param string $direction 'right' o. 'left' Richtung, in die gedreht wird
     */
    public function rotate($direction = 'right')
    {
        // nur bei gueltigen Uebergaben weiterarbeiten
        if(($direction === 'left' || $direction === 'right'))
        {
            // Erzeugung neues Bild
            $photo_rotate = imagecreatetruecolor($this->imageHeight, $this->imageWidth);

            // kopieren der Daten in neues Bild
            for($y = 0; $y < $this->imageHeight; ++$y)
            {
                for($x = 0; $x < $this->imageWidth; ++$x)
                {
                    if($direction === 'right')
                    {
                        imagecopy($photo_rotate, $this->imageResource, $this->imageHeight - $y - 1, $x, $x, $y, 1, 1);
                    }
                    elseif($direction === 'left')
                    {
                        imagecopy($photo_rotate, $this->imageResource, $y, $this->imageWidth - $x - 1, $x, $y, 1, 1);
                    }
                }
            }

            // speichern
            $this->copyToFile($photo_rotate);

            // Loeschen des Bildes aus Arbeitsspeicher
            imagedestroy($photo_rotate);
        }
    }

    /**
     * Methode skaliert die laengere Seite des Bildes auf den uebergebenen Pixelwert
     * die andere Seite wird dann entsprechend dem Seitenverhaeltnis zurueckgerechnet
     * @param int $newMaxSize
     */
    public function scaleLargerSide($newMaxSize)
    {
        // Errechnung Seitenverhaeltnis
        $seitenverhaeltnis = $this->imageWidth / $this->imageHeight;

        if($this->imageWidth >= $this->imageHeight)
        {
            // x-Seite soll scalliert werden
            $newXSize = $newMaxSize;
            $newYSize = round($newMaxSize / $seitenverhaeltnis);
        }
        else
        {
            // y-Seite soll scalliert werden
            $newXSize = round($newMaxSize * $seitenverhaeltnis);
            $newYSize = $newMaxSize;
        }
        $this->scale($newXSize, $newYSize, false);
    }

    /**
     * Scale an image to the new size of the parameters. Therefore the PHP instanze may need
     * some memory which should be set through the PHP setting memory_limit.
     * @param int  $newXSize            The new horizontal width in pixel. The image will be scaled to this size.
     * @param int  $newYSize            The new vertical height in pixel. The image will be scaled to this size.
     * @param bool $maintainAspectRatio If this is set to true, the image will be within the given size
     *                                  but maybe one side will be smaller than set with the parameters.
     */
    public function scale($newXSize, $newYSize, $maintainAspectRatio = true)
    {
        if($maintainAspectRatio)
        {
            if($newXSize < $this->imageWidth || $newYSize < $this->imageHeight)
            {
                if($this->imageWidth / $this->imageHeight > $newXSize / $newYSize)
                {
                    // scale to maximum width
                    $newWidth  = $newXSize;
                    $newHeight = round($newXSize / ($this->imageWidth / $this->imageHeight));
                }
                else
                {
                    // scale to maximum height
                    $newWidth  = round($newYSize * ($this->imageWidth / $this->imageHeight));
                    $newHeight = $newYSize;
                }
                $this->scale($newWidth, $newHeight, false);
            }
        }
        else
        {
            // check current memory limit and set this to 50MB if the current value is lower
            preg_match('/(\d+)/', ini_get('memory_limit'), $memoryLimit);
            if($memoryLimit[0] < 50)
            {
                @ini_set('memory_limit', '50M');
            }

            // create a new image
            $resizedUserPhoto = imagecreatetruecolor($newXSize, $newYSize);

            // copy image data to a new image with the new given size
            imagecopyresampled($resizedUserPhoto, $this->imageResource, 0, 0, 0, 0, $newXSize, $newYSize, $this->imageWidth, $this->imageHeight);
            imagedestroy($this->imageResource);

            // update the class parameters to new image data
            $this->imageResource = $resizedUserPhoto;
            $this->imageWidth    = $newXSize;
            $this->imageHeight   = $newYSize;
        }
    }

    /**
     * Delete image from class and server memory
     */
    public function delete()
    {
        imagedestroy($this->imageResource);
        $this->imageResource = null;
        $this->imagePath = '';
    }
}
