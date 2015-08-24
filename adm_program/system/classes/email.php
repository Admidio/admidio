<?php
/******************************************************************************
 * Create and send a text or html email with attachments
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Mit dieser Klasse kann ein Email-Objekt erstellt
 * und anschliessend verschickt werden.
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors:
 * Email()
 *
 * Nun wird der Absender gesetzt:
 * setSender($address, $name='')
 * Parameters: $address - Die Emailadresse
 *             $name    - Der Name des Absenders (optional)
 *
 * Nun koennen in beliebiger Reihenfolge und Anzahl Adressaten (To,Cc,Bcc)
 * der Mail hinzugefuegt werden:
 * (optional und mehrfach aufrufbar, es muss jedoch mindestens
 * ein Empfaenger mittels einer der drei Funktionen gesetzt werden)
 *
 * addRecipient($address, $name='')
 * addCopy($address, $name='')
 * addBlindCopy($address, $name='')
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
 * size_unit : 'b' = byte; 'kb' = kilobyte; 'mb' = megabyte
 * getMaxAttachementSize($size_unit = 'kb')
 *
 * Soll die Nachricht als HTML Code interpretiert und versendet werden,
 * muss folgende Funktion auch noch aufgerufen werden (optional):
 * function sendDataAsHtml()
 *
 * Am Ende muss die Mail natuerlich noch gesendet werden:
 * function sendEmail();
 *
 *****************************************************************************/
require_once(SERVER_PATH.'/adm_program/libs/phpmailer/PHPMailerAutoload.php');

class Email extends PHPMailer
{
    private $emText;              // plain text of email
    private $emHtmlText;          // html text of email
    private $emSender;            // mail sender adress and Name

    // constructor of class
    public function __construct()
    {
        //Übername Einstellungen
        global $gL10n, $gPreferences, $gDebug;

        //Wir zeigen richtige Fehlermeldungen an
        $this->exceptions = TRUE;

        $this->emCopyToSender        = false;
        $this->emListRecipients      = false;
        $this->emSendAsHTML          = false;
        $this->emText                = '';    // content of text part
        $this->emHtmlText            = '';    // content of html part

        //Hier werden noch mal alle Empfaenger der Mail reingeschrieben,
        //fuer den Fall das eine Kopie der Mail angefordert wird...
        $this->emAddresses = '';

        //Versandmethode festlegen
        if($gPreferences['mail_send_method'] == 'SMTP')
        {
            PHPMailerAutoload('smtp');
            $this->IsSMTP();

            $this->Host        = $gPreferences['mail_smtp_host'];
            $this->SMTPAuth    = $gPreferences['mail_smtp_auth'];
            $this->Port        = $gPreferences['mail_smtp_port'];
            $this->SMTPSecure  = $gPreferences['mail_smtp_secure'];
            $this->AuthType    = $gPreferences['mail_smtp_authentication_type'];
            $this->Username    = $gPreferences['mail_smtp_user'];
            $this->Password    = $gPreferences['mail_smtp_password'];
            $this->Debugoutput = 'error_log';

            if($gDebug)
            {
                $this->SMTPDebug  = 2;
            }
        }
        else
        {
             $this->IsMail();
        }

        // set language for error reporting
        $this->SetLanguage($gL10n->getLanguageIsoCode());
        $this->CharSet =  $gPreferences['mail_character_encoding'];
    }

    // method adds BCC recipients to mail
    //Bcc Empfänger werden ersteinmal gesammelt, damit später Päckchen verschickt werden können
    public function addBlindCopy($address, $name='')
    {
        $address = mb_strtolower($address);
        // Blindcopy must be Ascii-US formated, so encode in MimeHeader
        $asciiName = stripslashes($name);

        if (strValidCharacters($address, 'email'))
        {
            //$this->emBccArray[] = '"'. $asciiName. '" <'. $address. '>';
            $this->emBccArray[] = array('name'=>$asciiName, 'address'=>$address);
            $this->emAddresses = $this->emAddresses. $name. ", ".$address."\r\n";
            return true;
        }
        return false;
    }

    // method adds CC recipients to mail
    public function addCopy($address, $name='')
    {
        $address = mb_strtolower($address);
        // Copy must be Ascii-US formated, so encode in MimeHeader
        $asciiName = stripslashes($name);

        try
        {
            $this->AddCC($address, $name);
        }
        catch (phpmailerException $e)
        {
           return $e->errorMessage();
        }

        $this->emAddresses = $this->emAddresses. $name. ", ". $address. "\r\n";
        return true;
    }

