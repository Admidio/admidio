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
 * usr_id   - E-Mail an den entsprechenden Benutzer schreiben
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

if(array_key_exists("usr_id", $_GET) && !$g_session_valid)
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

if($g_current_organization->mail_extern == 1)
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
      		$sql    = "SELECT rol_mail_login FROM ". TBL_ROLES. "
           		       WHERE rol_org_shortname    = '$g_organization'
               		     AND UPPER(rol_name) = UPPER({0}) ";
   		}
   		else
   		{
      		$sql    = "SELECT rol_mail_logout FROM ". TBL_ROLES. "
           		       WHERE rol_org_shortname    = '$g_organization'
               		     AND UPPER(rol_name) = UPPER({0}) ";
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

   $mail_body = "$mail_body von $g_current_organization->homepage folgende Mail geschickt:\nEine Antwort kannst Du an ".
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

if(array_key_exists("usr_id", $_GET))
{
   $mail_receivers = "";

   // usr_id wurde uebergeben, dann E-Mail direkt an den User schreiben
   $sql    = "SELECT usr_email FROM ". TBL_USERS. " WHERE usr_id = {0} ";
   $sql    = prepareSQL($sql, array($_GET['usr_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   $mail_receivers = "- \"$row->usr_email\" ";
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
   $sql    = "SELECT usr_first_name, usr_last_name, usr_email, rol_name
                FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
               WHERE rol_org_shortname = '$g_organization'
                 AND rol_name     = {0}
                 AND mem_rol_id        = rol_id
                 AND mem_valid        = 1
                 AND mem_usr_id        = usr_id
                 AND LENGTH(usr_email) > 0 ";
   $sql    = prepareSQL($sql, array($_POST['rolle']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   while($row = mysql_fetch_object($result))
   {
      if(sendMail($row->usr_email, $row->rol_name))
      {
         $mail_receivers = $mail_receivers. "- \"$row->usr_first_name $row->usr_last_name $row->usr_email\" ";
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