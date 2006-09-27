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
    $g_message->show("norights");
}

$_SESSION['organization_request'] = $_REQUEST;

// Uebergabevariablen pruefen

if(isset($_GET["org_id"]) && is_numeric($_GET["org_id"]) == false)
{
    $g_message->show("invalid");
}

$err_code = "";
$err_text = "";

// *******************************************************************************
// Pruefen, ob alle notwendigen Felder gefuellt sind
// *******************************************************************************
$_POST["longname"] = strStripTags($_POST["longname"]);
if(strlen($_POST["longname"]) == 0)
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
    if($_POST['logout_minutes'] <= 0)
    {
        $err_code = "feld";
        $err_text = "Automatischer Logout";
    }
}

if ($err_code != "")
{
    $g_message->show($err_code, $err_text);
}

$g_current_organization->longname  = strStripTags($_POST["longname"]);
$g_current_organization->homepage  = strStripTags($_POST["homepage"]);
if(isset($_POST["parent"]))
{
    $g_current_organization->org_id_parent = $_POST["parent"];
}
else
{
    $g_current_organization->org_id_parent = null;
}

if(isset($_POST["send_email_extern"]) == false)
{
    $_POST["send_email_extern"] = 0;
}

if(isset($_POST["enable_bbcode"]) == false)
{
    $_POST["enable_bbcode"] = 0;
}

if(isset($_POST["enable_rss"]) == false)
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

if(isset($_POST["photo_image_text"]) == false)
{
    $_POST["photo_image_text"] = 0;
}

// *******************************************************************************
// Organisation updaten
// *******************************************************************************
$ret_code = $g_current_organization->update();
if($ret_code != 0)
{
    $g_message->show("mysql", $ret_code);
}

// Einstellungen speichern

writeOrgaPreferences('email_administrator', $_POST['email_administrator']);
writeOrgaPreferences('default_country',     $_POST['default_country']);
writeOrgaPreferences('enable_bbcode',       $_POST['enable_bbcode']);
writeOrgaPreferences('enable_rss',          $_POST['enable_rss']);
writeOrgaPreferences('logout_minutes',      $_POST['logout_minutes']);
//Einstellungen Mailmodul
writeOrgaPreferences('send_email_extern',   $_POST['send_email_extern']);
writeOrgaPreferences('max_email_attachment_size', $_POST['max_email_attachment_size']);
//Einstellungen Downloadmodul
writeOrgaPreferences('max_file_upload_size', $_POST['max_file_upload_size']);
//Einstellungen Photomodul
writeOrgaPreferences('photo_thumbs_column', $_POST['photo_thumbs_column']);
writeOrgaPreferences('photo_thumbs_row', $_POST['photo_thumbs_row']);
writeOrgaPreferences('photo_thumbs_scale', $_POST['photo_thumbs_scale']);
writeOrgaPreferences('photo_save_scale', $_POST['photo_save_scale']);
writeOrgaPreferences('photo_show_width', $_POST['photo_show_width']);
writeOrgaPreferences('photo_show_height', $_POST['photo_show_height']);
writeOrgaPreferences('photo_image_text', $_POST['photo_image_text']);
writeOrgaPreferences('photo_preview_scale', $_POST['photo_preview_scale']);

unset($_SESSION['organization_request']);
unset($_SESSION['g_current_organizsation']);
unset($_SESSION['g_preferences']);

// zur Ausgangsseite zurueck
$g_message->setForwardUrl("$g_root_path/adm_program/administration/organization/organization.php?url=". $_GET['url'], 2000);
$g_message->show("save");
?>