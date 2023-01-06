<?php
/**
 ***********************************************************************************************
 * Ecard functions
 *
 * @copyright 2004-2023 The Admidio Team
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
     * @param Language $gL10n
     */
    public function __construct(Language $gL10n)
    {
        $this->nameRecipientString      = $gL10n->get('SYS_RECIPIENT');
        $this->emailRecipientString     = $gL10n->get('SYS_RECIPIENT_EMAIL');
        $this->yourMessageString        = $gL10n->get('SYS_MESSAGE');
        $this->newMessageReceivedString = $gL10n->get('SYS_NEW_MESSAGE_RECEIVED');
        $this->greetingCardFrom         = $gL10n->get('SYS_ECARD_FROM');
        $this->greetingCardString       = $gL10n->get('SYS_GREETING_CARD');
        $this->sendToString             = $gL10n->get('SYS_TO');
        $this->emailString              = $gL10n->get('SYS_EMAIL');
    }

    /**
     * @param string $directory Path of the directory with the template files
     * @return array<int,string> Returns an array of the template filenames
     */
    public function getFileNames($directory)
    {
        try {
            $directoryFiles = FileSystemUtils::getDirectoryContent($directory, false, false, array(FileSystemUtils::CONTENT_TYPE_FILE));

            return array_keys($directoryFiles);
        } catch (\RuntimeException $exception) {
            return array();
        }
    }

    /**
     * This function fetches the template from the given directory and returns the template content and an error state.
     * @param string $tplFilename Filename of the template
     * @param string $tplFolder   Folder path of the templates
     * @return string|null Returns the content of the template file and null if file not found or couldn't open
     */
    public function getEcardTemplate($tplFilename, $tplFolder = '')
    {
        if ($tplFolder === '') {
            $tplFolder = ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates/';
        }

        if (!is_file($tplFolder . $tplFilename)) {
            return null;
        }

        $fileHandle = @fopen($tplFolder . $tplFilename, 'rb');
        if ($fileHandle) {
            $fileData = '';

            while (!feof($fileHandle)) {
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
        if (strip_tags(trim($recipientName)) === '') {
            $recipientName = '< '.$this->nameRecipientString.' >';
        }

        // Falls die Email des Empfaenger nicht vorhanden ist wird sie fuer die Vorschau ersetzt
        if (strip_tags(trim($recipientEmail)) === '') {
            $recipientEmail = '< '.$this->emailRecipientString.' >';
        }

        // Falls die Nachricht nicht vorhanden ist wird sie fuer die Vorschau ersetzt
        if (trim($ecardMessage) === '') {
            $ecardMessage = '< '.$this->yourMessageString.' >';
        }

        $pregRepArray = array();

        // Hier wird der Pfad zum Admidio Verzeichnis ersetzt
        $pregRepArray['/<%g_root_path%>/']                = ADMIDIO_URL;
        // Hier wird der Pfad zum aktuellen Template Verzeichnis ersetzt
        $pregRepArray['/<%theme_root_path%>/']            = THEME_URL;
        // Hier wird der Sender Name, Email und Id ersetzt
        $pregRepArray['/<%ecard_sender_id%>/']            = $gCurrentUser->getValue('usr_uuid');
        $pregRepArray['/<%ecard_sender_email%>/']         = $gCurrentUser->getValue('EMAIL');
        $pregRepArray['/<%ecard_sender_name%>/']          = $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME');
        // Hier wird der Empfaenger Name und Email ersetzt
        $pregRepArray['/<%ecard_reciepient_email%>/']     = SecurityUtils::encodeHTML($recipientEmail);
        $pregRepArray['/<%ecard_reciepient_name%>/']      = SecurityUtils::encodeHTML($recipientName);
        // Hier wird der Bildname ersetzt
        $pregRepArray['/<%ecard_image_name%>/']           = $imageName;

        $pregRepArray['/<%ecard_greeting_card_from%>/']   = SecurityUtils::encodeHTML($this->greetingCardFrom);
        $pregRepArray['/<%ecard_greeting_card_string%>/'] = SecurityUtils::encodeHTML($this->greetingCardString);
        $pregRepArray['/<%ecard_to_string%>/']            = SecurityUtils::encodeHTML($this->sendToString);
        $pregRepArray['/<%ecard_email_string%>/']         = SecurityUtils::encodeHTML($this->emailString);

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
     * @param string $recipientFirstName   der Name des Empfaengers
     * @param string $recipientLastName   der Name des Empfaengers
     * @param string $recipientEmail  die Email des Empfaengers
     * @param string $photoServerPath der Pfad wo die Bilder in der Grußkarte am Server liegen
     * @return bool|string
     */
    public function sendEcard($senderName, $senderEmail, $ecardHtmlData, $recipientFirstName, $recipientLastName, $recipientEmail, $photoServerPath)
    {
        global $gSettingsManager, $gLogger;

        $imgPhotoPath = '';
        $returnCode = true;

        $email = new Email();
        $email->setSender($senderEmail, $senderName);
        $email->setSubject($this->newMessageReceivedString);
        $email->addRecipient($recipientEmail, $recipientFirstName, $recipientLastName);

        // alle Bilder werden aus dem Template herausgeholt, damit diese als Anhang verschickt werden koennen
        if (preg_match_all('/(<img .*src=")(.*)(".*>)/Uim', $ecardHtmlData, $matchArray)) {
            foreach (array_unique($matchArray[2]) as $match) {
                // anstelle der URL muss nun noch der Server-Pfad gesetzt werden
                $replaces = array(
                    THEME_URL   => THEME_PATH,
                    ADMIDIO_URL => ADMIDIO_PATH
                );
                $imgServerPath = StringUtils::strMultiReplace($match, $replaces);

                // wird das Bild aus photo_show.php generiert, dann den uebergebenen Pfad zum Bild einsetzen
                if (str_contains($imgServerPath, 'photo_show.php')) {
                    $imgServerPath = $photoServerPath;
                }

                // Bildnamen und Typ ermitteln
                $imagePathInfo = pathinfo($imgServerPath);
                $imgName = $imagePathInfo['basename'];
                $imgType = $imagePathInfo['extension'];

                // das zu versendende eigentliche Bild, muss noch auf das entsprechende Format angepasst werden
                if (str_contains($match, 'photo_show.php')) {
                    $imgName = 'picture.' . $imgType;
                    $imgNameIntern = substr(md5(uniqid($imgName . time(), true)), 0, 8) . '.' . $imgType;
                    $imgServerPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/'. $imgNameIntern;
                    $imgPhotoPath  = $imgServerPath;

                    $imageSized = new Image($photoServerPath);
                    $imageSized->scale($gSettingsManager->getInt('ecard_card_picture_width'), $gSettingsManager->getInt('ecard_card_picture_height'));
                    $imageSized->copyToFile(null, $imgServerPath);
                }

                // Bild als Anhang an die Mail haengen
                if ($imgName !== 'none.jpg' && $imgName !== '') {
                    $cid = md5(uniqid($imgName . time(), true));
                    $result = $email->addEmbeddedImage($imgServerPath, $cid, $imgName, 'base64', 'image/' . $imgType);
                    if ($result) {
                        $ecardHtmlData = str_replace($match, 'cid:' . $cid, $ecardHtmlData);
                    } else {
                        $returnCode = $email->ErrorInfo;
                    }
                }
            }
        }

        $email->setText($ecardHtmlData);
        $email->setHtmlMail();

        if ($returnCode) {
            $returnCode = $email->sendEmail();
        }

        // now delete the resized image
        try {
            FileSystemUtils::deleteFileIfExists($imgPhotoPath);
        } catch (\RuntimeException $exception) {
            $gLogger->error('Could not delete file!', array('filePath' => $imgPhotoPath));
            // TODO
        }

        return $returnCode;
    }
}
