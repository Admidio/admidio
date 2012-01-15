<?php
/******************************************************************************
 * Funktionen des Benutzers speichern
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id      - Funktionen der uebergebenen ID aendern
 * new_user: 0 - (Default) Daten eines vorhandenen Users werden bearbeitet
 *           1 - Der User ist gerade angelegt worden -> Rollen muessen zugeordnet werden
 * inline: 	 0 - Ausgaben werden als eigene Seite angezeigt
 *			 1 - nur "body" HTML Code (z.B. für colorbox)
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_members.php');
require_once('../../system/classes/role_dependency.php');


// nur Webmaster & Moderatoren duerfen Rollen zuweisen
if(!$gCurrentUser->assignRoles() && !isGroupLeader($gCurrentUser->getValue('usr_id')))
{
   $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getUserId  = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', 0);
$getNewUser = admFuncVariableIsValid($_GET, 'new_user', 'boolean', 0);
$getInline  = admFuncVariableIsValid($_GET, 'inline', 'boolean', 0);

// detect number of selected roles
$roleCount = 0;
foreach($_POST as $key=>$value)
{
	if(preg_match('/^(role-)[0-9]+$/i',$key))
	{
		$roleCount++;
    }
}

// if no role is selected than show notice
if($roleCount == 0)
{
	if($getInline == 0)
	{
		die($gMessage->show($gL10n->get('PRO_ROLE_NOT_ASSIGNED')));
	}
	else
	{
		die($gL10n->get('PRO_ROLE_NOT_ASSIGNED'));
	}
}

if($gCurrentUser->assignRoles())
{
    // Benutzer mit Rollenrechten darf ALLE Rollen zuordnen
    $sql    = 'SELECT rol_id, rol_name, rol_max_members
                 FROM '. TBL_CATEGORIES. ', '. TBL_ROLES. '
                 LEFT JOIN '. TBL_MEMBERS. '
                   ON rol_id      = mem_rol_id
                  AND mem_usr_id  = '.$getUserId.'
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
    $sql    = 'SELECT rol_id, rol_name, rol_max_members
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
                  AND rol_valid      = 1
                  AND rol_visible    = 1
                  AND rol_cat_id     = cat_id
                  AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                      OR cat_org_id IS NULL )
                ORDER BY cat_sequence, rol_name';
}
$result_rol = $gDb->query($sql);

$count_assigned = 0;
$parentRoles = array();

// Ergebnisse durchlaufen und kontrollieren ob maximale Teilnehmerzahl ueberschritten wuerde
while($row = $gDb->fetch_array($result_rol))
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
        $gDb->query($sql);

        $row_usr = $gDb->fetch_array();

        if($row_usr[0] == 0)
        {
            // Benutzer ist der Rolle noch nicht zugeordnet, dann schauen, ob die Anzahl ueberschritten wird
            $sql = 'SELECT COUNT(*)
                      FROM '. TBL_MEMBERS.'
                     WHERE mem_rol_id = '.$row['rol_id'].'
                       AND mem_leader = 0
                       AND mem_begin <= \''.DATE_NOW.'\'
                       AND mem_end    > \''.DATE_NOW.'\'';
            $gDb->query($sql);

            $row_members = $gDb->fetch_array();

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

//Dateizeiger auf erstes Element zurueck setzen
if($gDb->num_rows($result_rol)>0)
{
    $gDb->data_seek($result_rol, 0);
}

$member = new TableMembers($gDb);

// Ergebnisse durchlaufen und Datenbankupdate durchfuehren
while($row = $gDb->fetch_array($result_rol))
{
	// if role is webmaster than only webmaster can add new user, 
	// but don't change their own membership, because there must be at least one webmaster
    if($row['rol_name'] != $gL10n->get('SYS_WEBMASTER')
	|| (  $row['rol_name'] == $gL10n->get('SYS_WEBMASTER') && $gCurrentUser->isWebmaster() 
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
            $member->startMembership($row['rol_id'], $getUserId, $roleLeader);
            $count_assigned++;

            // find the parent roles and assign user to parent roles
            $tmpRoles = RoleDependency::getParentRoles($gDb, $row['rol_id']);
            foreach($tmpRoles as $tmpRole)
            {
                if(!in_array($tmpRole,$parentRoles))
                {
                    $parentRoles[] = $tmpRole;
                }
            }
        }
        else
        {
            $member->stopMembership($row['rol_id'], $getUserId);
        }
    }
}

// assign all memberships of parent roles
// this must be done after all roles are assigned so that there aren't overlapping udpates
if(count($parentRoles) > 0 )
{
    foreach($parentRoles as $actRole)
    {
        $member->startMembership($actRole, $getUserId);
    }
}

$_SESSION['navigation']->deleteLastUrl();

// falls Rollen dem eingeloggten User neu zugewiesen wurden,
// dann muessen die Rechte in den Session-Variablen neu eingelesen werden
$gCurrentSession->renewUserObject();

if($getNewUser == 1 && $count_assigned == 0)
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
if(strpos($_SESSION['navigation']->getUrl(), 'new_user_assign.php') > 0)
{
    // von hier aus direkt zur Registrierungsuebersicht zurueck
    $_SESSION['navigation']->deleteLastUrl();
}
if($getInline == 0)
{
	$gMessage->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
	$gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}
else
{
	echo $gL10n->get('SYS_SAVE_DATA').'<SAVED/>';
}