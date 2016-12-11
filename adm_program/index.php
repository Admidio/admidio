<?php
/**
 ***********************************************************************************************
 * List of all modules and administration pages of Admidio
 *
 * @copyright 2004-2016 The Admidio Team
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

$sql = 'SELECT *
  FROM '.TBL_MENU.'
  where men_group < 4
 ORDER BY men_group DESC, men_order';
$statement = $gDb->query($sql);

if($statement->rowCount() > 0)
{
    $men_groups = array('1' => 'Administration', '2' => 'Modules', '3' => 'Plugins');
    $men_heads = array('1' => 'SYS_ADMINISTRATION', '2' => 'SYS_MODULES', '3' => 'SYS_PLUGIN');
    $last = 0;

    while ($row = $statement->fetchObject())
    {
        if($row->men_group != $last)
        {
            if($last > 0)
            {
                $page->addHtml($menu->show(true));
            }
            $menu = new Menu($men_groups[$row->men_group], $gL10n->get($men_heads[$row->men_group]));
            $last = $row->men_group;
        }

        $men_display = true;
        $desc = '';

        if(strlen($row->men_translate_desc) > 2)
        {
            $desc = $gL10n->get($row->men_translate_desc);
        }

        // Read current roles rights of the menu
        $displayMenu = new RolesRights($gDb, 'men_display_index', $row->men_id);
        $rolesDisplayRight = $displayMenu->getRolesIds();

        if($row->men_need_enable == 1)
        {
            if($gPreferences['enable_'.$row->men_modul_name.'_module'] == 1  || ($gPreferences['enable_'.$row->men_modul_name.'_module'] == 2 && $gValidLogin))
            {
                $men_display = true;
            }
            else
            {
                $men_display = false;
            }
        }

        $men_url = $row->men_url;
        $men_icon = $row->men_icon;
        $men_translate_name = $gL10n->get($row->men_translate_name);

        //special case because there are differnent links if you are logged in or out for mail
        if($row->men_modul_name === 'mail' && $gValidLogin)
        {
            $unreadBadge = '';

            // get number of unread messages for user
            $message = new TableMessage($gDb);
            $unread = $message->countUnreadMessageRecords($gCurrentUser->getValue('usr_id'));

            if($unread > 0)
            {
                $unreadBadge = '<span class="badge">' . $unread . '</span>';
            }

            $men_url = '/adm_program/modules/messages/messages.php';
            $men_icon = '/icons/messages.png';
            $men_translate_name = $gL10n->get('SYS_MESSAGES') . $unreadBadge;
        }

        if(count($rolesDisplayRight) >= 1)
        {
            // check for rigth to show the menue
            if(!$displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
            {
                $men_display = false;
            }
        }

        // special check for "newreg"
        if($row->men_modul_name === 'newreg')
        {
            $men_display = false;
            if($gCurrentUser->approveUsers() && $gPreferences['registration_mode'] > 0)
            {
                $men_display = true;
            }
        }

        // special check for "usrmgt"
        if($row->men_modul_name === 'usrmgt')
        {
            if(!$gCurrentUser->editUsers())
            {
                $men_display = false;
            }
        }

        // special check for "roladm"
        if($row->men_modul_name === 'roladm')
        {
            if(!$gCurrentUser->manageRoles())
            {
                $men_display = false;
            }
        }

        if($men_display == true)
        {
            $menu->addItem($row->men_modul_name, $men_url, $men_translate_name, $men_icon, $desc);
        }

        //Submenu for Lists
        if($gValidLogin && $row->men_modul_name === 'lists')
        {
            $menu->addSubItem('lists', 'mylist', '/adm_program/modules/lists/mylist.php',
                                    $gL10n->get('LST_MY_LIST'));
            $menu->addSubItem('lists', 'rolinac', '/adm_program/modules/lists/lists.php?active_role=0',
                                    $gL10n->get('ROL_INACTIV_ROLE'));
        }

        //Submenu for Dates
        if(($gPreferences['enable_dates_module'] == 1 && $row->men_modul_name === 'dates')
        || ($gPreferences['enable_dates_module'] == 2 && $gValidLogin && $row->men_modul_name === 'dates'))
        {
            $menu->addSubItem('dates', 'olddates', '/adm_program/modules/dates/dates.php?mode=old',
                                    $gL10n->get('DAT_PREVIOUS_DATES', $gL10n->get('DAT_DATES')));
        }
    }

    $page->addHtml($menu->show(true));
}

$page->show();
