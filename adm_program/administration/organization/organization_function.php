<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Gruppierungen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * org_id: ID der Gruppierung, die bearbeitet werden soll
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
require("../../system/login_valid.php");

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
$g_current_organization->org_id_parent = $_POST["parent"];

// Pruefen, ob alle notwendigen Felder gefuellt sind
if(strlen($g_current_organization->longname) == 0)
{
   $err_code = "feld";
   $err_text = "Name (lang)";
}

if(strlen($_POST["attachment_size"]) == 0)
{
    $_POST["attachment_size"] = 0;
}

if(strlen($_POST["upload_size"]) == 0)
{
    $_POST["upload_size"] = 0;
}

if ($err_code != "")
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
   header($location);
   exit();
}

// Gruppierung updaten
$ret_code = $g_current_organization->update();
if($ret_code != 0)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=&err_text=$ret_code";
   header($location);
   exit();
}

// Einstellungen speichern

$sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = {0}
         WHERE prf_name = 'send_mail_extern' ";
$sql = prepareSQL($sql, array($_POST['mail_extern']));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = {0}
         WHERE prf_name = 'enable_bbcode' ";
$sql = prepareSQL($sql, array($_POST['bbcode']));
$result = mysql_query($sql, $g_adm_con);
db_error($result);         
         
$sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = {0}
         WHERE prf_name = 'enable_rss' ";
$sql = prepareSQL($sql, array($_POST['enable_rss']));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = {0}
         WHERE prf_name = 'max_mail_attachment_size' ";
$sql = prepareSQL($sql, array($_POST['attachment_size']));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = {0}
         WHERE prf_name = 'max_file_upload_size' ";
$sql = prepareSQL($sql, array($_POST['upload_size']));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

// zur Ausgangsseite zurueck
$load_url = urlencode("$g_root_path/adm_program/administration/organization/organization.php?url=". $_GET['url']);
$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=save&timer=2000&url=$load_url";
header($location);
exit();
?>