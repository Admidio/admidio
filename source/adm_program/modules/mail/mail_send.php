<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Rollen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Uebergaben:
 *
 * au_id   - E-Mail an den entsprechenden Benutzer schreiben
 * rolle   - E-Mail an alle Mitglieder der Rolle schreiben
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
 
require("../../system/common.php");
require("../../system/session_check.php");

$err_code = "";
$err_text = "";

// Pruefungen, ob die Seite regulaer aufgerufen wurde

if(array_key_exists("au_id", $_GET) && !$g_session_valid)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid";
   header($location);
   exit();
}

// Falls Attachmentgröße die max_post_size aus der php.ini übertrifft, ist $_POST komplett leer.
// Deswegen muss dies überprüft werden...
if(empty($_POST))
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=attachment";
   header($location);
   exit();
}

if($g_orga_property['ag_mail_extern'] == 1)
{
	// es duerfen oder koennen keine Mails ueber den Server verschickt werden
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=mail_extern";
   header($location);
   exit();
}

$_POST['mailfrom'] = trim($_POST['mailfrom']);
$_POST['name']     = trim($_POST['name']);
$_POST['subject']  = trim($_POST['subject']);

if(strlen($_POST['name']) > 0)
{
   if(isValidEmailAddress($_POST["mailfrom"]))
   {
      if(strlen($_POST['subject']) == 0)
      {
         $err_code = "feld";
         $err_text = "Betreff";
      }
      else
      {
      	 if (($_FILES['userfile']['error'] != 0) &&  ($_FILES['userfile']['error'] != 4))
      	 {
      	 	$err_code = "attachment";
     	 }
      }
   }
   else
      $err_code = "email_invalid";
}
else
{
   $err_code = "feld";
   $err_text = "Name";
}

if(array_key_exists("rolle", $_POST) && strlen($err_code) == 0)
{

   if (strlen($_POST['rolle']) == 0)
   {
   		$err_code = "mail_rolle";
   }
   else
   {
   		if($g_session_valid)
   		{
      		$sql    = "SELECT ar_r_mail_login FROM ". TBL_ROLES. "
           		       WHERE ar_ag_shortname    = '$g_organization'
               		     AND UPPER(ar_funktion) = UPPER({0}) ";
   		}
   		else
   		{
      		$sql    = "SELECT ar_r_mail_logout FROM ". TBL_ROLES. "
           		       WHERE ar_ag_shortname    = '$g_organization'
               		     AND UPPER(ar_funktion) = UPPER({0}) ";
   		}
   		$sql    = prepareSQL($sql, array($_POST['rolle']));
   		$result = mysql_query($sql, $g_adm_con);
   		db_error($result);
   		$row = mysql_fetch_array($result);

   		if($row[0] != 1)
   		{
      		$err_code = "invalid";
   		}
   }
}

if(strlen($err_code) > 0)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
   header($location);
   exit();
}

// Funktion versendet die Mail an die uebergebene E-Mail-Adresse

