<?php
/**
 ***********************************************************************************************
 * Create and send a text or html email with attachments
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Email
 * Mit dieser Klasse kann ein Email-Objekt erstellt
 * und anschliessend verschickt werden.
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors:
 * Email()
 *
 * Nun wird der Absender gesetzt:
 * setSender($address, $name = '')
 * Parameters: $address - Die Emailadresse
 *             $name    - Der Name des Absenders (optional)
 *
 * Nun koennen in beliebiger Reihenfolge und Anzahl Adressaten (To,Cc,Bcc)
 * der Mail hinzugefuegt werden:
 * (optional und mehrfach aufrufbar, es muss jedoch mindestens
 * ein Empfaenger mittels einer der drei Funktionen gesetzt werden)
 *
 * addRecipient($address, $name = '')
 * addCopy($address, $name = '')
 * addBlindCopy($address, $name = '')
 * Parameters: $address - Die Emailadresse
 *             $name    - Der Name des Absenders (optional)
 *
 * Nun noch ein Subject setzen (optional):
 * setSubject($subject)
 * Parameters: $subject - Der Text des Betreffs
 *
 * Der Email einen Text geben:
 * function setText($text. )
 * Parameters: $text - Der Text der Mail
 *
 * Bei Bedarf kann man sich eine Kopie der Mail zuschicken lassen (optional):
 * setCopyToSenderFlag()
 *
 * Sollen in der Kopie zusaetzlich noch alle Empfaenger aufgelistet werden,
 * muss folgende Funktion auch noch aufgerufen werden (optional):
 * function setListRecipientsFlag()
 *
 * Methode gibt die maximale Groesse der Anhaenge zurueck
 * sizeUnit : 'Byte' = byte; 'KiB' = kibibyte; 'MiB' = mebibyte; 'GiB' = gibibyte; 'TiB' = tebibyte
 * getMaxAttachmentSize($sizeUnit = 'MiB')
 *
 * Soll die Nachricht als HTML Code interpretiert und versendet werden,
 * muss folgende Funktion auch noch aufgerufen werden (optional):
 * function sendDataAsHtml()
 *
 * Am Ende muss die Mail natuerlich noch gesendet werden:
 * function sendEmail();
 */
class Email extends PHPMailer
{
    const SIZE_UNIT_BYTE = 'Byte';
    const SIZE_UNIT_KIBIBYTE = 'KiB';
    const SIZE_UNIT_MEBIBYTE = 'MiB';
    const SIZE_UNIT_GIBIBYTE = 'GiB';
    const SIZE_UNIT_TEBIBYTE = 'TiB';

    private $emText           = ''; // plain text of email
    private $emHtmlText       = ''; // html text of email
    private $emAddresses      = array(); // Hier werden noch mal alle Empfaenger der Mail reingeschrieben, fuer den Fall das eine Kopie der Mail angefordert wird...
    private $emSender         = array(); // mail sender address and Name
    private $emCopyToSender   = false;
    private $emListRecipients = false;
    private $emSendAsHTML     = false;
    private $emBccArray       = array();

    /**
     * Email constructor.
     */
    public function __construct()
    {
        // Übername Einstellungen
        global $gL10n, $gPreferences, $gDebug;

        parent::__construct(true); // enable exceptions in PHPMailer

        $this->Timeout = 30; // set timeout to 30 seconds

        // Versandmethode festlegen
        if ($gPreferences['mail_send_method'] === 'SMTP')
        {
            $this->isSMTP();

            $this->Host        = $gPreferences['mail_smtp_host'];
            $this->SMTPAuth    = $gPreferences['mail_smtp_auth'];
            $this->Port        = $gPreferences['mail_smtp_port'];
            $this->SMTPSecure  = $gPreferences['mail_smtp_secure'];
            $this->AuthType    = $gPreferences['mail_smtp_authentication_type'];
            $this->Username    = $gPreferences['mail_smtp_user'];
            $this->Password    = $gPreferences['mail_smtp_password'];
            $this->Debugoutput = 'html';

            if ($gDebug)
            {
                $this->SMTPDebug = 2;
            }
        }
        else
        {
            $this->isMail();
        }

        // set language for error reporting
        $this->setLanguage($gL10n->getLanguageIsoCode());
        $this->CharSet = $gPreferences['mail_character_encoding'];
    }

