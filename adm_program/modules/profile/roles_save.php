<?php
/**
 ***********************************************************************************************
 * Assign or remove membership of roles in profile
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * user_uuid : UUID of the user whose roles should be edited
 * new_user  : 0 - (Default) Edit roles of an existing user
 *             1 - Edit roles of a new user
 *             2 - (not relevant)
 *             3 - Edit roles of a registration
 * inline    : false - Ausgaben werden als eigene Seite angezeigt
 *             true  - nur "body" HTML Code
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string');
$getNewUser  = admFuncVariableIsValid($_GET, 'new_user', 'int');
$getInline   = admFuncVariableIsValid($_GET, 'inline', 'bool');

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    $exception->showHtml();
    // => EXIT
}

// in ajax mode only return simple text on error
if ($getInline) {
    $gMessage->showHtmlTextOnly(true);
}

// if user is allowed to assign at least one role then allow access
if (!$gCurrentUser->assignRoles()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// if new user or registration than at least 1 role must be assigned
if ($getNewUser > 0) {
    // detect number of selected roles
    $roleCount = 0;
    foreach ($_POST as $key => $value) { // TODO possible security issue
        if (preg_match('/^(role-)\d+$/i', $key)) {
            ++$roleCount;
        }
    }
    // if no role is selected than show notice
    if ($roleCount === 0) {
        if (!$getInline) {
            $gMessage->show($gL10n->get('PRO_ROLE_NOT_ASSIGNED'));
        // => EXIT
        } else {
            exit($gL10n->get('PRO_ROLE_NOT_ASSIGNED'));
        }
    }
}

$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

if ($gCurrentUser->manageRoles()) {
    // User with role rights may assign ALL roles
    $sql = 'SELECT rol_id, rol_name, rol_max_members, rol_administrator, mem_id, mem_begin, mem_end
              FROM '.TBL_ROLES.'
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
         LEFT JOIN '.TBL_MEMBERS.'
                ON mem_rol_id = rol_id
               AND mem_usr_id = ? -- $user->getValue(\'usr_id\')
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
             WHERE rol_valid   = true
               AND cat_name_intern <> \'EVENTS\'
               AND (  cat_org_id = ? -- $gCurrentOrgId
                   OR cat_org_id IS NULL )
          ORDER BY cat_sequence, rol_name';
    $queryParams = array($user->getValue('usr_id'), DATE_NOW, DATE_NOW, $gCurrentOrgId);
} else {
    // A roll leader may only assign roles where he is also a leader
    $sql = 'SELECT rol_id, rol_name, rol_max_members, rol_administrator, mgl.mem_id, mgl.mem_begin, mgl.mem_end
              FROM '.TBL_MEMBERS.' AS bm
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = bm.mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
         LEFT JOIN '.TBL_MEMBERS.' AS mgl
                ON rol_id         = mgl.mem_rol_id
               AND mgl.mem_usr_id = ? -- $user->getValue(\'usr_id\')
               AND mgl.mem_begin <= ? -- DATE_NOW
               AND mgl.mem_end    > ? -- DATE_NOW
             WHERE bm.mem_usr_id  = ? -- $gCurrentUserId
               AND bm.mem_begin  <= ? -- DATE_NOW
               AND bm.mem_end     > ? -- DATE_NOW
               AND bm.mem_leader  = true
               AND rol_leader_rights IN (1,3)
               AND rol_valid      = true
               AND cat_name_intern <> \'EVENTS\'
               AND (  cat_org_id  = ? -- $gCurrentOrgId
                   OR cat_org_id IS NULL )
          ORDER BY cat_sequence, rol_name';
    $queryParams = array($user->getValue('usr_id'), DATE_NOW, DATE_NOW, $gCurrentUserId, DATE_NOW, DATE_NOW, $gCurrentOrgId);
}
$rolesStatement = $gDb->queryPrepared($sql, $queryParams);
$rolesList      = $rolesStatement->fetchAll();

$assignedCount = 0;
$parentRoles = array();

// Run results and check if maximum number of participants would be exceeded
foreach ($rolesList as $row) {
    if ($row['rol_max_members'] > 0) {
        // first check if the user is already assigned to this role
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = ? -- $row[\'rol_id\']
                   AND mem_usr_id = ? -- $user->getValue(\'usr_id\')
                   AND mem_leader = 0
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW';
        $pdoStatement = $gDb->queryPrepared($sql, array($row['rol_id'], $user->getValue('usr_id'), DATE_NOW, DATE_NOW));

        if ((int) $pdoStatement->fetchColumn() === 0) {
            // User is not yet assigned to the role, then see if the number is exceeded
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_MEMBERS.'
                     WHERE mem_rol_id = ? -- $row[\'rol_id\']
                       AND mem_leader = 0
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW';
            $pdoStatement = $gDb->queryPrepared($sql, array($row['rol_id'], DATE_NOW, DATE_NOW));

            // maximum number of participiants exceeded and it's not a role leader assignement
            if ($pdoStatement->fetchColumn() >= $row['rol_max_members']
            && isset($_POST['leader-'.$row['rol_id']]) && $_POST['leader-'.$row['rol_id']] == false
            && isset($_POST['role-'.$row['rol_id']])   && $_POST['role-'.$row['rol_id']]   == true) {
                if (!$getInline) {
                    $gMessage->show($gL10n->get('SYS_ROLE_MAX_MEMBERS', array($row['rol_name'])));
                // => EXIT
                } else {
                    echo $gL10n->get('SYS_ROLE_MAX_MEMBERS', array($row['rol_name']));
                }
            }
        }
    }
}

// Run through results and perform database update
foreach ($rolesList as $row) {
    // if role is administrator than only administrator can add new user,
    // but don't change their own membership, because there must be at least one administrator
    if ($row['rol_administrator'] == 0
    || ($row['rol_administrator'] == 1 && $gCurrentUser->isAdministrator()
        && (int) $user->getValue('usr_id') !== $gCurrentUserId)) {
        $roleAssign = false;
        if (isset($_POST['role-'.$row['rol_id']]) && $_POST['role-'.$row['rol_id']] == 1) {
            $roleAssign = true;
        }

        $roleLeader = false;
        if (isset($_POST['leader-'.$row['rol_id']]) && $_POST['leader-'.$row['rol_id']] == 1) {
            $roleLeader = true;
        }

        // update role membership
        if ($roleAssign) {
            $roleAssignmentEndDate = DATE_MAX;

            if ($row['mem_end'] > date('Y-m-d')) {
                $roleAssignmentEndDate = $row['mem_end'];
            }

            $user->setRoleMembership($row['rol_id'], DATE_NOW, $roleAssignmentEndDate, $roleLeader);
            ++$assignedCount;

            // find the parent roles and assign user to parent roles
            $tmpRoles = RoleDependency::getParentRoles($gDb, $row['rol_id']);
            foreach ($tmpRoles as $tmpRole) {
                if (!in_array($tmpRole, $parentRoles, true)) {
                    $parentRoles[] = $tmpRole;
                }
            }
        } else {
            // if membership already exists then stop this membership
            if ($row['mem_id'] > 0) {
                // subtract one day, so that user leaves role immediately
                $now = new \DateTime();
                $oneDayOffset = new \DateInterval('P1D');
                $newEndDate = $now->sub($oneDayOffset)->format('Y-m-d');
                $user->editRoleMembership($row['mem_id'], $row['mem_begin'], $newEndDate, $roleLeader);
            }
        }
    }
}

// assign all memberships of parent roles
// this must be done after all roles are assigned so that there aren't overlapping udpates
if (count($parentRoles) > 0) {
    foreach ($parentRoles as $actRole) {
        $user->setRoleMembership($actRole, DATE_NOW, DATE_MAX);
    }
}

// if role selection was a separate page then delete this page from the navigation stack
if (!$getInline) {
    $gNavigation->deleteLastUrl();
}

// refresh session user object to update the user rights because of the new or removed role
$gCurrentSession->reload($user->getValue('usr_id'));

// Check if a new user get's at least one role
if ($getNewUser > 0 && $assignedCount === 0) {
    // Neuem User wurden keine Rollen zugewiesen
    if (!$getInline) {
        $gMessage->show($gL10n->get('PRO_ROLE_NOT_ASSIGNED'));
    // => EXIT
    } else {
        echo $gL10n->get('PRO_ROLE_NOT_ASSIGNED');
    }
}

// back to the starting page

if (str_contains($gNavigation->getUrl(), 'new_user_assign.php')) {
    // go directly back to the registration overview
    $gNavigation->deleteLastUrl();
}

if ($getInline) {
    echo 'success';
} else {
    if ($getNewUser === 3) {
        $messageId = 'PRO_ASSIGN_REGISTRATION_SUCCESSFUL';
    } else {
        $messageId = 'SYS_SAVE_DATA';
    }

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get($messageId));
    // => EXIT
}
