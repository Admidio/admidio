<?php
/******************************************************************************
 * Save organization preferences
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_text.php');

// nur Webmaster duerfen Organisationen bearbeiten
if($gCurrentUser->isWebmaster() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$_SESSION['organization_request'] = $_REQUEST;

// *******************************************************************************
// Pruefen, ob alle notwendigen Felder gefuellt sind
// *******************************************************************************

if(strlen($_POST['org_longname']) == 0)
{
    $gMessage->show($gL10n->get('ORG_FIELD_EMPTY_AREA', $gL10n->get('SYS_NAME'), $gL10n->get('SYS_COMMON')));
}

if(strlen($_POST['email_administrator']) == 0)
{
    $gMessage->show($gL10n->get('ORG_FIELD_EMPTY_AREA', $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS'), $gL10n->get('SYS_SYSTEM_MAILS')));
}
else
{
    $_POST['email_administrator'] = admStrToLower($_POST['email_administrator']);
    if(!strValidCharacters($_POST['email_administrator'], 'email'))
    {
        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('ORG_SYSTEM_MAIL_ADDRESS')));
    }
}

if(strlen($_POST['mail_sendmail_address']) > 0)
{
    $_POST['mail_sendmail_address'] = admStrToLower($_POST['mail_sendmail_address']);
    if(!strValidCharacters($_POST['mail_sendmail_address'], 'email'))
    {
        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('MAI_SENDER_EMAIL')));
    }
}

if(strlen($_POST['theme']) == 0)
{
    $gMessage->show($gL10n->get('ORG_FIELD_EMPTY_AREA', $gL10n->get('ORG_ADMIDIO_THEME'), $gL10n->get('SYS_COMMON')));
}

if(is_numeric($_POST['logout_minutes']) == false || $_POST['logout_minutes'] <= 0)
{
    $gMessage->show($gL10n->get('ORG_FIELD_EMPTY_AREA', $gL10n->get('ORG_AUTOMATOC_LOGOUT_AFTER'), $gL10n->get('SYS_COMMON')));
}

if(is_numeric($_POST['weblinks_redirect_seconds']) == false || $_POST['weblinks_redirect_seconds'] < 0)
{
    $gMessage->show($gL10n->get('ORG_FIELD_EMPTY_AREA', $gL10n->get('LNK_DISPLAY_REDIRECT'), $gL10n->get('LNK_WEBLINKS')));
}

// bei allen Checkboxen muss geprueft werden, ob hier ein Wert uebertragen wurde
// falls nicht, dann den Wert hier auf 0 setzen, da 0 nicht uebertragen wird

$checkboxes = array('dates_show_calendar_select'
                   ,'dates_show_map_link'
                   ,'dates_show_rooms'
                   ,'enable_system_mails'
				   ,'enable_email_notification'
                   ,'enable_mail_captcha'
                   ,'enable_registration_captcha'
                   ,'enable_registration_admin_mail'
                   ,'enable_rss'
                   ,'enable_dates_ical'
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
                   ,'forum_link_intern'
                   ,'forum_set_admin'
                   ,'forum_sqldata_from_admidio'
                   ,'lists_hide_overview_details'
                   ,'mail_html_registered_users'
				   ,'mail_sender_into_to'
                   ,'photo_upload_mode'
                   ,'profile_show_map_link'
                   ,'profile_show_roles'
                   ,'profile_show_former_roles'
                   ,'profile_show_extern_roles'
                   ,'system_js_editor_enabled'
				   ,'system_organization_select'
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
        $gMessage->show($gL10n->get('SYS_FORUM_ACCESS_DATA'));
    }
    else
    {
        // Password 0000 ist aus Sicherheitsgruenden ein Dummy und bedeutet, dass es sich nicht geaendert hat
        if($_POST['forum_pw'] == '0000')
        {
            $_POST['forum_pw'] = $gPreferences['forum_pw'];
        }

        $forum_test = Forum::createForumObject($_POST['forum_version']);

        if($_POST['forum_sqldata_from_admidio'] == 0)
        {
            $connect_id = $forum_test->connect($_POST['forum_srv'], $_POST['forum_usr'], $_POST['forum_pw'], $_POST['forum_db'], $gDb);
        }
        else
        {
            $connect_id = $forum_test->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $_POST['forum_db'], $gDb);
        }
        if($connect_id == false)
        {
            $gMessage->show($gL10n->get('SYS_FORUM_DB_CONNECTION_FAILED'));
        }
    }
}

// *******************************************************************************
// Organisation updaten
// *******************************************************************************

$text = new TableText($gDb);

// Einstellungen speichern

foreach($_POST as $key => $value)
{
    // Elmente, die nicht in adm_preferences gespeichert werden hier aussortieren
    if($key != 'version' && $key != 'save')
    {
        if(strpos($key, 'org_') === 0)
        {
            $gCurrentOrganization->setValue($key, $value);
        }
        elseif(strpos($key, 'SYSMAIL_') === 0)
        {
            $text->readData($key);
            $text->setValue('txt_text', $value);
            $text->save();
        }
        elseif($key == 'forum_pw' && $value == '0000')
        {
            // Forumpassword hier gesondert behandeln, da es nicht angezeigt werden soll
            // 0000 bedeutet, dass das PW sich nicht veraendert hat
			$gPreferences[$key] = $gPreferences[$key];
		}
		elseif($key == 'enable_auto_login' && $value == 0 && $gPreferences['enable_auto_login'] == 1)
		{
			// if deactivate auto login than delete all saved logins
			$sql = 'DELETE FROM '.TBL_AUTO_LOGIN;
			$gDb->query($sql);
			$gPreferences[$key] = $value;
		}
		else
		{
			$gPreferences[$key] = $value;
		}
    }
}

// alle Daten nun speichern
$ret_code = $gCurrentOrganization->save();
if($ret_code != 0)
{
    $gCurrentOrganization->clear();
    $gMessage->show($gL10n->get('SYS_ERROR_DATABASE_ACCESS', $ret_code));
}

$gCurrentOrganization->setPreferences($gPreferences);

// Aufraeumen
unset($_SESSION['organization_request']);
unset($_SESSION['gForum']);
$gCurrentSession->renewOrganizationObject();

// zur Ausgangsseite zurueck
$gMessage->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$gMessage->show($gL10n->get('SYS_SAVE_DATA'));
?>