    /**
     * method adds main recipients to mail
     * @param string $address
     * @param string $name
     * @return true|string
     */
    public function addRecipient($address, $name = '')
    {
        $address = admStrToLower($address);

        try
        {
            $this->addAddress($address, $name);
        }
        catch (phpmailerException $e)
        {
            return $e->errorMessage();
        }

        $this->emAddresses[] = $name;

        return true;
    }

    /**
     * method adds CC recipients to mail
     * @param string $address
     * @param string $name
     * @return true|string
     */
    public function addCopy($address, $name = '')
    {
        $address = admStrToLower($address);

        try
        {
            $this->addCC($address, $name);
        }
        catch (phpmailerException $e)
        {
            return $e->errorMessage();
        }

        $this->emAddresses[] = $name;

        return true;
    }

    /**
     * method adds BCC recipients to mail
     * Bcc Empfänger werden ersteinmal gesammelt, damit später Päckchen verschickt werden können
     * @param string $address
     * @param string $name
     * @return bool
     */
    public function addBlindCopy($address, $name = '')
    {
        $address = admStrToLower($address);
        // Blindcopy must be Ascii-US formated, so encode in MimeHeader
        $asciiName = stripslashes($name);

        if (strValidCharacters($address, 'email'))
        {
            $this->emBccArray[] = array('name' => $asciiName, 'address' => $address);
            $this->emAddresses[] = $name;
            return true;
        }
        return false;
    }

    /**
     * method adds sender to mail
     * @param string $address
     * @param string $name
     * @return true|string
     */
    public function setSender($address, $name = '')
    {
        global $gPreferences;

        $address = admStrToLower($address);

        // save sender if a copy of the mail should be send to him
        $this->emSender = array('address' => $address, 'name' => $name);

        // Falls so eingestellt soll die Mail von einer bestimmten Adresse aus versendet werden
        if (strlen($gPreferences['mail_sendmail_address']) > 0)
        {
            // hier wird die Absenderadresse gesetzt
            $fromName    = $gPreferences['mail_sendmail_name'];
            $fromAddress = $gPreferences['mail_sendmail_address'];

        }
        // Im Normalfall wird aber versucht von der Adresse des schreibenden aus zu schicken
        else
        {
            // Der Absendername ist in Doppeltueddel gesetzt, damit auch Kommas im Namen kein Problem darstellen
            $fromName    = $name;
            $fromAddress = $address;
        }

        try
        {
            // if someone wants to reply to this mail then this should go to the users email
            // and not to a domain email, so add a separate reply-to address
            $this->addReplyTo($address, $name);
            $this->setFrom($fromAddress, $fromName);
        }
        catch (phpmailerException $e)
        {
            return $e->errorMessage();
        }

        return true;
    }

    /**
     * Set the subject of the email
     * @param string $subject A text that should be the subject of the email
     * @return bool Returns @b false if the parameter has no text
     */
    public function setSubject($subject)
    {
        if ($subject !== '')
        {
            $this->Subject = stripslashes($subject);
            return true;
        }
        return false;
    }

    /**
     * Funktion um den Nachrichtentext an die Mail uebergeben
     * @param string $text
     */
    public function setText($text)
    {
        // Erst mal die Zeilenumbrueche innerhalb des Mailtextes umwandeln in einfache Umbrueche
        // statt \r und \r\n nur noch \n
        $replaceArray = array(
            "\r"   => "\n",
            "\r\n" => "\n"
        );
        $text = str_replace(array_keys($replaceArray), array_values($replaceArray), $text);

        $this->emText .= strip_tags($text);
        $this->emHtmlText .= $text;
    }

    /**
     * Funktion um das Flag zu setzen, dass eine Kopie verschickt werden soll...
     */
    public function setCopyToSenderFlag()
    {
        $this->emCopyToSender = true;
    }

    /**
     * Funktion um das Flag zu setzen, dass in der Kopie alle Empfaenger der Mail aufgelistet werden
     */
    public function setListRecipientsFlag()
    {
        $this->emListRecipients = true;
    }

