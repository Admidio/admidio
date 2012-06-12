<?php
/******************************************************************************
 * Show all roles with their individual permissions
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
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
require_once('../../system/classes/table_roles.php');

// Initialize and check the parameters
$getInactive  = admFuncVariableIsValid($_GET, 'inactive', 'boolean', 0);
$getInvisible = admFuncVariableIsValid($_GET, 'invisible', 'boolean', 0);
 
// nur Moderatoren duerfen Rollen erfassen & verwalten
if(!$gCurrentUser->assignRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

unset($_SESSION['roles_request']);

// Html-Kopf ausgeben
$gLayout['title']  = $gL10n->get('ROL_ROLE_ADMINISTRATION');

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">'.$gLayout['title'].'</h1>';

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

echo '
<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php"><img
            src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('SYS_CREATE_ROLE').'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php">'.$gL10n->get('SYS_CREATE_ROLE').'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?inactive='.$activeRolesFlag.'"><img
            src="'. THEME_PATH. '/icons/'.$activeRolesImage.'" alt="'.$activeRolesLinkDescription.'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?inactive='.$activeRolesFlag.'">'.$activeRolesLinkDescription.'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?invisible='.$visibleRolesFlag.'"><img
            src="'. THEME_PATH. '/icons/'.$visibleRolesImage.'" alt="'.$visibleRolesLinkDescription.'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?invisible='.$visibleRolesFlag.'">'.$visibleRolesLinkDescription.'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL"><img
            src="'. THEME_PATH. '/icons/application_double.png" alt="'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL">'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'</a>
        </span>
    </li>
</ul>

<table class="tableList" cellspacing="0">
    <thead>
        <tr>
            <th>'.$listDescription.'</th>
            <th>'.$gL10n->get('SYS_AUTHORIZATION').'</th>
            <th>'.$gL10n->get('ROL_PREF').'</th>
            <th style="text-align: center;">'.$gL10n->get('SYS_FEATURES').'</th>
        </tr>
    </thead>';
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

    // Rollenobjekt anlegen
	$role = new TableRoles($gDb);

    while($row = $gDb->fetch_array($rol_result))
    {
        // Rollenobjekt mit Daten fuellen
        $role->setArray($row);
        
        if($cat_id != $role->getValue('cat_id'))
        {
            if($cat_id > 0)
            {
                echo '</tbody>';
            }
            $image_hidden = '';
            $block_id     = 'admCategory'.$role->getValue('cat_id');
            if($role->getValue('cat_hidden') == 1)
            {
                $image_hidden = '<img class="iconInformation" src="'. THEME_PATH. '/icons/user_key.png"
                                 alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $gL10n->get('SYS_ROLE')).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $gL10n->get('SYS_ROLE')).'" />';
            }
            echo '<tbody>
                <tr>
                    <td class="tableSubHeader" colspan="4">
                        <a class="iconShowHide" href="javascript:showHideBlock(\''.$block_id.'\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
                        id="'.$block_id.'Image" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$role->getValue('cat_name').' '.$image_hidden.'
                    </td>
                </tr>
            </tbody>
            <tbody id="'.$block_id.'">';

            $cat_id = $role->getValue('cat_id');
        }
        echo '
        <tr class="tableMouseOver">
            <td>&nbsp;<a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php?rol_id='.$role->getValue('rol_id').'" title="'.$role->getValue('rol_description').'">'.$role->getValue('rol_name').'</a></td>
            <td>';
                if($role->getValue('rol_assign_roles') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/roles.png"
                    alt="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" title="'.$gL10n->get('ROL_RIGHT_ASSIGN_ROLES').'" />';
                }
                if($role->getValue('rol_approve_users') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/new_registrations.png"
                    alt="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" title="'.$gL10n->get('ROL_RIGHT_APPROVE_USERS').'" />';
                }
                if($role->getValue('rol_edit_user') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/group.png"
                    alt="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" title="'.$gL10n->get('ROL_RIGHT_EDIT_USER').'" />';
                }
                if($role->getValue('rol_mail_to_all') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/email.png"
                    alt="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" title="'.$gL10n->get('ROL_RIGHT_MAIL_TO_ALL').'" />';
                }
                if($role->getValue('rol_profile') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/profile.png"
                    alt="'.$gL10n->get('ROL_RIGHT_PROFILE').'" title="'.$gL10n->get('ROL_RIGHT_PROFILE').'" />';
                }
                if($role->getValue('rol_announcements') == 1 && $gPreferences['enable_announcements_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/announcements.png"
                    alt="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" title="'.$gL10n->get('ROL_RIGHT_ANNOUNCEMENTS').'" />';
                }
                if($role->getValue('rol_dates') == 1 && $gPreferences['enable_dates_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/dates.png"
                    alt="'.$gL10n->get('ROL_RIGHT_DATES').'" title="'.$gL10n->get('ROL_RIGHT_DATES').'" />';
                }
                if($role->getValue('rol_photo') == 1 && $gPreferences['enable_photo_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/photo.png"
                    alt="'.$gL10n->get('ROL_RIGHT_PHOTO').'" title="'.$gL10n->get('ROL_RIGHT_PHOTO').'" />';
                }
                if($role->getValue('rol_download') == 1 && $gPreferences['enable_download_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/download.png"
                    alt="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" title="'.$gL10n->get('ROL_RIGHT_DOWNLOAD').'" />';
                }
                if($role->getValue('rol_guestbook') == 1 && $gPreferences['enable_guestbook_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/guestbook.png"
                    alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK').'" />';
                }
                // falls anonyme Gaestebuchkommentare erfassen werden duerfen, braucht man das Recht pro Rolle nicht mehr zu vergeben
                if($role->getValue('rol_guestbook_comments') == 1  && $gPreferences['enable_guestbook_module'] > 0 && $gPreferences['enable_gbook_comments4all'] == false)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/comments.png"
                    alt="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" title="'.$gL10n->get('ROL_RIGHT_GUESTBOOK_COMMENTS').'" />';
                }
                if($role->getValue('rol_weblinks') == 1 && $gPreferences['enable_weblinks_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/weblinks.png"
                    alt="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" title="'.$gL10n->get('ROL_RIGHT_WEBLINKS').'" />';
                }
                if($role->getValue('rol_all_lists_view') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/lists.png"
                    alt="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" title="'.$gL10n->get('ROL_RIGHT_ALL_LISTS_VIEW').'" />';
                }
            echo '</td>
            <td>';
                if($role->getValue("rol_this_list_view") == 1)
                {
                    echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/list_role.png"
                    alt="'.$gL10n->get('ROL_VIEW_LIST_ROLE').'" title="'.$gL10n->get('ROL_VIEW_LIST_ROLE').'" />';
                }
                if($role->getValue("rol_this_list_view") == 2)
                {
                    echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/list_key.png"
                    alt="'.$gL10n->get('ROL_VIEW_LIST_MEMBERS').'" title="'.$gL10n->get('ROL_VIEW_LIST_MEMBERS').'" />';
                }
                if($role->getValue("rol_mail_this_role") == 1 && $gPreferences['enable_mail_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/email_role.png"
                    alt="'.$gL10n->get('ROL_SEND_MAIL_ROLE').'" title="'.$gL10n->get('ROL_SEND_MAIL_ROLE').'" />';
                }
                if($role->getValue("rol_mail_this_role") == 2 && $gPreferences['enable_mail_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/email_key.png"
                    alt="'.$gL10n->get('ROL_SEND_MAIL_MEMBERS').'" title="'.$gL10n->get('ROL_SEND_MAIL_MEMBERS').'" />';
                }
                if($role->getValue("rol_mail_this_role") == 3 && $gPreferences['enable_mail_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/email.png"
                    alt="'.$gL10n->get('ROL_SEND_MAIL_GUESTS').'" title="'.$gL10n->get('ROL_SEND_MAIL_GUESTS').'" />';
                }
            echo '</td>
            <td style="text-align: center;">
                <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='.$role->getValue("rol_id").'"><img
                src="'. THEME_PATH. '/icons/list.png" alt="'.$gL10n->get('ROL_SHOW_MEMBERS').'" title="'.$gL10n->get('ROL_SHOW_MEMBERS').'" /></a>';

                if($getInactive == true)
                {
                    echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=5"><img
                        src="'.THEME_PATH.'/icons/roles.png" alt="'.$gL10n->get('ROL_ENABLE_ROLE').'" title="'.$gL10n->get('ROL_ENABLE_ROLE').'" /></a>';
                }
                else
                {
                    echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$role->getValue('rol_id').'"><img
                        src="'.THEME_PATH.'/icons/add.png" alt="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" title="'.$gL10n->get('SYS_ASSIGN_MEMBERS').'" /></a>';
                }

                if($role->getValue('rol_name') == $gL10n->get('SYS_WEBMASTER'))
                {
                    echo '<a class="iconLink"><img src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" /></a>';
                }
                else
                {
                    if($getInactive == true)
                    {
                        echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=6"><img
                            src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('ROL_ROLE_DELETE').'" title="'.$gL10n->get('ROL_ROLE_DELETE').'" /></a>';
                    }
                    else
                    {
                        echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=1"><img
                            src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('ROL_ROLE_DELETE').'" title="'.$gL10n->get('ROL_ROLE_DELETE').'" /></a>';
                    }
                }
                if($getInvisible == true)
                {
                    echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=8"><img
                            src="'. THEME_PATH. '/icons/light_on.png" alt="'.$gL10n->get('ROL_SET_ROLE_VISIBLE').'" title="'.$gL10n->get('ROL_SET_ROLE_VISIBLE').'" /></a>';
                }
                else
                {
                    echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=7"><img
                            src="'. THEME_PATH. '/icons/light_off.png" alt="'.$gL10n->get('ROL_SET_ROLE_INVISIBLE').'" title="'.$gL10n->get('ROL_SET_ROLE_INVISIBLE').'" /></a>';
                }
            echo '</td>
        </tr>';
    }
echo '</tbody>
</table>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>