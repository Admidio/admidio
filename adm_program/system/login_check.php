<?php
/******************************************************************************
 * Meldet den User bei Admidio an, wenn sich dieser einloggen darf
 * Cookies setzen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * url: Seite, die nach erfolgreichem Login aufgerufen wird
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

if(!array_key_exists("url", $_GET)
|| strlen($_GET['url']) == 0)
   $_GET['url'] = "home";

$_POST['loginname'] = trim($_POST['loginname']);

if(strlen($_POST['loginname']) == 0)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=feld&err_text=Benutzername";
   header($location);
   exit();
}

$user_found = 0;
$password_crypt = md5($_POST["passwort"]);

// Name und Passwort pruefen
// Rolle muss mind. Mitglied sein

$sql    = "SELECT *
             FROM ". TBL_USERS. ", ". TBL_MEMBERS. ", ". TBL_ROLES. "
            WHERE au_login     LIKE {0}
              AND am_au_id        = au_id
              AND am_ar_id        = ar_id
              AND am_valid        = 1
              AND ar_ag_shortname = '$g_organization'
              AND ar_valid        = 1 ";
$sql    = prepareSQL($sql, array($_POST["loginname"]));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$user_found = mysql_num_rows($result);
$user_row   = mysql_fetch_object($result);

if ($user_found >= 1)
{
   if(strlen($user_row->au_invalid_login) > 0)
   {
      // wenn innerhalb 15 min. 3 falsche Logins stattfanden -> Konto 15 min. sperren
      if(mktime() - mysqlmaketimestamp($user_row->au_invalid_login) < 900
      && $user_row->au_num_invalid >= 3 )
      {
         $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=login_failed";
         header($location);
         exit();
      }
   }

   if($user_row->au_password == $password_crypt)
   {
      // alte Sessions des Users loeschen

      $sql    = "DELETE FROM ". TBL_SESSIONS. " ".
                " WHERE as_au_id        LIKE '$user_row->au_id' ".
                "   AND as_ag_shortname LIKE '$g_organization'  ";
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      // Session-ID erzeugen

      $login_timestamp  = time();
      $login_datetime   = date("Y.m.d H:i:s", $login_timestamp);
      $login_timestamp += ip2long($REMOTE_ADDR);
      $user_session     = md5($login_timestamp);

      // darf der User laenger eingeloggt sein

      if(array_key_exists("long_login", $_POST))
         $long_login = 1;
      else
         $long_login = 0;

      // Session-ID speichern

      $sql = "INSERT INTO ". TBL_SESSIONS. " (as_au_id, as_session, as_datetime, as_long_login, as_ag_shortname) ".
             "VALUES ('$user_row->au_id', '$user_session', '$login_datetime', $long_login, '$g_organization') ";
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      // Cookies fuer die Anmeldung setzen
      if($_SERVER['HTTP_HOST'] == 'localhost')
      {
         // beim localhost darf keine Domaine uebergeben werden
         setcookie("". TBL_SESSIONS. "", "$user_session", 0, "/");
      }
      else
      {
         setcookie("". TBL_SESSIONS. "", "$user_session" , 0, "/", ".". $g_domain);
      }

      // Last-Login speichern

      $sql = "UPDATE ". TBL_USERS. " SET au_last_login = au_act_login
               WHERE au_id = $user_row->au_id";
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      // Logins zaehlen und aktuelles Login-Datum speichern

      $act_date = date("Y-m-d H:i:s", time());
      $sql = "UPDATE ". TBL_USERS. " SET au_num_login     = au_num_login + 1
                                 , au_act_login     = '$act_date'
                                 , au_invalid_login = NULL
                                 , au_num_invalid   = 0
               WHERE au_id = $user_row->au_id";
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      // Eingeloggt, weiter zur Main - Seite

      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=login&url=". urlencode($_GET['url']). "&timer=2000&status_refresh=1";
      header($location);
      exit();
   }
   else
   {
      // ungültige Logins werden mitgeloggt
      $sql    = "UPDATE ". TBL_USERS. " SET au_invalid_login = NOW()
                                    , au_num_invalid   = au_num_invalid + 1
                  WHERE au_id = $user_row->au_id ";
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=password_unknown";
      header($location);
      exit();
   }
}
else
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=login_unknown";
   header($location);
   exit();
}

?>