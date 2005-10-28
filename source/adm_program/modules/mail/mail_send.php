<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Rollen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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
require("../../../adm_config/config.php");
require("../../system/function.php");
require("../../system/date.php");
require("../../system/string.php");
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
   }
   else
      $err_code = "email";
}
else
{
   $err_code = "feld";
   $err_text = "Name";
}

if(array_key_exists("rolle", $_POST) && strlen($err_code) == 0)
{
   if($g_session_valid)
   {
      $sql    = "SELECT ar_r_mail_login FROM adm_rolle
                  WHERE ar_ag_shortname    = '$g_organization'
                    AND UPPER(ar_funktion) = UPPER({0}) ";
   }
   else
   {
      $sql    = "SELECT ar_r_mail_logout FROM adm_rolle
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

if(strlen($err_code) > 0)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
   header($location);
   exit();
}

// Funktion versendet die Mail an die uebergebene E-Mail-Adresse

function sendMail($email, $rolle = "")
{
   global $g_internet;
   global $g_homepage;
   global $g_session_valid;

   // E-Mail-Text zusammenbauen
   $mail_properties = "From: \"". $_POST['name']. "\" <". $_POST['mailfrom']. ">";
   $mail_body       = $_POST['name']. " hat ";
   if(strlen($rolle) > 0)
      $mail_body = $mail_body. "an die Rolle \"$rolle\"";
   else
      $mail_body = $mail_body. "dir";
      
   $mail_body = $mail_body. " von $g_homepage folgende Mail geschickt:\nEine Antwort kannst du an ".
                      $_POST['mailfrom']. " schicken.";

   if(!$g_session_valid)
   {
      $mail_body = $mail_body. "\n(Der Absender war nicht eingeloggt. Deshalb können die Absenderangaben fehlerhaft sein.)";
   }

   $mail_body = $mail_body. "\n\n\n". $_POST['body'];

   // Versenden nur im Internet ausfuehren
   if($g_internet == 1)
   {
      return mail($email, $_POST['subject'], $mail_body, $mail_properties);
   }
   else
      return true;
}

if(array_key_exists("au_id", $_GET))
{
   // au_id wurde uebergeben, dann E-Mail direkt an den User schreiben
   $sql    = "SELECT au_mail FROM adm_user WHERE au_id = {0} ";
   $sql    = prepareSQL($sql, array($_GET['au_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $row = mysql_fetch_array($result);
   sendMail($row[0]);
}
else
{
   $mail_receivers = "";

   // Rolle wurde uebergeben, dann an alle Mitglieder eine Mail schreiben
   $sql    = "SELECT au_vorname, au_name, au_mail, ar_funktion
                FROM adm_rolle, adm_mitglieder, adm_user
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
   
   // jetzt noch eine Mail an den Versender schicken mit allen Empfängern und dem Text
   
   // E-Mail-Text zusammenbauen
   $mail_properties = "From: \"". $_POST['name']. "\" <". $_POST['mailfrom']. ">";
   $mail_body       = "Du hast von $g_homepage folgende Mail an\n$mail_receivers\ngeschickt:\n\n\n". $_POST['body'];
   $mail_subject    = "Kopie: ". $_POST['subject'];
                      
   if($g_internet == 1)
   {
      If ($_POST[kopie]) mail($_POST['mailfrom'], $mail_subject, $mail_body, $mail_properties);
   }
   else
      echo $mail_body;
}

if(strlen($_POST['rolle']) > 0)
   $err_text = $_POST['rolle'];
else
   $err_text = $_POST['mailto'];

$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=mailSend&err_text=$err_text&url=". urlencode($_GET["url"]);
header($location);
exit();

?>