<?php
/******************************************************************************
 * Organisationseinstellungen speichern
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

// nur Webmaster duerfen Organisationen bearbeiten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show("norights");
}

$_SESSION['organization_request'] = $_REQUEST;

// *******************************************************************************
// Pruefen, ob alle notwendigen Felder gefuellt sind
// *******************************************************************************

if(strlen($_POST['org_longname']) == 0)
{
    $g_message->show("feld", "Name (lang)");
}

if(strlen($_POST['email_administrator']) == 0)
{
    $g_message->show("feld", "E-Mail Adresse des Administrator");
}
else
{
    if(!isValidEmailAddress($_POST['email_administrator']))
    {
        $g_message->show("email_invalid");
    }
}

if(is_numeric($_POST['logout_minutes']) == false || $_POST['logout_minutes'] <= 0)
{
    $g_message->show("feld", "Automatischer Logout");
}

// *******************************************************************************
// Daten speichern
// *******************************************************************************

if(isset($_POST['enable_system_mails']) == false)
{
    $_POST['enable_system_mails'] = 0;
}

if(strlen($_POST['max_email_attachment_size']) == 0)
{
    $_POST['max_email_attachment_size'] = 0;
}

if(isset($_POST['enable_mail_captcha']) == false)
{
    $_POST['enable_mail_captcha'] = 0;
}

if(isset($_POST['enable_registration_captcha']) == false)
{
    $_POST['enable_registration_captcha'] = 0;
}

if(isset($_POST['enable_registration_admin_mail']) == false)
{
    $_POST['enable_registration_admin_mail'] = 0;
}

if(isset($_POST['enable_bbcode']) == false)
{
    $_POST['enable_bbcode'] = 0;
}

if(isset($_POST['enable_rss']) == false)
{
    $_POST['enable_rss'] = 0;
}

if(isset($_POST['enable_auto_login']) == false)
{
    $_POST['enable_auto_login'] = 0;
}

if(isset($_POST['enable_download_module']) == false)
{
    $_POST['enable_download_module'] = 0;
}

if(strlen($_POST['max_file_upload_size']) == 0)
{
    $_POST['max_file_upload_size'] = 0;
}

if(isset($_POST['enable_photo_module']) == false)
{
    $_POST['enable_photo_module'] = 0;
}

if(isset($_POST['photo_image_text']) == false)
{
    $_POST['photo_image_text'] = 0;
}

if(isset($_POST['enable_guestbook_captcha']) == false)
{
    $_POST['enable_guestbook_captcha'] = 0;
}

if(isset($_POST['enable_gbook_comments4all']) == false)
{
    $_POST['enable_gbook_comments4all'] = 0;
}

if(strlen($_POST['flooding_protection_time']) == 0)
{
    $_POST['flooding_protection_time'] = 0;
}

if(isset($_POST['enable_mail_module']) == false)
{
    $_POST['enable_mail_module'] = 0;
}

if(isset($_POST['enable_roles_view']) == false)
{
    $_POST['enable_roles_view'] = 0;
}

if(isset($_POST['enable_former_roles_view']) == false)
{
    $_POST['enable_former_roles_view'] = 0;
}

if(isset($_POST['enable_extern_roles_view']) == false)
{
    $_POST['enable_extern_roles_view'] = 0;
}
if(isset($_POST['enable_ecard_module']) == false)
{
    $_POST['enable_ecard_module'] = 0;
}

// *******************************************************************************
// Organisation updaten
// *******************************************************************************

// POST Variablen in das UserField-Objekt schreiben
foreach($_POST as $key => $value)
{
    if(strpos($key, "org_") === 0)
    {
        $g_current_organization->setValue($key, $value);
    }
}

$ret_code = $g_current_organization->save();
if($ret_code != 0)
{
    $g_current_organization->clear();
    $g_message->show("mysql", $ret_code);
}

// Einstellungen speichern

foreach($_POST as $key => $value)
{
    // Elmente, die nicht in adm_preferences gespeichert werden hier aussortieren
    if(strpos($key, "org_") === false
    && $key != "version"
    && $key != "save")
    {
        $g_preferences[$key] = $value;
    }
}

$g_current_organization->setPreferences($g_preferences);

// Aufraeumen
unset($_SESSION['organization_request']);
$g_current_session->renewOrganizationObject();

// zur Ausgangsseite zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show("save");
?>