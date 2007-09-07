<?php
/******************************************************************************
 * Captcha - Klasse
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
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
 *         echo "Das Captcha wurde nicht richtig geloest...";
 * }
 * else
 * {
 *        echo "Das Captcha wurde richtig geloest!";
 * }
 *
 * Wenn die auszuloesende Aktion erfolgreich ausgefuehrt wurde, sollte der
 * CaptchaCode aus der Session geloescht werden, damit man nicht anschliessend
 * erneut das Script aufrufen kann ohne vorher ein neues Captcha geloest zu
 * haben.
 *
 * Zum Beispiel so:
 *
 * // Der CaptchaCode wird bei erfolgreicher Aktion aus der Session geloescht
 * if (isset($_SESSION['captchacode']))
 * {
 *    unset($_SESSION['captchacode']);
 * }
 *
 *****************************************************************************/

class Captcha
{

    function Captcha()
    {
        $absolute_path = substr(__FILE__, 0, strpos(__FILE__, "captcha_class.php"));

        // Hier wird jetzt die Schriftart festgelegt...
        $this->font = $absolute_path. "mr_phone1.ttf";


        // Nun die Bildgroesse des Captchas festlegen
        $this->width = 200;
        $this->height = 60;

        // Hier wird die Hintergrundfarbe festgelegt. Einzelne RGB-Werte...
        $this->backgroundColourR = 255;
        $this->backgroundColourG = 239;
        $this->backgroundColourB = 196;

        // Hier wird die Schriftgroesse des CaptchaCodes festgelegt...
        $this->codeSize = 15;

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
        $image = imagecreate($this->width, $this->height);

        // Hintergrundfarbe setzen...
        $background =  imagecolorallocate($image, $this->backgroundColourR, $this->backgroundColourG, $this->backgroundColourB);
        ImageFilledRectangle($image, 0, 0, $this->width, $this->height, $background);

        // Gitter in den Hintergrund zeichnen...
        // erst vertikal...
        for($i=0; $i < $this->width; $i += intval($this->backgroundWritingSize / 2))
        {
            $color    = imagecolorallocate($image, $this->backgroundColourR - 40, $this->backgroundColourG - 40, $this->backgroundColourB - 40);
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

                $text    = substr($this->captchaCode, $i, 1);
                $color    =  imagecolorallocate($image, $this->backgroundColourR - 125, $this->backgroundColourG - 55, $this->backgroundColourB - 90);
                ImageTTFText($image, $this->codeSize, 0, $xPosition, 35, $color, $this->font, $text);
        }

        // Jetzt noch das finale Bild ausgeben...
        header('Content-type: image/png');
        ImagePNG($image);
        ImageDestroy($image);

    }


}


// Hier wird nun die Klasse initialisiert und das Bildchen ausgegeben...
session_name('admidio_php_session_id');
session_start();
$captcha = new Captcha();
$captcha->getCaptcha();


?>