<?php
/******************************************************************************
 * Rollen mit Berechtigungen auflisten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * inactive: 0 - (Default) alle aktiven Rollen anzeigen
 *           1 - alle inaktiven Rollen anzeigen
 *
 *****************************************************************************/

 require_once('../../system/common.php');
 require_once('../../system/login_valid.php');
 require_once('../../system/classes/table_roles.php');

// nur Moderatoren duerfen Rollen erfassen & verwalten
if(!$g_current_user->assignRoles())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

if(isset($_GET['inactive']) && $_GET['inactive'] == 1)
{
    $req_valid = 0;
}
else
{
    $req_valid = 1;
}

if(isset($_GET['invisible']) && $_GET['invisible']==1)
{
    $req_visible = 0;
}
else
{
    $req_visible = 1;
}

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

unset($_SESSION['roles_request']);

// Html-Kopf ausgeben
$g_layout['title']  = $g_l10n->get('ROL_ROLE_ADMINISTRATION');

require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">'.$g_layout['title'].'</h1>';

if($req_valid == true)
{
    $description_lnk = $g_l10n->get('ROL_INACTIV_ROLES');
    $description_lst = $g_l10n->get('ROL_ACTIV_ROLES');
    $image           = 'roles_gray.png';
}
else
{
    $description_lnk = $g_l10n->get('ROL_ACTIV_ROLES');
    $description_lst = $g_l10n->get('ROL_INACTIV_ROLES');
    $image           = 'roles.png';
}

if($req_visible == true)
{
    $visible_lnk    = $g_l10n->get('ROL_INVISIBLE_ROLES');
    $visible_lst    = $g_l10n->get('ROL_VISIBLE_ROLES');
    $visible_image  = 'light_off.png';
}
else
{
    $visible_lnk    = $g_l10n->get('ROL_VISIBLE_ROLES');
    $visible_lst    = $g_l10n->get('ROL_INVISIBLE_ROLES');
    $visible_image  = 'light_on.png';
}

echo '
<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php"><img
            src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('SYS_CREATE_ROLE').'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles_new.php">'.$g_l10n->get('SYS_CREATE_ROLE').'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?inactive='.$req_valid.'"><img
            src="'. THEME_PATH. '/icons/'.$image.'" alt="'.$description_lnk.'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?inactive='.$req_valid.'">'.$description_lnk.'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?invisible='.$req_visible.'"><img
            src="'. THEME_PATH. '/icons/'.$visible_image.'" alt="'.$visible_lnk.'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php?invisible='.$req_visible.'">'.$visible_lnk.'</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL"><img
            src="'. THEME_PATH. '/icons/application_double.png" alt="'.$g_l10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=ROL">'.$g_l10n->get('SYS_MAINTAIN_CATEGORIES').'</a>
        </span>
    </li>
</ul>

