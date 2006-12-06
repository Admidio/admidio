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

if(isset($_POST["longname"]) == false || strlen($_POST["longname"]) == 0)
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

if(isset($_POST["enable_mail_module"]) == false)
{
    $_POST["enable_mail_module"] = 0;
}

if(isset($_POST["enable_system_mails"]) == false)
{
    $_POST["enable_system_mails"] = 0;
}

if(strlen($_POST["max_email_attachment_size"]) == 0)
{
    $_POST["max_email_attachment_size"] = 0;
}

if(isset($_POST["enable_mail_captcha"]) == false)
{
    $_POST["enable_mail_captcha"] = 0;
}

if(isset($_POST["enable_registration_captcha"]) == false)
{
    $_POST["enable_registration_captcha"] = 0;
}

if(isset($_POST["enable_registration_admin_mail"]) == false)
{
    $_POST["enable_registration_admin_mail"] = 0;
}

if(isset($_POST["enable_bbcode"]) == false)
{
    $_POST["enable_bbcode"] = 0;
}

if(isset($_POST["enable_rss"]) == false)
{
    $_POST["enable_rss"] = 0;
}

if(isset($_POST["enable_download_module"]) == false)
{
    $_POST["enable_download_module"] = 0;
}

if(strlen($_POST["max_file_upload_size"]) == 0)
{
    $_POST["max_file_upload_size"] = 0;
}

if(isset($_POST["enable_photo_module"]) == false)
{
    $_POST["enable_photo_module"] = 0;
}

if(isset($_POST["photo_image_text"]) == false)
{
    $_POST["photo_image_text"] = 0;
}

if(isset($_POST["enable_guestbook_module"]) == false)
{
    $_POST["enable_guestbook_module"] = 0;
}

if(isset($_POST["enable_guestbook_captcha"]) == false)
{
    $_POST["enable_guestbook_captcha"] = 0;
}

if(strlen($_POST["flooding_protection_time"]) == 0)
{
    $_POST["flooding_protection_time"] = 0;
}

if(isset($_POST["enable_weblinks_module"]) == false)
{
    $_POST["enable_weblinks_module"] = 0;
}

if(isset($_POST["enable_dates_module"]) == false)
{
    $_POST["enable_dates_module"] = 0;
}

if(isset($_POST["enable_lists_module"]) == false)
{
    $_POST["enable_lists_module"] = 0;
}

if(isset($_POST["enable_announcements_module"]) == false)
{
    $_POST["enable_announcements_module"] = 0;
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
writeOrgaPreferences('enable_system_mails', $_POST['enable_system_mails']);
writeOrgaPreferences('default_country',     $_POST['default_country']);
writeOrgaPreferences('enable_bbcode',       $_POST['enable_bbcode']);
writeOrgaPreferences('enable_rss',          $_POST['enable_rss']);
writeOrgaPreferences('logout_minutes',      $_POST['logout_minutes']);
//Einstellungen Registrierung
writeOrgaPreferences('registration_mode',              $_POST['registration_mode']);
writeOrgaPreferences('enable_registration_captcha',    $_POST['enable_registration_captcha']);
writeOrgaPreferences('enable_registration_admin_mail', $_POST['enable_registration_admin_mail']);
//Einstellungen Mailmodul
writeOrgaPreferences('enable_mail_module',        $_POST['enable_mail_module']);
writeOrgaPreferences('max_email_attachment_size', $_POST['max_email_attachment_size']);
writeOrgaPreferences('enable_mail_captcha',       $_POST['enable_mail_captcha']);
//Einstellungen Downloadmodul
writeOrgaPreferences('enable_download_module', $_POST['enable_download_module']);
writeOrgaPreferences('max_file_upload_size',   $_POST['max_file_upload_size']);
//Einstellungen Photomodul
writeOrgaPreferences('enable_photo_module', $_POST['enable_photo_module']);
writeOrgaPreferences('photo_thumbs_column', $_POST['photo_thumbs_column']);
writeOrgaPreferences('photo_thumbs_row',    $_POST['photo_thumbs_row']);
writeOrgaPreferences('photo_thumbs_scale',  $_POST['photo_thumbs_scale']);
writeOrgaPreferences('photo_save_scale',    $_POST['photo_save_scale']);
writeOrgaPreferences('photo_show_width',    $_POST['photo_show_width']);
writeOrgaPreferences('photo_show_height',   $_POST['photo_show_height']);
writeOrgaPreferences('photo_image_text',    $_POST['photo_image_text']);
writeOrgaPreferences('photo_preview_scale', $_POST['photo_preview_scale']);
//Einstellungen Gaestebuchmodul
writeOrgaPreferences('enable_guestbook_module',  $_POST['enable_guestbook_module']);
writeOrgaPreferences('enable_guestbook_captcha', $_POST['enable_guestbook_captcha']);
writeOrgaPreferences('flooding_protection_time', $_POST['flooding_protection_time']);
//Einstellungen Listenmodul
writeOrgaPreferences('enable_lists_module',          $_POST['enable_lists_module']);
//Einstellungen Ankuendigungsmodul
writeOrgaPreferences('enable_announcements_module',  $_POST['enable_announcements_module']);
//Einstellungen Terminmodul
writeOrgaPreferences('enable_dates_module',          $_POST['enable_dates_module']);
//Einstellungen Weblinkmodul
writeOrgaPreferences('enable_weblinks_module',       $_POST['enable_weblinks_module']);

unset($_SESSION['organization_request']);
unset($_SESSION['g_current_organizsation']);
unset($_SESSION['g_preferences']);

// zur Ausgangsseite zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show("save");
?>