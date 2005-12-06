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

require("../../system/common.php");
require("../../system/session_check_login.php");

// nur Webmaster duerfen Gruppierungen bearbeiten
if(!hasRole("Webmaster"))
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

$err_code   = "";

$g_current_organization->longname  = strStripTags($_POST["longname"]);
$g_current_organization->homepage  = strStripTags($_POST["homepage"]);
$g_current_organization->org_shortname_mother = $_POST["mutter"];
$g_current_organization->bbcode      = $_POST["bbcode"];
$g_current_organization->mail_extern = $_POST["mail_extern"];
$g_current_organization->mail_size   = $_POST["attachment_size"];
$g_current_organization->enable_rss  = $_POST["enable_rss"];

// Pruefen, ob alle notwendigen Felder gefuellt sind
if(strlen($g_current_organization->longname) == 0)
{
   $err_code = "feld";
   $err_text = "Name (lang)";
}

if(strlen($g_current_organization->mail_size) == 0)
{
   $err_code = "feld";
   $err_text = "Max. Attachmentgr&ouml;&szlig;e";
}

if ($err_code != "")
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
   header($location);
   exit();
}

// Gruppierung updaten
$ret_code = $g_current_organization->update($g_adm_con);
if($ret_code != 0)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=&err_text=$ret_code";
   header($location);
   exit();
}

// zur Ausgangsseite zurueck
$load_url = urlencode("$g_root_path/adm_program/administration/organization/organization.php?url=". $_GET['url']);
$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=save&timer=2000&url=$load_url";
header($location);
exit();
?>