<table class="tableList" cellspacing="0">
    <thead>
        <tr>
            <th>'.$description_lst.'</th>
            <th>'.$g_l10n->get('SYS_AUTHORIZATION').'</th>
            <th>'.$g_l10n->get('ROL_PREF').'</th>
            <th style="text-align: center;">'.$g_l10n->get('SYS_FEATURES').'</th>
        </tr>
    </thead>';
    $cat_id = '';

    // alle Rollen gruppiert nach Kategorie auflisten
    $sql    = 'SELECT * FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE rol_valid  = '.$req_valid.'
                  AND rol_visible = '.$req_visible.'
                  AND rol_cat_id = cat_id
                  AND cat_type   = "ROL"
                  AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
                      OR cat_org_id IS NULL )
                ORDER BY cat_id ASC, rol_name ASC ';
    $rol_result = $g_db->query($sql);

    // Rollenobjekt anlegen
	$role = new TableRoles($g_db);

    while($row = $g_db->fetch_array($rol_result))
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
            $block_id     = 'cat_'.$role->getValue('cat_id');
            if($role->getValue('cat_hidden') == 1)
            {
                $image_hidden = '<img class="iconInformation" src="'. THEME_PATH. '/icons/user_key.png"
                                 alt="'.$g_l10n->get('SYS_PHR_VISIBLE_TO_USERS', $g_l10n->get('SYS_ROLE')).'" title="'.$g_l10n->get('SYS_PHR_VISIBLE_TO_USERS', $g_l10n->get('SYS_ROLE')).'" />';
            }
            echo '<tbody>
                <tr>
                    <td class="tableSubHeader" colspan="4">
                        <a class="iconShowHide" href="javascript:showHideBlock(\''.$block_id.'\')"><img
                        id="img_'.$block_id.'" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$g_l10n->get('SYS_HIDE').'" title="'.$g_l10n->get('SYS_HIDE').'" /></a>'.$role->getValue('cat_name').' '.$image_hidden.'
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
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_ASSIGN_ROLES').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_ASSIGN_ROLES').'" />';
                }
                if($role->getValue('rol_approve_users') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/new_registrations.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_APPROVE_USERS').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_APPROVE_USERS').'" />';
                }
                if($role->getValue('rol_edit_user') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/group.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_EDIT_USER').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_EDIT_USER').'" />';
                }
                if($role->getValue('rol_mail_to_all') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/email.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_MAIL_TO_ALL').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_MAIL_TO_ALL').'" />';
                }
                if($role->getValue('rol_profile') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/profile.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_PROFILE').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_PROFILE').'" />';
                }
                if($role->getValue('rol_announcements') == 1 && $g_preferences['enable_announcements_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/announcements.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_ANNOUNCEMENTS').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_ANNOUNCEMENTS').'" />';
                }
                if($role->getValue('rol_dates') == 1 && $g_preferences['enable_dates_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/dates.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_DATES').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_DATES').'" />';
                }
                if($role->getValue('rol_photo') == 1 && $g_preferences['enable_photo_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/photo.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_PHOTO').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_PHOTO').'" />';
                }
                if($role->getValue('rol_download') == 1 && $g_preferences['enable_download_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/download.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_DOWNLOAD').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_DOWNLOAD').'" />';
                }
                if($role->getValue('rol_guestbook') == 1 && $g_preferences['enable_guestbook_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/guestbook.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_GUESTBOOK').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_GUESTBOOK').'" />';
                }
                // falls anonyme Gaestebuchkommentare erfassen werden duerfen, braucht man das Recht pro Rolle nicht mehr zu vergeben
                if($role->getValue('rol_guestbook_comments') == 1  && $g_preferences['enable_guestbook_module'] > 0 && $g_preferences['enable_gbook_comments4all'] == false)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/comments.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_GUESTBOOK_COMMENTS').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_GUESTBOOK_COMMENTS').'" />';
                }
                if($role->getValue('rol_weblinks') == 1 && $g_preferences['enable_weblinks_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/weblinks.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_WEBLINKS').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_WEBLINKS').'" />';
                }
                /*if($role->getValue("rol_inventory") == 1 && $g_preferences['enable_inventory_module'] > 0)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/weblinks.png\"
                    alt=\"Inventar verwalten\" title=\"Inventar verwalten\" />";
                }*/
                if($role->getValue('rol_all_lists_view') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/lists.png"
                    alt="'.$g_l10n->get('ROL_PHR_RIGHT_ALL_LISTS_VIEW').'" title="'.$g_l10n->get('ROL_PHR_RIGHT_ALL_LISTS_VIEW').'" />';
                }
            echo '</td>
            <td>';
                if($role->getValue("rol_this_list_view") == 1)
                {
                    echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/list_role.png"
                    alt="'.$g_l10n->get('ROL_PHR_VIEW_LIST_ROLE').'" title="'.$g_l10n->get('ROL_PHR_VIEW_LIST_ROLE').'" />';
                }
                if($role->getValue("rol_this_list_view") == 2)
                {
                    echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/list_key.png"
                    alt="'.$g_l10n->get('ROL_PHR_VIEW_LIST_MEMBERS').'" title="'.$g_l10n->get('ROL_PHR_VIEW_LIST_MEMBERS').'" />';
                }
                if($role->getValue("rol_mail_this_role") == 1 && $g_preferences['enable_mail_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/email_role.png"
                    alt="'.$g_l10n->get('ROL_PHR_SEND_MAIL_ROLE').'" title="'.$g_l10n->get('ROL_PHR_SEND_MAIL_ROLE').'" />';
                }
                if($role->getValue("rol_mail_this_role") == 2 && $g_preferences['enable_mail_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/email_key.png"
                    alt="'.$g_l10n->get('ROL_PHR_SEND_MAIL_MEMBERS').'" title="'.$g_l10n->get('ROL_PHR_SEND_MAIL_MEMBERS').'" />';
                }
                if($role->getValue("rol_mail_this_role") == 3 && $g_preferences['enable_mail_module'] > 0)
                {
                    echo '<img class="iconInformation" src="'.THEME_PATH.'/icons/email.png"
                    alt="'.$g_l10n->get('ROL_PHR_SEND_MAIL_GUESTS').'" title="'.$g_l10n->get('ROL_PHR_SEND_MAIL_GUESTS').'" />';
                }
            echo '</td>
            <td style="text-align: center;">
                <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='.$role->getValue("rol_id").'"><img
                src="'. THEME_PATH. '/icons/list.png" alt="'.$g_l10n->get('ROL_SHOW_MEMBERS').'" title="'.$g_l10n->get('ROL_SHOW_MEMBERS').'" /></a>';

                if($req_valid == true)
                {
                    echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/lists/members.php?rol_id='.$role->getValue('rol_id').'"><img
                        src="'.THEME_PATH.'/icons/add.png" alt="'.$g_l10n->get('ROL_ASSIGN_MEMBERS').'" title="'.$g_l10n->get('ROL_ASSIGN_MEMBERS').'" /></a>';
                }
                else
                {
                    echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=5"><img
                        src="'.THEME_PATH.'/icons/roles.png" alt="'.$g_l10n->get('ROL_ENABLE_ROLE').'" title="'.$g_l10n->get('ROL_ENABLE_ROLE').'" /></a>';
                }

                if($role->getValue('rol_name') == $g_l10n->get('SYS_WEBMASTER'))
                {
                    echo '<a class="iconLink"><img src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" /></a>';
                }
                else
                {
                    if($req_valid == true)
                    {
                        echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=1"><img
                            src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('ROL_ROLE_DELETE').'" title="'.$g_l10n->get('ROL_ROLE_DELETE').'" /></a>';
                    }
                    else
                    {
                        echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=6"><img
                            src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('ROL_ROLE_DELETE').'" title="'.$g_l10n->get('ROL_ROLE_DELETE').'" /></a>';
                    }
                }
                if($req_visible == true)
                {
                    echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=7"><img
                            src="'. THEME_PATH. '/icons/light_off.png" alt="'.$g_l10n->get('ROL_SET_ROLE_INVISIBLE').'" title="'.$g_l10n->get('ROL_SET_ROLE_INVISIBLE').'" /></a>';
                }
                else
                {
                    echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$role->getValue('rol_id').'&amp;mode=8"><img
                            src="'. THEME_PATH. '/icons/light_on.png" alt="'.$g_l10n->get('ROL_SET_ROLE_VISIBLE').'" title="'.$g_l10n->get('ROL_SET_ROLE_VISIBLE').'" /></a>';
                }
            echo '</td>
        </tr>';
    }
echo '</tbody>
</table>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>