<?php
/******************************************************************************
 * Grußkarte Mail Klasse
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Aufruf:
 *
 *	$HMTLMail = new MailClass(< An >,< Betreff >);
 *	$HMTLMail->setFromName(< Name Sender >);
 *	$HMTLMail->setFromAddr(< Email Sender >);
 *  $HMTLMail->addBlindCarbonCopy(< BCC Email Adresse>);
 *	$HMTLMail->addCarbonCopy(< CC Email Adresse>);
 *	$HMTLMail->setReturnPath(< Return Email Adresse>);
 *	$HMTLMail->setEnvelopeTo(< Envelope Email Adresse>);
 *	$HMTLMail->addTextPlainBodyPart(< Text >);
 * 	$HMTLMail->addHTMLBodyPart(< HTML >);
 *	$HMTLMail->attachFile(< Fileaddresse >,< File Typ >,< Art des Anhangs >,< File Beschreibung >,< Content Id >,< File Name >);
 *	$HMTLMail->BuildAndSendMessage(); oder $HMTLMail->BuildAndSendMessageTextPlain() 
 *	
 *****************************************************************************/
 
class MailClass {

	var $to			= ""; // Empfänger Addresse
	var $from_name	= ""; // Sender Name
	var $from_addr	= ""; // Sender Addresse
	var $subject	= ""; // mail subject
	var $bodyParts	=	array("text/plain"=>"","text/html"=>""); // Mail Body Bereich (entweder plain text oder html)
	var $parts		=	array(); // Array  für die Anhänge
	var $headers	=	array(
		"Return-path:"=>"",
		"Envelope-to:"=>"",
		"From:"=>"",
		"MIME-Version:"=>"1.0",
		"Content-Type:"=>"multipart/mixed;\n\tboundary=\"----=_NextPart_000_0001_00000001.00000001\"",
		"X-priority:"=>"3",
		"X-MSMail-Priority:"=>"Normal",
		"X-Mailer:"=>"clsImap Mail Handler 2.0"
	); // Array für den Kopf der Mail
	
	// Dies Funktion ist der Klassen constructor
	function MailClass($to, $subject) 
	{
		if(empty($this->to))
		{
			$this->to			=	$to;
		}
		if(empty($this->subject))
		{
			$this->subject		=	$subject;
		}
	}
			
	// Diese Funktion fügt eine CC Addresse zur Mail hinzu (mehrere Möglich .. sie werden automatisch mit einem "," getrennt)
	function addCarbonCopy($cc_address) 
	{
		if(empty($this->headers["CC:"]))
		{
			$this->headers["CC:"]	= $cc_address;
		}
		else
		{
			$this->headers["CC:"]	.= ",".$cc_address;
		}
	}
		
	// Diese Funktion fügt eine BCC Addresse zur Mail hinzu (mehrere Möglich .. sie werden automatisch mit einem "," getrennt)
	function addBlindCarbonCopy($bcc_address) 
	{
		if(empty($this->headers["BCC:"]))
		{
			$this->headers["BCC:"]	= $cc_address;
		}
		else
		{
			$this->headers["BCC:"]	.= ",".$cc_address;
		}
	}
	
	//Diese Funktion setzt die Return Addresse
	function setReturnPath($rp) 
	{
		if(empty($this->headers["Return-Path:"]))
		{
			$this->headers["Return-Path:"]	=	"<".$rp.">";
		}
	}
	
	
	// Diese Funktion setzt die Envelope to Addresse
	function setEnvelopeTo($et) 
	{
		if(empty($this->headers["Envelope-to:"]))
		{
			$this->headers["Envelope-to:"]	=	$et;
		}
	}
	
	// Diese Funktion setzt den Inhalt Typ der Mail (z.B.: HTML oder plain)
	function setContentType($ct) 
	{
		if(empty($this->headers["Content-Type:"]))
		{	
			$this->headers["Content-Type:"]	= "$ct;\n\tboundary=\"----=_NextPart_000_0001_00000001.00000001\"";
		}
	}
	
