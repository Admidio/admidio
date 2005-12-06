<?php
/******************************************************************************
 * Verschiedene Funktionen fuer das Profil
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id : ID des Benutzers, der bearbeitet werden soll
 * new_user - 1 : Neuen Benutzer hinzuzufügen.
 * url :     URL auf die danach weitergeleitet wird
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
require("../../system/session_check_login.php");

$user_id       = $_GET['user_id'];
$err_code      = "";
$err_text      = "";

if(!isset($_POST['login']))
   $_POST['login'] = "";

/*------------------------------------------------------------*/
// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
/*------------------------------------------------------------*/
if(!editUser() && $user_id != $g_current_user->id)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}
else
{
   if($_GET['new_user'] == 0)
   {
      // jetzt noch schauen, ob User überhaupt Mitglied in der Gliedgemeinschaft ist
      $sql = "SELECT am_id
                FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
               WHERE ar_ag_shortname = '$g_organization'
                 AND ar_valid        = 1
                 AND am_ar_id        = ar_id
                 AND am_valid        = 1
                 AND am_au_id        = {0}";
      $sql    = prepareSQL($sql, array($user_id));
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      if(mysql_num_rows($result) == 0)
      {
         $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
         header($location);
         exit();
      }
   }
}

// Userdaten aus Datenbank holen
$user = new TblUsers($g_adm_con);
$user->getUser($user_id);

// Feldinhalte saeubern und der User-Klasse zuordnen
$user->last_name  = strStripTags($_POST['name']);
$user->first_name = strStripTags($_POST['vorname']);
$user->login_name = strStripTags($_POST['login']);
$user->address    = strStripTags($_POST['adresse']);
$user->zip_code   = strStripTags($_POST['plz']);
$user->city       = strStripTags($_POST['ort']);
$user->country    = strStripTags($_POST['land']);
$user->phone      = strStripTags($_POST['tel1']);
$user->mobile     = strStripTags($_POST['mobil']);
$user->fax        = strStripTags($_POST['fax']);
$user->birthday   = strStripTags($_POST['geburtstag']);
$user->email      = strStripTags($_POST['mail']);
$user->homepge    = strStripTags($_POST['weburl']);

/*------------------------------------------------------------*/
// Felder prüfen
/*------------------------------------------------------------*/
if(strlen($user->last_name) > 0)
{
   if(strlen($user->first_name) > 0)
   {
      if(strlen($user->email) != 0)
      {
         if(!isValidEmailAddress($user->email))
            $err_code = "email_invalid";
      }

      if($user_id > 0 && $_GET['new_user'] == 0)
      {
         // pruefen, ob dem Benutzer Rollen zugewiesen wurden
         $sql    = "SELECT ar_ag_shortname FROM ". TBL_ROLES. ", ". TBL_MEMBERS. "
                     WHERE ar_valid       = 1
                       AND am_ar_id       = ar_id
                       AND am_au_id       = {0}
                       AND am_valid       = 1 ";
         $sql    = prepareSQL($sql, array($user_id));
         $result = mysql_query($sql, $g_adm_con);
         db_error($result, 1);

         if(mysql_num_rows($result) == 0)
            $err_code = "norolle";
      }

      if(strlen($user->login_name) > 0)
      {
         // pruefen, ob der Benutzername bereits vergeben ist
         $sql = "SELECT au_id FROM ". TBL_USERS. "
                  WHERE au_login = {0} ";
         $sql    = prepareSQL($sql, array($user->login_name));
         $result = mysql_query($sql, $g_adm_con);
         db_error($result, 1);

         if(mysql_num_rows($result) > 0)
         {
            $row = mysql_fetch_array($result);

            if(strcmp($row[0], $user_id) != 0)
               $err_code = "login_name";
         }
      }

      if(strlen($user->birthday) > 0)
      {
         if(!dtCheckDate($user->birthday))
         {
            $err_code = "datum";
            $err_text = "Geburtstag";
         }
      }

      // Feldinhalt der gruppierungsspezifischen Felder pruefen
      $sql = "SELECT auf_name, auf_type
                FROM ". TBL_USER_FIELDS. "
               WHERE auf_ag_shortname  = '$g_organization' ";
      if(!isModerator())
         $sql = $sql. " AND auf_locked = 0 ";
      $sql = prepareSQL($sql, array($row_id));
      $result_msg = mysql_query($sql, $g_adm_con);
      db_error($result_msg);

      while($row = mysql_fetch_object($result_msg))
      {
         // ein neuer Wert vorhanden
         if(strlen($_POST[urlencode($row->auf_name)]) > 0)
         {
            if($row->auf_type == "NUMERIC"
            && !is_numeric($_POST[urlencode($row->auf_name)]))
            {
               $err_code = "field_numeric";
               $err_text = $row->auf_name;
            }
         }
      }
   }
   else
   {
      $err_code = "feld";
      $err_text = "Vorname";
   }
}
else
{
   $err_code = "feld";
   $err_text = "Name";
}

if(strlen($err_code) > 0)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
   header($location);
   exit();
}

// Geburtstag fuer die DB formatieren
if(strlen($user->birthday) > 0)
   $user->birthday = dtFormatDate($user->birthday, "Y-m-d");

/*------------------------------------------------------------*/
// Benutzerdaten in Datenbank schreiben
/*------------------------------------------------------------*/

if($user_id != 0 && $_GET['new_user'] == 0)
	$ret_code = $user->update($g_current_user_id);
else
	$ret_code = $user->insert($g_current_user_id);

