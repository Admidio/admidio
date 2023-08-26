<?php
/**
 ***********************************************************************************************
 * Create and send a text or html email with attachments
 *
 * @copyright 2004-2023 The Admidio Team
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

    public const EMAIL_ALL_MEMBERS         = 0;
    public const EMAIL_ONLY_ACTIVE_MEMBERS = 1;
    public const EMAIL_ONLY_FORMER_MEMBERS = 2;

    public const SENDINGMODE_BULK = 1;
    public const SENDINGMODE_SINGLE = 1;

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
    * @var int The sending mode from the settings: 0 = BULK, 1 = SINGLE
    */
    private $sendingMode = Email::SENDINGMODE_BULK;
    /**
     * @var array<int,array<string,string>>
     */
    private $emRecipientsArray = array();

    /**
     * Email constructor.
     */
    public function __construct()
    {
        global $gL10n, $gSettingsManager, $gDebug;

        parent::__construct(true); // enable exceptions in PHPMailer

        $this->Timeout = 30; // set timeout to 30 seconds

        // set sending method
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

        $this->sendingMode = $gSettingsManager->getInt('mail_sending_mode');
        // set language for error reporting
        $this->setLanguage($gL10n->getLanguageIsoCode());
        $this->CharSet = $gSettingsManager->getString('mail_character_encoding');
    }

    /**
     * Adds the name and address to the recipients list. The valid email address will only be added if it's not already
     * in the recipients list. The decision if the recipient will be sent as TO or BCC will be done later
     * in the email send process.
     * @param string $address   A valid email address to which the email should be sent.
     * @param string $firstName The first name of the recipient that will be shown in the email header.
     * @param string $lastName  The last name of the recipient that will be shown in the email header.
     * @param array $additionalFields Additional fields to map in a Key Value like Array. Not used at all yet.
     * @return bool Returns **true** if the address was added to the recipients list.
     */
    public function addRecipient(string $address, string $firstName = '', string $lastName = '', array $additionalFields = array()): bool
    {
        // Recipients must be Ascii-US formatted, so encode in MimeHeader
        $asciiName = stripslashes($firstName  . ' ' . $lastName);

        // check if valid email address and if email not in the recipients array
        if (StringUtils::strValidCharacters($address, 'email')
        && !in_array($address, array_column($this->emRecipientsArray, 'address'))) {
            $recipient = array('name' => $asciiName, 'address' => $address, 'firstname' => $firstName, 'surname' => $lastName);
            $recipient = array_merge($recipient , $additionalFields);
            $this->emRecipientsArray[] = $recipient;
            $this->emRecipientsNames[] = $firstName  . ' ' . $lastName;

            return true;
        }
        return false;
    }

    /**
     * Add the name and email address of all users of the role to the email as a normal recipient. If the system setting
     * **mail_send_to_all_addresses** is set than all email addresses of the given users will be added.
     * @param string $roleUuid  UUID of a role whose users should be the recipients of the email.
     * @param int $memberStatus Status of the members who should get the email. Possible values are
     *                          EMAIL_ALL_MEMBERS, EMAIL_ONLY_ACTIVE_MEMBERS, EMAIL_ONLY_FORMER_MEMBERS
     *                          The default value will be EMAIL_ONLY_ACTIVE_MEMBERS.
     * @return int Returns the number of added email addresses.
     */
    public function addRecipientsByRole(string $roleUuid, int $memberStatus = self::EMAIL_ONLY_ACTIVE_MEMBERS): int
    {
        global $gSettingsManager, $gProfileFields, $gL10n, $gDb, $gCurrentOrgId, $gCurrentUserId, $gValidLogin;

        $sqlEmailField = '';
        $numberRecipientsAdded = 0;

        // set condition if email should only send to the email address of the user field
        // with the internal name 'EMAIL'
        if (!$gSettingsManager->getBool('mail_send_to_all_addresses')) {
            $sqlEmailField = ' AND field.usf_name_intern = \'EMAIL\' ';
        }

        $queryParams = array(
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $roleUuid,
            $gCurrentOrgId
        );

        if ($memberStatus === self::EMAIL_ONLY_FORMER_MEMBERS && $gSettingsManager->getBool('mail_show_former')) {
            // only former members
            $sqlConditions = '
                AND mem_end < ? -- DATE_NOW
                AND NOT EXISTS (
                    SELECT 1
                         FROM '.TBL_MEMBERS.' AS act
                        WHERE act.mem_rol_id = mem.mem_rol_id
                          AND act.mem_usr_id = mem.mem_usr_id
                          AND \''.DATE_NOW.'\' BETWEEN act.mem_begin AND act.mem_end
                    )';
        } elseif ($memberStatus === self::EMAIL_ALL_MEMBERS && $gSettingsManager->getBool('mail_show_former')) {
            // former members and active members
            $sqlConditions = ' AND mem_begin < ? -- DATE_NOW ';
        } else {
            // only active members
            $sqlConditions = ' AND mem_begin <= ? -- DATE_NOW
                                       AND mem_end    > ? -- DATE_NOW ';
            $queryParams[] = DATE_NOW;
        }
        $queryParams[] = DATE_NOW;

        $sql = 'SELECT first_name.usd_value AS firstname, last_name.usd_value AS lastname, email.usd_value AS email
                          FROM ' . TBL_MEMBERS . ' mem
                    INNER JOIN ' . TBL_ROLES . '
                            ON rol_id = mem_rol_id
                    INNER JOIN ' . TBL_CATEGORIES . '
                            ON cat_id = rol_cat_id
                    INNER JOIN ' . TBL_USERS . '
                            ON usr_id = mem_usr_id
                    INNER JOIN ' . TBL_USER_DATA . ' AS email
                            ON email.usd_usr_id = usr_id
                           AND LENGTH(email.usd_value) > 0
                    INNER JOIN ' . TBL_USER_FIELDS . ' AS field
                            ON field.usf_id = email.usd_usf_id
                           AND field.usf_type = \'EMAIL\'
                               ' . $sqlEmailField . '
                     LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                            ON last_name.usd_usr_id = usr_id
                           AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                     LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                            ON first_name.usd_usr_id = usr_id
                           AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                         WHERE rol_uuid      = ? -- $roleUuid
                           AND (  cat_org_id = ? -- $gCurrentOrgId
                               OR cat_org_id IS NULL )
                           AND usr_valid = true
                               ' . $sqlConditions;

        // if current user is logged in the user id must be excluded because we don't want
        // to send the email to himself
        if ($gValidLogin) {
            $sql .= '
                        AND usr_id <> ? -- $gCurrentUserId';
            $queryParams[] = $gCurrentUserId;
        }
        $statement = $gDb->queryPrepared($sql, $queryParams);

        if ($statement->rowCount() > 0) {
            // all email addresses will be attached as BCC
            while ($row = $statement->fetch()) {
                if (StringUtils::strValidCharacters($row['email'], 'email')) {
                    $this->addRecipient($row['email'], (string) $row['firstname'], (string) $row['lastname']);
                    ++$numberRecipientsAdded;
                }
            }
        }


        return $numberRecipientsAdded > 0;
    }

    /**
     * Add the name and email address of the given user id to the email as a normal recipient. If the system setting
     * **mail_send_to_all_addresses** is set than all email addresses of the given user id will be added.
     * @param int $userId ID of a user who should be the recipient of the email.
     * @return int Returns the number of added email addresses.
     */
    public function addRecipientsByUserId(int $userId): int
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
                    $this->addRecipient($row['email'], $row['firstname'], $row['lastname']);
                    ++$numberRecipientsAdded;
                }
            }
        }

        return $numberRecipientsAdded > 0;
    }

    /**
     * Adds the name and address to the carbon copy recipients list. The valid email address will only be
     * added if it's not already in the recipients list.
     * @param string $address   A valid email address to which the email should be sent.
     * @param string $firstName The first name of the recipient that will be shown in the email header.
     * @param string $lastName  The last name of the recipient that will be shown in the email header.
     * @return true|string Returns **true** if the address was added to the recipients list otherwise the error message
     */
    public function addCopy(string $address, string $firstName = '', string $lastName = '')
    {
        try {
            $this->addCC($address, $firstName .' '. $lastName);
        } catch (Exception $e) {
            return $e->errorMessage();
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $this->emRecipientsNames[] = $lastName;

        return true;
    }

    /**
     * method adds BCC recipients to mail
     * Bcc Empfänger werden ersteinmal gesammelt, damit später Päckchen verschickt werden können
     * @param string $address
     * @param string $lastName
     * @return bool
     * @deprecated 4.2.0:4.3.0 "addBlindCopy()" is deprecated, use "addRecipient()" instead.
     */
    public function addBlindCopy($address, $firstName = '', $lastName = '')
    {
        return $this->addRecipient($address, $firstName, $lastName);
    }

    /**
     * Send a notification email to all members of the notification role. This role is configured within the
     * global preference **system_notifications_role**.
     * @param string $subject     The subject of the email.
     * @param string $message     The body of the email.
     * @param string $editorName  The name of the sender of the email.
     * @param string $editorEmail The email address of the sender of the email.
     * @throws AdmException 'SYS_EMAIL_NOT_SEND'
     * @return bool
     * @deprecated 4.2.0:4.3.0 "adminNotification()" is deprecated, use "sendNotification()" instead.
     */
    public function adminNotification($subject, $message, $editorName = '', $editorEmail = '', $enable_flag = 'system_notifications_new_entries')
    {
        return $this->sendNotification($subject, $message);
    }

    /**
     * Get the count of all recipients that are currently set for this email.
     * @return int Returns the number of recipients currently set for this email.
     */
    public function countRecipients(): int
    {
        return count($this->emRecipientsArray);
    }

    /**
     * Returns the maximum size of an attachment
     * @param string $sizeUnit  'Byte' = byte, 'KiB' = kibibyte, 'MiB' = mebibyte, 'GiB' = gibibyte, 'TiB' = tebibyte
     * @param int $precision The number of decimal digits to round to
     * @return float The maximum attachment size in the given size-unit
     */
    public static function getMaxAttachmentSize(string $sizeUnit = self::SIZE_UNIT_BYTE, int $precision = 1): float
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
    public function setDebugMode(bool $outputGlobalVar = false)
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
     * Set a flag that a copy of the email will be sent to the sender
     */
    public function setCopyToSenderFlag()
    {
        $this->emCopyToSender = true;
    }

    /**
     * The mail will be sent as html email
     */
    public function setHtmlMail()
    {
        $this->emSendAsHTML = true;
    }

    /**
     * Set a flag that all recipients will be separately listed in the copy of the mail to the sender.
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
    public function setSender(string $address, string $name = '')
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
    public function setSubject(string $subject): bool
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
     * @param string $senderUuid  The unique ID of the sender.
     * @param string $recipients  List with firstname and lastname of all recipients of this mail
     */
    public function setTemplateText(string $text, string $senderName, string $senderEmail, string $senderUuid, string $recipients)
    {
        global $gValidLogin, $gCurrentOrganization, $gSettingsManager, $gL10n;


        // load the template and set the new email body with template
        try {
            $emailTemplateText = FileSystemUtils::readFile(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates/' . $gSettingsManager->getString('mail_template'));
        } catch (RuntimeException $exception) {
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
     * Add the user specific template text to the email message and replace the plaeholders of the template.
     * @param string $text      Email text that should be send
     * @param string $firstName Recipients firstname
     * @param string $surname Recipients surname
     * @param string $email  Recipients email address
     * @param string $name  Recipients firstname and surname
     */
    private function setUserSpecificTemplateText(string $text, string $firstName, string $surname, string $email, string $name): string
    {
        // replace all line feeds within the mailtext into simple breaks because only those are valid within mails
        $text = str_replace("\r\n", "\n", $text);

        // replace parameters in email template
        $replaces = array(
            '#recipient_firstname#'  => $firstName,
            '#recipient_lastname#'   => $surname,
            '#recipient_email#'      => $email,
            '#recipient_name#'       => $name
        );
        return StringUtils::strMultiReplace($text, $replaces);
    }

    /**
     * Funktion um den Nachrichtentext an die Mail uebergeben
     * @param string $text
     */
    public function setText(string $text)
    {
        // replace all line feeds within the mailtext into simple breaks because only those are valid within mails
        $text = str_replace("\r\n", "\n", $text);

        $this->emText .= strip_tags($text);
        $this->emHtmlText .= $text;
    }

    /**
     * Sends a copy of the mail back to the sender. If the flag emListRecipients it set than all
     * recipients will be listed in the mail.
     * @throws \PHPMailer\PHPMailer\Exception|Exception
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
     * Method will send the email to all recipients. Therefore, the method will evaluate how to send the email.
     * If it's necessary all recipients will be added to BCC and also smaller packages of recipients will be
     * created. So maybe several emails will be send. Also a copy to the sender will be send if the preferences are set.
     * If the Sending Mode is set to "SINGLE" every e-mail will be send on its own, so there will be send out a lot e-mails.
     * @return true|string
     */
    public function sendEmail()
    {
        global $gSettingsManager, $gLogger, $gDebug, $gValidLogin, $gCurrentUser, $gL10n;
        try {
            // If sending mode is "SINGLE" every E-mail is send on its own, so we do not need to check anything else here
            if($this->sendingMode == Email::SENDINGMODE_SINGLE) {
                foreach ($this->emRecipientsArray as $recipient) {
                    $this->clearAllRecipients();
                    $this->addAddress($recipient['address'], $recipient['name']);
                    if ($gDebug) {
                        $gLogger->notice('Email send as TO to ' . $recipient['name'] . ' (' . $recipient['address'] . ')');
                    }

                    // add body to the email
                    if($gValidLogin) {
                        if ($this->emSendAsHTML) {
                            $html = $this->setUserSpecificTemplateText($this->emHtmlText, $recipient['firstname'], $recipient['surname'], $recipient['address'], $recipient['name']);
                            $this->msgHTML($html);
                        } else {
                            $txt = $this->setUserSpecificTemplateText($this->emText, $recipient['firstname'], $recipient['surname'], $recipient['address'], $recipient['name']);
                            $this->Body = $txt;
                        }
                    } else {
                        if ($this->emSendAsHTML) {
                            $this->msgHTML($this->emHtmlText);
                        } else {
                            $this->Body = $this->emText;
                        }
                    }

                    // now send mail
                    $this->send();
                }
            } else {
                // add body to the email
                if ($this->emSendAsHTML) {
                    $this->msgHTML($this->emHtmlText);
                } else {
                    $this->Body = $this->emText;
                }

                // if there is a limit of email recipients than split the recipients into smaller packages
                $recipientsArrays = array_chunk($this->emRecipientsArray, $gSettingsManager->getInt('mail_number_recipients'));

                foreach ($recipientsArrays as $recipientsArray) {
                    // if number of bcc recipients = 1 then send the mail directly to the user and not as bcc
                    if ($this->countRecipients() === 1) {
                        // remove all current recipients from mail
                        $this->clearAllRecipients();

                        $this->addAddress($recipientsArray[0]['address'], $recipientsArray[0]['name']);
                        if ($gDebug) {
                            $gLogger->notice('Email send as TO to ' . $recipientsArray[0]['name'] . ' (' . $recipientsArray[0]['address'] . ')');
                        }
                    } elseif ($gSettingsManager->getBool('mail_into_to')) {
                        // remove all current recipients from mail
                        $this->clearAllRecipients();

                        // add all recipients as bcc to the mail
                        foreach ($recipientsArray as $recipientTO) {
                            $this->addAddress($recipientTO['address'], $recipientTO['name']);
                            if ($gDebug) {
                                $gLogger->notice('Email send as TO to ' . $recipientTO['name'] . ' (' . $recipientTO['address'] . ')');
                            }
                        }
                    } else {
                        // remove only all BCC because to-address could be explicit set if undisclosed recipients won't work
                        $this->clearBCCs();

                        // normally we need no To-address and set "undisclosed recipients", but if
                        // that won't work than the following address will be set
                        if ($gValidLogin && (int) $gSettingsManager->get('mail_recipients_with_roles') === 1) {
                            // fill recipient with sender address to prevent problems with provider
                            $this->addAddress($gCurrentUser->getValue('EMAIL'), $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'));
                        } elseif ((int) $gSettingsManager->get('mail_recipients_with_roles') === 2
                        || (!$gValidLogin && (int) $gSettingsManager->get('mail_recipients_with_roles') === 1)) {
                            // fill recipient with administrators address to prevent problems with provider
                            $this->addAddress($gSettingsManager->getString('email_administrator'), $gL10n->get('SYS_ADMINISTRATOR'));
                        }

                        // add all recipients as bcc to the mail
                        foreach ($recipientsArray as $recipientBCC) {
                            $this->addBCC($recipientBCC['address'], $recipientBCC['name']);
                            if ($gDebug) {
                                $gLogger->notice('Email send as BCC to ' . $recipientBCC['name'] . ' (' . $recipientBCC['address'] . ')');
                            }
                        }
                    }

                    // now send mail
                    $this->send();
                }
            }
            // now send the email as a copy to the sender
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

    /**
     * Send a notification email to all members of the notification role. This role is configured within the
     * global preference **system_notifications_role**.
     * @param string $subject     The subject of the email.
     * @param string $message     The body of the email.
     * @return bool Returns **true** if the notification was sent
     *@throws AdmException 'SYS_EMAIL_NOT_SEND'
     */
    public function sendNotification(string $subject, string $message): bool
    {
        global $gSettingsManager, $gCurrentOrganization, $gCurrentUser;

        // Send notification to configured role
        $this->addRecipientsByRole($gSettingsManager->getString('system_notifications_role'));

        // Set Sender
        if ($gCurrentUser->getValue('EMAIL') === '') {
            $this->setSender($gSettingsManager->getString('email_administrator'));
        } else {
            $this->setSender($gCurrentUser->getValue('EMAIL'), $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'));
        }

        $this->setSubject($gCurrentOrganization->getValue('org_shortname').': '.$subject);

        // send html if preference is set
        if ($gSettingsManager->getBool('mail_html_registered_users')) {
            $this->setHtmlMail();
        } else {
            // html linebreaks should be converted in simple linefeed
            $message = str_replace('<br />', "\n", $message);
        }

        $this->setText($message);
        $returnCode = $this->sendEmail();

        // if something went wrong then throw an exception with the error message
        if ($returnCode !== true) {
            throw new AdmException('SYS_EMAIL_NOT_SEND', array($gSettingsManager->getString('email_administrator'), $returnCode));
        }

        return true;
    }
}

