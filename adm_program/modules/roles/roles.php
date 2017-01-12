<?php
/**
 ***********************************************************************************************
 * Show all roles with their individual permissions
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * inactive:  false - (Default) show all active roles
 *            true  - show all inactive roles
 * invisible: false - (Default) show all visible roles
 *            true  - show all invisible roles
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getInactive  = admFuncVariableIsValid($_GET, 'inactive',  'bool');
$getInvisible = admFuncVariableIsValid($_GET, 'invisible', 'bool');

// only users with the special right are allowed to manage roles
if(!$gCurrentUser->manageRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set headline of the script
$headline = $gL10n->get('ROL_ROLE_ADMINISTRATION');

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

unset($_SESSION['roles_request']);

// per default show active and visible roles
$sqlRolesStatus = ' AND rol_valid   = \'1\'
                    AND rol_visible = \'1\' ';

if($getInactive)
{
    $activeRolesLinkDescription = $gL10n->get('ROL_ACTIV_ROLES');
    $listDescription  = $gL10n->get('ROL_INACTIV_ROLES');
    $activeRolesImage = 'roles.png';
    $activeRolesFlag  = '0';
    // in inactive mode show visible and invisible inactive roles
    $sqlRolesStatus   = ' AND rol_valid = \'0\' ';
}
else
{
    $activeRolesLinkDescription = $gL10n->get('ROL_INACTIV_ROLES');
    $listDescription  = $gL10n->get('ROL_ACTIV_ROLES');
    $activeRolesImage = 'roles_gray.png';
    $activeRolesFlag  = '1';
}

if($getInvisible)
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

// create html page object
$page = new HtmlPage($headline);

$page->addJavascript('$(".admidio-group-heading").click(function() { showHideBlock($(this).attr("id")); });', true);

// get module menu
$rolesMenu = $page->getMenu();

// define link to create new profile field
$rolesMenu->addItem('menu_item_new_role', ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_new.php',
                    $gL10n->get('SYS_CREATE_ROLE'), 'add.png');
// define link to maintain categories
$rolesMenu->addItem('menu_item_maintain_category', ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php?type=ROL',
                    $gL10n->get('SYS_MAINTAIN_CATEGORIES'), 'edit.png');
// define link to show inactive roles
$rolesMenu->addItem('menu_item_inactive_role', ADMIDIO_URL.FOLDER_MODULES.'/roles/roles.php?inactive='.$activeRolesFlag,
                    $activeRolesLinkDescription, $activeRolesImage);
// define link to show hidden roles
$rolesMenu->addItem('menu_item_hidden_role', ADMIDIO_URL.FOLDER_MODULES.'/roles/roles.php?invisible='.$visibleRolesFlag,
                    $visibleRolesLinkDescription, $visibleRolesImage);

// Create table
$table = new HtmlTable('roles_table', $page, true, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_CATEGORY'),
    $listDescription,
    $gL10n->get('SYS_AUTHORIZATION'),
    $gL10n->get('ROL_PREF'),
    $gL10n->get('SYS_FEATURES')
);
$table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'right'));
$table->disableDatatablesColumnsSort(array(3, 4, 5));
$table->setDatatablesGroupColumn(1);
$table->addRowHeadingByArray($columnHeading);

// list all roles group by category
$sql = 'SELECT *
          FROM '.TBL_ROLES.'
    INNER JOIN '.TBL_CATEGORIES.'
            ON cat_id = rol_cat_id
         WHERE cat_type = \'ROL\'
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                OR cat_org_id IS NULL )
               '.$sqlRolesStatus.'
      ORDER BY cat_sequence ASC, rol_name ASC';
$rolStatement = $gDb->query($sql);

// Create role object
$role = new TableRoles($gDb);

while($row = $rolStatement->fetch())
{
    $assignRoles        = '';
    $listView           = '';
    $linkAdministration = '';

    // Add data to role object
    $role->setArray($row);

    $categoryName = $role->getValue('cat_name');

    if($role->getValue('cat_hidden') == 1)
    {
        $categoryName .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/user_key.png"
                             alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $gL10n->get('SYS_ROLE')).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $gL10n->get('SYS_ROLE')).'" />';
    }

    if($role->getValue('rol_assign_roles') == 1)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/roles.png"
                            alt="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" title="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" />';
    }
    if($role->getValue('rol_approve_users') == 1)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/new_registrations.png"
                            alt="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" title="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" />';
    }
    if($role->getValue('rol_edit_user') == 1)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/group.png"
                            alt="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" title="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" />';
    }
    if($role->getValue('rol_mail_to_all') == 1)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/email.png"
                            alt="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" title="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" />';
    }
    if($role->getValue('rol_profile') == 1)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/profile.png"
                            alt="'.$gL10n->get('ROL_RIGHT_PROFILE').'" title="'.$gL10n->get('ROL_RIGHT_PROFILE').'" />';
    }
    if($role->getValue('rol_announcements') == 1 && $gPreferences['enable_announcements_module'] > 0)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/announcements.png"
                            alt="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" title="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" />';
    }
    if($role->getValue('rol_dates') == 1 && $gPreferences['enable_dates_module'] > 0)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/dates.png"
                            alt="'.$gL10n->get('ROL_RIGHT_DATES').'" title="'.$gL10n->get('ROL_RIGHT_DATES').'" />';
    }
    if($role->getValue('rol_photo') == 1 && $gPreferences['enable_photo_module'] > 0)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/photo.png"
                            alt="'.$gL10n->get('ROL_RIGHT_PHOTO').'" title="'.$gL10n->get('ROL_RIGHT_PHOTO').'" />';
    }
    if($role->getValue('rol_download') == 1 && $gPreferences['enable_download_module'] > 0)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/download.png"
                            alt="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" title="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" />';
    }
    if($role->getValue('rol_guestbook') == 1 && $gPreferences['enable_guestbook_module'] > 0)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/guestbook.png"
                            alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" />';
    }
    // If allowed to write anonymous guestbook entries, then we don´t need to set rights for the roles
    if($role->getValue('rol_guestbook_comments') == 1  && $gPreferences['enable_guestbook_module'] > 0 && $gPreferences['enable_gbook_comments4all'] == false)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/comment.png"
                            alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" />';
    }
    if($role->getValue('rol_weblinks') == 1 && $gPreferences['enable_weblinks_module'] > 0)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/weblinks.png"
                            alt="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" title="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" />';
    }
    if($role->getValue('rol_all_lists_view') == 1)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/lists.png"
                            alt="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" title="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" />';
    }
    if($role->getValue('rol_inventory') == 1)
    {
        $assignRoles .= '<img class="admidio-icon-info" src="'. THEME_URL. '/icons/inventory.png"
                            alt="'.$gL10n->get('ROL_RIGHT_INVENTORY').'" title="'.$gL10n->get('ROL_RIGHT_INVENTORY').'" />';
    }
    // if no assigned roles
    if($assignRoles === '')
    {
        $assignRoles = '&nbsp;';
    }

    if($role->getValue('rol_this_list_view') == 1)
    {
        $listView .= '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/list_role.png"
                        alt="'.$gL10n->get('ROL_VIEW_LIST_ROLE').'" title="'.$gL10n->get('ROL_VIEW_LIST_ROLE').'" />';
    }
    if($role->getValue('rol_this_list_view') == 2)
    {
        $listView .= '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/list_key.png"
                        alt="'.$gL10n->get('ROL_VIEW_LIST_MEMBERS').'" title="'.$gL10n->get('ROL_VIEW_LIST_MEMBERS').'" />';
    }
    if($role->getValue('rol_mail_this_role') == 1 && $gPreferences['enable_mail_module'] > 0)
    {
        $listView .= '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/email_role.png"
                        alt="'.$gL10n->get('ROL_SEND_MAIL_ROLE').'" title="'.$gL10n->get('ROL_SEND_MAIL_ROLE').'" />';
    }
    if($role->getValue('rol_mail_this_role') == 2 && $gPreferences['enable_mail_module'] > 0)
    {
        $listView .= '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/email_key.png"
                        alt="'.$gL10n->get('ROL_SEND_MAIL_MEMBERS').'" title="'.$gL10n->get('ROL_SEND_MAIL_MEMBERS').'" />';
    }
    if($role->getValue('rol_mail_this_role') == 3 && $gPreferences['enable_mail_module'] > 0)
    {
        $listView .= '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/email.png"
                        alt="'.$gL10n->get('ROL_SEND_MAIL_GUESTS').'" title="'.$gL10n->get('ROL_SEND_MAIL_GUESTS').'" />';
    }
    // if no matches for list view
    if($listView === '')
    {
        $listView = '&nbsp;';
    }

    $rolId = (int) $role->getValue('rol_id');
    $rolName = $role->getValue('rol_name');

    $linkAdministration .= '<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php?mode=html&amp;rol_ids='.$rolId.'"><img
                                src="'. THEME_URL. '/icons/list.png" alt="'.$gL10n->get('ROL_SHOW_MEMBERS').'" title="'.$gL10n->get('ROL_SHOW_MEMBERS').'" /></a>';

    if($getInactive)
    {
        $linkAdministration .= '<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_function.php?rol_id='.$rolId.'&amp;mode=5"><img
                                    src="'.THEME_URL.'/icons/roles.png" alt="'.$gL10n->get('ROL_ENABLE_ROLE').'" title="'.$gL10n->get('ROL_ENABLE_ROLE').'" /></a>';
    }
    else
    {
        $linkAdministration .= '<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php?rol_id='.$rolId.'"><img
                                    src="'.THEME_URL.'/icons/add.png" alt="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" /></a>';
    }

    $linkAdministration .= '<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_function.php?mode=8&amp;rol_id='. $rolId.'"><img
                src="'. THEME_URL. '/icons/vcard.png"
                alt="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', $rolName).'"
                title="'.$gL10n->get('PRO_EXPORT_VCARD_FROM_VAR', $rolName).'"/></a>';

    if($role->getValue('rol_administrator') == 1)
    {
        $linkAdministration .= '<a class="admidio-icon-link"><img src="'. THEME_URL. '/icons/dummy.png" alt="dummy" /></a>';
    }
    else
    {
        if($getInactive)
        {
            $linkAdministration .= '<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_function.php?rol_id='.$rolId.'&amp;mode=6"><img
                                        src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('ROL_ROLE_DELETE').'" title="'.$gL10n->get('ROL_ROLE_DELETE').'" /></a>';
        }
        else
        {
            $linkAdministration .='<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_function.php?rol_id='.$rolId.'&amp;mode=1"><img
                                        src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('ROL_ROLE_DELETE').'" title="'.$gL10n->get('ROL_ROLE_DELETE').'" /></a>';
        }
    }
    if($getInvisible)
    {
        $linkAdministration .= '<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_function.php?rol_id='.$rolId.'&amp;mode=8"><img
                                    src="'. THEME_URL. '/icons/light_on.png" alt="'.$gL10n->get('ROL_SET_ROLE_VISIBLE').'" title="'.$gL10n->get('ROL_SET_ROLE_VISIBLE').'" /></a>';
    }
    else
    {
        $linkAdministration .= '<a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_function.php?rol_id='.$rolId.'&amp;mode=7"><img
                                    src="'. THEME_URL. '/icons/light_off.png" alt="'.$gL10n->get('ROL_SET_ROLE_INVISIBLE').'" title="'.$gL10n->get('ROL_SET_ROLE_INVISIBLE').'" /></a>';
    }

    // create array with all column values
    $columnValues = array(
        array('value' => $categoryName, 'order' => $role->getValue('cat_sequence')),
        '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_new.php?rol_id='.$rolId.'" title="'.$role->getValue('rol_description').'">'.$rolName.'</a>',
        $assignRoles,
        $listView,
        $linkAdministration
    );

    $table->addRowByArray($columnValues);
}

$page->addHtml($table->show());

// show html of complete page
$page->show();