if($ret_code != 0)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=&err_text=$ret_code";
   header($location);
   exit();
}

/*------------------------------------------------------------*/
// Messenger-Daten und gruppierungsspezifische Felder anlegen / updaten
/*------------------------------------------------------------*/

$sql = "SELECT auf_id, auf_name, aud_id, aud_value
          FROM ". TBL_USER_FIELDS. " LEFT JOIN ". TBL_USER_DATA. "
            ON aud_auf_id = auf_id
           AND aud_au_id         = {0}
         WHERE (  auf_ag_shortname IS NULL
               OR auf_ag_shortname  = '$g_organization' ) ";
if(!isModerator())
   $sql = $sql. " AND auf_locked = 0 ";
$sql = prepareSQL($sql, array($user->id));
$result_msg = mysql_query($sql, $g_adm_con);
db_error($result_msg);

while($row = mysql_fetch_object($result_msg))
{
   if(is_null($row->aud_value))
   {
      // noch kein Wert vorhanden -> neu einfuegen
      if(strlen(trim($_POST[urlencode($row->auf_name)])) > 0)
      {
         $sql = "INSERT INTO ". TBL_USER_DATA. " (aud_au_id, aud_auf_id, aud_value)
                                    VALUES ({0}, $row->auf_id, '". $_POST[urlencode($row->auf_name)]. "') ";
         $sql = prepareSQL($sql, array($user->id));
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);
      }
   }
   else
   {
      // auch ein neuer Wert vorhanden
      if(strlen(trim($_POST[urlencode($row->auf_name)])) > 0)
      {
         if($_POST[urlencode($row->auf_name)] != $row->aud_value)
         {
            $sql = "UPDATE ". TBL_USER_DATA. " SET aud_value = {0}
                     WHERE aud_id = $row->aud_id ";
            $sql = prepareSQL($sql, array($_POST[urlencode($row->auf_name)]));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
         }
      }
      else
      {
         $sql = "DELETE FROM ". TBL_USER_DATA. "
                  WHERE aud_id = $row->aud_id ";
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);
      }
   }
}

if($_GET['new_user'] == 1 && $user_id > 0)
{
   /*------------------------------------------------------------*/
   // neuer Benutzer wurde ueber Webanmeldung angelegt
   /*------------------------------------------------------------*/
   if($g_forum == 1)
   {
      $sql    = "SELECT * FROM ". TBL_NEW_USER. " WHERE anu_id = {0}";
      $sql    = prepareSQL($sql, array($_GET['anu_id']));
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      if($user_row = mysql_fetch_object($result))
      {
         mysql_select_db($g_forum_db, $g_forum_con);

         // jetzt noch den neuen User ins Forum eintragen
         $sql    = "SELECT MAX(user_id) as anzahl FROM ". $g_forum_praefix. "_users";
         $result = mysql_query($sql, $g_forum_con);
         db_error($result);
         $row    = mysql_fetch_array($result);
         $new_user_id = $row[0] + 1;

         $sql    = "INSERT INTO ". $g_forum_praefix. "_users
                                        (user_id, username, user_password, user_regdate, user_timezone,
                                         user_style, user_lang, user_viewemail, user_attachsig, user_allowhtml,
                                         user_dateformat, user_email, user_notify, user_notify_pm, user_popup_pm,
                                         user_avatar)
                        VALUES ($new_user_id, '$user_row->anu_login', '$user_row->anu_password', ". time(). ", 1.00,
                                2, 'german', 0, 1, 0,
                                'd.m.Y, H:i', '$user_row->anu_mail', 0, 1, 1,
                                '') ";
         $result = mysql_query($sql, $g_forum_con);
         db_error($result);

         mysql_select_db($g_adm_db, $g_adm_con);
      }
   }

   // Login und Passwort eintragen
   $sql = "UPDATE ". TBL_USERS. " SET au_login    = {0}
                             , au_password = {1}
            WHERE au_id = $user->id ";
   $sql    = prepareSQL($sql, array($user->login_name, $_GET['pw']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   // User wurde aus ". TBL_NEW_USER. " angelegt, dieser Satz kann jetzt gelöscht werden
   $sql    = "DELETE FROM ". TBL_NEW_USER. " WHERE anu_id = {0}";
   $sql    = prepareSQL($sql, array($user_id));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
   if($g_current_organization->mail_extern != 1)
   {
      mail($user->email, "Anmeldung auf $g_homepage", "Hallo $user->first_name,\n\ndeine Anmeldung auf $g_homepage ".
           "wurde bestätigt.\n\nNun kannst du dich mit deinem Benutzernamen : $user->login_name\nund dem Passwort auf der Homepage ".
           "einloggen.\n\nSollten noch Fragen bestehen, schreib eine Mail an webmaster@$g_domain .\n\nViele Grüße\nDie Webmaster",
           "From: webmaster@$g_domain");
   }
}

/*------------------------------------------------------------*/
// auf die richtige Seite weiterleiten
/*------------------------------------------------------------*/

if($_GET['new_user'] == 1)
{
   // neuer User -> Rollen zuordnen
   $location = "location: roles.php?user_id=$user->id&new_user=1&url=". $_GET['url'];
   header($location);
   exit();
}

// zur Profilseite zurueckkehren und die URL, von der die Profilseite aufgerufen wurde uebergeben
$load_url = urlencode("$g_root_path/adm_program/modules/profile/profile.php?user_id=$user_id&url=". $_GET['url']);
$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=save&timer=2000&url=$load_url";
header($location);
exit();
?>