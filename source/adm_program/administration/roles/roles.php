<?php
/******************************************************************************
 * Show all roles with their individual permissions
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * inactive:  0 - (Default) show all active roles
 *            1 - show all inactive roles
 * invisible: 0 - (Default) show all visible roles
 *            1 - show all invisible roles
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getInactive  = admFuncVariableIsValid($_GET, 'inactive', 'boolean', 0);
$getInvisible = admFuncVariableIsValid($_GET, 'invisible', 'boolean', 0); 

// nonly moderators are allowed to set/manage roles !
if(!$gCurrentUser->manageRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Navigation faengt hier im Modul an
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL);

unset($_SESSION['roles_request']);

// Html header
$gLayout['title']  = $gL10n->get('ROL_ROLE_ADMINISTRATION');
// per default show active and visible roles
$sqlRolesStatus   = ' AND rol_valid   = \'1\'
					  AND rol_visible = \'1\' ';

if($getInactive == true)
{
    $activeRolesLinkDescription = $gL10n->get('ROL_ACTIV_ROLES');
    $listDescription  = $gL10n->get('ROL_INACTIV_ROLES');
    $activeRolesImage = 'roles.png';
	$activeRolesFlag  = '0';
	// in inactive mode show visible and invisible inactive roles
	$sqlRolesStatus   = ' AND rol_valid   = \'0\' ';
}
else
{
    $activeRolesLinkDescription = $gL10n->get('ROL_INACTIV_ROLES');
    $listDescription  = $gL10n->get('ROL_ACTIV_ROLES');
    $activeRolesImage = 'roles_gray.png';
	$activeRolesFlag  = '1';
}

if($getInvisible == true)
{
    $visibleRolesLinkDescription = $gL10n->get('ROL_VISIBLE_ROLES');
    $listDescription   = $gL10n->get('ROL_INVISIBLE_ROLES');
    $visibleRolesImage = 'light_on.png';
	$visibleRolesFlag  = '0';
	// in invisible mode show active and inactive invisible roles
	$sqlRolesStatus   = ' AND rol_visible = \'0\' ';
}
else
{
    $visibleRolesLinkDescription = $gL10n->get('ROL_INVISIBLE_ROLES');
    $visibleRolesImage = 'light_off.png';
	$visibleRolesFlag  = '1';
}

// create module menu
$rolesMenu = new ModuleMenu('admMenuRoles');

// define link to create new profile field
$rolesMenu->addItem('admMenuItemNewRole', $g_root_path.'/adm_program/administration/roles/roles_new.php', 
							$gL10n->get('SYS_CREATE_ROLE'), 'add.png');
// define link to maintain categories
$rolesMenu->addItem('admMenuItemMaintainCategory', $g_root_path.'/adm_program/administration/categories/categories.php?type=ROL', 
							$gL10n->get('SYS_MAINTAIN_CATEGORIES'), 'edit.png');
// define link to show inactive roles
$rolesMenu->addItem('admMenuItemInactiveRole', $g_root_path.'/adm_program/administration/roles/roles.php?inactive='.$activeRolesFlag, 
							$activeRolesLinkDescription, $activeRolesImage);
// define link to show hidden roles
$rolesMenu->addItem('admMenuItemHiddenRole', $g_root_path.'/adm_program/administration/roles/roles.php?invisible='.$visibleRolesFlag, 
							$visibleRolesLinkDescription, $visibleRolesImage);
// Create table
$table = new HtmlTableBasic('', 'tableList');
$table->addAttribute('cellspacing', '0');
$table->addTableHeader();
$table->addRow();
$table->addColumn($listDescription, '', '', 'th');
$table->addColumn($gL10n->get('SYS_AUTHORIZATION'), '', '', 'th');
$table->addColumn($gL10n->get('ROL_PREF'), '', '', 'th');
$table->addColumn($gL10n->get('SYS_FEATURES'), 'style', 'text-align: center;', 'th');

$cat_id = '';
// list all roles group by category
$sql    = 'SELECT * FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
            WHERE rol_cat_id  = cat_id
                AND cat_type    = \'ROL\'
				    '.$sqlRolesStatus.'
                AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                     OR cat_org_id IS NULL )
            ORDER BY cat_sequence ASC, rol_name ASC ';
$rol_result = $gDb->query($sql);

// Create role object
$role = new TableRoles($gDb);

while($row = $gDb->fetch_array($rol_result))
{
    $assignRoles        = '';
    $listView           = '';
    $linkAdministration = '';
    // Add data to role object
    $role->setArray($row);
        
    if($cat_id != $role->getValue('cat_id'))
    {
        $image_hidden = '';
        $block_id     = 'admCategory'.$role->getValue('cat_id');
        if($role->getValue('cat_hidden') == 1)
        {
            $image_hidden = '<img class="iconInformation" src="'. THEME_PATH. '/icons/user_key.png"
                                 alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $gL10n->get('SYS_ROLE')).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $gL10n->get('SYS_ROLE')).'" />';
        }
        $table->addTableBody();
        $table->addRow();
        $table->addColumn('', 'class', 'tableSubHeader');
        $table->addAttribute('colspan', '4');
        $table->addData('<a class="iconShowHide" href="javascript:showHideBlock(\''.$block_id.'\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
                        id="'.$block_id.'Image" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$role->getValue('cat_name').' '.$image_hidden);
        // next body element
        $table->addTableBody('id', $block_id);
        
        $cat_id = $role->getValue('cat_id');
    }
    
    $table->addRow('', 'class', 'tableMouseOver');
    $table->addColumn('&nbsp;<a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php?rol_id='.$role->getValue('rol_id').'" title="'.$role->getValue('rol_description').'">'.$role->getValue('rol_name').'</a>');
                
    if($role->getValue('rol_assign_roles') == 1)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/roles.png"
                            alt="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" title="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" />';
    }
    if($role->getValue('rol_approve_users') == 1)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/new_registrations.png"
                            alt="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" title="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" />';
    }
    if($role->getValue('rol_edit_user') == 1)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/group.png"
                            alt="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" title="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" />';
    }
    if($role->getValue('rol_mail_to_all') == 1)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/email.png"
                            alt="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" title="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" />';
    }
    if($role->getValue('rol_profile') == 1)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/profile.png"
                            alt="'.$gL10n->get('ROL_RIGHT_PROFILE').'" title="'.$gL10n->get('ROL_RIGHT_PROFILE').'" />';
    }
    if($role->getValue('rol_announcements') == 1 && $gPreferences['enable_announcements_module'] > 0)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/announcements.png"
                            alt="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" title="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" />';
    }
    if($role->getValue('rol_dates') == 1 && $gPreferences['enable_dates_module'] > 0)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/dates.png"
                            alt="'.$gL10n->get('ROL_RIGHT_DATES').'" title="'.$gL10n->get('ROL_RIGHT_DATES').'" />';
    }
    if($role->getValue('rol_photo') == 1 && $gPreferences['enable_photo_module'] > 0)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/photo.png"
                            alt="'.$gL10n->get('ROL_RIGHT_PHOTO').'" title="'.$gL10n->get('ROL_RIGHT_PHOTO').'" />';
    }
    if($role->getValue('rol_download') == 1 && $gPreferences['enable_download_module'] > 0)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/download.png"
                            alt="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" title="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" />';
    }
    if($role->getValue('rol_guestbook') == 1 && $gPreferences['enable_guestbook_module'] > 0)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/guestbook.png"
                            alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" />';
    }
    // If allowed to write anonymous guestbook entries, then we don´t need to set rights for the roles
    if($role->getValue('rol_guestbook_comments') == 1  && $gPreferences['enable_guestbook_module'] > 0 && $gPreferences['enable_gbook_comments4all'] == false)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/comments.png"
                            alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" />';
    }
    if($role->getValue('rol_weblinks') == 1 && $gPreferences['enable_weblinks_module'] > 0)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/weblinks.png"
                            alt="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" title="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" />';
    }
    if($role->getValue('rol_all_lists_view') == 1)
    {
        $assignRoles .= '<img class="iconInformation" src="'. THEME_PATH. '/icons/lists.png"
                            alt="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" title="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" />';
    }
    // if no assigned roles
    if(strlen($assignRoles) == 0)
    {
        $assignRoles= '&nbsp;';
    }
    
    $table->addColumn($assignRoles);
    
    if($role->getValue("rol_this_list_view") == 1)
    {
        $listView .= '<img class="iconInformation" src="'.THEME_PATH.'/icons/list_role.png"
                        alt="'.$gL10n->get('ROL_VIEW_LIST_ROLE').'" title="'.$gL10n->get('ROL_VIEW_LIST_ROLE').'" />';
    }
    if($role->getValue("rol_this_list_view") == 2)
    {
        $listView .= '<img class="iconInformation" src="'.THEME_PATH.'/icons/list_key.png"
                        alt="'.$gL10n->get('ROL_VIEW_LIST_MEMBERS').'" title="'.$gL10n->get('ROL_VIEW_LIST_MEMBERS').'" />';
    }
    if($role->getValue("rol_mail_this_role") == 1 && $gPreferences['enable_mail_module'] > 0)
    {
        $listView .= '<img class="iconInformation" src="'.THEME_PATH.'/icons/email_role.png"
                        alt="'.$gL10n->get('ROL_SEND_MAIL_ROLE').'" title="'.$gL10n->get('ROL_SEND_MAIL_ROLE').'" />';
    }
    if($role->getValue("rol_mail_this_role") == 2 && $gPreferences['enable_mail_module'] > 0)
    {
        $listView .= '<img class="iconInformation" src="'.THEME_PATH.'/icons/email_key.png"
                        alt="'.$gL10n->get('ROL_SEND_MAIL_MEMBERS').'" title="'.$gL10n->get('ROL_SEND_MAIL_MEMBERS').'" />';
    }
    if($role->getValue("rol_mail_this_role") == 3 && $gPreferences['enable_mail_module'] > 0)
    {
        $listView .= '<img class="iconInformation" src="'.THEME_PATH.'/icons/email.png"
                        alt="'.$gL10n->get('ROL_SEND_MAIL_GUESTS').'" title="'.$gL10n->get('ROL_SEND_MAIL_GUESTS').'" />';
    }
    // if no matches for list view
    if(strlen($listView) == 0)
    {
        $listView = '&nbsp;';
    }
    
    $table->addColumn($listView);
    
    $linkAdministration .= '<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='.$role->getValue("rol_id").'"><img
                                src="'. THEME_PATH. '/icons/list.png" alt="'.$gL10n->get('ROL_SHOW_MEMBERS').'" title="'.$gL10n->get('ROL_SHOW_MEMBERS').'" /></a>';

    if($getInactive == true)
    {
        $linkAdministration .= '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=5"><img
                                    src="'.THEME_PATH.'/icons/roles.png" alt="'.$gL10n->get('ROL_ENABLE_ROLE').'" title="'.$gL10n->get('ROL_ENABLE_ROLE').'" /></a>';
    }
    else
    {
        $linkAdministration .= '<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$role->getValue('rol_id').'"><img
                                    src="'.THEME_PATH.'/icons/add.png" alt="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" /></a>';
    }

    if($role->getValue('rol_webmaster') == 1)
    {
        $linkAdministration .= '<a class="iconLink"><img src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" /></a>';
    }
    else
    {
        if($getInactive == true)
        {
            $linkAdministration .= '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=6"><img
                                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('ROL_ROLE_DELETE').'" title="'.$gL10n->get('ROL_ROLE_DELETE').'" /></a>';
        }
        else
        {
            $linkAdministration .='<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=1"><img
                                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('ROL_ROLE_DELETE').'" title="'.$gL10n->get('ROL_ROLE_DELETE').'" /></a>';
        }
    }
    if($getInvisible == true)
    {
        $linkAdministration .= '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=8"><img
                                    src="'. THEME_PATH. '/icons/light_on.png" alt="'.$gL10n->get('ROL_SET_ROLE_VISIBLE').'" title="'.$gL10n->get('ROL_SET_ROLE_VISIBLE').'" /></a>';
    }
    else
    {
        $linkAdministration .= '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=7"><img
                                    src="'. THEME_PATH. '/icons/light_off.png" alt="'.$gL10n->get('ROL_SET_ROLE_INVISIBLE').'" title="'.$gL10n->get('ROL_SET_ROLE_INVISIBLE').'" /></a>';
    }

    $table->addColumn($linkAdministration, 'style', 'text-align: center;');

}

require(SERVER_PATH. '/adm_program/system/overall_header.php');
// Html output of the module
echo '<h1 class="moduleHeadline">'.$gLayout['title'].'</h1>';
// output menue
$rolesMenu->show();
// output table
echo $table->getHtmlTable();
require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>