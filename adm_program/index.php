<?php
/**
 ***********************************************************************************************
 * List of all modules and administration pages of Admidio
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// if config file doesn't exists, than show installation dialog
if(!file_exists('../adm_my_files/config.php'))
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
    $mainMenu->addItem('adm_menu_item_my_profile', $g_root_path.'/adm_program/modules/profile/profile.php',
                       $gL10n->get('PRO_MY_PROFILE'), 'profile.png');
    // show logout link
    $mainMenu->addItem('adm_menu_item_logout', $g_root_path.'/adm_program/system/logout.php',
                       $gL10n->get('SYS_LOGOUT'), 'door_in.png');
}
else
{
    // show login link
    $mainMenu->addItem('adm_menu_item_login', $g_root_path.'/adm_program/system/login.php',
                       $gL10n->get('SYS_LOGIN'), 'key.png');

    if($gPreferences['registration_mode'] > 0)
    {
        // show registration link
        $mainMenu->addItem('adm_menu_item_registration',
                           $g_root_path.'/adm_program/modules/registration/registration.php',
                           $gL10n->get('SYS_REGISTRATION'), 'new_registrations.png');
    }
}

// Plugin Menu
$sql = 'SELECT *
  FROM '.TBL_MENU.'
 WHERE men_group = 3 and men_display_index = 1
 ORDER BY men_order';
$statement = $gDb->query($sql);

if($statement->rowCount() > 0)
{
    $pluginMenu = new Menu('plugins', $gL10n->get('SYS_PLUGIN'));
    while ($row = $statement->fetchObject())
    {
        $men_need_login = false;
        if(($row->men_need_login == 1 && $gValidLogin) || $row->men_need_login == 0)
        {
            $men_need_login = true;
        }
        
        $men_need_admin = false;
        if(($row->men_need_admin == 1 && $gCurrentUser->isAdministrator()) || $row->men_need_admin == 0)
        {
            $men_need_admin = true;
        }
        
        $desc = '';
        if(strlen($row->men_translat_desc) > 2)
        {
            $desc = $gL10n->get($row->men_translat_desc);
        }

        if($men_need_login == true && $men_need_admin == true)
        {
            $pluginMenu->addItem($row->men_modul_name, $row->men_url,
                         $gL10n->get($row->men_translat_name), $row->men_icon, $desc);
        }
    }
    $page->addHtml($pluginMenu->show(true));
}

// menu with links to all modules of Admidio

$sql = 'SELECT *
  FROM '.TBL_MENU.'
 WHERE men_group = 2 and men_display_index = 1
 ORDER BY men_order';
$statement = $gDb->query($sql);

if($statement->rowCount() > 0)
{
    $moduleMenu = new Menu('index_modules', $gL10n->get('SYS_MODULES'));
    while ($row = $statement->fetchObject())
    {
        
        $men_need_enable = false;
        if($row->men_need_enable == 1)
        {
            if($gPreferences['enable_'.$row->men_modul_name.'_module'] == 1)
            {
                $men_need_enable = true;
            }
            elseif($gPreferences['enable_'.$row->men_modul_name.'_module'] == 2 && $gValidLogin)
            {
                $men_need_enable = true;
            }
        }
        elseif($row->men_need_enable == 0)
        {
            $men_need_enable = true;
        }
        
        $men_need_login = false;
        if(($row->men_need_login == 1 && $gValidLogin) || $row->men_need_login == 0)
        {
            $men_need_login = true;
        }
        
        $men_need_admin = false;
        if(($row->men_need_admin == 1 && $gCurrentUser->isAdministrator()) || $row->men_need_admin == 0)
        {
            $men_need_admin = true;
        }
        
        $desc = '';
        if(strlen($row->men_translat_desc) > 2)
        {
            $desc = $gL10n->get($row->men_translat_desc);
        }

        $men_url = $row->men_url;
        $men_icon = $row->men_icon;
        $men_translat_name = $gL10n->get($row->men_translat_name);
        
        //special case because there are differnent links if you are logged in or out for mail
        if($row->men_modul_name === 'mail' && $gValidLogin)
        {
            if($gPreferences['enable_pm_module'] == 1 || $men_need_enable == true)
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
                $men_translat_name = $gL10n->get('SYS_MESSAGES') . $unreadBadge;
            }
        }

        if($men_need_enable == true && $men_need_login == true && $men_need_admin == true)
        {
            $moduleMenu->addItem($row->men_modul_name, $men_url,
                         $men_translat_name, $men_icon, $desc);
        }
    }
    
    if($gValidLogin)
    {
        $moduleMenu->addSubItem('lists', 'mylist', '/adm_program/modules/lists/mylist.php',
                                $gL10n->get('LST_MY_LIST'));
        $moduleMenu->addSubItem('lists', 'rolinac', '/adm_program/modules/lists/lists.php?active_role=0',
                                $gL10n->get('ROL_INACTIV_ROLE'));
    }
    if($gPreferences['enable_dates_module'] == 1
    || ($gPreferences['enable_dates_module'] == 2 && $gValidLogin))
    {
        $moduleMenu->addSubItem('dates', 'olddates', '/adm_program/modules/dates/dates.php?mode=old',
                                $gL10n->get('DAT_PREVIOUS_DATES', $gL10n->get('DAT_DATES')));
    }

    $page->addHtml($moduleMenu->show(true));
}

// Administration Menu
if($gCurrentUser->approveUsers() || $gCurrentUser->editUsers()
|| $gCurrentUser->manageRoles()  || $gCurrentUser->isAdministrator())
{
    
    $sql = 'SELECT *
      FROM '.TBL_MENU.'
     WHERE men_group = 1 and men_display_index = 1
     ORDER BY men_order';
    $statement = $gDb->query($sql);

    if($statement->rowCount() > 0)
    {
        $adminMenu = new Menu('administration', $gL10n->get('SYS_ADMINISTRATION'));
        while ($row = $statement->fetchObject())
        {
            
            $men_need_enable = false;
            if($row->men_need_enable == 1)
            {
                if($gPreferences['enable_'.$row->men_modul_name.'_module'] == 1)
                {
                    $men_need_enable = true;
                }
                elseif($gPreferences['enable_'.$row->men_modul_name.'_module'] == 2 && $gValidLogin)
                {
                    $men_need_enable = true;
                }
            }
            elseif($row->men_need_enable == 0)
            {
                $men_need_enable = true;
            }
            
            $men_need_admin = false;
            if(($row->men_need_admin == 1 && $gCurrentUser->isAdministrator()) || $row->men_need_admin == 0)
            {
                $men_need_admin = true;
            }

            $desc = '';
            if(strlen($row->men_translat_desc) > 2)
            {
                $desc = $gL10n->get($row->men_translat_desc);
            }
            
            // special check for "newreg"
            if($row->men_modul_name === 'newreg')
            {
                $men_need_admin = false;
                if($gCurrentUser->approveUsers() && $gPreferences['registration_mode'] > 0)
                {
                    $men_need_admin = true;
                }
            }
            
            // special check for "usrmgt"
            if($row->men_modul_name === 'usrmgt')
            {
                $men_need_admin = false;
                if($gCurrentUser->editUsers())
                {
                    $men_need_admin = true;
                }
            }
            
            // special check for "roladm"
            if($row->men_modul_name === 'roladm')
            {
                $men_need_admin = false;
                if($gCurrentUser->manageRoles())
                {
                    $men_need_admin = true;
                }
            }

            if($men_need_enable == true && $men_need_admin == true)
            {
                $adminMenu->addItem($row->men_modul_name, $row->men_url,
                             $gL10n->get($row->men_translat_name), $row->men_icon, $desc);
            }
        }
        $page->addHtml($adminMenu->show(true));
    }
}

$page->show();