	// Diese Funktion setzt den Von Namen für den Kopf der Mail
	function setFromName($fromName) 
	{
		if(empty($this->from_name))
		{
			$this->from_name	=	$fromName;
		}
		else
		{
			$this->from_name	=	$fromName;
		}
	}

	// Diese Funktion setzt die Von Addresse für den Kopf der Mail
	function setFromAddr($fromAddr) 
	{
		if(empty($this->from_addr))
		{
			$this->from_addr	=	$fromAddr;
		}
		else
		{
			$this->from_addr	=	$fromAddr;
		}
	}

	// Diese Funktion fügt ein File zur Mail hinzu
	// Übergaben:
	//			$fullname			.. vollständige Addresse des Files
	//			$ftype				.. type des anzuhängenden Files
	//			$fdispos			.. angabe der Art wie der Anhang zu handhaben ist (z.B.: inline oder attachment)
	//			$fdescrip			.. Beschreibung des Anhangs	(optional)
	//			$fid				.. File Id 					(optional)
	//			$attach_file_name	.. Name des Anhangs 		(optional)
	function attachFile($fullname,$ftype,$fdispos = "",$fdescrip = "",$fid = "",$attach_file_name = "") 
	{
		if ($attach_file_name == "")
		{
			$fname	=	basename($fullname);
		} 
		else 
		{
			$fname	= $attach_file_name;
		}
		$thePart	= "Content-Type: $ftype;\n\tname=\"$fname\"\n";
		$thePart	.= "Content-Transfer-Encoding: base64\n";
		if (!empty($fdispos)) $thePart	.= "Content-Disposition: $fdispos;\n\tfilename=\"$fname\"\n";
		if (!empty($fdescrip)) $thePart	.= "Content-Description: $fdescrip\n";
		if (!empty($fid)) $thePart		.= "Content-Id: <$fid>\n";
		$thePart	.= "\n";
		
		// File öffnen und base64 konvertieren
		$return = "";
		$data	= "";
		if ($fp = fopen($fullname, 'rb')) 
		{
			while (!feof($fp)) 
			{
				$return .= fread($fp, 1024);
			}
			fclose($fp);
			$data = base64_encode($return);

		} 
		else 
		{
			echo "Anhang \"".$fullname."\" konnte nicht angef&uuml;gt werden!<br />";
		}

		if (function_exists("chunk_split"))
		{
		   $thePart	.= chunk_split($data,76,"\n");
		}
		else 
		{
			$theData	= $data;
			while (strlen($theData)>76) 
			{
				$thePart.=substr($theData,0,76)."\n";
				$theData=substr($theData,76);
			}
			$thePart	.= $theData."\n";
		}
		$this->parts[]	= $thePart;
	}
	
	// Diese Funktion fügt ein base64 enkodierten String als Anhang hinzu
	//			$data				.. base64 enkodierter String
	//			$dname				.. name des base64 enkodierten Strings
	//			$dtype				.. type des anzuhängenden base64 enkodierten Strings
	//			$ddispos			.. angabe der Art wie der Anhang zu handhaben ist (z.B.: inline oder attachment)
	function attachData($data,$dname,$dtype,$ddispos) 
	{
	
		$thePart="Content-Type: $dtype\n\tname=\"$dname\"\nContent-Transfer-Encoding: base64\nContent-Disposition: $ddispos;\n\tfilename=\"$dname\"\n\n";
		
		if (function_exists("chunk_split"))
		{
		   $thePart	.= chunk_split($data,76,"\n");
		}
		else 
		{
			$theData = $data;
			while (strlen($theData)>76) 
			{
				$thePart.=substr($theData,0,76)."\n";
				$theData=substr($theData,76);
			}
			$thePart	.= $theData."\n";
		}
		$thePart		.= $theData."\n";
		$this->parts[]	.= $thePart;
	}
	
