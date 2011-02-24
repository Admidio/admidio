<?php
/******************************************************************************
 * Organisationseinstellungen speichern
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_text.php');

// nur Webmaster duerfen Organisationen bearbeiten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

$_SESSION['organization_request'] = $_REQUEST;

// *******************************************************************************
// Pruefen, ob alle notwendigen Felder gefuellt sind
// *******************************************************************************

if(strlen($_POST['org_longname']) == 0)
{
    $g_message->show($g_l10n->get('ORG_FIELD_EMPTY_AREA', $g_l10n->get('SYS_NAME'), $g_l10n->get('SYS_COMMON')));
}

if(strlen($_POST['email_administrator']) == 0)
{
    $g_message->show($g_l10n->get('ORG_FIELD_EMPTY_AREA', $g_l10n->get('ORG_SYSTEM_MAIL_ADDRESS'), $g_l10n->get('SYS_SYSTEM_MAILS')));
}
else
{
    $_POST['email_administrator'] = admStrToLower($_POST['email_administrator']);
    if(!strValidCharacters($_POST['email_administrator'], 'email'))
    {
        $g_message->show($g_l10n->get('SYS_EMAIL_INVALID', $g_l10n->get('ORG_SYSTEM_MAIL_ADDRESS')));
    }
}

if(strlen($_POST['mail_sendmail_address']) > 0)
{
    $_POST['mail_sendmail_address'] = admStrToLower($_POST['mail_sendmail_address']);
    if(!strValidCharacters($_POST['mail_sendmail_address'], 'email'))
    {
        $g_message->show($g_l10n->get('SYS_EMAIL_INVALID', $g_l10n->get('MAI_SENDER_EMAIL')));
    }
}

if(strlen($_POST['theme']) == 0)
{
    $g_message->show($g_l10n->get('ORG_FIELD_EMPTY_AREA', $g_l10n->get('ORG_ADMIDIO_THEME'), $g_l10n->get('SYS_COMMON')));
}

if(is_numeric($_POST['logout_minutes']) == false || $_POST['logout_minutes'] <= 0)
{
    $g_message->show($g_l10n->get('ORG_FIELD_EMPTY_AREA', $g_l10n->get('ORG_AUTOMATOC_LOGOUT_AFTER'), $g_l10n->get('SYS_COMMON')));
}

if(is_numeric($_POST['weblinks_redirect_seconds']) == false || $_POST['weblinks_redirect_seconds'] < 0)
{
    $g_message->show($g_l10n->get('ORG_FIELD_EMPTY_AREA', $g_l10n->get('LNK_DISPLAY_REDIRECT'), $g_l10n->get('LNK_WEBLINKS')));
}

// bei allen Checkboxen muss geprueft werden, ob hier ein Wert uebertragen wurde
// falls nicht, dann den Wert hier auf 0 setzen, da 0 nicht uebertragen wird

$checkboxes = array('dates_show_map_link'
                   ,'dates_show_rooms'
                   ,'enable_system_mails'
				   ,'enable_email_notification'
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
                   ,'forum_export_user'
                   ,'forum_sqldata_from_admidio'
                   ,'forum_link_intern'
                   ,'photo_upload_mode'
                   ,'profile_show_map_link'
                   ,'profile_show_roles'
                   ,'profile_show_former_roles'
                   ,'profile_show_extern_roles'
                   ,'lists_hide_overview_details'
                   ,'dates_show_calendar_select'
                   ,'system_search_similar'
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
        $g_message->show($g_l10n->get('SYS_FORUM_ACCESS_DATA'));
    }
    else
    {
        // Password 0000 ist aus Sicherheitsgruenden ein Dummy und bedeutet, dass es sich nicht geaendert hat
        if($_POST['forum_pw'] == '0000')
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
            $g_message->show($g_l10n->get('SYS_FORUM_DB_CONNECTION_FAILED'));
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
    if($key != 'version' && $key != 'save')
    {
        if(strpos($key, 'org_') === 0)
        {
            $g_current_organization->setValue($key, $value);
        }
        elseif(strpos($key, 'SYSMAIL_') === 0)
        {
            $text->readData($key);
            $text->setValue('txt_text', $value);
            $text->save();
        }
        else
        {
            // Forumpassword hier gesondert behandeln, da es nicht angezeigt werden soll
            // 0000 bedeutet, dass das PW sich nicht veraendert hat
            if($key == 'forum_pw' && $value == '0000')
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
    $g_message->show($g_l10n->get('SYS_DATABASE_ERROR', $ret_code));
}

$g_current_organization->setPreferences($g_preferences);

// Aufraeumen
unset($_SESSION['organization_request']);
unset($_SESSION['g_forum']);
$g_current_session->renewOrganizationObject();

// zur Ausgangsseite zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($g_l10n->get('SYS_SAVE_DATA'));
?>