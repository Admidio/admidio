<?php
/******************************************************************************
 * Funktionen des Benutzers speichern
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
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

if(isset($_GET['rol_id']) == false || is_numeric($_GET['rol_id']) == false)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Objekt der uebergeben Rollen-ID erstellen
$role = new TableRoles($g_db, $_GET['rol_id']);

// nur Moderatoren duerfen Rollen zuweisen
// nur Webmaster duerfen die Rolle Webmaster zuweisen
// beide muessen mitglied der richtigen Gliedgemeinschaft sein
if(  (!$g_current_user->assignRoles()
   && !isGroupLeader($g_current_user->getValue('usr_id'), $role->getValue('rol_id')))
|| (  !$g_current_user->isWebmaster()
   && $role->getValue('rol_name') == 'Webmaster')
|| $role->getValue('cat_org_id') != $g_current_organization->getValue('org_id'))
{
   $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// Verarbeitung der Daten
// Ermittlung aller bisherigen aktiven Mitgliedschaften
$sql = ' SELECT *
           FROM '. TBL_MEMBERS. '
          WHERE mem_rol_id = '. $role->getValue('rol_id'). '
            AND mem_begin <= "'.DATE_NOW.'"
            AND mem_end    > "'.DATE_NOW.'"';
$result_mem_role = $g_db->query($sql);

//Schreiben der Datensaetze in Array sortiert nach zugewiesenen Benutzern (id)
$mitglieder_array= array(array());
for($x=0; $mem_role= $g_db->fetch_array($result_mem_role); $x++)
{
    foreach($mem_role as $key => $value)
    {
        $mitglieder_array[$mem_role['mem_usr_id']][$key] = $value;
    }
}

//Aufrufen alle Leute aus der Datenbank
$sql =' SELECT *
        FROM '. TBL_USERS. '
        WHERE usr_valid = 1 ';
$result_user = $g_db->query($sql);

//Kontrolle ob nicht am Ende die Mitgliederzahl ueberstiegen wird
if($role->getValue('rol_max_members') != NULL)
{
    //Zaehler fuer die Mitgliederzahl
    $counter=0;
    while($user= $g_db->fetch_array($result_user))
    {
        if ($_POST['member_'.$user['usr_id']]==true && $_POST['leader_'.$user['usr_id']]==false)
        {
            $counter++;
        }
    }
    if($counter > $role->getValue('rol_max_members'))
    {
        $g_message->show($g_l10n->get('SYS_PHR_ROLE_MAX_MEMBERS', $role->getValue('rol_name')));
    }

    //Dateizeiger zurueck zum Anfang
    $g_db->data_seek($result_user,0);
}

$member = new TableMembers($g_db);

// Datensaetze durchgehen und sehen, ob fuer den Benutzer eine Aenderung vorliegt
while($user= $g_db->fetch_array($result_user))
{
    $parentRoles = array();

    // Mitgliedschaft bearbeiten, aber nur wenn die Mitgliedschaft sich veraendert hat
    if((isset($_POST['member_'.$user['usr_id']]) == false && array_key_exists($user['usr_id'], $mitglieder_array) == true)
    || (isset($_POST['member_'.$user['usr_id']]) == true  && array_key_exists($user['usr_id'], $mitglieder_array) == false)
    || (isset($_POST['member_'.$user['usr_id']]) == true  && isset($_POST['leader_'.$user['usr_id']]) == true  && $mitglieder_array[$user['usr_id']]['mem_leader'] == 0)
    || (isset($_POST['member_'.$user['usr_id']]) == true  && isset($_POST['leader_'.$user['usr_id']]) == false && $mitglieder_array[$user['usr_id']]['mem_leader'] == 1) )
    {
        if(isset($_POST['member_'.$user['usr_id']]) == true)
        {
            // Leiterstatus noch entsprechend weitergeben
            if(isset($_POST['leader_'.$user['usr_id']]) == true)
            {
                $member->startMembership($role->getValue('rol_id'), $user['usr_id'], 1);
            }
            else
            {
                $member->startMembership($role->getValue('rol_id'), $user['usr_id'], 0);
            }
        }
        else
        {
            $member->stopMembership($role->getValue('rol_id'), $user['usr_id']);
        }

        if(isset($_POST['member_'.$user['usr_id']]) == true  && array_key_exists($user['usr_id'], $mitglieder_array) == false)
        {
            // abhaengige Rollen finden
            $tmpRoles = RoleDependency::getParentRoles($g_db,$role->getValue('rol_id'));
            foreach($tmpRoles as $tmpRole)
            {
                if(!in_array($tmpRole,$parentRoles))
                $parentRoles[] = $tmpRole;
            }
        }
    }

    if(count($parentRoles) > 0 )
    {
        $sql = 'REPLACE INTO '. TBL_MEMBERS. ' (mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_leader) VALUES ';

        // alle einzufuegenden Rollen anhaengen
        foreach($parentRoles as $actRole)
        {
            $sql .= ' ('.$actRole.', '. $user['usr_id']. ', "'.DATE_NOW.'", "9999-12-31", 0),';
        }

        //Das letzte Komma wieder wegschneiden
        $sql = substr($sql,0,-1);

        $result = $g_db->query($sql);
    }

}

//Zurueck zur Herkunftsseite
$g_message->setForwardUrl($g_root_path.'/adm_program/system/back.php', 2000);
$g_message->show('members_changed');

