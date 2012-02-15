<?php
/******************************************************************************
 * Functions to save user memberships of roles
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * rol_id : edit the membership of this role id
 * usr_id : edit the membership of this user id
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/role_dependency.php');
require_once('../../system/classes/table_members.php');
require_once('../../system/classes/table_roles.php');

// Initialize and check the parameters
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', null, true, null, true);
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', null, true, null, true);

//Member
$member = new TableMembers($gDb);

// Objekt der uebergeben Rollen-ID erstellen
$role = new TableRoles($gDb, $getRoleId);

// nur Moderatoren duerfen Rollen zuweisen
// nur Webmaster duerfen die Rolle Webmaster zuweisen
// beide muessen mitglied der richtigen Gliedgemeinschaft sein
if( (!$gCurrentUser->assignRoles()
     && !isGroupLeader($gCurrentUser->getValue('usr_id'), $role->getValue('rol_id')))
     || (  !$gCurrentUser->isWebmaster()
     && $role->getValue('rol_name') == $gL10n->get('SYS_WEBMASTER'))
    || ($role->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id') && $role->getValue('cat_org_id') > 0 ))
{
   echo 'SYS_NO_RIGHTS';
   exit(); 
}

//POST Daten übernehmen
$membership = 0;
$leadership = 0;

if(isset($_POST['member_'.$getUserId]) && $_POST['member_'.$getUserId]=='true')
{
    $membership = 1;
}
if(isset($_POST['leader_'.$getUserId]) && $_POST['leader_'.$getUserId]=='true')
{
    $membership = 1;    
    $leadership = 1;
}

//Datensatzupdate
$mem_count = array('mem_count' => 99999999);

//wenn Mitgliederzahl begrenzt aktuelle Mitgliederzahl ermitteln
if($role->getValue('rol_max_members') > 0)
{   
    //Zählen wievile Mitglieder die Rolle schon hat
    $sql = 'SELECT COUNT(*) as mem_count
            FROM '. TBL_MEMBERS. '
            WHERE mem_rol_id = '. $role->getValue('rol_id'). '
            AND mem_usr_id != '.$getUserId.'
            AND mem_leader = 0 
            AND mem_begin <= \''.DATE_NOW.'\'
            AND mem_end    > \''.DATE_NOW.'\'';
    $result_mem_count = $gDb->query($sql);
    $mem_count = $gDb->fetch_array($result_mem_count);
}
//echo $membership.' - '.$leadership.' - '.$mem_count['mem_count'].' - '.$role->getValue('rol_max_members');exit();
//Wenn Rolle weniger mitglieder hätte als zugelassen oder Leiter hinzugefügt werden soll
if($leadership==1 || ($leadership==0 && $membership==1 && ($role->getValue('rol_max_members') > $mem_count['mem_count'] || $role->getValue('rol_max_members')==0)))
{
    $member->startMembership($role->getValue('rol_id'), $getUserId, $leadership);
    echo 'success';
}
elseif($leadership==0 && $membership==0)
{
    $member->stopMembership($role->getValue('rol_id'), $getUserId);
    echo 'success';
}
else
{
    echo 'max_mem_reached';
}

?>