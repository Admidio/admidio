<?php
/******************************************************************************
 * Liste aller Module und Administrationsseiten von Admidio
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// if config file doesn't exists, than show installation dialog
if(!file_exists('../config.php'))
{
    $location = 'Location: installation/index.php';
    header($location);
    exit();
}

require_once('system/common.php');

// Url-Stack loeschen
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$gLayout['title']  = 'Admidio '.$gL10n->get('SYS_OVERVIEW');
$gLayout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/overview_modules.css" type="text/css" />';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<h1 class="moduleHeadline">'.$gCurrentOrganization->getValue('org_longname').'</h1>

<ul class="iconTextLinkList">';
    if($gValidLogin == 1)
    {
        echo '<li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/logout.php"><img
                src="'.THEME_PATH.'/icons/door_in.png" alt="'.$gL10n->get('SYS_LOGOUT').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/logout.php">'.$gL10n->get('SYS_LOGOUT').'</a>
            </span>
        </li>';
    }
    else
    {
        echo '<li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/login.php"><img
                src="'.THEME_PATH.'/icons/key.png" alt="'.$gL10n->get('SYS_LOGIN').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/login.php">'.$gL10n->get('SYS_LOGIN').'</a>
            </span>
        </li>';

        if($gPreferences['registration_mode'] > 0)
        {
            echo '<li>
                <span class="iconTextLink">
                    <a href="'.$g_root_path.'/adm_program/system/registration.php"><img
                    src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$gL10n->get('SYS_REGISTRATION').'" /></a>
                    <a href="'.$g_root_path.'/adm_program/system/registration.php">'.$gL10n->get('SYS_REGISTRATION').'</a>
                </span>
            </li>';
        }
    }
echo '</ul>';

$moduleMenu = new Menu('modules', $gL10n->get('SYS_MODULES'));
if( $gPreferences['enable_announcements_module'] == 1
|| ($gPreferences['enable_announcements_module'] == 2 && $gValidLogin))
{
	$moduleMenu->addItem('announcements', '/adm_program/modules/announcements/announcements.php',
						$gL10n->get('ANN_ANNOUNCEMENTS'), '/icons/announcements_big.png',
						$gL10n->get('ANN_ANNOUNCEMENTS_DESC'));
}
if($gPreferences['enable_download_module'] == 1)
{
	$moduleMenu->addItem('download', '/adm_program/modules/downloads/downloads.php',
						$gL10n->get('DOW_DOWNLOADS'), '/icons/download_big.png',
						$gL10n->get('DOW_DOWNLOADS_DESC'));
}
if($gPreferences['enable_mail_module'] == 1)
{
	$moduleMenu->addItem('email', '/adm_program/modules/mail/mail.php',
						$gL10n->get('SYS_EMAIL'), '/icons/email_big.png',
						$gL10n->get('MAI_EMAIL_DESC'));
}
if($gPreferences['enable_photo_module'] == 1 
|| ($gPreferences['enable_photo_module'] == 2 && $gValidLogin))
{
	$moduleMenu->addItem('photo', '/adm_program/modules/photos/photos.php',
						$gL10n->get('PHO_PHOTOS'), '/icons/photo_big.png',
						$gL10n->get('PHO_PHOTOS_DESC'));
}
if( $gPreferences['enable_guestbook_module'] == 1
|| ($gPreferences['enable_guestbook_module'] == 2 && $gValidLogin))
{
	$moduleMenu->addItem('guestbk', '/adm_program/modules/guestbook/guestbook.php',
						$gL10n->get('GBO_GUESTBOOK'), '/icons/guestbook_big.png',
						$gL10n->get('GBO_GUESTBOOK_DESC'));
}
$moduleMenu->addItem('lists', '/adm_program/modules/lists/lists.php',
					$gL10n->get('LST_LISTS'), '/icons/lists_big.png',
					$gL10n->get('LST_LISTS_DESC'));
$moduleMenu->addSubItem('lists', 'mylist', '/adm_program/modules/lists/mylist.php',
						$gL10n->get('LST_MY_LIST'));
$moduleMenu->addSubItem('lists', 'rolinac', '/adm_program/modules/lists/lists.php?active_role=0',
						$gL10n->get('ROL_INACTIV_ROLE'));
$moduleMenu->addItem('profile', '/adm_program/modules/profile/profile.php',
					$gL10n->get('PRO_MY_PROFILE'), '/icons/profile_big.png',
					$gL10n->get('PRO_MY_PROFILE_DESC'));
$moduleMenu->addSubItem('profile', 'editprof', '/adm_program/modules/profile/profile_new.php?user_id='.$gCurrentUser->getValue('usr_id'),
						$gL10n->get('PRO_EDIT_MY_PROFILE'));
if( $gPreferences['enable_dates_module'] == 1
|| ($gPreferences['enable_dates_module'] == 2 && $gValidLogin))
{
	$moduleMenu->addItem('dates', $g_root_path.'/adm_program/modules/dates/dates.php',
						$gL10n->get('DAT_DATES'), '/icons/dates_big.png',
						$gL10n->get('DAT_DATES_DESC'));
	$moduleMenu->addSubItem('dates', 'olddates', '/adm_program/modules/dates/dates.php?mode=old',
						$gL10n->get('DAT_PREVIOUS_DATES', $gL10n->get('DAT_DATES')));
}
if( $gPreferences['enable_weblinks_module'] == 1
|| ($gPreferences['enable_weblinks_module'] == 2 && $gValidLogin))
{
	$moduleMenu->addItem('links', $g_root_path.'/adm_program/modules/links/links.php',
						$gL10n->get('LNK_WEBLINKS'), '/icons/weblinks_big.png',
						$gL10n->get('LNK_WEBLINKS_DESC'));
}
// Wenn das Forum aktiv ist, dieses auch in der Uebersicht anzeigen.
if($gPreferences['enable_forum_interface'])
{
	if($gForum->session_valid)
	{
		$forumstext = $gL10n->get('SYS_FORUM_LOGIN_DESC', $gForum->user, $gForum->sitename, $gForum->getUserPM($gCurrentUser->getValue('usr_login_name')));
	}
	else
	{
		$forumstext = $gL10n->get('SYS_FORUM_DESC');
	}
	$moduleMenu->addItem('forum', $gForum->url,
						$gL10n->get('SYS_FORUM'), '/icons/forum_big.png',
						$forumstext);
}
$moduleMenu->show('long');


if($gCurrentUser->isWebmaster() || $gCurrentUser->manageRoles() || $gCurrentUser->approveUsers() || $gCurrentUser->editUsers())
{
	$adminMenu = new Menu('administration', $gL10n->get('SYS_ADMINISTRATION'));
	if($gCurrentUser->approveUsers() && $gPreferences['registration_mode'] > 0)
	{
		$adminMenu->addItem('newreg', '/adm_program/administration/new_user/new_user.php',
							$gL10n->get('NWU_NEW_REGISTRATIONS'), '/icons/new_registrations_big.png',
							$gL10n->get('NWU_MANAGE_NEW_REGISTRATIONS'));
	}

	if($gCurrentUser->editUsers())
	{
		$adminMenu->addItem('usrmgt', '/adm_program/administration/members/members.php',
							$gL10n->get('MEM_USER_MANAGEMENT'), '/icons/user_administration_big.png',
							$gL10n->get('MEM_USER_MANAGEMENT_DESC'));
	}

	if($gCurrentUser->manageRoles())
	{
		$adminMenu->addItem('roladm', '/adm_program/administration/roles/roles.php',
							$gL10n->get('ROL_ROLE_ADMINISTRATION'), '/icons/roles_big.png',
							$gL10n->get('ROL_ROLE_ADMINISTRATION_DESC'));
	}
	
	if($gCurrentUser->isWebmaster())
	{
		$adminMenu->addItem('dbback', '/adm_program/administration/backup/backup.php',
							$gL10n->get('BAC_DATABASE_BACKUP'), '/icons/backup_big.png',
							$gL10n->get('BAC_DATABASE_BACKUP_DESC'));
		$adminMenu->addItem('orgprop', '/adm_program/administration/organization/organization.php',
							$gL10n->get('ORG_ORGANIZATION_PROPERTIES'), '/icons/options_big.png',
							$gL10n->get('ORG_ORGANIZATION_PROPERTIES_DESC'));
	}
	$adminMenu->show('long');
}

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>