function sendMail($email, $rolle = "")
{
   global $g_orga_property;
   global $g_session_valid;

   $mail_body = "";

   //Zufaelliges Trennzeichen zwischen MessageParts generieren
   $mailBoundary = "--NextPart_AdmidioMailSystem_". md5(uniqid(rand()));


   // E-Mail-Header zusammenbauen
   $mail_properties = "From: ". $_POST['name']. " <". $_POST['mailfrom']. ">\n";
   $mail_properties = $mail_properties. "MIME-Version: 1.0\n";


   // Im Falle eines Attachments Header und Body um Zusaetze ergaenzen
   if ($_FILES['userfile']['error'] != 4)
   {
      $mail_properties = $mail_properties. "Content-Type: multipart/mixed;\n\tboundary=\"$mailBoundary\"\n";

 	  // E-Mail-Body fuer Attachment vorbereiten
      $mail_body	= "This message is in MIME format.\n";
      $mail_body	= $mail_body. "Since your mail reader does not understand this format,\n";
      $mail_body	= $mail_body. "some or all of this message may not be legible.\n\n";

      $mail_body = $mail_body. "--". $mailBoundary. "\nContent-Type: text/plain; charset=\"iso-8859-1\"\n\n";
   }

   // Eigentliche Nachricht an den Body anfuegen
   $mail_body		= $mail_body. $_POST['name']. " hat ";
   if(strlen($rolle) > 0)
      $mail_body = $mail_body. "an die Rolle \"$rolle\"";
   else
      $mail_body = $mail_body. "Dir";

   $mail_body = "$mail_body von ". $g_orga_property['ag_homepage']. " folgende Mail geschickt:\nEine Antwort kannst Du an ".
                      $_POST['mailfrom']. " schicken.";

   if(!$g_session_valid)
   {
      $mail_body = $mail_body. "\n(Der Absender war nicht eingeloggt. Deshalb könnten die Absenderangaben fehlerhaft sein.)";
   }

   $mail_body = $mail_body. "\n\n\n". $_POST['body']. "\n\n";


   // Eventuell noch ein Attachment an die Mail haengen
   if ($_FILES['userfile']['error'] != 4)
   {
      $mail_body = $mail_body. "--". $mailBoundary. "\nContent-Type: ". $_FILES['userfile']['type']. ";\n";
      $mail_body = $mail_body. "\tname=\"". $_FILES['userfile']['name']. "\"\nContent-Transfer-Encoding: base64\n";
      $mail_body = $mail_body. "Content-Disposition: attachment;\n\tfilename=\"". $_FILES['userfile']['name']. "\"\n\n";
	  $theFile = fopen($_FILES['userfile']['tmp_name'], "rb");
	  $fileContent = fread($theFile, $_FILES['userfile']['size']);

	  // Attachment encodieren und splitten
	  $fileContent = chunk_split(base64_encode($fileContent));

	  $mail_body = $mail_body. $fileContent. "\n\n";

	  // Das Ende der Mail mit der Boundary kennzeichnen...
	  $mail_body = $mail_body. "--". $mailBoundary. "--";
   }

	return mail($email, $_POST['subject'], $mail_body, $mail_properties);
}

if(array_key_exists("au_id", $_GET))
{
   $mail_receivers = "";

   // au_id wurde uebergeben, dann E-Mail direkt an den User schreiben
   $sql    = "SELECT au_mail FROM ". TBL_USERS. " WHERE au_id = {0} ";
   $sql    = prepareSQL($sql, array($_GET['au_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   $mail_receivers = "- \"$row->au_mail\" ";
   $row = mysql_fetch_array($result);
   if(sendMail($row[0]))
   {
   	 $mail_receivers = "- \"$row[0]\" ";
   }
}
else
{
   $mail_receivers = "";

   // Rolle wurde uebergeben, dann an alle Mitglieder eine Mail schreiben
   $sql    = "SELECT au_vorname, au_name, au_mail, ar_funktion
                FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
               WHERE ar_ag_shortname = '$g_organization'
                 AND ar_funktion     = {0}
                 AND am_ar_id        = ar_id
                 AND am_valid        = 1
                 AND am_au_id        = au_id
                 AND LENGTH(au_mail) > 0 ";
   $sql    = prepareSQL($sql, array($_POST['rolle']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   while($row = mysql_fetch_object($result))
   {
      if(sendMail($row->au_mail, $row->ar_funktion))
      {
         $mail_receivers = $mail_receivers. "- \"$row->au_vorname $row->au_name $row->au_mail\" ";
      }
   }
}

// jetzt noch eventuell eine Kopie der Mail an den Versender schicken...
if($_POST[kopie])
{
	 $_POST['body'] = "Hier ist Deine angeforderte Kopie der Nachricht:\n\n". $_POST['body'];
	 $_POST['body'] = $mail_receivers. "\n\n". $_POST['body'];
	 $_POST['body'] = "Die Nachricht ging an:\n". $_POST['body'];
	 sendMail($_POST['mailfrom']);
}

if(strlen($_POST['rolle']) > 0)
   $err_text = $_POST['rolle'];
else
   $err_text = $_POST['mailto'];

$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=mail_send&err_text=$err_text&url=". urlencode($_GET["url"]);
header($location);
exit();

?>