<?php
/******************************************************************************
 * Assign or remove membership of roles in profile
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id   : ID of the user whose roles should be edited
 * new_user : 0 - (Default) Edit roles of an existing user
 *            1 - Edit roles of a new user
 *            2 - (not relevant)
 *            3 - Edit roles of a registration
 * inline   : 0 - Ausgaben werden als eigene Seite angezeigt
 *            1 - nur "body" HTML Code
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getUserId  = admFuncVariableIsValid($_GET, 'usr_id', 'numeric');
$getNewUser = admFuncVariableIsValid($_GET, 'new_user', 'numeric');
$getInline  = admFuncVariableIsValid($_GET, 'inline', 'boolean');

// in ajax mode only return simple text on error
if($getInline == true)
{
    $gMessage->showHtmlTextOnly(true);
}

// if user is allowed to assign at least one role then allow access
if($gCurrentUser->assignRoles() == false)
{
   $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// detect number of selected roles
$roleCount = 0;
foreach($_POST as $key=>$value)
{
    if(preg_match('/^(role-)[0-9]+$/i', $key))
    {
        $roleCount++;
    }
}

// if no role is selected than show notice
if($roleCount == 0)
{
    if($getInline == 0)
    {
        exit($gMessage->show($gL10n->get('PRO_ROLE_NOT_ASSIGNED')));
    }
    else
    {
        exit($gL10n->get('PRO_ROLE_NOT_ASSIGNED'));
    }
}

if($gCurrentUser->manageRoles())
{
    // Benutzer mit Rollenrechten darf ALLE Rollen zuordnen
    $sql    = 'SELECT rol_id, rol_name, rol_max_members, rol_webmaster, mem_id, mem_begin, mem_end
                 FROM '. TBL_CATEGORIES. ', '. TBL_ROLES. '
                 LEFT JOIN '. TBL_MEMBERS. '
                   ON rol_id      = mem_rol_id
                  AND mem_usr_id  = '.$getUserId.'
                  AND mem_begin <= \''.DATE_NOW.'\'
                  AND mem_end    > \''.DATE_NOW.'\'
                WHERE rol_valid   = 1
                  AND rol_visible = 1
                  AND rol_cat_id  = cat_id
                  AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                      OR cat_org_id IS NULL )
                ORDER BY cat_sequence, rol_name';
}
else
{
    // Ein Leiter darf nur Rollen zuordnen, bei denen er auch Leiter ist
    $sql    = 'SELECT rol_id, rol_name, rol_max_members, rol_webmaster, mgl.mem_id, mgl.mem_begin, mgl.mem_end
                 FROM '. TBL_MEMBERS. ' bm, '. TBL_CATEGORIES. ', '. TBL_ROLES. '
                 LEFT JOIN '. TBL_MEMBERS. ' mgl
                   ON rol_id         = mgl.mem_rol_id
                  AND mgl.mem_usr_id = '.$getUserId.'
                  AND mgl.mem_begin <= \''.DATE_NOW.'\'
                  AND mgl.mem_end    > \''.DATE_NOW.'\'
                WHERE bm.mem_usr_id  = '. $gCurrentUser->getValue('usr_id'). '
                  AND bm.mem_begin  <= \''.DATE_NOW.'\'
                  AND bm.mem_end     > \''.DATE_NOW.'\'
                  AND bm.mem_leader  = 1
                  AND rol_id         = bm.mem_rol_id
                  AND rol_leader_rights IN (1,3)
                  AND rol_valid      = 1
                  AND rol_visible    = 1
                  AND rol_cat_id     = cat_id
                  AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                      OR cat_org_id IS NULL )
                ORDER BY cat_sequence, rol_name';
}
$rolesStatement = $gDb->query($sql);
$rolesList      = $rolesStatement->fetchAll();

$count_assigned = 0;
$parentRoles = array();

// Ergebnisse durchlaufen und kontrollieren ob maximale Teilnehmerzahl ueberschritten wuerde
foreach($rolesList as $row)
{
    if($row['rol_max_members'] > 0)
    {
        // erst einmal schauen, ob der Benutzer dieser Rolle bereits zugeordnet ist
        $sql = 'SELECT COUNT(*)
                  FROM '. TBL_MEMBERS.'
                 WHERE mem_rol_id = '.$row['rol_id'].'
                   AND mem_usr_id = '.$getUserId.'
                   AND mem_leader = 0
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'';
        $pdoStatement = $gDb->query($sql);

        $row_usr = $pdoStatement->fetch();

        if($row_usr[0] == 0)
        {
            // Benutzer ist der Rolle noch nicht zugeordnet, dann schauen, ob die Anzahl ueberschritten wird
            $sql = 'SELECT COUNT(*)
                      FROM '. TBL_MEMBERS.'
                     WHERE mem_rol_id = '.$row['rol_id'].'
                       AND mem_leader = 0
                       AND mem_begin <= \''.DATE_NOW.'\'
                       AND mem_end    > \''.DATE_NOW.'\'';
            $pdoStatement = $gDb->query($sql);

            $row_members = $pdoStatement->fetch();

            //Bedingungen fuer Abbruch und Abbruch
            if($row_members[0] >= $row['rol_max_members']
            && isset($_POST['leader-'.$row['rol_id']]) && $_POST['leader-'.$row['rol_id']] == false
            && isset($_POST['role-'.$row['rol_id']])   && $_POST['role-'.$row['rol_id']]   == true)
            {
                if($getInline == 0)
                {
                    $gMessage->show($gL10n->get('SYS_ROLE_MAX_MEMBERS', $row['rol_name']));
                }
                else
                {
                    echo $gL10n->get('SYS_ROLE_MAX_MEMBERS', $row['rol_name']);
                }
            }
        }
    }
}

$user = new User($gDb, $gProfileFields, $getUserId);

// Ergebnisse durchlaufen und Datenbankupdate durchfuehren
foreach($rolesList as $row)
{
    // if role is webmaster than only webmaster can add new user,
    // but don't change their own membership, because there must be at least one webmaster
    if($row['rol_webmaster'] == 0
    || ($row['rol_webmaster'] == 1 && $gCurrentUser->isWebmaster()
       && $getUserId != $gCurrentUser->getValue('usr_id')))
    {
        $roleAssign = 0;
        if(isset($_POST['role-'.$row['rol_id']]) && $_POST['role-'.$row['rol_id']] == 1)
        {
            $roleAssign = 1;
        }

        $roleLeader = 0;
        if(isset($_POST['leader-'.$row['rol_id']]) && $_POST['leader-'.$row['rol_id']] == 1)
        {
            $roleLeader = 1;
        }

        // update role membership
        if($roleAssign == 1)
        {
            $user->setRoleMembership($row['rol_id'], DATE_NOW, '9999-12-31', $roleLeader);
            $count_assigned++;

            // find the parent roles and assign user to parent roles
            $tmpRoles = RoleDependency::getParentRoles($gDb, $row['rol_id']);
            foreach($tmpRoles as $tmpRole)
            {
                if(!in_array($tmpRole, $parentRoles))
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
                $newEndDate = date('Y-m-d', time() - (24 * 60 * 60));
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
        $user->setRoleMembership($actRole, DATE_NOW, '9999-12-31');
    }
}

// if role selection was a separate page then delete this page from the navigation stack
if($getInline == 0)
{
    $gNavigation->deleteLastUrl();
}

// all active users must renew their user data because maybe their
// rights have been changed if they where new members of this role
$gCurrentSession->renewUserObject();

// Check if a new user get's at least one role
if($getNewUser > 0 && $count_assigned == 0)
{
    // Neuem User wurden keine Rollen zugewiesen
    if($getInline == 0)
    {
        $gMessage->show($gL10n->get('PRO_ROLE_NOT_ASSIGNED'));
    }
    else
    {
        echo $gL10n->get('PRO_ROLE_NOT_ASSIGNED');
    }
}

// zur Ausgangsseite zurueck
if(strpos($gNavigation->getUrl(), 'new_user_assign.php') > 0)
{
    // von hier aus direkt zur Registrierungsuebersicht zurueck
    $gNavigation->deleteLastUrl();
}

if($getInline == true)
{
    echo 'success';
}
else
{
    if($getNewUser == 3)
    {
        $messageId = 'PRO_ASSIGN_REGISTRATION_SUCCESSFUL';
    }
    else
    {
        $messageId = 'SYS_SAVE_DATA';
    }

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get($messageId));
}
