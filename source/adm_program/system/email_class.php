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
 * function newEmail()
 *
 * Nun wird der Absender gesetzt:
 * function setSender($address, $name='')
 * Uebergaben: 	$address	-	Die Emailadresse
 * 					$name		-	Der Name des Absenders (optional)
 *
 * Nun können in beliebiger Reihenfolge und Anzahl Adressaten (To,Cc,Bcc)
 * der Mail hinzugefuegt werden:
 * (optional und mehrfach aufrufbar, es muss jedoch mindestens
 * ein Empfänger mittels einer der drei Funktionen gesetzt werden)
 *
 * function addRecipient($address, $name='')
 * function addCopy($address, $name='')
 * function addBlindCopy($address, $name='')
 * Uebergaben: 	$address	-	Die Emailadresse
 * 					$name		-	Der Name des Absenders (optional)
 *
 * Nun noch ein Subject setzen (optional):
 * function setSubject($subject)
 * Uebergaben: 	$subject	-	Der Text des Betreffs
 *
 * Der Email einen Text geben:
 * function setText($text)
 * Uebergaben: 	$text		-	Der Text der Mail
 *
 * Nun kann man ein Attachment hinzufuegen:
 * (optional und mehrfach aufrufbar)
 * function addAttachment($tmp_filename, $orig_filename = '', $file_type='application/octet-stream')
 * Uebergaben: 	$tmp_filename	-	Der Pfad und Name der Datei auf dem Server
 * 				$orig_filename		-	Der Name der datei auf dem Rechner des Users
 * 				$file_type			-	Den Contenttype der Datei. (optional)
 *
 * Bei Bedarf kann man sich eine Kopie der Mail zuschicken lassen (optional):
 * function setCopyToSenderFlag()
 *
 * Am Ende muss die Mail natürlich noch gesendet werden:
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

require("./common.php");

// Email - Klasse
class newEmail
{

//Konstruktor der Klasse.
function newEmail()
{
	//Wichtig ist das die MimeVersion das erste Element im Header ist...
	$this->headerOptions['MIME-Version'] = '1.0';

	$this->mailBoundary = "--NextPart_AdmidioMailSystem_". md5(uniqid(rand()));
	$this->copyToSender = FALSE;

	//Hier werden noch mal alle Empfänger der Mail reingeschrieben,
	//für den Fall das eine Kopie der Mail angefordert wird...
	$this->addresses = '';
}

// Funktion um den Absender zu setzen
function setSender($address, $name='')
{
	if (isValidEmailAddress($address))
	{
		$this->headerOptions['From'] = $name. " <". $address. ">";
		return TRUE;
	}
	return FALSE;
}

// Funktion um den Betreff zu setzen
function setSubject($subject)
{
	$this->headerOptions['Subject'] = $subject;
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
		return TRUE;
	}
	return FALSE;
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
		return TRUE;
	}
	return FALSE;
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
		return TRUE;
	}
	return FALSE;
}

// Funktion um den Nachrichtentext an die Mail uebergeben
function setText($text)
{
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
	$this->copyToSender = TRUE;
}

function sendEmail()
{
	// Erst mal einen leeren Mailbody erstellen...
	$mail_body = '';


	// Wenn keine Absenderadresse gesetzt wurde, ist hier Ende im Gelände...
	if (!isset($this->headerOptions['From']))
	{
		return FALSE;
	}

	// Wenn keine Empfänger gesetzt wurden, ist hier auch Ende...
	if (!isset($this->headerOptions['To']) and !isset($this->headerOptions['Cc']) and !isset($this->headerOptions['Bcc']))
	{
		return FALSE;
	}

	//Hier werden die Haupt-Mailempfänger gesetzt und aus den HeaderOptions entfernt...
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

	// Hier wird der Header für die Mail aufbereitet...
	$mail_properties = '';
	foreach ($this->headerOptions as $key => $value)
	{
		$mail_properties = $mail_properties. $key. ": ". $value. "\n";
	}
	//Den letzten Zeilenumbruch im Header entsorgen.
	$mail_properties = substr($mail_properties,0,strlen($mail_properties)-1);

	// Für die Attachments alles vorbereiten...
	if (isset ($this->attachments))
	{
		$mail_body	= $mail_body. "This message is in MIME format.\n";
		$mail_body	= $mail_body. "Since your mail reader does not understand this format,\n";
    	$mail_body	= $mail_body. "some or all of this message may not be legible.\n\n";
    	$mail_body	= $mail_body. "--". $this->mailBoundary. "\nContent-Type: text/plain; charset=\"iso-8859-1\"\r\n\r\n";
	}

	// Eigentlichen Mail-Text hinzufügen...
	$mail_body = $mail_body. $this->text. "\n\n";

	// Jetzt die Attachments hinzufuegen...
	if (isset ($this->attachments))
	{
		for($i = 0; $i < count($this->attachments); $i++)
		{
			$thefile = '';
			$fileContent = '';

			$mail_body = $mail_body. "--". $this->mailBoundary. "\r\n";
			$mail_body = $mail_body. "Content-Type: ". $this->attachments[$i]['file_type']. ";\r\n";
			$mail_body = $mail_body. "\tname=\"". $this->attachments[$i]['orig_filename']. "\"\r\n";
			$mail_body = $mail_body. "Content-Transfer-Encoding: base64\r\n";
			$mail_body = $mail_body. "Content-Disposition: attachment;\r\n";
			$mail_body = $mail_body. "\tfilename=\"". $this->attachments[$i]['orig_filename']. "\"\r\n\r\n";
			$theFile = fopen($this->attachments[$i]['tmp_filename'], "rb");
			$fileContent = fread($theFile, filesize($this->attachments[$i]['tmp_filename']));
			fclose($theFile);

			// Attachment encodieren und splitten...
			$fileContent = chunk_split(base64_encode($fileContent));

			// Attachment in den Body einfuegen...
			$mail_body = $mail_body. $fileContent. "\r\n\r\n";
		}
		// Das Ende der Mail mit der Boundary kennzeichnen...
		$mail_body = $mail_body. "--". $this->mailBoundary. "--";
	}

	// Mail wird jetzt versendet...
	if (!mail($recipient, $subject, $mail_body, $mail_properties))
	{
	 	return FALSE;
	}

	// Eventuell noch eine Kopie an den Absender:
	if ($this->copyToSender)
	{
		$mail_body = "Hier ist Deine angeforderte Kopie der Nachricht:\n\n". $mail_body;
	 	$mail_body = $this->addresses. "\n". $mail_body;
	 	$mail_body = "Diese Nachricht ging an:\n\n". $mail_body;
	 	unset($this->headerOptions['To']);
	 	unset($this->headerOptions['Cc']);
	 	unset($this->headerOptions['Bcc']);

	 	// Header für die Kopie aufbereiten...
	 	$mail_properties = '';
		foreach ($this->headerOptions as $key => $value)
		{
			$mail_properties = $mail_properties. $key. ": ". $value. "\n";
		}
		//Den letzten Zeilenumbruch im Header entsorgen.
		$mail_properties = substr($mail_properties,0,strlen($mail_properties)-1);

	 	// Kopie versenden an den originalen Absender...
	 	if (!mail($this->headerOptions['From'], $subject, $mail_body, $mail_properties))
	 	{
	 		return FALSE;
	 	}
	}
	return TRUE;
}



} // Ende der Klasse


?>