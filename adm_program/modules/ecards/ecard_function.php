<?php
/**
 ***********************************************************************************************
 * Ecard functions
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class FunctionClass
{
    public $nameRecipientString      = '';
    public $emailRecipientString     = '';
    public $yourMessageString        = '';
    public $newMessageReceivedString = '';
    public $greetingCardFrom         = '';
    public $greetingCardString       = '';
    public $sendToString             = '';
    public $emailString              = '';

    /**
     * @param \Language $gL10n
     */
    public function __construct(Language $gL10n)
    {
        $this->nameRecipientString      = $gL10n->get('ECA_RECIPIENT_NAME');
        $this->emailRecipientString     = $gL10n->get('ECA_RECIPIENT_EMAIL');
        $this->yourMessageString        = $gL10n->get('SYS_MESSAGE');
        $this->newMessageReceivedString = $gL10n->get('ECA_NEW_MESSAGE_RECEIVED');
        $this->greetingCardFrom         = $gL10n->get('ECA_A_ECARD_FROM');
        $this->greetingCardString       = $gL10n->get('ECA_GREETING_CARD');
        $this->sendToString             = $gL10n->get('SYS_TO');
        $this->emailString              = $gL10n->get('SYS_EMAIL');
    }

    /**
     * @param string $directory Path of the directory with the template files
     * @return string[] Returns an array of the template filenames
     */
    public function getFileNames($directory)
    {
        $files = array();

        $dirHandle = @opendir($directory);
        if($dirHandle)
        {
            while (($entry = readdir($dirHandle)) !== false)
            {
                if($entry !== '.' && $entry !== '..' && is_file($directory.'/'.$entry))
                {
                    $files[] = $entry;
                }
            }
            closedir($dirHandle);
        }

        return $files;
    }

    /**
     * Diese Funktion holt das Template aus dem uebergebenen Verzeichnis und liefert die Daten und einen error state zurueck
     * @param string $tplFilename Filename of the template
     * @param string $tplFolder   Folder path of the templates
     * @return string|null Returns the content of the template file and null if file not found or couldn't open
     */
    public function getEcardTemplate($tplFilename, $tplFolder = '')
    {
        if ($tplFolder === '')
        {
            $tplFolder = THEME_ADMIDIO_PATH.'/ecard_templates/';
        }

        if (!is_file($tplFolder.$tplFilename))
        {
            return null;
        }

        $fileHandle = @fopen($tplFolder.$tplFilename, 'rb');
        if ($fileHandle)
        {
            $fileData = '';

            while (!feof($fileHandle))
            {
                $fileData .= fgets($fileHandle, 4096);
            }
            fclose($fileHandle);

            return $fileData;
        }

        return null;
    }

    /**
     * Diese Funktion ersetzt alle im Template enthaltenen Platzhalter durch die dementsprechenden Informationen
     * @param string $imageName
     * @param string $ecardMessage
     * @param string $ecardData      geparste Information von dem Grußkarten Template
     * @param string $recipientName  der Name des Empfaengers
     * @param string $recipientEmail die Email des Empfaengers
     * @return string
     *
     * Ersetzt werden folgende Platzhalter
     * Admidio Pfad:           <%g_root_path%>
     * Template Verzeichnis:   <%template_root_path%>
     * Style Eigenschaften:    <%ecard_font%>              <%ecard_font_size%>         <%ecard_font_color%> <%ecard_font_bold%> <%ecard_font_italic%>
     * Empfaenger Daten:       <%ecard_reciepient_email%>  <%ecard_reciepient_name%>
     * Sender Daten:           <%ecard_sender_id%>         <%ecard_sender_email%>      <%ecard_sender_name%>
     * Bild Daten:             <%ecard_image_width%>       <%ecard_image_height%>      <%ecard_image_name%>
     * Nachricht:              <%ecard_message%>
     */
    public function parseEcardTemplate($imageName, $ecardMessage, $ecardData, $recipientName, $recipientEmail)
    {
        global $gCurrentUser;

        // Falls der Name des Empfaenger nicht vorhanden ist wird er fuer die Vorschau ersetzt
        if(strip_tags(trim($recipientName)) === '')
        {
            $recipientName = '< '.$this->nameRecipientString.' >';
        }

        // Falls die Email des Empfaenger nicht vorhanden ist wird sie fuer die Vorschau ersetzt
        if(strip_tags(trim($recipientEmail)) === '')
        {
            $recipientEmail = '< '.$this->emailRecipientString.' >';
        }

        // Falls die Nachricht nicht vorhanden ist wird sie fuer die Vorschau ersetzt
        if(trim($ecardMessage) === '')
        {
            $ecardMessage = '< '.$this->yourMessageString.' >';
        }

        $pregRepArray = array();

        // Hier wird der Pfad zum Admidio Verzeichnis ersetzt
        $pregRepArray['/<%g_root_path%>/']                = ADMIDIO_URL;
        // Hier wird der Pfad zum aktuellen Template Verzeichnis ersetzt
        $pregRepArray['/<%theme_root_path%>/']            = THEME_URL;
        // Hier wird der Sender Name, Email und Id ersetzt
        $pregRepArray['/<%ecard_sender_id%>/']            = $gCurrentUser->getValue('usr_id');
        $pregRepArray['/<%ecard_sender_email%>/']         = $gCurrentUser->getValue('EMAIL');
        $pregRepArray['/<%ecard_sender_name%>/']          = $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME');
        // Hier wird der Empfaenger Name und Email ersetzt
        $pregRepArray['/<%ecard_reciepient_email%>/']     = noHTML($recipientEmail);
        $pregRepArray['/<%ecard_reciepient_name%>/']      = noHTML($recipientName);
        // Hier wird der Bildname ersetzt
        $pregRepArray['/<%ecard_image_name%>/']           = $imageName;

        $pregRepArray['/<%ecard_greeting_card_from%>/']   = noHTML($this->greetingCardFrom);
        $pregRepArray['/<%ecard_greeting_card_string%>/'] = noHTML($this->greetingCardString);
        $pregRepArray['/<%ecard_to_string%>/']            = noHTML($this->sendToString);
        $pregRepArray['/<%ecard_email_string%>/']         = noHTML($this->emailString);

        // make html in description secure
        $ecardMessage = htmLawed(stripslashes($ecardMessage), array('safe' => 1));

        // Hier wird die Nachricht ersetzt
        $pregRepArray['/<%ecard_message%>/']              = $ecardMessage;

        $ecardData = preg_replace(array_keys($pregRepArray), array_values($pregRepArray), $ecardData);

        return $ecardData;
    }

    /**
     * Diese Funktion ruft die Mail Klasse auf und uebergibt ihr die zu sendenden Daten
     * @param string $senderName
     * @param string $senderEmail
     * @param string $ecardHtmlData   geparste Daten vom Template
     * @param string $recipientName   der Name des Empfaengers
     * @param string $recipientEmail  die Email des Empfaengers
     * @param string $photoServerPath der Pfad wo die Bilder in der Grußkarte am Server liegen
     * @return bool|string
     */
    public function sendEcard($senderName, $senderEmail, $ecardHtmlData, $recipientName, $recipientEmail, $photoServerPath)
    {
        global $gPreferences;

        $imgPhotoPath = '';
        $returnCode = true;

        $email = new Email();
        $email->setSender($senderEmail, $senderName);
        $email->setSubject($this->newMessageReceivedString);
        $email->addRecipient($recipientEmail, $recipientName);

        // alle Bilder werden aus dem Template herausgeholt, damit diese als Anhang verschickt werden koennen
        if (preg_match_all('/(<img.*src=")(.*)(".*>)/Uim', $ecardHtmlData, $matchArray))
        {
            //$matchArray[0] = $this->deleteDoubleEntries($matchArray[0]);
            //$matchArray[2] = $this->deleteDoubleEntries($matchArray[2]);
            $matchArray[0] = array_unique($matchArray[0]);
            $matchArray[2] = array_unique($matchArray[2]);

            for ($i = 0, $iMax = count($matchArray[0]); $i < $iMax; ++$i)
            {
                // anstelle der URL muss nun noch der Server-Pfad gesetzt werden
                $imgServerPath = str_replace(THEME_URL, THEME_ADMIDIO_PATH, $matchArray[2][$i]);
                $imgServerPath = str_replace(ADMIDIO_URL, ADMIDIO_PATH, $imgServerPath);

                // wird das Bild aus photo_show.php generiert, dann den uebergebenen Pfad zum Bild einsetzen
                if(strpos($imgServerPath, 'photo_show.php') !== false)
                {
                    $imgServerPath = $photoServerPath;
                }
                // Bildnamen und Typ ermitteln
                $imgName = substr(strrchr($imgServerPath, '/'), 1);
                $imgType = substr(strrchr($imgName, '.'), 1);

                // das zu versendende eigentliche Bild, muss noch auf das entsprechende Format angepasst werden
                if(strpos($matchArray[2][$i], 'photo_show.php') !== false)
                {
                    $imgName = 'picture.'. $imgType;
                    $imgNameIntern = substr(md5(uniqid($imgName.time(), true)), 0, 8). '.'. $imgType;
                    $imgServerPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/'. $imgNameIntern;
                    $imgPhotoPath  = $imgServerPath;

                    $imageSized = new Image($photoServerPath);
                    $imageSized->scale($gPreferences['ecard_card_picture_width'], $gPreferences['ecard_card_picture_height']);
                    $imageSized->copyToFile(null, $imgServerPath);
                }

                // Bild als Anhang an die Mail haengen
                if($imgName !== 'none.jpg' && $imgName !== '')
                {
                    $uid = md5(uniqid($imgName.time(), true));
                    try
                    {
                        $email->addEmbeddedImage($imgServerPath, $uid, $imgName, $encoding = 'base64', 'image/'.$imgType);
                    }
                    catch (phpmailerException $e)
                    {
                        $returnCode = $e->errorMessage();
                    }
                    $ecardHtmlData = str_replace($matchArray[2][$i], 'cid:'.$uid, $ecardHtmlData);
                }
            }
        }

        $email->setText($ecardHtmlData);
        $email->sendDataAsHtml();

        if($returnCode)
        {
            $returnCode = $email->sendEmail();
        }

        // nun noch das von der Groesse angepasste Bild loeschen
        unlink($imgPhotoPath);

        return $returnCode;
    }
}