	// Diese Funktion fügt einen HTML string zum Body Bereich der Mail hinzu 
	function addHtmlBodyPart($html) 
	{
		if(isset($this->bodyParts["text/html"]))
		{
			$this->bodyParts["text/html"]	.= imap_8bit($html);
		}
		else
		{
			$this->bodyParts["text/html"]	= imap_8bit($html);
		}
	}
	
	// Diese Funktion fügt einen Plain Text zum Body Bereich hinzu
	function addTextPlainBodyPart($text) 
	{
		if(isset($this->bodyParts["text/plain"]))
		{
			$this->bodyParts["text/plain"]	.= $text;
		}
		else
		{
			$this->bodyParts["text/plain"]	= $text;
		}
	}
	
	// Diese Funktion erstellt und sendet die Mail
	function BuildAndSendMessage() 
	{
		$theMessage	= "\n------=_NextPart_000_0001_00000001.00000001\nContent-type: multipart/alternative;\n\tboundary=\"----=_NextPart_000_0001_00000001.00000011\"";
		$theMessage	.= "\n\n\n------=_NextPart_000_0001_00000001.00000011\nContent-type: text/plain;\n\tcharset=\"iso-8859-1\"\nContent-Transfer-Encoding: quoted-printable\n\n".$this->bodyParts["text/plain"]."\n\n";
		$theMessage	.= "------=_NextPart_000_0001_00000001.00000011\nContent-type: text/html;\n\tcharset=\"iso-8859-1\"\nContent-Transfer-Encoding: quoted-printable\n\n".$this->bodyParts["text/html"]."\n\n";
		$theMessage	.= "------=_NextPart_000_0001_00000001.00000011--\n";
		
		for ($i=0; $i < count($this->parts); $i++) 
		{
			$theMessage	.= "\n------=_NextPart_000_0001_00000001.00000001\n";
			$theMessage	.= $this->parts[$i];
		}
		$theMessage.="\n------=_NextPart_000_0001_00000001.00000001--\n";
	
		if (empty($this->headers["Return-Path:"]))
		{
			$this->headers["Return-Path:"]	= "<".$this->from_addr.">";
		}
		if (empty($this->headers["Envelope-To:"]))
		{
			$this->headers["Envelope-To:"]	= $this->to;
		}
		if (!empty($this->from_name) && !empty($this->from_addr))
		{
			$this->headers["From:"]	= "\"".$this->from_name."\" <".$this->from_addr.">";
		}
		else if (!empty($this->from_addr))
		{
			$this->headers["From:"]	= $this->from_addr;
		}
		else
		{
			return "No sender specified";
		}
		$theHeaders	= "";
		reset($this->headers);
		while (list($k,$v) = each($this->headers)) 
		{
			$theHeaders	.= "$k $v\n";	
		}
		mail($this->to,$this->subject,$theMessage,chop($theHeaders));
	}
	// Diese Funktion erstellt und sendet eine plain Text Mail
	function BuildAndSendMessageTextPlain() 
	{
	
		$theMessage	= $this->bodyParts["text/plain"];

		$this->headers["Content-Type:"]	= "text/plain; charset=\"iso-8859-1\"\nContent-Transfer-Encoding: 7bit";
		if (empty($this->headers["Return-Path:"]))
		{
			$this->headers["Return-Path:"]	= "<".$this->from_addr.">";
		}
		if (empty($this->headers["Envelope-To:"]))
		{
			$this->headers["Envelope-To:"]	= $this->to;
		}
		if (!empty($this->from_name) && !empty($this->from_addr))
		{
			$this->headers["From:"]	= "\"".$this->from_name."\" <".$this->from_addr.">";
		}
		else if (!empty($this->from_addr))
		{
			$this->headers["From:"]	= $this->from_addr;
		}
		else
		{
			return "No sender specified";
		}
	
		$theHeaders	= "";
		reset($this->headers);
		while (list($k,$v) = each($this->headers)) 
		{
			$theHeaders	.= "$k $v\n";	
		}
		mail($this->to,$this->subject,$theMessage,chop($theHeaders));
	}
}
?>