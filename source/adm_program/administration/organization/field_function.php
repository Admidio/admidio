<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Profilfelder
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * auf_id: ID des Feldes
 * mode:   1 - Feld anlegen oder updaten
 *         2 - Feld loeschen
 * url :   URL von der die aufrufende Seite aufgerufen wurde
 *         (muss uebergeben werden, damit der Zurueck-Button funktioniert)
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
require("../../system/session_check_login.php");

// nur Moderatoren duerfen Felder erfassen & verwalten
if(!isModerator())
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

$err_code = "";
$err_text = "";

if($_GET['mode'] == 1)
{
   // Feld anlegen oder updaten

   if(strlen(trim($_POST['name'])) > 0
   && strlen(trim($_POST['typ'])) > 0)
   {
      if(!($_GET['auf_id'] > 0))
      {
         // Schauen, ob die Rolle bereits existiert
         $sql    = "SELECT COUNT(*) FROM ". TBL_USER_FIELDS. "
                     WHERE auf_ag_shortname LIKE '$g_organization'
                       AND auf_name         LIKE {0}";
         $sql    = prepareSQL($sql, array($_POST['name']));
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);
         $row = mysql_fetch_array($result);
      
         if($row[0] > 0)
         {
            $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=roleexist";
            header($location);
            exit();
         }      
      }

      if(array_key_exists("locked", $_POST))
         $locked = 1;
      else
         $locked = 0;

      if($_GET['auf_id'] > 0)
      {
         $sql = "UPDATE ". TBL_USER_FIELDS. "
                    SET auf_name        = {0}
                      , auf_description = {1}
                      , auf_type        = {2}
                      , auf_locked      = $locked
                  WHERE auf_id = {3}";
      }
      else
      {
         // Feld in Datenbank hinzufuegen
         $sql    = "INSERT INTO ". TBL_USER_FIELDS. " (auf_ag_shortname, auf_name, auf_description,
                                                auf_type, auf_locked)
                    VALUES ('$g_organization', {0}, {1}, {2}, $locked) ";
      }
      $sql    = prepareSQL($sql, array(trim($_POST['name']), trim($_POST['description']),
                                       trim($_POST['typ']), $_GET['auf_id']));
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);
   }
   else
   {
      // es sind nicht alle Felder gefuellt
      if(strlen(trim($_POST['name'])) == 0)
         $err_text = "Name";
      else
         $err_text = "Datentyp";
      $err_code = "feld";
   }
   
   if(strlen($err_code) > 0)
   {
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
      header($location);
      exit();
   }

   $err_code = "save";
}
elseif($_GET['mode'] == 2)
{
   // Feld loeschen

   // erst die Userdaten zum Feld loeschen
   $sql    = "DELETE FROM ". TBL_USER_DATA. "
               WHERE aud_auf_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['auf_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $sql    = "DELETE FROM ". TBL_USER_FIELDS. "
               WHERE auf_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['auf_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $err_code = "delete";
}
         
// zur Gruppierungsseite zurueck
$load_url = urlencode("$g_root_path/adm_program/administration/organization/organization.php?url=". $_GET['url']);
$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&timer=2000&url=$load_url";
header($location);
exit();
?>