<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Ankuendigungen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * aa_id:    ID der Ankuendigung, die angezeigt werden soll
 * mode:     1 - Neue Ankuendigung anlegen
 *           2 - Ankuendigung löschen
 *           3 - Ankuendigung ändern
 * url:      kann beim Loeschen mit uebergeben werden
 * headline: Ueberschrift, die ueber den Ankuendigungen steht
 *           (Default) Ankuendigungen
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
require("../../system/common.php");
require("../../system/session_check_login.php");

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!isModerator())
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

if(!array_key_exists("headline", $_GET))
   $_GET["headline"] = "Ankündigungen";

$err_code = "";
$err_text = "";

if($_GET["mode"] == 1 || $_GET["mode"] == 3)
{
	$headline = strStripTags($_POST['ueberschrift']);
	$content  = strStripTags($_POST['beschreibung']);

   if(strlen($headline) > 0
   && strlen($content)  > 0)
   {
		$act_date = date("Y.m.d G:i:s", time());

		if(array_key_exists("global", $_POST))
		   $global = 1;
		else
		   $global = 0;

		// Termin speichern

		if ($_GET["aa_id"] == 0)
		{
		   $sql = "INSERT INTO ". TBL_ANNOUNCEMENTS. " (aa_global, aa_ag_shortname, aa_au_id, aa_timestamp,
		                                            aa_ueberschrift, aa_beschreibung)
		                             VALUES ($global, '$g_organization', '$g_user_id', '$act_date',
		                                     {0}, {1})";
		   $sql    = prepareSQL($sql, array($headline, $content));
		   $result = mysql_query($sql, $g_adm_con);
		   db_error($result);
		}
		else
		{
		   $sql = "UPDATE ". TBL_ANNOUNCEMENTS. " SET aa_global         = $global
		                                        , aa_ueberschrift   = {0}
		                                        , aa_beschreibung   = {1}
		                                        , aa_last_change    = '$act_date'
		                                        , aa_last_change_id = $g_user_id
		            WHERE aa_id = {2}";
		   $sql    = prepareSQL($sql, array($headline, $content, $_GET['aa_id']));
		   $result = mysql_query($sql, $g_adm_con);
		   db_error($result);
		}

		$location = "location: $g_root_path/adm_program/modules/announcements/announcements.php?headline=". $_GET['headline'];
		header($location);
		exit();
   }
   else
   {
   	if(strlen($headline) > 0)
   		$err_text = "Beschreibung";
   	else
   		$err_text = "Überschrift";
      $err_code = "feld";
   }
}
elseif($_GET["mode"] == 2)
{
   $sql = "DELETE FROM ". TBL_ANNOUNCEMENTS. " WHERE aa_id = {0}";
   $sql    = prepareSQL($sql, array($_GET["aa_id"]));
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