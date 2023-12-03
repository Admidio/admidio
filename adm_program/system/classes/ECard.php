<?php
/**
 ***********************************************************************************************
 * Class will handle some ECard functions
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class ECard
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
     * @throws Exception
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
    public function getFileNames(string $directory): array
    {
        try {
            $directoryFiles = FileSystemUtils::getDirectoryContent($directory, false, false, array(FileSystemUtils::CONTENT_TYPE_FILE));

            return array_keys($directoryFiles);
        } catch (RuntimeException $exception) {
            return array();
        }
    }

    /**
     * This function fetches the template from the given directory and returns the template content and an error state.
     * @param string $tplFilename Filename of the template
     * @param string $tplFolder   Folder path of the templates
     * @return string|null Returns the content of the template file and null if file not found or couldn't open
     */
    public function getEcardTemplate(string $tplFilename, string $tplFolder = ''): ?string
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
     * This method replaces all placeholders contained in the template with the corresponding information.
     * The following placeholders are replaced:
     *  Admidio path:           <%g_root_path%>
     *  Template Verzeichnis:   <%template_root_path%>
     *  Style Eigenschaften:    <%ecard_font%>              <%ecard_font_size%>         <%ecard_font_color%> <%ecard_font_bold%> <%ecard_font_italic%>
     *  Recipient data:         <%ecard_reciepient_email%>  <%ecard_reciepient_name%>
     *  Sender Daten:           <%ecard_sender_id%>         <%ecard_sender_email%>      <%ecard_sender_name%>
     *  Bild Daten:             <%ecard_image_width%>       <%ecard_image_height%>      <%ecard_image_name%>
     *  Nachricht:              <%ecard_message%>     * @param string $imageName
     * @param string $ecardMessage
     * @param string $ecardData      Parsed information from the greeting card template
     * @param string $recipientName  the name of the recipient
     * @param string $recipientEmail the email of the recipient
     * @return string
     */
    public function parseEcardTemplate(string $imageName, string $ecardMessage, string $ecardData, string $recipientName, string $recipientEmail): string
    {
        global $gCurrentUser;

        // If the name of the recipient is not available, it will be replaced for the preview
        if (strip_tags(trim($recipientName)) === '') {
            $recipientName = '< '.$this->nameRecipientString.' >';
        }

        // If the email of the recipient is not available, it will be replaced for the preview
        if (strip_tags(trim($recipientEmail)) === '') {
            $recipientEmail = '< '.$this->emailRecipientString.' >';
        }

        // If the message is not available, it will be replaced for the preview
        if (trim($ecardMessage) === '') {
            $ecardMessage = '< '.$this->yourMessageString.' >';
        }

        $pregRepArray = array();

        $pregRepArray['/<%g_root_path%>/']                = ADMIDIO_URL;
        $pregRepArray['/<%theme_root_path%>/']            = THEME_URL;
        $pregRepArray['/<%ecard_sender_id%>/']            = $gCurrentUser->getValue('usr_uuid');
        $pregRepArray['/<%ecard_sender_email%>/']         = $gCurrentUser->getValue('EMAIL');
        $pregRepArray['/<%ecard_sender_name%>/']          = $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME');
        $pregRepArray['/<%ecard_reciepient_email%>/']     = SecurityUtils::encodeHTML($recipientEmail);
        $pregRepArray['/<%ecard_reciepient_name%>/']      = SecurityUtils::encodeHTML($recipientName);
        $pregRepArray['/<%ecard_image_name%>/']           = $imageName;

        $pregRepArray['/<%ecard_greeting_card_from%>/']   = SecurityUtils::encodeHTML($this->greetingCardFrom);
        $pregRepArray['/<%ecard_greeting_card_string%>/'] = SecurityUtils::encodeHTML($this->greetingCardString);
        $pregRepArray['/<%ecard_to_string%>/']            = SecurityUtils::encodeHTML($this->sendToString);
        $pregRepArray['/<%ecard_email_string%>/']         = SecurityUtils::encodeHTML($this->emailString);

        $pregRepArray['/<%ecard_message%>/']              = $ecardMessage;

        return preg_replace(array_keys($pregRepArray), array_values($pregRepArray), $ecardData);
    }

    /**
     * This method calls the mail class and passes it the data to be sent.
     * @param string $senderName
     * @param string $senderEmail
     * @param string $ecardHtmlData Parsed data from the template
     * @param string $recipientFirstName the first name of the recipient
     * @param string $recipientLastName the last name of the recipient
     * @param string $recipientEmail the email of the recipient
     * @param string $photoServerPath the path where the images in the greeting card are located on the server
     * @return bool|string
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function sendEcard(string $senderName, string $senderEmail, string $ecardHtmlData, string $recipientFirstName, string $recipientLastName, string $recipientEmail, string $photoServerPath)
    {
        global $gSettingsManager, $gLogger;

        $imgPhotoPath = '';
        $returnCode = true;

        $email = new Email();
        $email->setSender($senderEmail, $senderName);
        $email->setSubject($this->newMessageReceivedString);
        $email->addRecipient($recipientEmail, $recipientFirstName, $recipientLastName);

        // all images are extracted from the template so that they can be sent as attachments
        if (preg_match_all('/(<img .*src=")(.*)(".*>)/Uim', $ecardHtmlData, $matchArray)) {
            foreach (array_unique($matchArray[2]) as $match) {
                // The server path must now be set instead of the URL
                $replaces = array(
                    THEME_URL   => THEME_PATH,
                    ADMIDIO_URL => ADMIDIO_PATH
                );
                $imgServerPath = StringUtils::strMultiReplace($match, $replaces);

                // the image is generated from photo_show.php, then insert the given path to the image
                if (str_contains($imgServerPath, 'photo_show.php')) {
                    $imgServerPath = $photoServerPath;
                }

                // Determine image name and type
                $imagePathInfo = pathinfo($imgServerPath);
                $imgName = $imagePathInfo['basename'];
                $imgType = $imagePathInfo['extension'];

                // the actual image to be sent must still be adapted to the appropriate format
                if (str_contains($match, 'photo_show.php')) {
                    $imgName = 'picture.' . $imgType;
                    $imgNameIntern = substr(md5(uniqid($imgName . time(), true)), 0, 8) . '.' . $imgType;
                    $imgServerPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/'. $imgNameIntern;
                    $imgPhotoPath  = $imgServerPath;

                    $imageSized = new Image($photoServerPath);
                    $imageSized->scale($gSettingsManager->getInt('photo_ecard_scale'), $gSettingsManager->getInt('photo_ecard_scale'));
                    $imageSized->copyToFile(null, $imgServerPath);
                }

                // Attach the picture to the mail
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
        } catch (RuntimeException $exception) {
            $gLogger->error('Could not delete file!', array('filePath' => $imgPhotoPath));
            // TODO
        }

        return $returnCode;
    }
}
