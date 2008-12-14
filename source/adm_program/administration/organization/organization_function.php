<?php
/******************************************************************************
 * Organisationseinstellungen speichern
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/table_text.php");

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

if(strlen($_POST['theme']) == 0)
{
    $g_message->show("feld", "Admidio-Theme");
}

if(is_numeric($_POST['logout_minutes']) == false || $_POST['logout_minutes'] <= 0)
{
    $g_message->show("feld", "Automatischer Logout");
}

// bei allen Checkboxen muss geprueft werden, ob hier ein Wert uebertragen wurde
// falls nicht, dann den Wert hier auf 0 setzen, da 0 nicht uebertragen wird

$checkboxes = array('dates_show_map_link'
                   ,'enable_system_mails'
                   ,'enable_mail_captcha'
                   ,'enable_registration_captcha'
                   ,'enable_registration_admin_mail'
                   ,'enable_bbcode'
                   ,'enable_rss'
                   ,'enable_auto_login'
                   ,'enable_password_recovery'
                   ,'enable_download_module'
                   ,'enable_intial_comments_loading'
                   ,'enable_mail_module'
                   ,'enable_guestbook_captcha'
                   ,'enable_ecard_module'
                   ,'enable_forum_interface'
                   ,'enable_gbook_comments4all'
                   ,'enable_ecard_module'
                   ,'enable_messages_module'
                   ,'forum_export_user'
                   ,'forum_sqldata_from_admidio'
                   ,'forum_link_intern'
                   ,'photo_image_text'
                   ,'profile_show_map_link'
                   ,'profile_show_roles'
                   ,'profile_show_former_roles'
                   ,'profile_show_extern_roles'
				   ,'lists_hide_overview_details'
                   ,'dates_show_calendar_select'
                   );

foreach($checkboxes as $key => $value)
{
    if(isset($_POST[$value]) == false || $_POST[$value] != 1)
    {
        $_POST[$value] = 0;
    }
}

// Forumverbindung testen
if(isset($_POST['enable_forum_interface']) && $_POST['enable_forum_interface'] == 1 && $_POST['forum_sqldata_from_admidio'] == 0)
{
    if($_POST['forum_sqldata_from_admidio'] == 0 && (strlen($_POST['forum_srv']) == 0 || strlen($_POST['forum_usr']) == 0 || strlen($_POST['forum_pw']) == 0 || strlen($_POST['forum_db']) == 0 ))
    {
        $g_message->show("forum_access_data");
    }
    else
    {
        // Password 0000 ist aus Sicherheitsgruenden ein Dummy und bedeutet, dass es sich nicht geaendert hat
        if($_POST['forum_pw'] == "0000")
        {
            $_POST['forum_pw'] = $g_preferences['forum_pw'];
        }

        $forum_test = Forum::createForumObject($_POST['forum_version']);

        if($_POST['forum_sqldata_from_admidio'] == 0)
        {
            $connect_id = $forum_test->connect($_POST['forum_srv'], $_POST['forum_usr'], $_POST['forum_pw'], $_POST['forum_db'], $g_db);
        }
        else
        {
            $connect_id = $forum_test->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $_POST['forum_db'], $g_db);
        }
        if($connect_id == false)
        {
            $g_message->show("forum_db_connection_failed");
        }
    }
}

// *******************************************************************************
// Organisation updaten
// *******************************************************************************

$text = new TableText($g_db);

// Einstellungen speichern

foreach($_POST as $key => $value)
{
    // Elmente, die nicht in adm_preferences gespeichert werden hier aussortieren
    if($key != "version" && $key != "save")
    {
        if(strpos($key, "org_") === 0)
        {
            $g_current_organization->setValue($key, $value);
        }
        elseif(strpos($key, "SYSMAIL_") === 0)
        {
            $text->readData($key);
            $text->setValue("txt_text", $value);
            $text->save();
        }
        else
        {
            // Forumpassword hier gesondert behandeln, da es nicht angezeigt werden soll
            // 0000 bedeutet, dass das PW sich nicht veraendert hat
            if($key == "forum_pw" && $value == "0000")
            {
                $g_preferences[$key] = $g_preferences[$key];
            }
            else
            {
                $g_preferences[$key] = $value;
            }
        }
    }
}

// alle Daten nun speichern
$ret_code = $g_current_organization->save();
if($ret_code != 0)
{
    $g_current_organization->clear();
    $g_message->show("mysql", $ret_code);
}

$g_current_organization->setPreferences($g_preferences);

// Aufraeumen
unset($_SESSION['organization_request']);
$g_current_session->renewOrganizationObject();

// zur Ausgangsseite zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show("save");
?>