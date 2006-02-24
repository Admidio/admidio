<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Rollen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
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
require("../../system/email_class.php");

$err_code = "";
$err_text = "";

// Pruefungen, ob die Seite regulaer aufgerufen wurde

if(array_key_exists("usr_id", $_GET) && !$g_session_valid)
{
  //in ausgeloggtem Zustand duerfen nie direkt usr_ids uebergeben werden...
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid";
   header($location);
   exit();
}

// Falls Attachmentgröße die max_post_size aus der php.ini übertrifft, ist $_POST komplett leer.
// Deswegen muss dies überprüft werden...
if(empty($_POST))
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=attachment_or_invalid";
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

//Erst mal ein neues Emailobjekt erstellen...
$email = new newEmail();


//Nun der Mail die Absenderangaben,den Betreff und das Attachment hinzufuegen...
if(strlen($_POST['name']) > 0)
{
   //Absenderangaben setzen
   if ($email->setSender($_POST['mailfrom'],$_POST['name']))
   {
		//Betreff setzen
		if($email->setSubject($_POST['subject']))
      {
      	//Pruefen ob moeglicher Weise ein Attachment vorliegt
      	if (isset($_FILES['userfile']))
      	{
      		//Prüfen ob ein Fehler beim Upload vorliegt
  		    	if (($_FILES['userfile']['error'] != 0) &&  ($_FILES['userfile']['error'] != 4))
      		{
      			$err_code = "attachment";
     	 		}
     	 		//Wenn ein Attachment vorliegt dieses der Mail hinzufuegen
   	 		if ($_FILES['userfile']['error'] == 0)
   	 		{
   	 			if (strlen($_FILES['userfile']['type']) > 0)
   	 			{
   	 				$email->addAttachment($_FILES['userfile']['tmp_name'], $_FILES['userfile']['name'], $_FILES['userfile']['type']);
   	 			}
   	 			// Falls kein ContentType vom Browser uebertragen wird,
   	 			// setzt die MailKlasse automatisch "application/octet-stream" als FileTyp
   	 			else
   	 			{
   	 				$email->addAttachment($_FILES['userfile']['tmp_name'], $_FILES['userfile']['name']);
   	 			}
   	 		}
      	}
      }
      else
      {
      	$err_code = "feld";
        	$err_text = "Betreff";
      }
   }
   else
   {
   		$err_code = "email_invalid";
   }
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

//Pruefen ob bis hier Fehler aufgetreten sind
if(strlen($err_code) > 0)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
   header($location);
   exit();
}

//Nun die Empfänger zusammensuchen und an das Mailobjekt uebergeben
if(array_key_exists("usr_id", $_GET))
{
   //usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
   $sql    = "SELECT usr_first_name, usr_last_name, usr_email FROM ". TBL_USERS. " WHERE usr_id = {0} ";
   $sql    = prepareSQL($sql, array($_GET['usr_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   $row = mysql_fetch_row($result);

   //den gefundenen User dem Mailobjekt hinzufuegen...
   $email->addRecipient($row[2], "$row[0] $row[1]");
}
else
{
   //Rolle wurde uebergeben, dann an alle Mitglieder aus der DB fischen
   $sql    = "SELECT usr_first_name, usr_last_name, usr_email, rol_name
                FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
               WHERE rol_org_shortname = '$g_organization'
                 AND rol_name          = {0}
                 AND mem_rol_id        = rol_id
                 AND mem_valid         = 1
                 AND mem_usr_id        = usr_id
                 AND usr_valid         = 1
                 AND LENGTH(usr_email) > 0 ";
   $sql    = prepareSQL($sql, array($_POST['rolle']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   while($row = mysql_fetch_object($result))
   {
      $email->addBlindCopy($row->usr_email, "$row->usr_first_name $row->usr_last_name");
      $rolle = $row->rol_name;
   }
}

// Falls eine Kopie benoetigt wird, das entsprechende Flag im Mailobjekt setzen
if($_POST[kopie])
{
	 $email->setCopyToSenderFlag();

	 //Falls der User eingeloggt ist, werden die Empfänger der Mail in der Kopie aufgelistet
	 if($g_session_valid)
	 {
	 	$email->setListRecipientsFlag();
	 }
}

//Den Text fuer die Mail aufbereiten
$mail_body = $mail_body. $_POST['name']. " hat ";
if(strlen($rolle) > 0)
{
	$mail_body = $mail_body. "an die Rolle \"$rolle\"";
}
else
{
	$mail_body = $mail_body. "Dir";
}
$mail_body = $mail_body. " von $g_current_organization->homepage folgende Mail geschickt:\n";
$mail_body = $mail_body. "Eine Antwort kannst Du an ". $_POST['mailfrom']. " schicken.";

if(!$g_session_valid)
{
    $mail_body = $mail_body. "\n(Der Absender war nicht eingeloggt. Deshalb könnten die Absenderangaben fehlerhaft sein.)";
}
$mail_body = $mail_body. "\n\n\n". $_POST['body'];

//Den Text an das Mailobjekt uebergeben
$email->setText($mail_body);


//Nun kann die Mail endgueltig versendet werden...
if ($email->sendEmail())
{
	if(strlen($_POST['rolle']) > 0)
	{
   		$err_text = "die Rolle ". $_POST['rolle'];
	}
	else
	{
		$err_text = $_POST['mailto'];
	}
	$err_code="mail_send";
}
else
{
	if(strlen($_POST['rolle']) > 0)
	{
   		$err_text = "die Rolle ". $_POST['rolle'];
	}
	else
	{
		$err_text = $_POST['mailto'];
	}
	$err_code="mail_not_send";
}


$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text&url=". urlencode($_GET["url"]);
header($location);
exit();

?>