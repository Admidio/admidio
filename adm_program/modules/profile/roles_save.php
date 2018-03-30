<?php
/**
 ***********************************************************************************************
 * Assign or remove membership of roles in profile
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * usr_id   : ID of the user whose roles should be edited
 * new_user : 0 - (Default) Edit roles of an existing user
 *            1 - Edit roles of a new user
 *            2 - (not relevant)
 *            3 - Edit roles of a registration
 * inline   : false - Ausgaben werden als eigene Seite angezeigt
 *            true  - nur "body" HTML Code
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUserId  = admFuncVariableIsValid($_GET, 'usr_id',   'int');
$getNewUser = admFuncVariableIsValid($_GET, 'new_user', 'int');
$getInline  = admFuncVariableIsValid($_GET, 'inline',   'bool');

// in ajax mode only return simple text on error
if($getInline)
{
    $gMessage->showHtmlTextOnly(true);
}

// if user is allowed to assign at least one role then allow access
if(!$gCurrentUser->assignRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// if new user or registration than at least 1 role must be assigned
if($getNewUser > 0)
{
    // detect number of selected roles
    $roleCount = 0;
    foreach($_POST as $key => $value) // TODO possible security issue
    {
        if(preg_match('/^(role-)\d+$/i', $key))
        {
            ++$roleCount;
        }
    }
    // if no role is selected than show notice
    if($roleCount === 0)
    {
        if(!$getInline)
        {
            $gMessage->show($gL10n->get('PRO_ROLE_NOT_ASSIGNED'));
            // => EXIT
        }
        else
        {
            exit($gL10n->get('PRO_ROLE_NOT_ASSIGNED'));
        }
    }
}

if($gCurrentUser->manageRoles())
{
    // Benutzer mit Rollenrechten darf ALLE Rollen zuordnen
    $sql = 'SELECT rol_id, rol_name, rol_max_members, rol_administrator, mem_id, mem_begin, mem_end
              FROM '.TBL_ROLES.'
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
         LEFT JOIN '.TBL_MEMBERS.'
                ON mem_rol_id = rol_id
               AND mem_usr_id = ? -- $getUserId
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
             WHERE rol_valid   = 1
               AND cat_name_intern <> \'EVENTS\'
               AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                   OR cat_org_id IS NULL )
          ORDER BY cat_sequence, rol_name';
    $queryParams = array($getUserId, DATE_NOW, DATE_NOW, $gCurrentOrganization->getValue('org_id'));
}
else
{
    // Ein Leiter darf nur Rollen zuordnen, bei denen er auch Leiter ist
    $sql = 'SELECT rol_id, rol_name, rol_max_members, rol_administrator, mgl.mem_id, mgl.mem_begin, mgl.mem_end
              FROM '.TBL_MEMBERS.' AS bm
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = bm.mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
         LEFT JOIN '.TBL_MEMBERS.' AS mgl
                ON rol_id         = mgl.mem_rol_id
               AND mgl.mem_usr_id = ? -- $getUserId
               AND mgl.mem_begin <= ? -- DATE_NOW
               AND mgl.mem_end    > ? -- DATE_NOW
             WHERE bm.mem_usr_id  = ? -- $gCurrentUser->getValue(\'usr_id\')
               AND bm.mem_begin  <= ? -- DATE_NOW
               AND bm.mem_end     > ? -- DATE_NOW
               AND bm.mem_leader  = 1
               AND rol_leader_rights IN (1,3)
               AND rol_valid      = 1
               AND cat_name_intern <> \'EVENTS\'
               AND (  cat_org_id  = ? -- $gCurrentOrganization->getValue(\'org_id\')
                   OR cat_org_id IS NULL )
          ORDER BY cat_sequence, rol_name';
    $queryParams = array($getUserId, DATE_NOW, DATE_NOW, $gCurrentUser->getValue('usr_id'), DATE_NOW, DATE_NOW, $gCurrentOrganization->getValue('org_id'));
}
$rolesStatement = $gDb->queryPrepared($sql, $queryParams);
$rolesList      = $rolesStatement->fetchAll();

$assignedCount = 0;
$parentRoles = array();

// Ergebnisse durchlaufen und kontrollieren ob maximale Teilnehmerzahl ueberschritten wuerde
foreach($rolesList as $row)
{
    if($row['rol_max_members'] > 0)
    {
        // erst einmal schauen, ob der Benutzer dieser Rolle bereits zugeordnet ist
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_MEMBERS.'
                 WHERE mem_rol_id = ? -- $row[\'rol_id\']
                   AND mem_usr_id = ? -- $getUserId
                   AND mem_leader = 0
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW';
        $pdoStatement = $gDb->queryPrepared($sql, array($row['rol_id'], $getUserId, DATE_NOW, DATE_NOW));

        if((int) $pdoStatement->fetchColumn() === 0)
        {
            // Benutzer ist der Rolle noch nicht zugeordnet, dann schauen, ob die Anzahl ueberschritten wird
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_MEMBERS.'
                     WHERE mem_rol_id = ? -- $row[\'rol_id\']
                       AND mem_leader = 0
                       AND mem_begin <= ? -- DATE_NOW
                       AND mem_end    > ? -- DATE_NOW';
            $pdoStatement = $gDb->queryPrepared($sql, array($row['rol_id'], DATE_NOW, DATE_NOW));

            // Bedingungen fuer Abbruch und Abbruch
            if($pdoStatement->fetchColumn() >= $row['rol_max_members']
            && isset($_POST['leader-'.$row['rol_id']]) && $_POST['leader-'.$row['rol_id']] == false
            && isset($_POST['role-'.$row['rol_id']])   && $_POST['role-'.$row['rol_id']]   == true)
            {
                if(!$getInline)
                {
                    $gMessage->show($gL10n->get('SYS_ROLE_MAX_MEMBERS', array($row['rol_name'])));
                    // => EXIT
                }
                else
                {
                    echo $gL10n->get('SYS_ROLE_MAX_MEMBERS', array($row['rol_name']));
                }
            }
        }
    }
}

$user = new User($gDb, $gProfileFields, $getUserId);

// Ergebnisse durchlaufen und Datenbankupdate durchfuehren
foreach($rolesList as $row)
{
    // if role is administrator than only administrator can add new user,
    // but don't change their own membership, because there must be at least one administrator
    if($row['rol_administrator'] == 0
    || ($row['rol_administrator'] == 1 && $gCurrentUser->isAdministrator()
        && $getUserId !== (int) $gCurrentUser->getValue('usr_id')))
    {
        $roleAssign = false;
        if(isset($_POST['role-'.$row['rol_id']]) && $_POST['role-'.$row['rol_id']] == 1)
        {
            $roleAssign = true;
        }

        $roleLeader = false;
        if(isset($_POST['leader-'.$row['rol_id']]) && $_POST['leader-'.$row['rol_id']] == 1)
        {
            $roleLeader = true;
        }

        // update role membership
        if($roleAssign)
        {
            $roleAssignmentEndDate = DATE_MAX;

            if($row['mem_end'] > date('Y-m-d'))
            {
                $roleAssignmentEndDate = $row['mem_end'];
            }

            $user->setRoleMembership($row['rol_id'], DATE_NOW, $roleAssignmentEndDate, $roleLeader);
            ++$assignedCount;

            // find the parent roles and assign user to parent roles
            $tmpRoles = RoleDependency::getParentRoles($gDb, $row['rol_id']);
            foreach($tmpRoles as $tmpRole)
            {
                if(!in_array($tmpRole, $parentRoles, true))
                {
                    $parentRoles[] = $tmpRole;
                }
            }
        }
        else
        {
            // if membership already exists then stop this membership
            if($row['mem_id'] > 0)
            {
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
if(count($parentRoles) > 0)
{
    foreach($parentRoles as $actRole)
    {
        $user->setRoleMembership($actRole, DATE_NOW, DATE_MAX);
    }
}

// if role selection was a separate page then delete this page from the navigation stack
if(!$getInline)
{
    $gNavigation->deleteLastUrl();
}

// all active users must renew their user data because maybe their
// rights have been changed if they where new members of this role
$gCurrentSession->renewUserObject();

// Check if a new user get's at least one role
if($getNewUser > 0 && $assignedCount === 0)
{
    // Neuem User wurden keine Rollen zugewiesen
    if(!$getInline)
    {
        $gMessage->show($gL10n->get('PRO_ROLE_NOT_ASSIGNED'));
        // => EXIT
    }
    else
    {
        echo $gL10n->get('PRO_ROLE_NOT_ASSIGNED');
    }
}

// zur Ausgangsseite zurueck
if(StringUtils::strContains($gNavigation->getUrl(), 'new_user_assign.php'))
{
    // von hier aus direkt zur Registrierungsuebersicht zurueck
    $gNavigation->deleteLastUrl();
}

if($getInline)
{
    echo 'success';
}
else
{
    if($getNewUser === 3)
    {
        $messageId = 'PRO_ASSIGN_REGISTRATION_SUCCESSFUL';
    }
    else
    {
        $messageId = 'SYS_SAVE_DATA';
    }

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get($messageId));
    // => EXIT
}
