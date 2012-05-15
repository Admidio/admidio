<?php
/******************************************************************************
 * Create and send a text or html email with attachments
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
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
 * Nun kann man ein Attachment hinzufuegen:
 * (optional und mehrfach aufrufbar)
 * function addAttachment($tmp_filename, $orig_filename = '', $file_type='application/octet-stream',  
                          $file_disposition = 'attachment',$file_id = '')
 * Parameters: $tmp_filename     - Der Pfad und Name der Datei auf dem Server
 *             $orig_filename    - Der Name der datei auf dem Rechner des Users
 *             $file_type        - Den Contenttype der Datei. (optional)
 *             $file_disposition - Damit kann mann festlegen ob mann das Attachment als Anhang (=> 'attachment') 
 *                                 oder in der Mail verwendent (=> 'inline')
 *             $file_id          - Damit kann mann die id jeden Attachments festlegen
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

class Email
{
private $countBoundaries;   // number of boundaries in email
private $arrayBoundaries;   // array with all boundaries
private $text;              // plain text of email
private $htmlText;          // html text of email

// constructor of class
public function __construct()
{
    global $gPreferences;

    // Important: MimeVersion should be first element of header
    $this->headerOptions['MIME-Version'] = '1.0';

    $this->headerOptions['Return-Path'] = $gPreferences['email_administrator'];
    $this->headerOptions['Sender']      = $gPreferences['email_administrator'];

    $this->copyToSender        = false;
    $this->listRecipients      = false;
    $this->sendAsHTML          = false;
    $this->text                = '';    // content of text part
    $this->htmlText            = '';    // content of html part
	$this->charset             = $gPreferences['mail_character_encoding'];
	
	// initialize email boundaries
	$this->countBoundaries = 0;
	$this->arrayBoundaries = array();

    //Hier werden noch mal alle Empfaenger der Mail reingeschrieben,
    //fuer den Fall das eine Kopie der Mail angefordert wird...
    $this->addresses = '';

}

// method adds an attachement to the mail
// tempFilename     : filename of the local server path where admidio is installed
// originalFilename : original filename that will be shown in the email
// fileType         : content type of the file in the email
// fileDisposition  : how the file should be shown in email : 'attachement' or 'inline'
// fileId           : a unique id for the file, so that it could be identified in the email
public function addAttachment($tempFilename, $originalFilename = '', $fileType='application/octet-stream' ,
					   $fileDisposition = 'attachment',$fileId = '')
{
    $this->attachments[] = array(
            'orig_filename' => $originalFilename,
            'tmp_filename'  => $tempFilename,
            'file_type'     => $fileType,
            'file_disposition' => $fileDisposition,
            'file_id'       => $fileId);
}

// method adds BCC recipients to mail
public function addBlindCopy($address, $name='')
{
    $address = admStrToLower($address);
    // Blindcopy must be Ascii-US formated, so encode in MimeHeader
	$asciiName = admEncodeMimeheader(stripslashes($name));

    if (strValidCharacters($address, 'email'))
    {
        $this->bccArray[] = '"'. $asciiName. '" <'. $address. '>';
        $this->addresses = $this->addresses. $name. ', '.$address."\r\n";
        return true;
    }
    return false;
}

// method adds CC recipients to mail
public function addCopy($address, $name='')
{
    $address = admStrToLower($address);
    // Copy must be Ascii-US formated, so encode in MimeHeader
	$asciiName = admEncodeMimeheader(stripslashes($name));

    if (strValidCharacters($address, 'email'))
    {
        if (!isset($this->headerOptions['Cc']))
        {
            $this->headerOptions['Cc'] = '"'. $asciiName. '" <'. $address. '>';
        }
        else
        {
			$this->headerOptions['Cc'] = $this->headerOptions['Cc']. ', "'. $asciiName. '" <'. $address. '>';
        }
        $this->addresses = $this->addresses. $name. ', '. $address. "\r\n";
        return true;
    }
    return false;
}

// method adds main recipients to mail
public function addRecipient($address, $name='')
{
    $address = admStrToLower($address);
    // Recipient must be Ascii-US formated, so encode in MimeHeader
	$asciiName = admEncodeMimeheader(stripslashes($name));

    if (strValidCharacters($address, 'email'))
    {
        if (!isset($this->headerOptions['To']))
        {
            $this->headerOptions['To'] = '"'. $asciiName. '" <'. $address. '>';
        }
        else
        {
			$this->headerOptions['To'] = $this->headerOptions['To']. ', "'. $asciiName. '" <'. $address. '>';
        }
        $this->addresses = $this->addresses. $name. ', '. $address. "\r\n";
        return true;
    }
    return false;
}

// close current boundary and set previous boundary active
public function closeCurrentBoundary()
{
    $this->mail_body = $this->mail_body.'--'.$this->arrayBoundaries[$this->countBoundaries].'--';
    if($this->countBoundaries > 1)
    {
        $this->mail_body = $this->mail_body. "\n\n";
    }

    $this->countBoundaries--;
}

// Methode gibt die maximale Groesse der Anhaenge zurueck
// size_unit : 'b' = byte; 'kb' = kilobyte; 'mb' = megabyte
public static function getMaxAttachementSize($size_unit = 'kb')
{
    global $gPreferences;
    
    if(round(admFuncMaxUploadSize()/pow(1024, 1), 1) < $gPreferences['max_email_attachment_size'])
    {
        $attachment_size = round(admFuncMaxUploadSize()/pow(1024, 1), 2);
    }
    else
    {
        $attachment_size = $gPreferences['max_email_attachment_size'];
    }
    
    if($size_unit == 'mb')
    {
        $attachment_size = $attachment_size / 1024;
    }
    elseif($size_unit == 'b')
    {
        $attachment_size = $attachment_size * 1024;
    }
    return round($attachment_size, 2);
}


// prepares email body with different content types
private function prepareBody()
{
    $this->mail_body = '';
    
    // set html and plain text into email body
    if(isset($this->attachments))
    {
        if($this->attachments[0]['file_disposition'] == 'inline')
        {
            // html email with inline pictures
		    $this->setNewBoundary('multipart/alternative');
	
	        // add text to body
	        $this->setNextBoundary('text/plain');
	        $this->mail_body = $this->mail_body. $this->text. "\n\n";
	        
	        // add html to body
            $this->setNewBoundary('multipart/related');
	        $this->setNextBoundary('text/html');
	        $this->mail_body = $this->mail_body. $this->htmlText."\n\n";
        }
        else
        {
            // email with attachements in html or plain text
            $this->setNewBoundary('multipart/mixed');
            
            if($this->sendAsHTML)
            {
		        // html email that will also be send as text
		        $this->setNewBoundary('multipart/alternative');
		
		        // add text to body
		        $this->setNextBoundary('text/plain');
		        $this->mail_body = $this->mail_body. $this->text. "\n\n";
		        // add html to body
		        $this->setNextBoundary('text/html');
		        $this->mail_body = $this->mail_body. $this->htmlText."\n\n";
		
		        $this->closeCurrentBoundary();
            }
            else
            {
		        // only a text email
		        $this->setNextBoundary('text/plain');
		
		        // add text to body
		        $this->mail_body = $this->mail_body. $this->text. "\n\n";
            }
        }
    }
    elseif($this->sendAsHTML)
    {
        // html email that will also be send as text
        $this->setNewBoundary('multipart/alternative');

        // add text to body
        $this->setNextBoundary('text/plain');
        $this->mail_body = $this->mail_body. $this->text. "\n\n";
        // add html to body
        $this->setNextBoundary('text/html');
        $this->mail_body = $this->mail_body. $this->htmlText."\n\n";

        $this->closeCurrentBoundary();
    }
    else
    {
        // only a text email
        $this->setNewBoundary('text/plain');

        // add text to body
        $this->mail_body = $this->mail_body. $this->text. "\n\n";
    }
    
    // now add all attachments to the email
    if (isset ($this->attachments))
    {
        for ($i = 0; $i < count($this->attachments); $i++)
        {
            $thefile = '';
            $fileContent = '';

			$this->setNextBoundary($this->attachments[$i]['file_type']);
            $this->mail_body = $this->mail_body. "Content-Type: ". $this->attachments[$i]['file_type']. ";\n";
            $this->mail_body = $this->mail_body. "\tname=\"". $this->attachments[$i]['orig_filename']. "\"\n";
            $this->mail_body = $this->mail_body. "Content-Transfer-Encoding: base64\n";
            if (!empty($this->attachments[$i]['file_disposition']))
            {
                $this->mail_body = $this->mail_body. "Content-Disposition: ".$this->attachments[$i]['file_disposition'].";\n";
            }
            else
            {
                $this->mail_body = $this->mail_body. "Content-Disposition: attachment;\n";
            }
            $this->mail_body = $this->mail_body. "\tfilename=\"". $this->attachments[$i]['orig_filename']. "\"\n";
            if (!empty($this->attachments[$i]['file_id']))
            {
                $this->mail_body = $this->mail_body. "Content-ID: <".$this->attachments[$i]['file_id'].">\n\n";
            }

            // open file and convert to base64
            $return  = '';
            $data    = '';
            $thePart = '';
            if ($fp = fopen($this->attachments[$i]['tmp_filename'], 'rb'))
            {
                while (!feof($fp))
                {
                    $return .= fread($fp, 1024);
                }
                fclose($fp);
                $data = base64_encode($return);

            }

            if (function_exists('chunk_split'))
            {
               $thePart    .= chunk_split($data,76,"\n");
            }
            else
            {
                $theData    = $data;
                while (strlen($theData)>76)
                {
                    $thePart.=substr($theData,0,76)."\n";
                    $theData=substr($theData,76);
                }
                $thePart    .= $theData."\n";
            }
            $this->mail_body = $this->mail_body. "\n". $thePart. "\n\n";
        }
    }

	// if html email or has attachements than close all open boundaries
	if($this->sendAsHTML || isset($this->attachments))
	{
		for($number = $this->countBoundaries; $number > 0; $number--)
		{
			$this->closeCurrentBoundary();
		}
	}

    // initialize boundaries for next mail
	$this->countBoundaries = 0;
	$this->arrayBoundaries = array();

	// if character encoding is iso-8859-1 than decode our utf-8 string to iso-8859-1
	if($this->charset == 'iso-8859-1')
	{
		utf8_decode($this->mail_body);
	}
}

// prepares email header informations
private function prepareHeader()
{
    $this->mail_properties = '';
    foreach ($this->headerOptions as $key => $value)
    {
		// mail headers should be separated with \n , NOT as recommendet see in PHP doku and RFC 2822
        $this->mail_properties = $this->mail_properties. $key. ': '. $value. "\n";
    }
    // removes last line feed from header
    $this->mail_properties = substr($this->mail_properties,0,strlen($this->mail_properties)-1);
}

// set a new boundary header; the first one is set in email header and the other are set in email content
public function setNewBoundary($contentType)
{
    $this->countBoundaries++;
	$this->arrayBoundaries[$this->countBoundaries] = 'NextPart_AdmidioMailSystem_'. md5(uniqid(rand()));

    if($this->countBoundaries == 1)
    {
        // if it's the first boundary than set infos into email header
        if($contentType == 'text/plain')
        {
            $this->headerOptions['Content-Type'] = $contentType.'; charset='.$this->charset;
        }
        else
        {
            $this->headerOptions['Content-Type'] = $contentType.";\n\tboundary=\"". $this->arrayBoundaries[$this->countBoundaries]. '"';
        }
    }
    else
    {
        // all other boundaries are set in email content
        $this->mail_body = $this->mail_body. '--'. $this->arrayBoundaries[$this->countBoundaries - 1].
                                "\nContent-Type: ".$contentType.";\n\tboundary=\"".$this->arrayBoundaries[$this->countBoundaries]."\"\n\n";
    }
}

// set next content part of current boundary
public function setNextBoundary($contentType)
{
    $this->mail_body = $this->mail_body. '--'. $this->arrayBoundaries[$this->countBoundaries]. "\n";
    
    // if content is text or html than define content type
    if($contentType == 'text/plain' || $contentType == 'text/html')
    {
        $this->mail_body = $this->mail_body.'Content-Type: '.$contentType.'; charset='.$this->charset."\nContent-Transfer-Encoding: 7bit\n\n";
    }
}

// method adds sender to mail
public function setSender($address, $name='')
{
    global $gPreferences;
    $address = admStrToLower($address);
	if(strlen($name) == 0)
	{
		$name = $address;
	}
	else
	{
		// Sender must be Ascii-US formated, so encode in MimeHeader
		$name = admEncodeMimeheader(stripslashes($name));
	}
    
    if (strValidCharacters($address, 'email'))
    {
        //Falls so eingestellt soll die Mail von einer bestimmten Adresse aus versendet werden
        if(strlen($gPreferences['mail_sendmail_address']) > 0) // && $address != $gPreferences['email_administrator'])
        {
            //hier wird die Absenderadresse gesetzt
            $this->headerOptions['From'] = '"'. $name. '" <'. $gPreferences['mail_sendmail_address']. '>';
			
            //wenn der Empfänger dann auf anworten klickt soll die natürlich an den wirklichen Absender gehen
			//Der Absendername ist in Doppeltueddel gesetzt, damit auch Kommas im Namen kein Problem darstellen
			$this->headerOptions['Reply-To'] = '"'. $name. '" <'. $address. '>';
        }
        //Im Normalfall wird aber versucht von der Adresse des schreibenden aus zu schicken
        else
        {
			//Der Absendername ist in Doppeltueddel gesetzt, damit auch Kommas im Namen kein Problem darstellen
			$this->headerOptions['From'] = '"'. $name. '" <'. $address. '>';
        }
        return true;
    }
    return false;
}

// write a short text with sender informations in text of email
public function setSenderInText($senderName, $senderEmail, $roleName)
{
    global $gL10n, $gValidLogin, $gCurrentOrganization;
    
    if($this->sendAsHTML)
    {
        $senderCode = '<a href="mailto:'.$senderEmail.'">'.$senderName.'</a>';
    }
    else
    {
        $senderCode = $senderName.' ('.$senderEmail.')';
    }
    
    if(strlen($roleName) > 0)
    {
        $senderText = $gL10n->get('MAI_EMAIL_SEND_TO_ROLE', $senderCode, $gCurrentOrganization->getValue('org_homepage'), $roleName);
    }
    else
    {
        $senderText = $gL10n->get('MAI_EMAIL_SEND_TO_USER', $senderCode, $gCurrentOrganization->getValue('org_homepage'));
    }
    
    if($gValidLogin == false)
    {
        $senderText = $senderText."\r\n".$gL10n->get('MAI_SENDER_NOT_LOGGED_IN');
    }

    $senderText = $senderText."\r\n".
    '*****************************************************************************************************************************'.
    "\r\n"."\r\n";
    
    $this->text = $this->text.$senderText;
    $this->htmlText = $this->htmlText.nl2br($senderText);
}

// set subject in email
public function setSubject($subject)
{
    if (strlen($subject) > 0)
    {
        $this->headerOptions['Subject'] = admEncodeMimeheader(stripslashes($subject));
        return true;
    }
    return false;
}

// Funktion um den Nachrichtentext an die Mail uebergeben
public function setText($text)
{
    //Erst mal die Zeilenumbrueche innerhalb des Mailtextes umwandeln in einfache Umbrueche
    // statt \r und \r\n nur noch \n
    $text = str_replace('\r\n', '\n', $text);
    $text = str_replace('\r', '\n', $text);

    $this->text = $this->text.strip_tags($text);
    $this->htmlText = $this->htmlText.$text;
}

// Funktion um das Flag zu setzen, dass eine Kopie verschickt werden soll...
public function setCopyToSenderFlag()
{
    $this->copyToSender = true;
}

// Funktion um das Flag zu setzen, dass in der Kopie alle Empfaenger der Mail aufgelistet werden
public function setListRecipientsFlag()
{
    $this->listRecipients = true;
}

// method change email header so that client will interpret mail as html mail
public function sendDataAsHtml()
{
    $this->sendAsHTML = true;
}

// Funktion um die Email endgueltig zu versenden...
public function sendEmail()
{
    global $gPreferences, $gL10n;
    
	// Wenn keine Absenderadresse gesetzt wurde, ist hier Ende im Gelaende...
    if (!isset($this->headerOptions['From']))
    {
        return false;
    }

    // Wenn keine Empfaenger gesetzt wurden, ist hier auch Ende...
    if (!isset($this->headerOptions['To']) and !isset($this->headerOptions['Cc']) and !isset($this->bccArray))
    {
        return false;
    }

    //Hier werden die Haupt-Mailempfaenger gesetzt und aus den HeaderOptions entfernt...
    $recipient = '';
    if (isset($this->headerOptions['To']))
    {
        $recipient = $this->headerOptions['To'];
        unset($this->headerOptions['To']);
    }

    // Hier wird das MailSubject gesetzt und aus den HeaderOptions entfernt...
    $subject = '';
    if (isset($this->headerOptions['Subject']))
    {
        $subject = $this->headerOptions['Subject'];
        unset($this->headerOptions['Subject']);
    }

    //Hier werden jetzt die BCC-Empfaenger im Header verewigt und die Mail dann abgeschickt.
    //Da dies haeppchenweise geschehen soll, wird mit einer Schleife gearbeitet
    $bccCounter = 0;

    if (isset($this->bccArray))
    {
        foreach ($this->bccArray as $key => $value)
        {
			if($gPreferences['mail_bcc_count'] == 1)
			{
				// if BCC count is set to 1 than send as TO mail
				$recipient = $value;
			}
			else
			{
				if (!isset($this->headerOptions['Bcc']))
				{
					$this->headerOptions['Bcc'] = $value;
				}
				else
				{
					$this->headerOptions['Bcc'] = $this->headerOptions['Bcc']. ', '. $value;
				}
			}

            $bccCounter++;

            //immer wenn die Anzahl der BCCs $gPreferences['mail_bcc_count'] (Standard: 50) erreicht hat 
            // oder aber das letzte Element des Arrays erreicht ist, wird die Mail versand.
            if ($bccCounter == $gPreferences['mail_bcc_count'] || count($this->bccArray) == $key+1)
            {
                // Hier wird der Body fuer die Mail aufbereitet...
                $this->prepareBody();

                // Hier wird der Header fuer die Mail aufbereitet...
                $this->prepareHeader();

                // Mail wird jetzt versendet...
                // das Versenden in UTF8 funktioniert noch nicht bei allen Mailclients (Outlook, GMX)
                if (!mail($recipient, $subject, $this->mail_body, $this->mail_properties))
                {
                     return false;
                }

                unset($this->headerOptions['Bcc']);
                $bccCounter = 0;
            }


        }
    }
    else
    {
        //...und hier noch das ganze wenn es keine BCCs gibt!

        // Hier wird der Body fuer die Mail aufbereitet...
        $this->prepareBody();

        // Hier wird der Header fuer die Mail aufbereitet...
        $this->prepareHeader();

        // Mail wird jetzt versendet...
        // das Versenden in UTF8 funktioniert noch nicht bei allen Mailclients (Outlook, GMX)
        if (!mail($recipient, $subject, $this->mail_body, $this->mail_properties))
//        if (!mail(utf8_decode($recipient), utf8_decode($subject),  $this->mail_body, utf8_decode($this->mail_properties)))
        {
             return false;
        }
    }


    // Eventuell noch eine Kopie an den Absender verschicken:
    if ($this->copyToSender)
    {
        $copyHeader = '*****************************************************************************************************************************'.
                      "\r\n"."\r\n";
        $copyHeader = $gL10n->get('MAI_COPY_OF_YOUR_EMAIL').':'."\r\n".$copyHeader;

        //Falls das listRecipientsFlag gesetzt ist werden in der Kopie
        //die einzelnen Empfaenger aufgelistet:
        if ($this->listRecipients)
        {
             $copyHeader = $this->addresses."\r\n".$copyHeader;
             $copyHeader = $gL10n->get('MAI_MESSAGE_WENT_TO').':'."\r\n"."\r\n".$copyHeader;
        }
         
        $this->text = $copyHeader.$this->text;
        $this->htmlText = nl2br($copyHeader).$this->htmlText;

        unset($this->headerOptions['To']);
        unset($this->headerOptions['Cc']);
        unset($this->headerOptions['Bcc']);

        // Body fuer die Kopie aufbereiten...
        $this->prepareBody();

        // Header fuer die Kopie aufbereiten...
        $this->prepareHeader();

        //Das Subject modifizieren
        $subject = $gL10n->get('MAI_CARBON_COPY').': '. $subject;

        //Empfänger
        $mailto = $this->headerOptions['From'];
    	//Wenn gesonderte Versandadresse gesetzt ist die Antwortadresse verwenden
        if($gPreferences['mail_sendmail_address'] != '' && $mailto != $gPreferences['email_administrator'])
        {
            $mailto = $this->headerOptions['Reply-To'];
        }
        // Kopie versenden an den originalen Absender...
         // das Versenden in UTF8 funktioniert noch nicht bei allen Mailclients (Outlook, GMX)
         if (!mail($mailto, $subject, $this->mail_body, $this->mail_properties))
         {
             return false;
         }
    }
    return true;
}
}
?>