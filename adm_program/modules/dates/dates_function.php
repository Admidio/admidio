<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Termine
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * dat_id: ID des Termins, der angezeigt werden soll
 * mode:  1 - Neuen Termin anlegen
 *        2 - Termin löschen
 *        3 - Termin ändern
 * url:   kann beim Loeschen mit uebergeben werden
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

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!editDate())
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

if($_GET["mode"] == 2 || $_GET["mode"] == 3)
{
   // aendern & loeschen darf man nur eigene Termine, außer Moderatoren
   if(!isModerator())
   {
      $sql = "SELECT * FROM ". TBL_DATES. " WHERE dat_id = {0}";
      $sql = prepareSQL($sql, array($_GET['dat_id']));
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);

      $row_date = mysql_fetch_object($result);

      if($g_current_user->id != $row_date->dat_usr_id)
      {
         $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=noforeigndel";
         header($location);
         exit();
      }
   }
}

$err_code = "";
$err_text = "";

if($_GET["mode"] == 1 || $_GET["mode"] == 3)
{
	$headline = strStripTags($_POST['ueberschrift']);
	$content  = strStripTags($_POST['beschreibung']);
	$place    = strStripTags($_POST['treffpunkt']);
   $_POST['datum_von']    = trim($_POST['datum_von']);

   if(strlen($headline) > 0
   && strlen($content)  > 0
   && strlen($_POST['datum_von'])    > 0 )
   {
      // wenn Datum gueltig, dann speichern
      if(dtCheckDate($_POST['datum_von']))
      {
         if(dtCheckTime($_POST['uhrzeit_von'])
         || $_POST['uhrzeit_von'] == "")
         {
            $dt_datum_von = dtFormatDate($_POST['datum_von'], "Y-m-d"). " ". dtFormatTime($_POST['uhrzeit_von']);

            // wenn Datum-bis nicht gefüllt ist, dann mit Datum-von nehmen
            if(strlen($_POST['datum_bis'])   == 0)
               $_POST['datum_bis']   = $_POST['datum_von'];
            if(strlen($_POST['uhrzeit_bis']) == 0)
               $_POST['uhrzeit_bis'] = $_POST['uhrzeit_von'];

            if(dtCheckDate($_POST['datum_bis']))
            {
               if(dtCheckTime($_POST['uhrzeit_bis'])
               || $_POST['uhrzeit_bis'] == "")
               {
                  $dt_datum_bis = dtFormatDate($_POST['datum_bis'], "Y-m-d"). " ". dtFormatTime($_POST['uhrzeit_bis']);
               }
               else
               {
                  $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=uhrzeit";
                  header($location);
                  exit();
               }
            }
            else
            {
            	$err_text = "Datum Ende";
               $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=datum&err_text=$err_text";
               header($location);
               exit();
            }

            $act_date = date("Y.m.d G:i:s", time());

            if(array_key_exists("global", $_POST))
               $global = 1;
            else
               $global = 0;

            // Termin speichern

            if ($_GET["dat_id"] == 0)
            {
               $sql = "INSERT INTO ". TBL_DATES. " (dat_global, dat_org_shortname, dat_usr_id, dat_timestamp, dat_headline,
                                                 dat_begin, dat_end, dat_location, dat_description)
                                         VALUES ($global, '$g_organization', '$g_current_user->id', '$act_date', {0},
                                                 '$dt_datum_von', '$dt_datum_bis', {1}, {2})";
            }
            else
            {
               $sql = "UPDATE ". TBL_DATES. " SET dat_global         = $global
                                             , dat_headline   = {0}
                                             , dat_begin            = '$dt_datum_von'
                                             , dat_end            = '$dt_datum_bis'
                                             , dat_location            = {1}
                                             , dat_description   = {2}
                                             , dat_last_change    = '$act_date'
                                             , dat_usr_id_change = $g_current_user->id
                        WHERE dat_id = {3}";
            }
            $sql    = prepareSQL($sql, array($headline, $place, $content, $_GET['dat_id']));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);

            $location = "location: $g_root_path/adm_program/modules/dates/dates.php";
            header($location);
            exit();
         }
         else
         {
            $err_code = "uhrzeit";
         }
      }
      else
      {
         $err_code = "datum";
         $err_text = "Datum Beginn";
      }
   }
   else
   {
   	if(strlen($headline) == 0)
   		$err_text = "Überschrift";
   	elseif(strlen($content) == 0)
   		$err_text = "Beschreibung";
   	else
   		$err_text = "Datum von";
      $err_code = "feld";
   }
}
elseif($_GET["mode"] == 2)
{
   $sql = "DELETE FROM ". TBL_DATES. " WHERE dat_id = {0}";
   $sql = prepareSQL($sql, array($_GET['dat_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   if(!isset($_GET["url"]))
      $_GET["url"] = "$g_root_path/$g_main_page";

   $location = "location: $g_root_path/adm_program/system/err_msg.php?id=$id&err_code=delete&url=". urlencode($_GET["url"]);
   header($location);
   exit();
}

if ($err_code != "")
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
   header($location);
   exit();
}
?>