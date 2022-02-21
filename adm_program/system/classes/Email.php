<?php
/**
 ***********************************************************************************************
 * Create and send a text or html email with attachments
 *
 * @copyright 2004-2022 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
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
 * setText($text)
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
    public const SIZE_UNIT_BYTE = 'Byte';
    public const SIZE_UNIT_KIBIBYTE = 'KiB';
    public const SIZE_UNIT_MEBIBYTE = 'MiB';
    public const SIZE_UNIT_GIBIBYTE = 'GiB';
    public const SIZE_UNIT_TEBIBYTE = 'TiB';

    /**
     * @var string Plain text of email
     */
    private $emText = '';
    /**
     * @var string HTML text of email
     */
    private $emHtmlText = '';
    /**
     * @var array<int,string> Array with all recipients names
     */
    private $emRecipientsNames = array();
    /**
     * @var array<string,string> Mail sender address and Name
     */
    private $emSender = array();
    /**
     * @var bool
     */
    private $emCopyToSender = false;
    /**
     * @var bool
     */
    private $emListRecipients = false;
    /**
     * @var bool
     */
    private $emSendAsHTML = false;
    /**
     * @var array<int,array<string,string>>
     */
    private $emBccArray = array();

    /**
     * Email constructor.
     */
    public function __construct()
    {
        // Übername Einstellungen
        global $gL10n, $gSettingsManager, $gDebug;

        parent::__construct(true); // enable exceptions in PHPMailer

        $this->Timeout = 30; // set timeout to 30 seconds

        // Versandmethode festlegen
        if ($gSettingsManager->getString('mail_send_method') === 'SMTP') {
            $this->isSMTP();

            $this->Host        = $gSettingsManager->getString('mail_smtp_host');
            $this->SMTPAuth    = $gSettingsManager->getBool('mail_smtp_auth');
            $this->Port        = $gSettingsManager->getInt('mail_smtp_port');
            $this->SMTPSecure  = $gSettingsManager->getString('mail_smtp_secure');
            // only set auth type if there is a value, otherwise phpmailer will check all methods automatically
            if (strlen($gSettingsManager->getString('mail_smtp_authentication_type')) > 0) {
                $this->AuthType    = $gSettingsManager->getString('mail_smtp_authentication_type');
            }
            $this->Username    = $gSettingsManager->getString('mail_smtp_user');
            $this->Password    = $gSettingsManager->getString('mail_smtp_password');

            if ($gDebug) {
                $this->setDebugMode();
            }
        } else {
            $this->isMail();
        }

        // set language for error reporting
        $this->setLanguage($gL10n->getLanguageIsoCode());
        $this->CharSet = $gSettingsManager->getString('mail_character_encoding');
    }

    /**
     * method adds main recipients to mail
     * @param string $address
     * @param string $name
     * @return true|string
     */
    public function addRecipient($address, $name = '')
    {
        try {
            $this->addAddress($address, $name);
        } catch (Exception $e) {
            return $e->errorMessage();
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $this->emRecipientsNames[] = $name;

        return true;
    }

    /**
     * Add the name and email address of the given user id to the email as a normal recipient. If the system setting
     * **mail_send_to_all_addresses** is set than all email addresses of the given user id will be added.
     * @param int $userId Id of an user who should be the recipient of the email.
     * @throws AdmException in case of errors. exception->text contains a string with the reason why no recipient could be added.
     *                     Possible reasons: SYS_NO_VALID_RECIPIENTS
     * @return Returns true if recipients could be added to the email.
     */
    public function addRecipientsByUserId($userId)
    {
        global $gSettingsManager, $gProfileFields, $gL10n, $gDb;

        $sqlEmailField = '';
        $numberRecipientsAdded = 0;

        // set condition if email should only send to the email address of the user field
        // with the internal name 'EMAIL'
        if (!$gSettingsManager->getBool('mail_send_to_all_addresses')) {
            $sqlEmailField = ' AND field.usf_name_intern = \'EMAIL\' ';
        }

        $sql = 'SELECT first_name.usd_value AS firstname, last_name.usd_value AS lastname, email.usd_value AS email
                  FROM ' . TBL_USER_DATA . ' AS email
            INNER JOIN ' . TBL_USER_FIELDS . ' AS field
                    ON field.usf_id = email.usd_usf_id
                   AND field.usf_type = \'EMAIL\'
                       ' . $sqlEmailField . '
            INNER JOIN ' . TBL_USER_DATA . ' AS last_name
                    ON last_name.usd_usr_id = email.usd_usr_id
                   AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
            INNER JOIN ' . TBL_USER_DATA . ' AS first_name
                    ON first_name.usd_usr_id = email.usd_usr_id
                   AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                 WHERE email.usd_usr_id = ? -- userId
                   AND LENGTH(email.usd_value) > 0 ';

        $statement = $gDb->queryPrepared($sql, array($gProfileFields->getProperty('LAST_NAME', 'usf_id'), $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), $userId));

        if ($statement->rowCount() > 0) {
            // all email addresses will be attached as BCC
            while ($row = $statement->fetch()) {
                if (StringUtils::strValidCharacters($row['email'], 'email')) {
                    $this->addRecipient($row['email'], $row['firstname'] . ' ' . $row['lastname']);
                    ++$numberRecipientsAdded;
                }
            }
        } else {
            throw new AdmException($gL10n->get('SYS_NO_VALID_RECIPIENTS'));
        }

        return $numberRecipientsAdded > 0;
    }

    /**
     * method adds CC recipients to mail
     * @param string $address
     * @param string $name
     * @return true|string
     */
    public function addCopy($address, $name = '')
    {
        try {
            $this->addCC($address, $name);
        } catch (Exception $e) {
            return $e->errorMessage();
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $this->emRecipientsNames[] = $name;

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
        // Blindcopy must be Ascii-US formated, so encode in MimeHeader
        $asciiName = stripslashes($name);

        if (StringUtils::strValidCharacters($address, 'email')) {
            $this->emBccArray[] = array('name' => $asciiName, 'address' => $address);
            $this->emRecipientsNames[] = $name;
            return true;
        }
        return false;
    }

    /**
     * Creates an email for the administrator. Therefore we use the email that is stored in
     * the global preference email_administrator. We don't use the role administrator!
     * @param string $subject
     * @param string $message
     * @param string $editorName
     * @param string $editorEmail
     * @throws AdmException 'SYS_EMAIL_NOT_SEND'
     * @return bool|string
     */
    public function adminNotification($subject, $message, $editorName = '', $editorEmail = '', $enable_flag = 'enable_email_notification')
    {
        global $gSettingsManager, $gCurrentOrganization;

        if (!$gSettingsManager->getBool($enable_flag)) {
            return false;
        }

        // Send Notification to Admin
        $this->addRecipient($gSettingsManager->getString('email_administrator'));

        // Set Sender
        if ($editorEmail === '') {
            $this->setSender($gSettingsManager->getString('email_administrator'));
        } else {
            $this->setSender($editorEmail, $editorName);
        }

        // Set Subject
        $this->setSubject($gCurrentOrganization->getValue('org_shortname').': '.$subject);

        // send html if preference is set
        if ($gSettingsManager->getBool('mail_html_registered_users')) {
            $this->sendDataAsHtml();
        } else {
            // html linebreaks should be converted in simple linefeed
            $message = str_replace('<br />', "\n", $message);
        }

        // Set Text
        $this->setText($message);

        // Verschicken
        $returnCode = $this->sendEmail();

        // if something went wrong then throw an exception with the error message
        if ($returnCode !== true) {
            throw new AdmException('SYS_EMAIL_NOT_SEND', array($gSettingsManager->getString('email_administrator'), $returnCode));
        }

        return true;
    }

    /**
     * Returns the maximum size of an attachment
     * @param string $sizeUnit  'Byte' = byte, 'KiB' = kibibyte, 'MiB' = mebibyte, 'GiB' = gibibyte, 'TiB' = tebibyte
     * @param int    $precision The number of decimal digits to round to
     * @return float The maximum attachment size in the given size-unit
     */
    public static function getMaxAttachmentSize($sizeUnit = self::SIZE_UNIT_BYTE, $precision = 1)
    {
        global $gSettingsManager;

        $maxUploadSize = PhpIniUtils::getUploadMaxSize();
        $currentAttachmentSize = $gSettingsManager->getInt('max_email_attachment_size') * 1024** 2;

        $attachmentSize = min($maxUploadSize, $currentAttachmentSize);

        switch ($sizeUnit) {
            case self::SIZE_UNIT_TEBIBYTE: // fallthrough
                $attachmentSize /= 1024;
                // no break
            case self::SIZE_UNIT_GIBIBYTE: // fallthrough
                $attachmentSize /= 1024;
                // no break
            case self::SIZE_UNIT_MEBIBYTE: // fallthrough
                $attachmentSize /= 1024;
                // no break
            case self::SIZE_UNIT_KIBIBYTE: // fallthrough
                $attachmentSize /= 1024;
                // no break
            default:
        }

        return round($attachmentSize, $precision);
    }

    /**
     * Set a debug modus for sending emails. This will only be useful if you use smtp for sending
     * emails. If you still use PHP mail() there fill be no debug output. With the parameter
     * **$outputGlobalVar** you have the option to put the output in a global variable
     * **$GLOBALS['phpmailer_output_debug']**. Otherwise the output will go to the Admidio log files.
     * @param bool $outputGlobalVar Put the output in a global variable **$GLOBALS['phpmailer_output_debug']**.
     */
    public function setDebugMode($outputGlobalVar = false)
    {
        global $gLogger;

        $this->SMTPDebug = SMTP::DEBUG_SERVER;

        if ($outputGlobalVar) {
            $this->Debugoutput = function ($str, $level) {
                $GLOBALS['phpmailer_output_debug'] .= $level . ': ' . $str . '<br />';
            };
        } else {
            $this->Debugoutput = $gLogger;
        }
    }

    /**
     * Funktion um das Flag zu setzen, dass eine Kopie verschickt werden soll...
     */
    public function setCopyToSenderFlag()
    {
        $this->emCopyToSender = true;
    }

    /**
     * The mail will be send as html email
     */
    public function setHtmlMail()
    {
        $this->emSendAsHTML = true;
    }

    /**
     * Funktion um das Flag zu setzen, dass in der Kopie alle Empfaenger der Mail aufgelistet werden
     */
    public function setListRecipientsFlag()
    {
        $this->emListRecipients = true;
    }

    /**
     * method adds sender to mail
     * @param string $address
     * @param string $name
     * @return true|string
     */
    public function setSender($address, $name = '')
    {
        global $gSettingsManager;

        // save sender if a copy of the mail should be send to him
        $this->emSender = array('address' => $address, 'name' => $name);

        // Falls so eingestellt soll die Mail von einer bestimmten Adresse aus versendet werden
        if (strlen($gSettingsManager->getString('mail_sendmail_address')) > 0) {
            // hier wird die Absenderadresse gesetzt
            $fromName    = $gSettingsManager->getString('mail_sendmail_name');
            $fromAddress = $gSettingsManager->getString('mail_sendmail_address');
        }
        // Im Normalfall wird aber versucht von der Adresse des schreibenden aus zu schicken
        else {
            // Der Absendername ist in Doppeltueddel gesetzt, damit auch Kommas im Namen kein Problem darstellen
            $fromName    = $name;
            $fromAddress = $address;
        }

        try {
            // if someone wants to reply to this mail then this should go to the users email
            // and not to a domain email, so add a separate reply-to address
            $this->addReplyTo($address, $name);
            $this->setFrom($fromAddress, $fromName);
        } catch (Exception $e) {
            return $e->errorMessage();
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return true;
    }

    /**
     * Set the subject of the email
     * @param string $subject A text that should be the subject of the email
     * @return bool Returns **false** if the parameter has no text
     */
    public function setSubject($subject)
    {
        if ($subject !== '') {
            $this->Subject = stripslashes($subject);
            return true;
        }
        return false;
    }

    /**
     * Add the template text to the email message and replace the plaeholders of the template.
     * @param string $text        Email text that should be send
     * @param string $senderName  Firstname and lastname of email sender
     * @param string $senderEmail The email address of the sender
     * @param string $recipients  List with firstname and lastname of all recipients of this mail
     */
    public function setTemplateText($text, $senderName, $senderEmail, $senderUuid, $recipients)
    {
        global $gValidLogin, $gCurrentOrganization, $gSettingsManager, $gL10n;


        // load the template and set the new email body with template
        try {
            $emailTemplateText = FileSystemUtils::readFile(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates/' . $gSettingsManager->getString('mail_template'));
        } catch (\RuntimeException $exception) {
            $emailTemplateText = '#message#';
        }

        if (!$gValidLogin) {
            $senderName .= ' (' . $gL10n->get('SYS_SENDER_NOT_LOGGED_IN') . ') ';
        }

        // replace all line feeds within the mailtext into simple breaks because only those are valid within mails
        $text = str_replace("\r\n", "\n", $text);

        // replace parameters in email template
        $replaces = array(
            '#sender#'       => $senderName,
            '#sender_name#'  => $senderName,
            '#sender_email#' => $senderEmail,
            '#sender_uuid#'  => $senderUuid,
            '#message#'      => $text,
            '#receiver#'     => $recipients,
            '#recipients#'   => $recipients,
            '#organization_name#'      => $gCurrentOrganization->getValue('org_longname'),
            '#organization_shortname#' => $gCurrentOrganization->getValue('org_shortname'),
            '#organization_website#'   => $gCurrentOrganization->getValue('org_homepage')
        );
        $emailHtmlText = StringUtils::strMultiReplace($emailTemplateText, $replaces);

        // now remove html und css from template
        $emailText = strip_tags($emailHtmlText, '<style>');
        if (strpos($emailText, '</style>') > 0) {
            $substring = substr($emailText, strpos($emailText, '<style'), strpos($emailText, '</style>') + 6);
            $emailText = str_replace($substring, '', $emailText);
        }
        // remove linefeeds from html \r\n but don't remove the linefeed from the message \n
        $emailText = str_replace(array("\t", "\r\n"), '', $emailText);
        $emailText = trim($emailText);

        $this->emText = $emailText;
        $this->emHtmlText = $emailHtmlText;
    }

    /**
     * Funktion um den Nachrichtentext an die Mail uebergeben
     * @param string $text
     */
    public function setText($text)
    {
        // replace all line feeds within the mailtext into simple breaks because only those are valid within mails
        $text = str_replace("\r\n", "\n", $text);

        $this->emText .= strip_tags($text);
        $this->emHtmlText .= $text;
    }

    /**
     * Sends Emails with BCC addresses
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    private function sendBccMails()
    {
        global $gSettingsManager;

        // Bcc Array in Päckchen zerlegen
        $bccArrays = array_chunk($this->emBccArray, $gSettingsManager->getInt('mail_bcc_count'));

        foreach ($bccArrays as $bccArray) {
            // if number of bcc recipients = 1 then send the mail directly to the user and not as bcc
            if (count($bccArray) === 1) {
                // remove all current recipients from mail
                $this->clearAllRecipients();

                $this->addAddress($bccArray[0]['address'], $bccArray[0]['name']);
            } elseif ($gSettingsManager->getBool('mail_into_to')) {
                // remove all current recipients from mail
                $this->clearAllRecipients();

                // add all recipients as bcc to the mail
                foreach ($bccArray as $bcc) {
                    $this->addAddress($bcc['address'], $bcc['name']);
                }
            } else {
                // remove only all BCC because to-address could be explicit set if undisclosed recipients won't work
                $this->clearBCCs();

                // add all recipients as bcc to the mail
                foreach ($bccArray as $bcc) {
                    $this->addBCC($bcc['address'], $bcc['name']);
                }
            }

            // now send mail
            $this->send();
        }
    }

    /**
     * Sends a copy of the mail back to the sender. If the flag emListRecipients it set than all
     * recipients will be listed in the mail.
     * @throws \PHPMailer\PHPMailer\Exception
     */
    private function sendCopyMail()
    {
        global $gL10n;

        // remove all recipients
        $this->clearAllRecipients();

        $this->Subject = $gL10n->get('SYS_CARBON_COPY') . ': ' . $this->Subject;

        // add a separate header with info of the copy mail
        if ($this->emSendAsHTML) {
            $copyHeader = $gL10n->get('SYS_COPY_OF_YOUR_EMAIL') . ':' . static::$LE . '<hr style="border: 1px solid;" />' .
                static::$LE . static::$LE;
        } else {
            $copyHeader = $gL10n->get('SYS_COPY_OF_YOUR_EMAIL') . ':' . static::$LE .
                '*****************************************************************************************************************************' .
                static::$LE . static::$LE;
        }

        // if the flag emListRecipients is set than list all recipients of the mail
        if ($this->emListRecipients) {
            $copyHeader = $gL10n->get('SYS_MESSAGE_WENT_TO').':' . static::$LE . static::$LE .
                implode(static::$LE, $this->emRecipientsNames) . static::$LE . static::$LE . $copyHeader;
        }

        $this->emText = $copyHeader . $this->emText;
        $this->emHtmlText = nl2br($copyHeader) . $this->emHtmlText;

        // add the text of the message
        if ($this->emSendAsHTML) {
            $this->msgHTML($this->emHtmlText);
        } else {
            $this->Body = $this->emText;
        }

        // now set the sender of the original mail as the recipients of the copy mail
        $this->addAddress($this->emSender['address'], $this->emSender['name']);

        $this->send();
    }

    /**
     * method change email header so that client will interpret mail as html mail
     * @deprecated 4.0.0:4.1.0 "sendDataAsHtml()" is deprecated, use "setHtmlMail()" instead.
     */
    public function sendDataAsHtml()
    {
        $this->setHtmlMail();
    }

    /**
     * Funktion um die Email endgueltig zu versenden...
     * @return true|string
     */
    public function sendEmail()
    {
        // Text in Nachricht einfügen
        if ($this->emSendAsHTML) {
            $this->msgHTML($this->emHtmlText);
        } else {
            $this->Body = $this->emText;
        }

        try {
            // Wenn es Bcc-Empfänger gibt
            if (count($this->emBccArray) > 0) {
                $this->sendBccMails();
            }
            // Einzelmailversand
            else {
                $this->send();
            }

            // Jetzt noch die Mail an den Kopieempfänger
            if ($this->emCopyToSender) {
                $this->sendCopyMail();
            }
        } catch (Exception $e) {
            return $e->errorMessage();
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        // initialize recipient addresses so same email could be send to other recipients
        $this->emRecipientsNames = array();
        $this->clearAddresses();

        return true;
    }
}
