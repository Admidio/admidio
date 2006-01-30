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

// Email - Klasse
class newEmail
{



//Konstruktor
function newEmail()
{
	$this->headerOptions = array();
	$this->is_built    = false;

	// Wichtig ist es als erstes die MIME-Version zu setzen
	$this->headers['MIME-Version'] = '1.0';
	$this->mailBoundary = "--NextPart_AdmidioMailSystem_". md5(uniqid(rand()));
}

function addSender($sender)
{
	$this->headerOptions['From'] = $sender;
}

function addSubject($subject)
{
	$this->headerOptions['Subject'] = $subject;
}

function addReceiver($receiver)
{
	$this->headerOptions['Cc'] = $receiver;
}

function addCc($cc)
{
	$this->headerOptions['Cc'] = $cc;
}

function addBcc($bcc)
{
	$this->headerOptions['Bcc'] = $bcc;
}

function setText($text = '')
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
}

function setCopyToSenderFlag()
{
	$this->copyToSender = 'TRUE';
}

function buildEmail()
{

}

function sendEmail()
{

}

} // Ende der Klasse

?>