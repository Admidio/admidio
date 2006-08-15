<?php
/******************************************************************************
 * Captcha - Klasse
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Diese Klasse erzeugt ein Captcha-Bildchen und speichert den zu
 * loesenden Code in der Session ab.
 *
 *
 * Um in einem Formular ein Captcha einzubinden, muss nur diese Datei als
 * Bild eingebunden werden. Zusaetzlich muss natuerlich ein Formularfeld
 * existieren, in das der User den Code eingibt. Die Captcha-Klasse speichert
 * seine eigene Loesung in der SessionVariable $_SESSION['captchacode']. Der
 * vom User eingegebene Code muss nun im aufgerufenen Script verglichen werden.
 *
 * Beispiel:
 *
 * if ( strtoupper($_SESSION['captchacode']) != strtoupper($_POST['captcha']) )
 * {
 * 		echo "Das Captcha wurde nicht richtig geloest...";
 * }
 * else
 * {
 *		echo "Das Captcha wurde richtig geloest!";
 * }
 *
 *
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

class Captcha
{

    function Captcha()
    {
        // Erst einmal die Schriftart fuer das Captcha festlegen
        $this->font = "mr_phone1.ttf";

        // Nun die Bildgroesse des Captchas festlegen
        $this->width = 200;
        $this->height = 60;

        // Hier wird die Hintergrundfarbe festgelegt. Einzelne RGB-Werte...
        $this->backgroundColourR = 255;
        $this->backgroundColourG = 239;
        $this->backgroundColourB = 196;

        // Hier wird die Schriftgroesse des CaptchaCodes festgelegt...
        $this->codeSize = 23;

        // Hier wird der Untertitel festgelegt.
        $this->backgroundWriting = "POWERED  BY   A D M I D I O . O R G";
        $this->backgroundWritingSize = 9;

        // Diese Zeichen sind erlaubt innerhalb des Captcha-Codes.
        // Schlecht lesbare Zeichen habe ich raus geworfen...
        $this->allowedChars = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ";

    }

    function getCaptcha()
    {
        // erst einmal einen Code generieren
        $this->generateNewCaptchaCode();

        // und hier wird das Captcha generiert und ausgegeben
        $this->makeCaptcha();
    }


    function generateNewCaptchaCode()
    {
        // neuen CaptchaCode erzeugen...

        // Hier wird die Anzahl der Captcha-Zeichen festgelegt
        // (das Captcha soll zwischen 4 und 6 Zeichen beinhalten)
        $this->charCount = rand(4,6);

        $this->captchaCode = '';
        for ($i=0; $i < $this->charCount; $i++)
        {
            $this->captchaCode = $this->captchaCode. $this->allowedChars{rand(0,strlen($this->allowedChars)-1)};
        }

        // hier wird der Code jetzt in der Session gespeichert...
        $_SESSION['captchacode'] = $this->captchaCode;
    }


    function makeCaptcha()
    {

        // ein leeres Bild definieren
        $image = imagecreatetruecolor($this->width, $this->height);

        // Hintergrundfarbe setzen...
        $background =  imagecolorallocate($image, $this->backgroundColourR, $this->backgroundColourG, $this->backgroundColourB);
        ImageFilledRectangle($image, 0, 0, $this->width, $this->height, $background);

        // Gitter in den Hintergrund zeichnen...
        // erst vertikal...
        for($i=0; $i < $this->width; $i += intval($this->backgroundWritingSize / 2))
        {
            $color	= imagecolorallocate($image, $this->backgroundColourR - 40, $this->backgroundColourG - 40, $this->backgroundColourB - 40);
            imageline($image, $i, 0, $i, $this->height, $color);
        }

        // ...dann horizontal
        for($i=0; $i < $this->height; $i += intval($this->backgroundWritingSize / 2))
        {
            imageline($image, 0, $i, $this->width, $i, $color);
        }

        // Untertitel in das Captcha reinschreiben...
        ImageTTFText($image, $this->backgroundWritingSize, 0, 15, $this->height-5, imagecolorallocate($image, 0, 0, 0), $this->font, $this->backgroundWriting);



        // Jetzt wird dem Bild der eigentliche CaptchaCode hinzugefuegt...
        $xStartPosition = 15;

        for ($i=0; $i < $this->charCount; $i++)
        {
                $xPosition = intval($xStartPosition + $i * ($this->width / ($this->charCount +1)));

                $text	= substr($this->captchaCode, $i, 1);
                $color	=  imagecolorallocate($image, $this->backgroundColourR - 150, $this->backgroundColourG - 150, $this->backgroundColourB - 150);
                ImageTTFText($image, $this->codeSize, 0, $xPosition, 35, $color, $this->font, $text);
        }

        // Jetzt noch das finale Bild ausgeben...
        header('Content-type: image/png');
        ImagePNG($image);

    }


}


// Hier wird nun die Klasse initialisiert und das Bildchen ausgegeben...
session_start();
$captcha = new Captcha();
$captcha->getCaptcha();


?>