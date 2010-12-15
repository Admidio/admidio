<?php
/******************************************************************************
 * Funktionen zum Verwalten der Rollenmitgliedschaft im Profil
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

if ('roles_functions.php' == basename($_SERVER['SCRIPT_FILENAME']))
{
    die('This page may not be called directly !');
}

require_once('../../system/classes/table_members.php');


function getRolesFromDatabase($g_db,$user_id,$g_current_organization)
{
    require_once('../../system/common.php');
    // Alle Rollen auflisten, die dem Mitglied zugeordnet sind
    $sql = 'SELECT *
              FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
             WHERE mem_rol_id  = rol_id
               AND mem_begin  <= "'.DATE_NOW.'"
               AND mem_end    >= "'.DATE_NOW.'"
               AND mem_usr_id  = '.$user_id.'
               AND rol_valid   = 1
               AND rol_visible = 1
               AND rol_cat_id  = cat_id
               AND (  cat_org_id  = '. $g_current_organization->getValue('org_id'). '
                   OR cat_org_id IS NULL )
             ORDER BY cat_org_id, cat_sequence, rol_name';
    return $g_db->query($sql);
}
function getFormerRolesFromDatabase($g_db,$user_id,$g_current_organization)
{
    require_once('../../system/common.php');
    $sql    = 'SELECT *
                 FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE mem_rol_id  = rol_id
                  AND mem_end     < "'.DATE_NOW.'"
                  AND mem_usr_id  = '.$user_id.'
                  AND rol_valid   = 1
                  AND rol_visible = 1
                  AND rol_cat_id  = cat_id
                  AND (  cat_org_id  = '. $g_current_organization->getValue('org_id'). '
                      OR cat_org_id IS NULL )
                ORDER BY cat_org_id, cat_sequence, rol_name';
    return $g_db->query($sql);
}
function getRoleMemberships($g_db,$g_current_user,$user,$result_role,$count_role,$directOutput,$g_l10n)
{
    global $g_preferences, $g_root_path, $g_current_organization;
    
    $count_show_roles  = 0;
    $member = new TableMembers($g_db);
    $roleMemHTML = '<ul class="formFieldList" id="role_list">';

    while($row = $g_db->fetch_array($result_role))
    {
        if($g_current_user->viewRole($row['mem_rol_id']) && $row['rol_visible']==1)
        {
            $show_rol_end_date = false;

            $member->clear();
            $member->setArray($row);
            
            // falls die Mitgliedschaft nicht endet, dann soll das fiktive Enddatum auch nicht angezeigt werden
            $dateEndless = new DateTime('9999-12-31 00:00:00');
            if ($member->getValue('mem_end', $g_preferences['system_date']) != $dateEndless->format($g_preferences['system_date']))
            {
               $show_rol_end_date = true;
            }

            // jede einzelne Rolle anzeigen
            $roleMemHTML .= '<li id="role_'. $row['mem_rol_id']. '">
                <dl>
                    <dt>
                        '. $row['cat_name']. ' - ';
                            if($g_current_user->viewRole($member->getValue('mem_rol_id')))
                            {
                                $roleMemHTML .= '<a href="'. $g_root_path. '/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $member->getValue('mem_rol_id'). '" title="'. $row['rol_description']. '">'. $row['rol_name']. '</a>';
                            }
                            else
                            {
                                echo $row['rol_name'];
                            }
                            if($member->getValue('mem_leader') == 1)
                            {
                                $roleMemHTML .= ' - '.$g_l10n->get('SYS_LEADER');
                            }
                        $roleMemHTML .= '&nbsp;
                    </dt>
                    <dd>';
                        if($show_rol_end_date == true)
                        {
                            $roleMemHTML .= $g_l10n->get('SYS_SINCE_TO',$member->getValue('mem_begin', $g_preferences['system_date']),$member->getValue('mem_end', $g_preferences['system_date']));
                        }
                        else
                        {
                            $roleMemHTML .= $g_l10n->get('SYS_SINCE',$member->getValue('mem_begin', $g_preferences['system_date']));
                        }
                        if($g_current_user->assignRoles())
                        {
                            // Löschen wird nur bei anderen Webmastern ermöglicht
                            if (($row['rol_name'] == $g_l10n->get('SYS_WEBMASTER') && $g_current_user->getValue('usr_id') != $user->getValue('usr_id')) || ($row['rol_name'] != $g_l10n->get('SYS_WEBMASTER')))
                            {
                                $roleMemHTML .= '
                                <a class="iconLink" href="javascript:profileJS.deleteRole('.$row['rol_id'].', \''.$row['rol_name'].'\')"><img
                                    src="'.THEME_PATH.'/icons/delete.png" alt="'.$g_l10n->get('ROL_ROLE_DELETE').'" title="'.$g_l10n->get('ROL_ROLE_DELETE').'" /></a>';
                            }
                            else
                            {
                                $roleMemHTML .= '
                                <a class="iconLink"><img src="'.THEME_PATH.'/icons/dummy.png" alt=""/></a>';
                            }
                            // Bearbeiten des Datums nicht bei Webmastern möglich
                            if ($row['rol_name'] != $g_l10n->get('SYS_WEBMASTER'))
                            {
                                $roleMemHTML .= '<a class="iconLink" style="cursor:pointer;" onclick="profileJS.toggleDetailsOn('.$row['rol_id'].')"><img
                                    src="'.THEME_PATH.'/icons/edit.png" alt="'.$g_l10n->get('PRO_CHANGE_DATE').'" title="'.$g_l10n->get('PRO_CHANGE_DATE').'" /></a>';
                            }
                            else
                            {
                                $roleMemHTML .= '<a class="iconLink"><img src="'.THEME_PATH.'/icons/dummy.png" alt=""/></a>';
                            }

                        }
                    $roleMemHTML .= '</dd>
                </dl>
            </li>
            <li id="mem_rol_'.$row['rol_id'].'" style="text-align: right; visibility: hidden; display: none;">
                <form action="'.$g_root_path.'/adm_program/modules/profile/roles_date.php?usr_id='.$user->getValue("usr_id").'&amp;mode=1&amp;rol_id='.$row['rol_id'].'" method="post">
                    <div>
                        <label for="begin'.$row['rol_name'].'">'.$g_l10n->get('SYS_START').':</label>
                        <input type="text" id="begin'.$row['rol_name'].'" name="rol_begin" size="10" maxlength="20" value="'.$member->getValue('mem_begin', $g_preferences['system_date']).'"/>
                        <a class="iconLink" id="anchor_begin'.$row['rol_name'].'" href="javascript:calPopup.select(document.getElementById(\'begin'.$row['rol_name'].'\'),\'anchor_begin'.$row['rol_name'].'\',\''.$g_preferences['system_date'].'\',\'begin'.$row['rol_name'].'\',\'end'.$row['rol_name'].'\');"><img 
                        src="'.THEME_PATH.'/icons/calendar.png" alt="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" title="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" /></a>&nbsp;

                        <label for="end'.$row['rol_name'].'">'.$g_l10n->get('SYS_END').':</label>
                        <input type="text" id="end'.$row['rol_name'].'" name="rol_end" size="10" maxlength="20" value="'.$member->getValue('mem_end', $g_preferences['system_date']).'"/>
                        <a class="iconLink" id="anchor_end'.$row['rol_name'].'" href="javascript:calPopup.select(document.getElementById(\'end'.$row['rol_name'].'\'),\'anchor_end'.$row['rol_name'].'\',\''.$g_preferences['system_date'].'\',\'begin'.$row['rol_name'].'\',\'end'.$row['rol_name'].'\');"><img 
                        src="'.THEME_PATH.'/icons/calendar.png" alt="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" title="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" /></a>

                        <a class="iconLink" href="javascript:profileJS.changeRoleDates(\''.$row['rol_name'].'\',\''.$row['rol_id'].'\')" id="enter'.$row['rol_name'].'"><img src="'.THEME_PATH.'/icons/disk.png" alt="'.$g_l10n->get('SYS_SAVE').'" title="'.$g_l10n->get('SYS_SAVE').'"/></a>
                        <a class="iconLink" href="javascript:profileJS.toggleDetailsOff('.$row['rol_id'].')"><img src="'.THEME_PATH.'/icons/delete.png" alt="'.$g_l10n->get('SYS_ABORT').'" title="'.$g_l10n->get('SYS_ABORT').'"/></a>
                    </div>
                </form>
            </li>';
            $count_show_roles++;
        }
    }
    $roleMemHTML .= '<span id="calendardiv" style="position: absolute; visibility: hidden;"></span>';
    if($count_show_roles == 0)
    {
        $roleMemHTML .= $g_l10n->get('ROL_NO_MEMBER_RESP_ROLE_VISIBLE',$g_current_organization->getValue('org_longname'));
    }
            
    $roleMemHTML .= '</ul>';

    if($directOutput)
    {
        echo $roleMemHTML;
        return '';
    }
    else
    {
        return $roleMemHTML;	
    }
}
function getFormerRoleMemberships($g_db,$g_current_user,$user,$result_role,$count_role,$directOutput,$g_l10n)
{
    global $g_preferences, $g_root_path;

    $count_show_roles = 0;
    $member = new TableMembers($g_db);
    $formerRoleMemHTML = '<ul class="formFieldList" id="former_role_list">';
    while($row = $g_db->fetch_array($result_role))
    {
        $member->clear();
        $member->setArray($row);
        
        if($g_current_user->viewRole($member->getValue('mem_rol_id')))
        {
            // jede einzelne Rolle anzeigen
            $formerRoleMemHTML .= '
            <li id="former_role_'. $member->getValue('mem_rol_id'). '">
                <dl>
                    <dt>'.
                        $row['cat_name'];
                        if($g_current_user->viewRole($member->getValue('mem_rol_id')))
                        {
                            $formerRoleMemHTML .= ' - <a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $member->getValue('mem_rol_id'). '">'. $row['rol_name']. '</a>';
                        }
                        else
                        {
                            $formerRoleMemHTML .= ' - '. $row['rol_name'];
                        }
                        if($member->getValue('mem_leader') == 1)
                        {
                            $formerRoleMemHTML .= ' - '.$g_l10n->get('SYS_LEADER');
                        }
                    $formerRoleMemHTML .= '</dt>
                    <dd>'.$g_l10n->get('SYS_FROM_TO', $member->getValue('mem_begin', $g_preferences['system_date']), $member->getValue('mem_end', $g_preferences['system_date']));
                        if($g_current_user->isWebmaster())
                        {
                            $formerRoleMemHTML .= '
                            <a class="iconLink" href="javascript:profileJS.deleteFormerRole('. $row['rol_id']. ', \''. $row['rol_name']. '\')"><img
                                src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('ROL_ROLE_DELETE').'" title="'.$g_l10n->get('ROL_ROLE_DELETE').'" /></a>';
                        }
                    $formerRoleMemHTML .= '</dd>
                </dl>
            </li>';
            $count_show_roles++;
        }
    }
    if($count_show_roles == 0 && $count_role > 0)
    {
        $formerRoleMemHTML .= $g_l10n->get('ROL_CANT_SHOW_FORMER_ROLES');
    }
    $formerRoleMemHTML .= '</ul>
    <script type="text/javascript">if(profileJS){profileJS.formerRoleCount="'.$count_role.'";}</script>';

    if($directOutput)
    {
        echo $formerRoleMemHTML;
        return "";
    }
    else
    {
        return $formerRoleMemHTML;	
    }
}
?>