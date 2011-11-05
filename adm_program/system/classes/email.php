<?php
/******************************************************************************
 * Email - Klasse
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
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
 * function setText($text)
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
 * function setDataAsHtml()
 *
 * Am Ende muss die Mail natuerlich noch gesendet werden:
 * function sendEmail();
 *
 *****************************************************************************/

// Email - Klasse
class Email
{

//Konstruktor der Klasse.
public function __construct()
{
    global $gPreferences;

    //Wichtig ist das die MimeVersion das erste Element im Header ist...
    $this->headerOptions['MIME-Version'] = '1.0';

    //Jetzt wird noch der ContentType der Mail gesetzt.
    //Dieser wird im Falle eines Attachments oder HTML spaeter ersetzt.
    $this->headerOptions['Content-Type'] = 'text/plain; charset=iso-8859-1';

    $this->headerOptions['Return-Path'] = $gPreferences['email_administrator'];
    $this->headerOptions['Sender']      = $gPreferences['email_administrator'];

    $this->mailBoundary        = '--NextPart_AdmidioMailSystem_'. md5(uniqid(rand()));
    $this->mailBoundaryRelated = $this->mailBoundary;
    $this->copyToSender        = false;
    $this->listRecipients      = false;
    $this->sendAsHTML          = false;

    //Hier werden noch mal alle Empfaenger der Mail reingeschrieben,
    //fuer den Fall das eine Kopie der Mail angefordert wird...
    $this->addresses = '';

}

// Funktion um den Absender zu setzen
public function setSender($address, $name='')
{
    global $gPreferences;
    $address = admStrToLower($address);
    // Sender must be Ascii-US formated, so encode in MimeHeader
	$name    = admEncodeMimeheader(stripslashes($name));
    
    if (strValidCharacters($address, 'email'))
    {
        //Falls so eingestellt soll die Mail von einer bestimmten Adresse aus versendet werden
        if($gPreferences['mail_sendmail_address'] != '' && $address != $gPreferences['email_administrator'])
        {
            //hier wird die Absenderadresse gesetzt
            $this->headerOptions['From'] = " <". $gPreferences['mail_sendmail_address']. ">";    
            //wenn der Empfänger dann auf anworten klickt soll die natürlich an den wirklichen Absender gehen
            if (strlen($name) > 0)
            {
                //Der Absendername ist in Doppeltueddel gesetzt, damit auch Kommas im Namen kein Problem darstellen
                $this->headerOptions['Reply-To'] = '"'. $name. '" <'. $address. '>';
            }
            else
            {
                //Kein Name gesetzt...
                $this->headerOptions['Reply-To'] = " <". $address. ">";
            }              
        }
        //Im Normalfall wird aber versucht von der Adresse des schreibenden aus zu schicken
        else
        {
            if (strlen($name) > 0)
            {
                //Der Absendername ist in Doppeltueddel gesetzt, damit auch Kommas im Namen kein Problem darstellen
                $this->headerOptions['From'] = '"'. $name. '" <'. $address. '>';
            }
            else
            {
                //Kein Name gesetzt...
                $this->headerOptions['From'] = " <". $address. ">";
            }
        }
        return true;
    }
    return false;
}

// Funktion um den Betreff zu setzen
public function setSubject($subject)
{
    if (strlen($subject) > 0)
    {
        $this->headerOptions['Subject'] = admEncodeMimeheader(stripslashes($subject));
        return true;
    }
    return false;
}

// Funktion um Hauptempfaenger hinzuzufuegen
public function addRecipient($address, $name='')
{
    $address = admStrToLower($address);
    // Recipient must be Ascii-US formated, so encode in MimeHeader
	$name    = admEncodeMimeheader(stripslashes($name));
    if (strValidCharacters($address, 'email'))
    {
        if (!isset($this->headerOptions['To']))
        {
            $this->headerOptions['To'] = $name. " <". $address. ">";
        }
        else
        {
			$this->headerOptions['To'] = $this->headerOptions['To']. ", ". $name. " <". $address. ">";
        }
        $this->addresses = $this->addresses. $name. " <". $address. ">\n";
        return true;
    }
    return false;
}

// Funktion um Ccs hinzuzufuegen
public function addCopy($address, $name='')
{
    $address = admStrToLower($address);
    // Copy must be Ascii-US formated, so encode in MimeHeader
	$name    = admEncodeMimeheader(stripslashes($name));
    if (strValidCharacters($address, 'email'))
    {
        if (!isset($this->headerOptions['Cc']))
        {
            $this->headerOptions['Cc'] = $name. " <". $address. ">";
        }
        else
        {
        $this->headerOptions['Cc'] = $this->headerOptions['Cc']. ", ". $name. " <". $address. ">";
        }
        $this->addresses = $this->addresses. $name. " <". $address. ">\n";
        return true;
    }
    return false;
}

// Funktion um Bccs hinzuzufuegen
public function addBlindCopy($address, $name='')
{
    $address = admStrToLower($address);
    // Blindcopy must be Ascii-US formated, so encode in MimeHeader
	$name    = admEncodeMimeheader(stripslashes($name));
    if (strValidCharacters($address, 'email'))
    {
        $this->bccArray[] = $name. " <". $address. ">";
        $this->addresses = $this->addresses. $name. " <". $address. ">\n";
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

    $this->text = $text;
}

// Funktion um ein Attachment an die Mail zu uebergeben...
public function addAttachment($tmp_filename, $orig_filename = '', $file_type='application/octet-stream' ,
					   $file_disposition = 'attachment',$file_id = '')
{
    $this->attachments[] = array(
            'orig_filename' => $orig_filename,
            'tmp_filename'  => $tmp_filename,
            'file_type'     => $file_type,
            'file_disposition' => $file_disposition,
            'file_id'       => $file_id);
    if($this->sendAsHTML == false)
    {
	    // HTML-Mails mit Anhang behalten ihren Type, Textmails bekommen einen Speziellen
		$this->headerOptions['Content-Type'] = "multipart/mixed;\n\tboundary=\"". $this->mailBoundary. '"';
	}
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

// Funktion um die zu sendenden Daten als HTML Code zu inerpretieren zu lassen.
public function setDataAsHtml()
{
    $this->sendAsHTML = true;
	$this->headerOptions['Content-Type'] = "multipart/alternative;\n\tboundary=\"". $this->mailBoundary. '"';
}

// Funktion um den Header aufzubereiten
private function prepareHeader()
{
    $this->mail_properties = '';
    foreach ($this->headerOptions as $key => $value)
    {
        $this->mail_properties = $this->mail_properties. $key. ": ". $value. "\n";
    }
    //Den letzten Zeilenumbruch im Header entsorgen.
    $this->mail_properties = substr($this->mail_properties,0,strlen($this->mail_properties)-1);
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

// Funktion um den Body zusammenzusetzen
private function prepareBody()
{
    $this->mail_body = '';

    // bei multipart-Mails muss auch der Content-Type fuer Text explizit gesetzt werden
    if($this->sendAsHTML || isset($this->attachments))
    {
	  	$this->mail_body = $this->mail_body. "--". $this->mailBoundary.
	    				   "\nContent-Type: text/plain; charset=iso-8859-1\nContent-Transfer-Encoding: 7bit\n\n";
    }

    // nun den Mailtext als Text-Format hinzufuegen
    $this->mail_body = $this->mail_body. strip_tags($this->text). "\n\n";

	// wenn gewuenscht, nun den Inhalt als HTML einsetzen
    if($this->sendAsHTML)
    {
    	// Html mit eingebetteten Bildern bekommt eine eigene Boundary
    	if(isset($this->attachments))
    	{
    		$this->mailBoundaryRelated = '--NextPart_AdmidioMailSystem_'. md5(uniqid(rand()));
			$this->mail_body = $this->mail_body. "--". $this->mailBoundary. 
						   	   "\nContent-Type: multipart/related;\n\tboundary=\"". $this->mailBoundaryRelated. "\"\n\n";
		}
		$this->mail_body = $this->mail_body. "--". $this->mailBoundaryRelated. 
					   	   "\nContent-Type: text/html; charset=iso-8859-1\nContent-Transfer-Encoding: 7bit\n\n";
        $this->mail_body = $this->mail_body. $this->text."\n\n";
    }

    // Jetzt die Attachments hinzufuegen...
    if (isset ($this->attachments))
    {
        for ($i = 0; $i < count($this->attachments); $i++)
        {
            $thefile = '';
            $fileContent = '';

            $this->mail_body = $this->mail_body. "--". $this->mailBoundaryRelated. "\n";
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

            // File oeffnen und base64 konvertieren
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
        // Das Ende der Mail mit der Boundary kennzeichnen...
        if($this->mailBoundary != $this->mailBoundaryRelated)
        {
        	$this->mail_body = $this->mail_body. "--". $this->mailBoundaryRelated. "--\n\n";
        }
        $this->mail_body = $this->mail_body. "--". $this->mailBoundary. "--";
    }

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
            if (!isset($this->headerOptions['Bcc']))
            {
                $this->headerOptions['Bcc'] = $value;
            }
            else
            {
            $this->headerOptions['Bcc'] = $this->headerOptions['Bcc']. ', '. $value;
            }

            $bccCounter++;

            //immer wenn die Anzahl der BCCs $gPreferences['mail_bcc_count'] (Standard: 50) erreicht hat 
            // oder aber das letzte Element des Arrays erreicht ist, wird die Mail versand.
            if ($bccCounter == $gPreferences['mail_bcc_count'] || count($this->bccArray) == $key+1)
            {
                // Hier wird der Header fuer die Mail aufbereitet...
                $this->prepareHeader();

                // Hier wird der Body fuer die Mail aufbereitet...
                $this->prepareBody();

                // Mail wird jetzt versendet...
                // das Versenden in UTF8 funktioniert noch nicht bei allen Mailclients (Outlook, GMX)
                if (!mail($recipient, $subject, utf8_decode($this->mail_body), $this->mail_properties))
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

        // Hier wird der Header fuer die Mail aufbereitet...
        $this->prepareHeader();

        // Hier wird der Body fuer die Mail aufbereitet...
        $this->prepareBody();

        // Mail wird jetzt versendet...
        // das Versenden in UTF8 funktioniert noch nicht bei allen Mailclients (Outlook, GMX)
        if (!mail($recipient, $subject, utf8_decode($this->mail_body), $this->mail_properties))
        {
             return false;
        }
    }


    // Eventuell noch eine Kopie an den Absender verschicken:
    if ($this->copyToSender)
    {
        $this->text = "*******************************************************************\n\n". $this->text;
        $this->text = $gL10n->get('MAI_COPY_OF_YOUR_EMAIL').":\n". $this->text;

         //Falls das listRecipientsFlag gesetzt ist werden in der Kopie
         //die einzelnen Empfaenger aufgelistet:
         if ($this->listRecipients)
         {
             $this->text = $this->addresses. "\n". $this->text;
             $this->text = $gL10n->get('MAI_MESSAGE_WENT_TO').":\n\n". $this->text;
         }

         unset($this->headerOptions['To']);
         unset($this->headerOptions['Cc']);
         unset($this->headerOptions['Bcc']);

         // Header fuer die Kopie aufbereiten...
         $this->prepareHeader();

         // Body fuer die Kopie aufbereiten...
        $this->prepareBody();

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
         if (!mail($mailto, $subject, utf8_decode($this->mail_body), $this->mail_properties))
         {
             return false;
         }
    }
    return true;
}// Ende der Klasse
}
?>