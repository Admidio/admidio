<?php
/**
 ***********************************************************************************************
 * List of all modules and administration pages of Admidio
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// if config file doesn't exists, than show installation dialog
if(!is_file('../adm_my_files/config.php'))
{
    header('Location: installation/index.php');
    exit();
}

require_once('system/common.php');

$headline = 'Admidio '.$gL10n->get('SYS_OVERVIEW');

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

// main menu of the page
$mainMenu = $page->getMenu();

if($gValidLogin)
{
    // show link to own profile
    $mainMenu->addItem('adm_menu_item_my_profile', ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php',
                       $gL10n->get('PRO_MY_PROFILE'), 'profile.png');
    // show logout link
    $mainMenu->addItem('adm_menu_item_logout', ADMIDIO_URL . '/adm_program/system/logout.php',
                       $gL10n->get('SYS_LOGOUT'), 'door_in.png');
}
else
{
    // show login link
    $mainMenu->addItem('adm_menu_item_login', ADMIDIO_URL . '/adm_program/system/login.php',
                       $gL10n->get('SYS_LOGIN'), 'key.png');

    if($gPreferences['registration_mode'] > 0)
    {
        // show registration link
        $mainMenu->addItem('adm_menu_item_registration',
                           ADMIDIO_URL . FOLDER_MODULES . '/registration/registration.php',
                           $gL10n->get('SYS_REGISTRATION'), 'new_registrations.png');
    }
}

// menu with links to all modules of Admidio
$moduleMenu = new Menu('index_modules', $gL10n->get('SYS_MODULES'));

if($gPreferences['enable_announcements_module'] == 1
|| ($gPreferences['enable_announcements_module'] == 2 && $gValidLogin))
{
    $moduleMenu->addItem('announcements', FOLDER_MODULES . '/announcements/announcements.php',
                         $gL10n->get('ANN_ANNOUNCEMENTS'), '/icons/announcements_big.png',
                         $gL10n->get('ANN_ANNOUNCEMENTS_DESC'));
}
if($gPreferences['enable_download_module'] == 1)
{
    $moduleMenu->addItem('download', FOLDER_MODULES . '/downloads/downloads.php',
                         $gL10n->get('DOW_DOWNLOADS'), '/icons/download_big.png',
                         $gL10n->get('DOW_DOWNLOADS_DESC'));
}
if($gPreferences['enable_mail_module'] == 1 && !$gValidLogin)
{
    $moduleMenu->addItem('email', FOLDER_MODULES . '/messages/messages_write.php',
                         $gL10n->get('SYS_EMAIL'), '/icons/email_big.png',
                         $gL10n->get('MAI_EMAIL_DESC'));
}
if(($gPreferences['enable_pm_module'] == 1 || $gPreferences['enable_mail_module'] == 1) && $gValidLogin)
{
    $unreadBadge = '';

    // get number of unread messages for user
    $message = new TableMessage($gDb);
    $unread = $message->countUnreadMessageRecords($gCurrentUser->getValue('usr_id'));

    if($unread > 0)
    {
        $unreadBadge = '<span class="badge">' . $unread . '</span>';
    }

    $moduleMenu->addItem('private message', FOLDER_MODULES . '/messages/messages.php',
                         $gL10n->get('SYS_MESSAGES') . $unreadBadge, '/icons/messages_big.png',
                         $gL10n->get('MAI_EMAIL_DESC'));
}
if($gPreferences['enable_photo_module'] == 1
|| ($gPreferences['enable_photo_module'] == 2 && $gValidLogin))
{
    $moduleMenu->addItem('photo', FOLDER_MODULES . '/photos/photos.php',
                         $gL10n->get('PHO_PHOTOS'), '/icons/photo_big.png',
                         $gL10n->get('PHO_PHOTOS_DESC'));
}
if($gPreferences['enable_guestbook_module'] == 1
|| ($gPreferences['enable_guestbook_module'] == 2 && $gValidLogin))
{
    $moduleMenu->addItem('guestbk', FOLDER_MODULES . '/guestbook/guestbook.php',
                         $gL10n->get('GBO_GUESTBOOK'), '/icons/guestbook_big.png',
                         $gL10n->get('GBO_GUESTBOOK_DESC'));
}
$moduleMenu->addItem('lists', FOLDER_MODULES . '/lists/lists.php',
                     $gL10n->get('LST_LISTS'), '/icons/lists_big.png',
                     $gL10n->get('LST_LISTS_DESC'));
if($gValidLogin)
{
    $moduleMenu->addSubItem('lists', 'mylist', FOLDER_MODULES . '/lists/mylist.php',
                            $gL10n->get('LST_MY_LIST'));
    $moduleMenu->addSubItem('lists', 'rolinac', FOLDER_MODULES . '/lists/lists.php?active_role=0',
                            $gL10n->get('ROL_INACTIV_ROLE'));
}
if($gPreferences['enable_dates_module'] == 1
|| ($gPreferences['enable_dates_module'] == 2 && $gValidLogin))
{
    $moduleMenu->addItem('dates', ADMIDIO_URL . FOLDER_MODULES . '/dates/dates.php',
                         $gL10n->get('DAT_DATES'), '/icons/dates_big.png',
                         $gL10n->get('DAT_DATES_DESC'));
    $moduleMenu->addSubItem('dates', 'olddates', FOLDER_MODULES . '/dates/dates.php?mode=old',
                            $gL10n->get('DAT_PREVIOUS_DATES', $gL10n->get('DAT_DATES')));
}
if($gPreferences['enable_weblinks_module'] == 1
|| ($gPreferences['enable_weblinks_module'] == 2 && $gValidLogin))
{
    $moduleMenu->addItem('links', ADMIDIO_URL . FOLDER_MODULES . '/links/links.php',
                         $gL10n->get('LNK_WEBLINKS'), '/icons/weblinks_big.png',
                         $gL10n->get('LNK_WEBLINKS_DESC'));
}

$page->addHtml($moduleMenu->show(true));

// menu with links to all administration pages of Admidio if the user has the right to administrate
if($gCurrentUser->isAdministrator() || $gCurrentUser->manageRoles()
|| $gCurrentUser->approveUsers() || $gCurrentUser->editUsers())
{
    $adminMenu = new Menu('index_administration', $gL10n->get('SYS_ADMINISTRATION'));

    if($gCurrentUser->approveUsers() && $gPreferences['registration_mode'] > 0)
    {
        $adminMenu->addItem('newreg', FOLDER_MODULES . '/registration/registration.php',
                            $gL10n->get('NWU_NEW_REGISTRATIONS'), '/icons/new_registrations_big.png',
                            $gL10n->get('NWU_MANAGE_NEW_REGISTRATIONS_DESC'));
    }

    if($gCurrentUser->editUsers())
    {
        $adminMenu->addItem('usrmgt', FOLDER_MODULES . '/members/members.php',
                            $gL10n->get('MEM_USER_MANAGEMENT'), '/icons/user_administration_big.png',
                            $gL10n->get('MEM_USER_MANAGEMENT_DESC'));
    }

    if($gCurrentUser->manageRoles())
    {
        $adminMenu->addItem('roladm', FOLDER_MODULES . '/roles/roles.php',
                            $gL10n->get('ROL_ROLE_ADMINISTRATION'), '/icons/roles_big.png',
                            $gL10n->get('ROL_ROLE_ADMINISTRATION_DESC'));
    }

    if($gCurrentUser->isAdministrator())
    {
        $adminMenu->addItem('dbback', FOLDER_MODULES . '/backup/backup.php',
                            $gL10n->get('BAC_DATABASE_BACKUP'), '/icons/backup_big.png',
                            $gL10n->get('BAC_DATABASE_BACKUP_DESC'));
        $adminMenu->addItem('orgprop', FOLDER_MODULES . '/preferences/preferences.php',
                            $gL10n->get('SYS_SETTINGS'), '/icons/options_big.png',
                            $gL10n->get('ORG_ORGANIZATION_PROPERTIES_DESC'));
    }

    $page->addHtml($adminMenu->show(true));
}

$page->show();