    /**
     * Write a short text with sender information in text of email
     * @param string $senderName Firstname and lastname of email sender
     * @param string $receivers  List with firstname and lastname of all recipients of this mail
     */
    public function setSenderInText($senderName, $receivers)
    {
        global $gL10n, $gValidLogin, $gCurrentOrganization;

        $senderText = $gL10n->get('MAI_EMAIL_SEND_TO_RECEIVER', $senderName, $gCurrentOrganization->getValue('org_homepage'), $receivers);

        if (!$gValidLogin)
        {
            $senderText .= self::CRLF . $gL10n->get('MAI_SENDER_NOT_LOGGED_IN');
        }

        $senderText .= self::CRLF .
            '*****************************************************************************************************************************' .
            self::CRLF . self::CRLF;

        $this->emText .= $senderText;
        $this->emHtmlText .= nl2br($senderText);
    }

    /**
     * method change email header so that client will interpret mail as html mail
     */
    public function sendDataAsHtml()
    {
        $this->emSendAsHTML = true;
    }

    /**
     * Mailbenachrichtigung für Admin
     * @param string $subject
     * @param string $message
     * @param string $editorName
     * @param string $editorEmail
     * @throws AdmException 'SYS_EMAIL_NOT_SEND'
     * @return bool|string
     */
    public function adminNotification($subject, $message, $editorName = '', $editorEmail = '')
    {
        global $gPreferences, $gCurrentOrganization;

        if ($gPreferences['enable_email_notification'] == 0)
        {
            return false;
        }

        // Send Notification to Admin
        $this->addRecipient($gPreferences['email_administrator']);

        // Set Sender
        if ($editorEmail === '')
        {
            $this->setSender($gPreferences['email_administrator']);
        }
        else
        {
            $this->setSender($editorEmail, $editorName);
        }

        // Set Subject
        $this->setSubject($gCurrentOrganization->getValue('org_shortname').': '.$subject);

        // send html if preference is set
        if ($gPreferences['mail_html_registered_users'] == 1)
        {
            $this->sendDataAsHtml();
        }
        else
        {
            // html linebreaks should be converted in simple linefeed
            $message = str_replace('<br />', "\n", $message);
        }

        // Set Text
        $this->setText($message);

        // Verschicken
        $returnCode = $this->sendEmail();

        // if something went wrong then throw an exception with the error message
        if ($returnCode !== true)
        {
            throw new AdmException('SYS_EMAIL_NOT_SEND', $gPreferences['email_administrator'], $returnCode);
        }

        return true;
    }

    /**
     * Sends Emails with BCC addresses
     * @throws phpmailerException
     */
    private function sendBccMails()
    {
        global $gPreferences;

        // Bcc Array in Päckchen zerlegen
        $bccArrays = array_chunk($this->emBccArray, $gPreferences['mail_bcc_count']);

        foreach ($bccArrays as $bccArray)
        {
            // if number of bcc recipients = 1 then send the mail directly to the user and not as bcc
            if (count($bccArray) === 1)
            {
                // remove all current recipients from mail
                $this->clearAllRecipients();

                $this->addAddress($bccArray[0]['address'], $bccArray[0]['name']);
            }
            elseif ($gPreferences['mail_into_to'] == 1)
            {
                // remove all current recipients from mail
                $this->clearAllRecipients();

                // add all recipients as bcc to the mail
                foreach ($bccArray as $bcc)
                {
                    $this->addAddress($bcc['address'], $bcc['name']);
                }
            }
            else
            {
                // remove only all BCC because to-address could be explicit set if undisclosed recipients won't work
                $this->clearBCCs();

                // add all recipients as bcc to the mail
                foreach ($bccArray as $bcc)
                {
                    $this->addBCC($bcc['address'], $bcc['name']);
                }
            }

            // now send mail
            $this->send();
        }
    }

    /**
     * Sends a copy of the mail back to the sender
     * @throws phpmailerException
     */
    private function sendCopyMail()
    {
        global $gL10n;

        // Alle Empfänger entfernen
        $this->clearAllRecipients();

        $this->Subject = $gL10n->get('MAI_CARBON_COPY') . ': ' . $this->Subject;

        // Kopie Header ergänzen
        $copyHeader = $gL10n->get('MAI_COPY_OF_YOUR_EMAIL') . ':' . self::CRLF .
            '*****************************************************************************************************************************' .
            self::CRLF . self::CRLF;

        // Falls das listRecipientsFlag gesetzt ist werden in der Kopie
        // die einzelnen Empfaenger aufgelistet:
        if ($this->emListRecipients)
        {
            $copyHeader = $gL10n->get('MAI_MESSAGE_WENT_TO').':' . self::CRLF . self::CRLF .
                implode(self::CRLF, $this->emAddresses) . self::CRLF . self::CRLF . $copyHeader;
        }

        $this->emText = $copyHeader . $this->emText;
        $this->emHtmlText = nl2br($copyHeader) . $this->emHtmlText;

        // Text in Nachricht einfügen
        if ($this->emSendAsHTML)
        {
            $this->msgHTML($this->emHtmlText);
        }
        else
        {
            $this->Body = $this->emText;
        }

        // neuer Empänger
        $this->addAddress($this->emSender['address'], $this->emSender['name']);

        $this->send();
    }

