<?php
/******************************************************************************
 * Funktionen des Benutzers speichern
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * rol_id : Rolle zu denen die Zuordnug geaendert werden soll
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/role_dependency.php');
require_once('../../system/classes/table_members.php');
require_once('../../system/classes/table_roles.php');

// Uebergabevariablen pruefen
if(!isset($_GET['rol_id']) || !is_numeric($_GET['rol_id']) || !isset($_GET['uid']))
{
    echo 'SYS_INVALID_PAGE_VIEW';exit();
}

//UID
$uid = $_GET['uid'];

//Member
$member = new TableMembers($g_db);

// Objekt der uebergeben Rollen-ID erstellen
$role = new TableRoles($g_db, $_GET['rol_id']);

// nur Moderatoren duerfen Rollen zuweisen
// nur Webmaster duerfen die Rolle Webmaster zuweisen
// beide muessen mitglied der richtigen Gliedgemeinschaft sein
if( (!$g_current_user->assignRoles()
     && !isGroupLeader($g_current_user->getValue('usr_id'), $role->getValue('rol_id')))
     || (  !$g_current_user->isWebmaster()
     && $role->getValue('rol_name') == $g_l10n->get('SYS_WEBMASTER'))
    || ($role->getValue('cat_org_id') != $g_current_organization->getValue('org_id') && $role->getValue('cat_org_id') > 0 ))
{
   echo 'SYS_NO_RIGHTS';exit(); 
}

//POST Daten übernehmen
$membership = 0;
$leadership = 0;
if(isset($_POST['member_'.$uid]) && $_POST['member_'.$uid]=='true')
{
    $membership = 1;
}
if(isset($_POST['leader_'.$uid]) && $_POST['leader_'.$uid]=='true')
{
    $membership = 1;    
    $leadership = 1;
}

//Datensatzupdate
$mem_count = array('mem_count'=>99999999);

//wenn Mitgliederzahl begrenzt aktuelle Mitgliederzahl ermitteln
if($role->getValue('rol_max_members') > 0)
{   
    //Zählen wievile Mitglieder die Rolle schon hat
    $sql = 'SELECT COUNT(*) as mem_count
            FROM '. TBL_MEMBERS. '
            WHERE mem_rol_id = '. $role->getValue('rol_id'). '
            AND mem_usr_id != '.$uid.'
            AND mem_leader = "0" 
            AND mem_begin <= "'.DATE_NOW.'"
            AND mem_end    > "'.DATE_NOW.'"';
    $result_mem_count = $g_db->query($sql);
    $mem_count = mysql_fetch_array($result_mem_count);
}
//echo $membership.' - '.$leadership.' - '.$mem_count['mem_count'].' - '.$role->getValue('rol_max_members');exit();
//Wenn Rolle weniger mitglieder hätte als zugelassen oder Leiter hinzugefügt werden soll
if($leadership==1 || ($leadership==0 && $membership==1 && ($role->getValue('rol_max_members') > $mem_count['mem_count'] || $role->getValue('rol_max_members')==0)))
{
    $member->startMembership($role->getValue('rol_id'), $uid, $leadership);
    echo 'success';
}
elseif($leadership==0 && $membership==0)
{
    $member->stopMembership($role->getValue('rol_id'), $uid);
    echo 'success';
}
else
{
    echo 'max_mem_reached';
}

?>