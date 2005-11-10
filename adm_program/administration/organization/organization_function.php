<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Gruppierungen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * ag_id: ID der Gruppierung, die bearbeitet werden soll
 * url:   URL auf die danach weitergeleitet wird
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
require("../../system/string.php");
require("../../system/date.php");
require("../../system/session_check_login.php");

// nur Webmaster duerfen Gruppierungen bearbeiten
if(!hasRole("Webmaster"))
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

$err_code   = "";

if(strlen($_POST["longname"]) == 0)
{
   $err_code = "feld";
   $err_text = "Name (lang)";
}

if ($err_code != "")
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
   header($location);
   exit();
}

// Gruppierung updaten
$sql = "UPDATE adm_gruppierung SET ag_longname    = {0}
                                 , ag_mail_extern = {1}
                                 , ag_homepage    = {2}
                                 , ag_mother      = ";
if(strlen($_POST["mutter"]) > 0)
   $sql = $sql. " {3} ";
else
   $sql = $sql. " NULL ";
   
$sql = $sql. " WHERE ag_id = {4} ";
$sql    = prepareSQL($sql, array($_POST['longname'], $_POST['mail_extern'], $_POST['homepage'], $_POST['mutter'], $_GET['ag_id']));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

// zur Ausgangsseite zurueck
$load_url = urlencode("$g_root_path/adm_program/administration/organization/organization.php?url=". $_GET['url']);
$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=save&timer=2000&url=$load_url";
header($location);
exit();
?>