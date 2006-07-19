<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Organisationen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * org_id: ID der Organisation, die bearbeitet werden soll
 * url:    URL auf die danach weitergeleitet wird
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

// nur Webmaster duerfen Organisationen bearbeiten
if(!hasRole("Webmaster"))
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
    header($location);
    exit();
}

$err_code   = "";

$g_current_organization->longname  = strStripTags($_POST["longname"]);
$g_current_organization->homepage  = strStripTags($_POST["homepage"]);
$g_current_organization->org_id_parent = $_POST["parent"];

// *******************************************************************************
// Pruefen, ob alle notwendigen Felder gefuellt sind
// *******************************************************************************
if(strlen($g_current_organization->longname) == 0)
{
    $err_code = "feld";
    $err_text = "Name (lang)";
}

if(strlen($err_code) == 0)
{
    if(strlen($_POST['email_administrator']) == 0)
    {
        $err_code = "feld";
        $err_text = "E-Mail Adresse des Administrator";
    }
    else
    {
        if(!isValidEmailAddress($_POST['email_administrator']))
        {
            $err_code = "email_invalid";
        }
    }
}

if($_POST["send_email_extern"] != 1)
{
    $_POST["send_email_extern"] = 0;
}

if($_POST["enable_bbcode"] != 1)
{
    $_POST["enable_bbcode"] = 0;
}

if($_POST["enable_rss"] != 1)
{
    $_POST["enable_rss"] = 0;
}

if(strlen($_POST["max_email_attachment_size"]) == 0)
{
    $_POST["max_email_attachment_size"] = 0;
}

if(strlen($_POST["max_file_upload_size"]) == 0)
{
    $_POST["max_file_upload_size"] = 0;
}

if ($err_code != "")
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
    header($location);
    exit();
}

// *******************************************************************************
// Organisation updaten
// *******************************************************************************
$ret_code = $g_current_organization->update();
if($ret_code != 0)
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=&err_text=$ret_code";
    header($location);
    exit();
}

// Einstellungen speichern

writeOrgaPreferences('email_administrator', $_POST['email_administrator']);
writeOrgaPreferences('default_country',     $_POST['default_country']);
writeOrgaPreferences('send_email_extern',   $_POST['send_email_extern']);
writeOrgaPreferences('enable_bbcode',       $_POST['enable_bbcode']);
writeOrgaPreferences('enable_rss',          $_POST['enable_rss']);
writeOrgaPreferences('max_email_attachment_size', $_POST['max_email_attachment_size']);
writeOrgaPreferences('max_file_upload_size', $_POST['max_file_upload_size']);
writeOrgaPreferences('photo_save_scale', $_POST['photo_save_scale']);

// zur Ausgangsseite zurueck
$load_url = urlencode("$g_root_path/adm_program/administration/organization/organization.php?url=". $_GET['url']);
$location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=save&timer=2000&url=$load_url";
header($location);
exit();
?>