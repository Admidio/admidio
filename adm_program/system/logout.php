<?php
/******************************************************************************
 * User auf der Homepage und im Forum ausloggen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
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
require("../../adm_config/config.php");
require("function.php");
require("date.php");
require("tbl_user.php");
require("session_check.php");

// Session pruefen

$sql    = "SELECT * FROM ". TBL_SESSIONS. " WHERE as_session = {0} ";
$sql    = prepareSQL($sql, array($g_session_id));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$session_found = mysql_num_rows($result);

// Inhalt der Cookies loeschen

if($_SERVER['HTTP_HOST'] == 'localhost')
{
   // beim localhost darf keine Domaine uebergeben werden
   setcookie("". TBL_SESSIONS. "", "", 0, "/");
   setcookie("". TBL_USERS. "_id", "", 0, "/");
   setcookie("adm_login",   "", 0, "/");
}
else
{
   setcookie("". TBL_SESSIONS. "", "", 0, "/", ".". $g_domain);
   setcookie("". TBL_USERS. "_id", "", 0, "/", ".". $g_domain);
   setcookie("adm_login",   "", 0, "/", ".". $g_domain);
}

if ($session_found > 0)
{
   // Session loeschen
   
   $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE as_session = {0}";
   $sql    = prepareSQL($sql, array($g_session_id));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
}

if($g_forum == 1)
{
   mysql_select_db($g_forum_db, $g_forum_con);
   
   // User nun in Foren-Tabelle suchen und dort Session löschen
   $sql    = "SELECT user_id FROM ". $g_forum_praefix. "_users WHERE username LIKE {0} ";
   $sql    = prepareSQL($sql, array($g_nickname));
   $result = mysql_query($sql, $g_forum_con);
   db_error($result);

   $row = mysql_fetch_array($result);
   $forum_user_id = $row[0];   

   // User-Session im Forum löschen
   $sql    = "DELETE FROM ". $g_forum_praefix. "_sessions WHERE session_user_id = $forum_user_id ";
   $result = mysql_query($sql, $g_forum_con);
   db_error($result);
   
   mysql_select_db($g_adm_db, $g_adm_con);
}

$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=logout&url=home&timer=2000&status_refresh=1";
header($location);
exit();

?>