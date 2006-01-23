<?php
/******************************************************************************
 * Registrieren - Funktionen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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

require("common.php");
require("session_check.php");

$err_code   = "";
$err_text   = "";
$count_user = 0;

$_POST['benutzername'] = strStripTags($_POST['benutzername']);
$_POST['nachname']     = strStripTags($_POST['nachname']);
$_POST['vorname']      = strStripTags($_POST['vorname']);
$_POST['email']        = strStripTags($_POST['email']);

// Felder prüfen
if ($_POST['passwort'] != $_POST['passwort2'])
   $err_code = "passwort";

if(strlen($err_code) == 0)
{
   if(!isValidEmailAddress($_POST['email']))
      $err_code = "email_invalid";
}
 
if(strlen($err_code) == 0)
{
   if(strlen($_POST["benutzername"]) == 0)
      $err_text = "Benutzername";

   if(strlen($_POST["passwort"]) == 0)
      $err_text = "Passwort";

   if(strlen($_POST["nachname"]) == 0)
      $err_text = "Nachname";

   if(strlen($_POST["vorname"]) == 0)
      $err_text = "Vorname";
      
   if(strlen($err_text) != 0)
      $err_code = "feld";
}

if(strlen($err_code) != 0)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
   header($location);
   exit();
}


// pruefen, ob der Username bereits vergeben ist
$sql    = "SELECT usr_id FROM ". TBL_USERS. " ".
          " WHERE usr_login_name LIKE {0}";
$sql    = prepareSQL($sql, array($_POST["benutzername"]));
$result = mysql_query($sql, $g_adm_con);
db_error($result);      

$count_user = mysql_num_rows($result);

if ($count_user == 0 && $g_forum == 1)
{
   mysql_select_db($g_forum_db, $g_forum_con);
   
   // auch im Forum pruefen
   $sql    = "SELECT user_id FROM ". $g_forum_praefix. "_users ".
             " WHERE username LIKE {0}";
   $sql    = prepareSQL($sql, array($_POST["benutzername"]));
   $result = mysql_query($sql, $g_forum_con);
   db_error($result);      

   $count_user = mysql_num_rows($result);
   mysql_select_db($g_adm_db, $g_adm_con);
}

if ($count_user == 0)
{
   $password_crypt = md5($_POST["passwort"]);

   $sql    = "INSERT INTO ". TBL_USERS. " (usr_reg_org_shortname, usr_last_name, usr_first_name, usr_email, usr_login_name, usr_password, usr_valid) ".
             "                     VALUES ('$g_organization', {0}, {1}, {2}, {3}, '$password_crypt', 0)";
   $sql    = prepareSQL($sql, array($_POST["nachname"], $_POST["vorname"], $_POST["email"], $_POST["benutzername"]));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   // E-Mail an alle Webmaster schreiben
   $sql    = "SELECT usr_email
                FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
               WHERE rol_org_shortname = '$g_organization'
                 AND rol_name          = 'Webmaster'
                 AND mem_rol_id        = rol_id
                 AND mem_valid         = 1
                 AND mem_usr_id        = usr_id
                 AND usr_valid         = 1
                 AND LENGTH(usr_email) > 0 ";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   while($row = mysql_fetch_object($result))
   {
		// nur ausfuehren, wenn E-Mails auch unterstuetzt werden
		if($g_current_organization->mail_extern != 1)
      {
         mail("$row->usr_email", "Anmeldung", "Es hat sich ein neuer User auf ".
              "$g_current_organization->homepage angemeldet\n\nNachname: ". $_POST["nachname"]. "\nVorname:  ". $_POST["vorname"]. "\n".
              "E-Mail:   ". $_POST["email"]. "\n\n\nDiese Nachricht wurde automatisch erzeugt.",
              "From: webmaster@$g_domain");
      }
   }

   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=anmeldung&url=home&timer=";
   header($location);
   exit();
}
else
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=login_name";
   header($location);
   exit();
}
?>