    /**
     * Funktion um die Email endgueltig zu versenden...
     * @return true|string
     */
    public function sendEmail()
    {
        // Text in Nachricht einfügen
        if ($this->emSendAsHTML)
        {
            $this->msgHTML($this->emHtmlText);
        }
        else
        {
            $this->Body = $this->emText;
        }

        try
        {
            // Wenn es Bcc-Empfänger gibt
            if (count($this->emBccArray) > 0)
            {
                $this->sendBccMails();
            }
            // Einzelmailversand
            else
            {
                $this->send();
            }

            // Jetzt noch die Mail an den Kopieempfänger
            if ($this->emCopyToSender)
            {
                $this->sendCopyMail();
            }
        }
        catch (phpmailerException $e)
        {
            return $e->errorMessage();
        }

        // initialize recipient addresses so same email could be send to other recipients
        $this->emAddresses = array();
        $this->clearAddresses();

        return true;
    }

    /**
     * Returns the maximum size of an attachment
     * @param string $sizeUnit 'Byte' = byte, 'KiB' = kibibyte, 'MiB' = mebibyte, 'GiB' = gibibyte, 'TiB' = tebibyte
     * @return float The maximum attachment size in the given size-unit
     */
    public static function getMaxAttachmentSize($sizeUnit = self::SIZE_UNIT_BYTE)
    {
        global $gPreferences;

        $maxUploadSize = PhpIni::getUploadMaxSize();
        $currentAttachmentSize = $gPreferences['max_email_attachment_size'] * pow(1024, 2);

        $attachmentSize = min($maxUploadSize, $currentAttachmentSize);

        switch ($sizeUnit)
        {
            case self::SIZE_UNIT_TEBIBYTE:
                $attachmentSize /= 1024;
            case self::SIZE_UNIT_GIBIBYTE:
                $attachmentSize /= 1024;
            case self::SIZE_UNIT_MEBIBYTE:
                $attachmentSize /= 1024;
            case self::SIZE_UNIT_KIBIBYTE:
                $attachmentSize /= 1024;
            default:
        }

        return round($attachmentSize, 1);
    }

    /**
     * Mailbenachrichtigung für Admin
     * @deprecated 3.3.0:4.0.0 "adminNotfication()" is a typo. Use "adminNotification()" instead.
     * @param string $subject
     * @param string $message
     * @param string $editorName
     * @param string $editorEmail
     * @throws AdmException 'SYS_EMAIL_NOT_SEND'
     * @return bool|string
     */
    public function adminNotfication($subject, $message, $editorName = '', $editorEmail = '')
    {
        global $gLogger;

        $gLogger->warning('DEPRECATED: "$email->adminNotfication()" is deprecated, use "$email->adminNotification()" instead!');

        return $this->adminNotification($subject, $message, $editorName, $editorEmail);
    }

    /**
     * Returns the maximum size of an attachment
     * @deprecated 3.3.0:4.0.0 "getMaxAttachementSize()" is a typo. Use "getMaxAttachmentSize()" instead.
     * @param string $sizeUnit 'b' = byte, 'kib' = kilobyte, 'mib' = megabyte, 'gib' = gigabyte, 'tib' = terabyte
     * @return float The maximum attachment size in the given size-unit
     */
    public static function getMaxAttachementSize($sizeUnit = self::SIZE_UNIT_MEBIBYTE)
    {
        global $gLogger;

        $gLogger->warning('DEPRECATED: "Email::getMaxAttachementSize()" is deprecated, use "Email::getMaxAttachmentSize()" instead!');

        return self::getMaxAttachmentSize($sizeUnit);
    }
}
