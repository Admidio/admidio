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
 * function newEmail($homepage, $title, $description)
 * Uebergaben: 	$homepage		-	Link zur Homepage
 * 				$title			-	Titel des RSS-Feeds
 * 				$description	-	Ergaenzende Beschreibung zum Titel
 *
 * Dem RSSfeed koennen ueber die Funktion addItem Inhalt zugeordnet werden:
 * function addItem($title, $description, $date, $guid)
 * Uebergaben:	$title			-	Titel des Items
 * 				$description	-	der Inhalt des Items
 * 				$date			-	Das Erstellungsdatum des Items
 * 				$link			-	Ein Link zum Termin/Newsbeitrag etc.
 *
 * Wenn alle benoetigten Items zugeordnet sind, wird der RSSfeed generiert mit:
 * function build_feed()
 *
 *
 *
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



//Konstruktor der Klasse. Hier werden auch automatisch Encoding-Optionen etc. gesetzt
function newEmail()
{
	$this->headerOptions['MIME-Version'] = '1.0';
	$this->mailBoundary = "--NextPart_AdmidioMailSystem_". md5(uniqid(rand()));
	$this->copyToSender = FALSE;

}

function setSender($address, $name='')
{
	if (isValidEmailAddress($address))
	{
		$this->headerOptions['From'] = $name. " <". $address. ">";
		return TRUE;
	}
	return FALSE;
}

function addSubject($subject)
{
	$this->headerOptions['Subject'] = $subject;
}

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
		return TRUE;
	}
	return FALSE;
}

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
		return TRUE;
	}
	return FALSE;
}

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
		return TRUE;
	}
	return FALSE;
}

function setText($text)
{
	$this->text = $text;
}

function addAttachment($file, $name = '', $c_type='application/octet-stream', $encoding = 'base64')
{
	$this->attachments[] = array(
			'body'		=> $file,
            'name'		=> $name,
            'c_type'	=> $c_type,
            'encoding'	=> $encoding);
    $this->headerOptions['Content-Type'] = "multipart/mixed;\n\tboundary=\"$mailBoundary\"";
}

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

	// Wenn keine Empfänger gesetzt wurde, ist hier auch Ende...
	if (!isset($this->headerOptions['To']) and !isset($this->headerOptions['Cc']) and !isset($this->headerOptions['Bcc']))
	{
		return FALSE;
	}

	//Hier werden die HauptMailempfänger gesetzt und aus dem Header genommen...
	$recipient = '';
	if (isset($this->headerOptions['To']))
	{
		$recipient = $this->headerOptions['To'];
		unset($this->headerOptions['To']);
	}

	// Hier wird das MailSubject gesetzt und aus dem Header genommen...
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
		$mail_properties = $mail_properties. $key. ": ". $value. "\r\n";
	}

	// Eigentlichen Mail-Text hinzufügen...
	$mail_body = $this->text;


	// Mail wir jetzt versendet...
	mail($recipient, $subject, $mail_body, $mail_properties);

	// Eventuell noch eine Kopie an den Absender:
	if ($this->copyToSender)
	{
		//TODO!!!!
	}

	//DebugKrempel von Elmar
	echo "$mail_properties";

	//Ende DebugKrempel von Elmar
	return TRUE;
}



} // Ende der Klasse


?>