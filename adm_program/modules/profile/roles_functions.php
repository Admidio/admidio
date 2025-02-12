<?php
/**
 ***********************************************************************************************
 * Functions for managing role membership in the profile
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\Users\Entity\User;

if (basename($_SERVER['SCRIPT_FILENAME']) === 'roles_functions.php') {
    exit('This page may not be called directly!');
}

/**
 * get all memberships where the user is assigned
 * @param int $userId
 * @return PDOStatement
 * @throws \Admidio\Infrastructure\Exception
 */
function getRolesFromDatabase(int $userId): PDOStatement
{
    global $gDb;

    $sql = 'SELECT *
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE mem_usr_id  = ? -- $userId
               AND mem_begin  <= ? -- DATE_NOW
               AND mem_end    >= ? -- DATE_NOW
               AND rol_valid   = true
               AND cat_name_intern <> \'EVENTS\'
               AND (  cat_org_id  = ? -- $GLOBALS[\'gCurrentOrgId\']
                   OR cat_org_id IS NULL )
          ORDER BY cat_org_id, cat_sequence, rol_name';
    return $gDb->queryPrepared($sql, array($userId, DATE_NOW, DATE_NOW, $GLOBALS['gCurrentOrgId']));
}

/**
 * get all memberships where the user will be assigned
 * @param int $userId
 * @return PDOStatement
 * @throws \Admidio\Infrastructure\Exception
 */
function getFutureRolesFromDatabase(int $userId): PDOStatement
{
    global $gDb;

    $sql = 'SELECT *
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE mem_usr_id  = ? -- $userId
               AND mem_begin   > ? -- DATE_NOW
               AND rol_valid   = true
               AND cat_name_intern <> \'EVENTS\'
               AND (  cat_org_id  = ? -- $GLOBALS[\'gCurrentOrgId\']
                   OR cat_org_id IS NULL )
          ORDER BY cat_org_id, cat_sequence, rol_name';
    return $gDb->queryPrepared($sql, array($userId, DATE_NOW, $GLOBALS['gCurrentOrgId']));
}

/**
 * get all memberships where the user was assigned
 * @param int $userId
 * @return PDOStatement
 * @throws \Admidio\Infrastructure\Exception
 */
function getFormerRolesFromDatabase(int $userId): PDOStatement
{
    global $gDb;

    $sql = 'SELECT *
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE mem_usr_id  = ? -- $userId
               AND mem_end     < ? -- DATE_NOW
               AND rol_valid   = true
               AND cat_name_intern <> \'EVENTS\'
               AND (  cat_org_id  = ? -- $GLOBALS[\'gCurrentOrgId\']
                   OR cat_org_id IS NULL )
          ORDER BY cat_org_id, cat_sequence, rol_name';
    return $gDb->queryPrepared($sql, array($userId, DATE_NOW, $GLOBALS['gCurrentOrgId']));
}

/**
 * @param string $htmlListId
 * @param User $user
 * @param PDOStatement $roleStatement
 * @return string
 * @throws \Admidio\Infrastructure\Exception
 */
function getRoleMemberships(string $htmlListId, User $user, PDOStatement $roleStatement): string
{
    global $gDb, $gL10n, $gCurrentUser, $gSettingsManager, $gCurrentSession;

    $countShowRoles = 0;
    $member = new Membership($gDb);
    $role   = new Role($gDb);
    $smarty = HtmlPage::createSmartyObject();
    $smarty->assign('listID', $htmlListId);
    $smarty->assign('l10n', $gL10n);
    $smarty->assign('settings', $gSettingsManager);
    $memberships = array();

    while ($row = $roleStatement->fetch()) {
        // you must have the right to view memberships of the role, or it must be your own profile
        if ($gCurrentUser->hasRightViewRole($row['mem_rol_id'])
        || $GLOBALS['gCurrentUserId'] === (int) $user->getValue('usr_id')) {
            $futureMembership = false;
            $showRoleEndDate  = false;
            $deleteMode = 'stop_membership';
            $deleteMessage = 'SYS_MEMBERSHIP_DELETE';
            $callbackFunction = 'callbackRoles';

            $member->clear();
            $member->setArray($row);
            $role->clear();
            $role->setArray($row);

            // if membership will not end, then don't show end date
            if ($member->getValue('mem_end', 'Y-m-d') !== DATE_MAX) {
                $showRoleEndDate = true;
            }

            // check if membership ends in the past
            if (strcmp($member->getValue('mem_end', 'Y-m-d'), DATE_NOW) < 0) {
                $deleteMode = 'remove_former_membership';
                $deleteMessage = 'SYS_LINK_MEMBERSHIP_DELETE';
                $callbackFunction = 'callbackFormerRoles';
            }

            // check if membership starts in the future
            if (strcmp($member->getValue('mem_begin', 'Y-m-d'), DATE_NOW) > 0) {
                $futureMembership = true;
                $deleteMode = 'remove_former_membership';
                $callbackFunction = 'callbackFutureRoles';
            }

            $memberUuid = $member->getValue('mem_uuid');

            $membership = array(
                'memberUUID' => $memberUuid,
                'category' => $role->getValue('cat_name'),
                'showRelationsCreateEdit' => $gSettingsManager->get('system_show_create_edit') > 0
            );

            if ($gCurrentUser->hasRightViewRole((int) $member->getValue('mem_rol_id'))) {
                $membership['role'] = '<a href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/groups-roles/lists_show.php', array('role_list' => $row['rol_uuid'])). '" title="'. $role->getValue('rol_description'). '">'. $role->getValue('rol_name'). '</a>';
            } else {
                $membership['role'] = $role->getValue('rol_name');
            }
            if ($member->getValue('mem_leader') == 1) {
                $membership['leader'] = $gL10n->get('SYS_LEADER');
            }

            if ($showRoleEndDate) {
                $membership['period'] = $gL10n->get('SYS_SINCE_TO', array($member->getValue('mem_begin', $gSettingsManager->getString('system_date')), $member->getValue('mem_end', $gSettingsManager->getString('system_date'))));
            } elseif ($futureMembership) {
                $membership['period'] = $gL10n->get('SYS_FROM', array($member->getValue('mem_begin', $gSettingsManager->getString('system_date'))));
            } else {
                $membership['period'] = $gL10n->get('SYS_SINCE', array($member->getValue('mem_begin', $gSettingsManager->getString('system_date'))));
            }

            if ($role->allowedToAssignMembers($gCurrentUser)) {
                // do not edit administrator role
                if ($row['rol_administrator'] == 0) {
                    $linkMembershipEdit = '<a class="admidio-icon-link" style="cursor:pointer;" href="javascript:profileJS.toggleDetailsOn(\''.$memberUuid.'\')"><i
                                        class="bi bi-pencil-square" data-bs-toggle="tooltip" title="'.$gL10n->get('SYS_CHANGE_DATE').'"></i></a>';
                } else {
                    $linkMembershipEdit = '<a style="padding: 3px;"><i class="bi bi-pencil-square invisible"></i></a>';
                }
                $membership['linkMembershipEdit'] = $linkMembershipEdit;

                // You are not allowed to delete your own administrator membership, other roles could be deleted
                if (($role->getValue('rol_administrator') == 1 && $GLOBALS['gCurrentUserId'] !== (int) $user->getValue('usr_id'))
                                || ($role->getValue('rol_administrator') == 0)) {
                    $linkMembershipDelete = '
                    <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                        data-message="' . $gL10n->get($deleteMessage, array($role->getValue('rol_name', 'database'))) . '"
                        data-href="callUrlHideElement(\'role_' . $role->getValue('rol_uuid') . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_function.php', array('mode' => $deleteMode, 'member_uuid' => $memberUuid)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\', \'' . $callbackFunction . '\')">
                        <i class="bi bi-trash" data-bs-toggle="tooltip" title="'.$gL10n->get('SYS_CANCEL_MEMBERSHIP').'"></i></a>';
                } else {
                    $linkMembershipDelete = '<a style="padding: 3px;"><i class="bi bi-trash invisible"></i></a>';
                }
                $membership['linkMembershipDelete'] = $linkMembershipDelete;
            }

            $form = new FormPresenter(
                'adm_membership_period_form_'.$memberUuid,
                'sys-template-parts/form.filter.tpl',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_function.php', array('mode' => 'save_membership', 'user_uuid' => $user->getValue('usr_uuid'), 'member_uuid' => $row['mem_uuid'])),
                null,
                array('type' => 'navbar', 'method' => 'post', 'setFocus' => false, 'class' => 'admidio-form-membership-period')
            );
            $form->addInput(
                'adm_membership_start_date',
                $gL10n->get('SYS_START'),
                $member->getValue('mem_begin', $gSettingsManager->getString('system_date')),
                array('type' => 'date', 'maxLength' => 10)
            );
            $form->addInput(
                'adm_membership_end_date',
                $gL10n->get('SYS_END'),
                $member->getValue('mem_end', $gSettingsManager->getString('system_date')),
                array('type' => 'date', 'maxLength' => 10)
            );
            $form->addSubmitButton(
                'adm_button_send',
                $gL10n->get('SYS_OK'),
                array('class' => 'btn btn-primary button-membership-period-form', 'data-admidio' => $memberUuid)
            );
            $membership['form'] = array(
                'attributes' => $form->getAttributes(),
                'elements' => $form->getElements()
            );

            // only show info if system setting is activated
            if ((int)$gSettingsManager->get('system_show_create_edit') > 0) {
                $membership['nameUserCreated'] = $member->getNameOfCreatingUser();
                $membership['timestampUserCreated'] = $member->getValue('ure_timestamp_create');
                $membership['nameLastUserEdited'] = $member->getNameOfLastEditingUser();
                $membership['timestampLastUserEdited'] = $member->getValue('ure_timestamp_change');
            }

            ++$countShowRoles;
            $memberships[] = $membership;
        }
    }
    $smarty->assign('memberships', $memberships);
    try {
        return $smarty->fetch('modules/profile.roles-list.row.tpl');
    } catch (Throwable $e) {
        throw new \Admidio\Infrastructure\Exception($e->getMessage());
    }
}
