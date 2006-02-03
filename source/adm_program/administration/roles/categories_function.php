<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Rollen-Kategorien
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * rlc_id: ID der Rollen-Kategorien
 * mode:   1 - Kategorie anlegen oder updaten
 *         2 - Kategorie loeschen
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
 
require("../../system/common.php");
require("../../system/session_check_login.php");

// nur Moderatoren duerfen Kategorien erfassen & verwalten
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
   
   $category_name = strStripTags($_POST['name']);

   if(strlen($category_name) > 0)
   {
      if(!($_GET['rlc_id'] > 0))
      {
         // Schauen, ob die Kategorie bereits existiert
         $sql    = "SELECT COUNT(*) FROM ". TBL_ROLE_CATEGORIES. "
                     WHERE rlc_org_shortname LIKE '$g_organization'
                       AND rlc_name          LIKE {0}";
         $sql    = prepareSQL($sql, array($category_name));
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

      if($_GET['rlc_id'] > 0)
      {
         $sql = "UPDATE ". TBL_ROLE_CATEGORIES. "
                    SET rlc_name = {0}
                  WHERE rlc_id   = {1}";
      }
      else
      {
         // Feld in Datenbank hinzufuegen
         $sql    = "INSERT INTO ". TBL_ROLE_CATEGORIES. " (rlc_org_shortname, rlc_name)
                                                   VALUES ('$g_organization', {0}) ";
      }
      $sql    = prepareSQL($sql, array(trim($category_name), $_GET['rlc_id']));
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);
   }
   else
   {
      // es sind nicht alle Felder gefuellt
		$err_text = "Name";
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

   $sql    = "DELETE FROM ". TBL_ROLE_CATEGORIES. "
               WHERE rlc_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['rlc_id']));
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