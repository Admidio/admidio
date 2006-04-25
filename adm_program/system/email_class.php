<?php
/******************************************************************************
 * Email - Klasse
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Mit dieser Klasse kann ein Email-Objekt erstellt
 * und anschliessend verschickt werden.
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors:
 * function Email()
 *
 * Nun wird der Absender gesetzt:
 * function setSender($address, $name='')
 * Uebergaben: $address	- Die Emailadresse
 *             $name    - Der Name des Absenders (optional)
 *
 * Nun koennen in beliebiger Reihenfolge und Anzahl Adressaten (To,Cc,Bcc)
 * der Mail hinzugefuegt werden:
 * (optional und mehrfach aufrufbar, es muss jedoch mindestens
 * ein Empfaenger mittels einer der drei Funktionen gesetzt werden)
 *
 * function addRecipient($address, $name='')
 * function addCopy($address, $name='')
 * function addBlindCopy($address, $name='')
 * Uebergaben: $address - Die Emailadresse
 *             $name    - Der Name des Absenders (optional)
 *
 * Nun noch ein Subject setzen (optional):
 * function setSubject($subject)
 * Uebergaben: $subject - Der Text des Betreffs
 *
 * Der Email einen Text geben:
 * function setText($text)
 * Uebergaben: $text - Der Text der Mail
 *
 * Nun kann man ein Attachment hinzufuegen:
 * (optional und mehrfach aufrufbar)
 * function addAttachment($tmp_filename, $orig_filename = '', $file_type='application/octet-stream')
 * Uebergaben: $tmp_filename  - Der Pfad und Name der Datei auf dem Server
 *             $orig_filename - Der Name der datei auf dem Rechner des Users
 *             $file_type     - Den Contenttype der Datei. (optional)
 *
 * Bei Bedarf kann man sich eine Kopie der Mail zuschicken lassen (optional):
 * function setCopyToSenderFlag()
 *
 * Sollen in der Kopie zusaetzlich noch alle Empfaenger aufgelistet werden,
 * muss folgende Funktion auch noch aufgerufen werden (optional):
 * function setListRecipientsFlag()
 *
 * Am Ende muss die Mail natuerlich noch gesendet werden:
 * function sendEmail();
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

// Email - Klasse
class Email
{

//Konstruktor der Klasse.
function Email()
{
    //Wichtig ist das die MimeVersion das erste Element im Header ist...
    $this->headerOptions['MIME-Version'] = '1.0';

    //Jetzt wird noch der ContentType der Mail gesetzt.
    //Dieser wird im Falle eines Attachments spaeter ersetzt.
    $this->headerOptions['Content-Type'] = "text/plain; charset=\"ISO-8859-1\"";

    $this->mailBoundary = "--NextPart_AdmidioMailSystem_". md5(uniqid(rand()));
    $this->copyToSender = false;
    $this->listRecipients = false;

    //Hier werden noch mal alle Empfaenger der Mail reingeschrieben,
    //fuer den Fall das eine Kopie der Mail angefordert wird...
    $this->addresses = '';
}

// Funktion um den Absender zu setzen
function setSender($address, $name='')
{
    if (isValidEmailAddress($address))
    {
        $this->headerOptions['From'] = $name. " <". $address. ">";
        return true;
    }
    return false;
}

// Funktion um den Betreff zu setzen
function setSubject($subject)
{
    if (strlen($subject) > 0)
    {
        $this->headerOptions['Subject'] = $subject;
        return true;
    }
    return false;
}

// Funktion um Hauptempfaenger hinzuzufuegen
function addRecipient($address, $name='')
{
    if (isValidEmailAddress($address))
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
function addCopy($address, $name='')
{
    if (isValidEmailAddress($address))
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
function addBlindCopy($address, $name='')
{
    if (isValidEmailAddress($address))
    {
        if (!isset($this->headerOptions['Bcc']))
        {
            $this->headerOptions['Bcc'] = $name. " <". $address. ">";
        }
        else
        {
        $this->headerOptions['Bcc'] = $this->headerOptions['Bcc']. ", ". $name. " <". $address. ">";
        }
        $this->addresses = $this->addresses. $name. " <". $address. ">\n";
        return true;
    }
    return false;
}

// Funktion um den Nachrichtentext an die Mail uebergeben
function setText($text)
{
    //Erst mal die Zeilenumbrueche innerhalb des Mailtextes umwandeln in einfache Umbrueche
    // statt \r und \r\n nur noch \n
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\r", "\n", $text);

    $this->text = $text;
}

// Funktion um ein Attachment an die Mail zu uebergeben...
function addAttachment($tmp_filename, $orig_filename = '', $file_type='application/octet-stream')
{
    $this->attachments[] = array(
            'orig_filename'	=> $orig_filename,
            'tmp_filename'	=> $tmp_filename,
            'file_type'	=> $file_type);
    $this->headerOptions['Content-Type'] = "multipart/mixed;\n\tboundary=\"". $this->mailBoundary. "\"";
}

// Funktion um das Flag zu setzen, dass eine Kopie verschickt werden soll...
function setCopyToSenderFlag()
{
    $this->copyToSender = true;
}

// Funktion um das Flag zu setzen, dass in der Kopie alle Empf�nger der Mail aufgelistet werden
function setListRecipientsFlag()
{
    $this->listRecipients = true;
}

// Funktion um den Header aufzubereiten
function prepareHeader()
{
    $this->mail_properties = '';
    foreach ($this->headerOptions as $key => $value)
    {
        $this->mail_properties = $this->mail_properties. $key. ": ". $value. "\n";
    }
    //Den letzten Zeilenumbruch im Header entsorgen.
    $this->mail_properties = substr($this->mail_properties,0,strlen($this->mail_properties)-1);
}

// Funktion um den Body zusammenzusetzen
function prepareBody()
{
    $this->mail_body = '';

    // Fuer die Attachments alles vorbereiten...
    if (isset($this->attachments))
    {
        $this->mail_body	= $this->mail_body. "This message is in MIME format.\n";
        $this->mail_body	= $this->mail_body. "Since your mail reader does not understand this format,\n";
        $this->mail_body	= $this->mail_body. "some or all of this message may not be legible.\n\n";
        $this->mail_body	= $this->mail_body. "--". $this->mailBoundary. "\nContent-Type: text/plain; charset=\"iso-8859-1\"\n\n";
    }

    // Eigentlichen Mail-Text hinzufuegen...
    $this->mail_body = $this->mail_body. $this->text. "\n\n";

    // Jetzt die Attachments hinzufuegen...
    if (isset ($this->attachments))
    {
        for ($i = 0; $i < count($this->attachments); $i++)
        {
            $thefile = '';
            $fileContent = '';

            $this->mail_body = $this->mail_body. "--". $this->mailBoundary. "\n";
            $this->mail_body = $this->mail_body. "Content-Type: ". $this->attachments[$i]['file_type']. ";\n";
            $this->mail_body = $this->mail_body. "\tname=\"". $this->attachments[$i]['orig_filename']. "\"\n";
            $this->mail_body = $this->mail_body. "Content-Transfer-Encoding: base64\n";
            $this->mail_body = $this->mail_body. "Content-Disposition: attachment;\n";
            $this->mail_body = $this->mail_body. "\tfilename=\"". $this->attachments[$i]['orig_filename']. "\"\n\n";
            $theFile = fopen($this->attachments[$i]['tmp_filename'], "rb");
            $fileContent = fread($theFile, filesize($this->attachments[$i]['tmp_filename']));
            fclose($theFile);

            // Attachment encodieren und splitten...
            $fileContent = chunk_split(base64_encode($fileContent));

            // Attachment in den Body einfuegen...
            $this->mail_body = $this->mail_body. $fileContent. "\n\n";
        }
        // Das Ende der Mail mit der Boundary kennzeichnen...
        $this->mail_body = $this->mail_body. "--". $this->mailBoundary. "--";
    }

}

// Funktion um die Email endgueltig zu versenden...
function sendEmail()
{
    // Wenn keine Absenderadresse gesetzt wurde, ist hier Ende im Gelaende...
    if (!isset($this->headerOptions['From']))
    {
        return false;
    }

    // Wenn keine Empfaenger gesetzt wurden, ist hier auch Ende...
    if (!isset($this->headerOptions['To']) and !isset($this->headerOptions['Cc']) and !isset($this->headerOptions['Bcc']))
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

    // Hier wird der Header fuer die Mail aufbereitet...
    $this->prepareHeader();

    // Hier wird der Body fuer die Mail aufbereitet...
    $this->prepareBody();

    // Mail wird jetzt versendet...
    if (!mail($recipient, $subject, $this->mail_body, $this->mail_properties))
    {
         return false;
    }

    // Eventuell noch eine Kopie an den Absender verschicken:
    if ($this->copyToSender)
    {
        $this->text = "*******************************************************************\n\n". $this->text;
        $this->text = "Hier ist Deine angeforderte Kopie der Nachricht:\n". $this->text;

         //Falls das listRecipientsFlag gesetzt ist werden in der Kopie
         //die einzelnen Empfaenger aufgelistet:
         if ($this->listRecipients)
         {
             $this->text = $this->addresses. "\n". $this->text;
             $this->text = "Diese Nachricht ging an:\n\n". $this->text;
         }

         unset($this->headerOptions['To']);
         unset($this->headerOptions['Cc']);
         unset($this->headerOptions['Bcc']);

         // Header fuer die Kopie aufbereiten...
         $this->prepareHeader();

         // Body fuer die Kopie aufbereiten...
        $this->prepareBody();

        //Das Subject modifizieren
        $subject = "Kopie: ". $subject;

         // Kopie versenden an den originalen Absender...
         if (!mail($this->headerOptions['From'], $subject, $this->mail_body, $this->mail_properties))
         {
             return false;
         }
    }
    return true;
}


} // Ende der Klasse


?>