    // method adds main recipients to mail
    public function addRecipient($address, $name='')
    {
        $address = mb_strtolower($address);
        // Recipient must be Ascii-US formated, so encode in MimeHeader
        $asciiName = stripslashes($name);

        try
        {
            $this->AddAddress($address, $name);
        }
        catch (phpmailerException $e)
        {
           return $e->errorMessage();
        }

        $this->emAddresses = $this->emAddresses. $name. ", ". $address. "\r\n";

        return true;
    }

    /**
     * Returns the maximum size of an attachment
     * @param  string $sizeUnit 'b' = byte, 'kib' = kilobyte, 'mib' = megabyte, 'gib' = gigabyte, 'tib' = terabyte
     * @return float  The maximum attachment size in the given size-unit
     */
    public static function getMaxAttachementSize($sizeUnit = 'mib')
    {
        global $gPreferences;

        $maxUploadSize = admFuncMaxUploadSize();
        $currentAttachmentSize = $gPreferences['max_email_attachment_size'] * pow(1024, 2);

        if($maxUploadSize < $currentAttachmentSize)
        {
            $attachmentSize = $maxUploadSize;
        }
        else
        {
            $attachmentSize = $currentAttachmentSize;
        }

        switch($sizeUnit)
        {
            case 'tib':
                $attachmentSize /= 1024;
            case 'gib':
                $attachmentSize /= 1024;
            case 'mib':
                $attachmentSize /= 1024;
            case 'kib':
                $attachmentSize /= 1024;
            default:
        }

        return round($attachmentSize, 1);
    }

    // method adds sender to mail
    public function setSender($address, $name='')
    {
        global $gPreferences;

        // save sender if a copy of the mail should be send to him
        $this->emSender = array('address'=>$address, 'name'=>$name);

        $fromName    = '';
        $fromAddress = '';
        //Falls so eingestellt soll die Mail von einer bestimmten Adresse aus versendet werden
        if(strlen($gPreferences['mail_sendmail_address']) > 0)
        {
            //hier wird die Absenderadresse gesetzt
            $fromName    = $gPreferences['mail_sendmail_name'];
            $fromAddress = $gPreferences['mail_sendmail_address'];

        }
        //Im Normalfall wird aber versucht von der Adresse des schreibenden aus zu schicken
        else
        {
            //Der Absendername ist in Doppeltueddel gesetzt, damit auch Kommas im Namen kein Problem darstellen
            $fromName    = $name;
            $fromAddress = $address;
        }

        try
        {
            // if someone wants to reply to this mail then this should go to the users email
            // and not to a domain email, so add a separate reply-to address
            $this->AddReplyTo($address, $name);
            $this->SetFrom($fromAddress, $fromName);
        }
        catch (phpmailerException $e)
        {
           return $e->errorMessage();
        }

        return TRUE;
    }

