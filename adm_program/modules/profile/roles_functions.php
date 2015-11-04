<?php
/******************************************************************************
 * Funktionen zum Verwalten der Rollenmitgliedschaft im Profil
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

if ('roles_functions.php' == basename($_SERVER['SCRIPT_FILENAME']))
{
    exit('This page may not be called directly !');
}

// get all memberships where the user is assigned
function getRolesFromDatabase($user_id)
{
    global $gDb, $gCurrentOrganization;

    $sql = 'SELECT *
              FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
             WHERE mem_rol_id  = rol_id
               AND mem_begin  <= \''.DATE_NOW.'\'
               AND mem_end    >= \''.DATE_NOW.'\'
               AND mem_usr_id  = '.$user_id.'
               AND rol_valid   = 1
               AND rol_visible = 1
               AND rol_cat_id  = cat_id
               AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                   OR cat_org_id IS NULL )
             ORDER BY cat_org_id, cat_sequence, rol_name';
    return $gDb->query($sql);
}

// get all memberships where the user will be assigned
function getFutureRolesFromDatabase($user_id)
{
    global $gDb, $gCurrentOrganization;

    $sql = 'SELECT *
              FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
             WHERE mem_rol_id  = rol_id
               AND mem_begin   > \''.DATE_NOW.'\'
               AND mem_usr_id  = '.$user_id.'
               AND rol_valid   = 1
               AND rol_visible = 1
               AND rol_cat_id  = cat_id
               AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                   OR cat_org_id IS NULL )
             ORDER BY cat_org_id, cat_sequence, rol_name';
    return $gDb->query($sql);
}

// get all memberships where the user was assigned
function getFormerRolesFromDatabase($user_id)
{
    global $gDb, $gCurrentOrganization;

    $sql    = 'SELECT *
                 FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE mem_rol_id  = rol_id
                  AND mem_end     < \''.DATE_NOW.'\'
                  AND mem_usr_id  = '.$user_id.'
                  AND rol_valid   = 1
                  AND rol_visible = 1
                  AND rol_cat_id  = cat_id
                  AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                      OR cat_org_id IS NULL )
                ORDER BY cat_org_id, cat_sequence, rol_name';
    return $gDb->query($sql);
}

function getRoleMemberships($htmlListId, $user, $result_role, $count_role, $directOutput)
{
    global $gDb, $gL10n, $gCurrentUser, $gPreferences, $g_root_path, $gProfileFields;

    $countShowRoles  = 0;
    $member = new TableMembers($gDb);
    $role   = new TableRoles($gDb);
    $roleMemHTML = '<ul class="list-group admidio-list-roles-assign" id="'.$htmlListId.'">';

    while($row = $gDb->fetch_array($result_role))
    {
        if($gCurrentUser->hasRightViewRole($row['mem_rol_id']) && $row['rol_visible']==1)
        {
            $formerMembership = false;
            $futureMembership = false;
            $showRoleEndDate  = false;
            $deleteMode = 'pro_role';

            $member->clear();
            $member->setArray($row);
            $role->clear();
            $role->setArray($row);

            // if membership will not end, then don't show end date
            if(strcmp($member->getValue('mem_end', 'Y-m-d'), '9999-12-31') != 0)
            {
                   $showRoleEndDate = true;
            }

            // check if membership ends in the past
            if(strcmp(DATE_NOW, $member->getValue('mem_end', 'Y-m-d')) > 0)
            {
                   $formerMembership = true;
                $deleteMode = 'pro_former';
            }

            // check if membership starts in the future
            if(strcmp($member->getValue('mem_begin', 'Y-m-d'), DATE_NOW) > 0)
            {
                $futureMembership = true;
                $deleteMode = 'pro_future';
            }

            // create list entry for one role
            $roleMemHTML .= '
            <li class="list-group-item" id="role_'. $row['mem_rol_id']. '">
                <ul class="list-group admidio-list-roles-assign-pos">
                    <li class="list-group-item">
                        <span>'.
                            $role->getValue('cat_name'). ' - ';

                            if($gCurrentUser->hasRightViewRole($member->getValue('mem_rol_id')))
                            {
                                $roleMemHTML .= '<a href="'. $g_root_path. '/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $member->getValue('mem_rol_id'). '" title="'. $role->getValue('rol_description'). '">'. $role->getValue('rol_name'). '</a>';
                            }
                            else
                            {
                                echo $role->getValue('rol_name');
                            }
                            if($member->getValue('mem_leader') == 1)
                            {
                                $roleMemHTML .= ' - '.$gL10n->get('SYS_LEADER');
                            }

                            $roleMemHTML .= '&nbsp;
                        </span>
                        <span class="pull-right text-right">';
                            if($showRoleEndDate == true)
                            {
                                $roleMemHTML .= $gL10n->get('SYS_SINCE_TO', $member->getValue('mem_begin', $gPreferences['system_date']), $member->getValue('mem_end', $gPreferences['system_date']));
                            }
                            elseif($futureMembership == true)
                            {
                                $roleMemHTML .= $gL10n->get('SYS_FROM', $member->getValue('mem_begin', $gPreferences['system_date']));
                            }
                            else
                            {
                                $roleMemHTML .= $gL10n->get('SYS_SINCE', $member->getValue('mem_begin', $gPreferences['system_date']));
                            }

                            if($role->allowedToAssignMembers($gCurrentUser))
                            {
                                // You are not allowed to delete your own webmaster membership, other roles could be deleted
                                if (($role->getValue('rol_webmaster') == 1 && $gCurrentUser->getValue('usr_id') != $user->getValue('usr_id'))
                                || ($role->getValue('rol_webmaster') == 0))
                                {
                                    $roleMemHTML .= '
                                    <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                        href="'.$g_root_path.'/adm_program/system/popup_message.php?type='.$deleteMode.'&amp;element_id=role_'.
                                        $role->getValue('rol_id'). '&amp;database_id='.$member->getValue('mem_id').'&amp;name='.urlencode($role->getValue('rol_name')).'"><img
                                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('PRO_CANCEL_MEMBERSHIP').'" title="'.$gL10n->get('PRO_CANCEL_MEMBERSHIP').'" /></a>';
                                }
                                else
                                {
                                    $roleMemHTML .= '
                                    <a class="admidio-icon-link"><img src="'.THEME_PATH.'/icons/dummy.png" alt=""/></a>';
                                }

                                // do not edit webmaster role
                                if ($row['rol_webmaster'] == 0)
                                {
                                    $roleMemHTML .= '<a class="admidio-icon-link" style="cursor:pointer;" onclick="profileJS.toggleDetailsOn('.$member->getValue('mem_id').')"><img
                                        src="'.THEME_PATH.'/icons/edit.png" alt="'.$gL10n->get('PRO_CHANGE_DATE').'" title="'.$gL10n->get('PRO_CHANGE_DATE').'" /></a>';
                                }
                                else
                                {
                                    $roleMemHTML .= '<a class="admidio-icon-link"><img src="'.THEME_PATH.'/icons/dummy.png" alt=""/></a>';
                                }

                            }

                            // only show info if system setting is activated
                            if($gPreferences['system_show_create_edit'] > 0)
                            {
                                $roleMemHTML .= '<a class="admidio-icon-link admMemberInfo" id="member_info_'.$member->getValue('mem_id').'" href="javascript:"><img src="'.THEME_PATH.'/icons/info.png" alt="'.$gL10n->get('SYS_INFORMATIONS').'" title="'.$gL10n->get('SYS_INFORMATIONS').'"/></a>';
                            }
                        $roleMemHTML .= '</span>
                    </li>
                    <li class="list-group-item" id="membership_period_'.$member->getValue('mem_id').'" style="visibility: hidden; display: none;"><div class="collapse navbar-collapse">';
                        $form = new HtmlForm('membership_period_form_'.$member->getValue('mem_id'), $g_root_path.'/adm_program/modules/profile/profile_function.php?mode=7&amp;user_id='.$user->getValue('usr_id').'&amp;mem_id='.$row['mem_id'], null, array('type' => 'navbar', 'setFocus' => false, 'class' => 'admidio-form-membership-period'));
                        $form->addInput('membership_start_date_'.$member->getValue('mem_id'), $gL10n->get('SYS_START'), $member->getValue('mem_begin', $gPreferences['system_date']), array('type' => 'date', 'maxLength' => 10));
                        $form->addInput('membership_end_date_'.$member->getValue('mem_id'), $gL10n->get('SYS_END'), $member->getValue('mem_end', $gPreferences['system_date']), array('type' => 'date', 'maxLength' => 10));
                        $form->addButton('btn_send_'.$member->getValue('mem_id'), $gL10n->get('SYS_OK'), array('class' => 'button-membership-period-form', 'data-admidio' => $member->getValue('mem_id')));
                        $roleMemHTML .= $form->show(false);
                    $roleMemHTML .= '</div></li>
                    <li class="list-group-item" id="member_info_'.$member->getValue('mem_id').'_Content" style="display: none;">';
                        // show informations about user who creates the recordset and changed it
                        $roleMemHTML .= admFuncShowCreateChangeInfoById($member->getValue('mem_usr_id_create'), $member->getValue('mem_timestamp_create'), $member->getValue('mem_usr_id_change'), $member->getValue('mem_timestamp_change')).'
                    </li>
                </ul>
            </li>';
            $countShowRoles++;
        }
    }
    if($countShowRoles == 0)
    {
        $roleMemHTML = '<div class="block-padding">'.$gL10n->get('PRO_NO_ROLES_VISIBLE').'</div>';
    }
    else
    {
        $roleMemHTML .= '</ul>';
    }

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