    /** Set the subject of the email
     *  @param $subject A text that should be the subject of the email
     *  @return Returns @b false if the parameter has no text
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

    // Funktion um den Nachrichtentext an die Mail uebergeben
    public function setText($text)
    {
        //Erst mal die Zeilenumbrueche innerhalb des Mailtextes umwandeln in einfache Umbrueche
        // statt \r und \r\n nur noch \n
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        $this->emText = $this->emText.strip_tags($text);
        $this->emHtmlText = $this->emHtmlText.$text;
    }

    // Funktion um das Flag zu setzen, dass eine Kopie verschickt werden soll...
    public function setCopyToSenderFlag()
    {
        $this->emCopyToSender = true;
    }

    // Funktion um das Flag zu setzen, dass in der Kopie alle Empfaenger der Mail aufgelistet werden
    public function setListRecipientsFlag()
    {
        $this->emListRecipients = true;
    }

    // write a short text with sender informations in text of email
    public function setSenderInText($senderName, $senderEmail, $receivers)
    {
        global $gL10n, $gValidLogin, $gCurrentOrganization;

        if($this->emSendAsHTML)
        {
            $senderCode = '<a href="mailto:'.$senderEmail.'">'.$senderName.'</a>';
        }
        else
        {
            $senderCode = $senderName.' ('.$senderEmail.')';
        }

        $senderText = $gL10n->get('MAI_EMAIL_SEND_TO_RECEIVER', $senderCode, $gCurrentOrganization->getValue('org_homepage'), $receivers);

        if($gValidLogin == false)
        {
            $senderText = $senderText."\r\n".$gL10n->get('MAI_SENDER_NOT_LOGGED_IN');
        }

        $senderText = $senderText."\r\n".
        '*****************************************************************************************************************************'.
        "\r\n"."\r\n";

        $this->emText = $this->emText.$senderText;
        $this->emHtmlText = $this->emHtmlText.nl2br($senderText);
    }

    // method change email header so that client will interpret mail as html mail
    public function sendDataAsHtml()
    {
        $this->emSendAsHTML = true;
    }

    //Mailbenachrichtigung für Admin
    public function adminNotfication($subject, $message, $editorName='', $editorEmail='')
    {
        global $gPreferences;
        global $gCurrentOrganization;
        global $gL10n;

        if($gPreferences['enable_email_notification'] == 1)
        {
            //Send Notifivation to Admin
            $this->addRecipient($gPreferences['email_administrator']);

            //Set Sender
            if($editorEmail == '')
            {
                $this->setSender($gPreferences['email_administrator']);
            }
            else
            {
                $this->setSender($editorEmail, $editorName);
            }

            //Set Subject
            $this->setSubject($gCurrentOrganization->getValue('org_shortname'). ": ".$subject);

            // send html if preference is set
            if($gPreferences['mail_html_registered_users'] == 1)
            {
               $this->sendDataAsHtml();
            }
            else
            {
                // html linebreaks should be converted in simple linefeed
                $message = str_replace('<br />', "\n", $message);
            }

            //Set Text
            $this->setText($message);

            //Verschicken
            return $this->sendEmail();
        }
        else
        {
            return FALSE;
        }

    }

    // Funktion um die Email endgueltig zu versenden...
    public function sendEmail()
    {
        global $gPreferences, $gL10n;

        //Text in Nachricht einfügen
        if($this->emSendAsHTML)
        {
            $this->MsgHTML($this->emHtmlText);
        }
        else
        {
            $this->Body = $this->emText;
        }

        //Wenn es Bcc-Empfänger gibt
        if (isset($this->emBccArray))
        {
            //Bcc Array in Päckchen zerlegen
            $bccArrays =  array_chunk($this->emBccArray, $gPreferences['mail_bcc_count']);

            foreach($bccArrays as $bccArray)
            {
                // remove all current bcc recipients from mail
                $this->ClearBCCs();

                try
                {
                    // if number of bcc recipients = 1 then send the mail directly to the user and not as bcc
                    if(count($bccArray) == 1)
                    {
                        $this->addAddress($bccArray[0]['address'], $bccArray[0]['name']);
                    }
                    else
                    {
                        // add all recipients as bcc to the mail
                        foreach($bccArray as $bcc)
                        {
                            $this->AddBCC($bcc['address'], $bcc['name']);
                        }
                    }

                    // now send mail
                   $this->Send();
                }
                catch (phpmailerException $e)
                {
                   return $e->errorMessage();
                }
            }
        }
        //Einzelmailversand
        else
        {
            try
            {
              $this->Send();
            }
            catch (phpmailerException $e)
            {
               return $e->errorMessage();
            }
        }

        //Jetzt noch die Mail an den Kopieempfänger
        if ($this->emCopyToSender)
        {
            //Alle Empfänger entfernen
            $this->ClearAllRecipients();

            $this->Subject = $gL10n->get('MAI_CARBON_COPY').": ".$this->Subject;

            //Kopie Header ergänzen
            $copyHeader = '*****************************************************************************************************************************'.
                          "\r\n"."\r\n";
            $copyHeader = $gL10n->get('MAI_COPY_OF_YOUR_EMAIL').':'."\r\n".$copyHeader;

            //Falls das listRecipientsFlag gesetzt ist werden in der Kopie
            //die einzelnen Empfaenger aufgelistet:
            if ($this->emListRecipients)
            {
                 $copyHeader = $this->emAddresses."\r\n".$copyHeader;
                 $copyHeader = $gL10n->get('MAI_MESSAGE_WENT_TO').':'."\r\n"."\r\n".$copyHeader;
            }

            $this->emText = $copyHeader.$this->emText;
            $this->emHtmlText = nl2br($copyHeader).$this->emHtmlText;

            //Text in Nachricht einfügen
            if($this->emSendAsHTML)
            {
                $this->MsgHTML($this->emHtmlText);
            }
            else
            {
                $this->Body = $this->emText;
            }

            //neuer Empänger
            $this->AddAddress($this->emSender['address'], $this->emSender['name']);
            try
            {
               $this->Send();
            }
            catch (phpmailerException $e)
            {
               return $e->errorMessage();
            }
        }

        // initialize recepient adresses so same email could be send to other recepients
        $this->emAddresses = '';
        $this->ClearAddresses();

        return TRUE;
    }
}
?>
