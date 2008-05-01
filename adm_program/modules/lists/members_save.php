<?php
/******************************************************************************
 * Funktionen des Benutzers speichern
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * user_id: Benutzer deren Zuordnung geaendert werden soll
 * url:     URL auf die danach weitergeleitet wird
 * role_id: Rolle zu denen die Zuordnug geaendert werden soll
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/role_class.php");
require("../../system/role_dependency_class.php");

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]) && is_numeric($_GET["user_id"]) == false)
{
    $g_message->show("invalid");
}

if(isset($_GET["role_id"]) && is_numeric($_GET["role_id"]) == false)
{
    $g_message->show("invalid");
}
else
{
    $role_id = $_GET["role_id"];
}

// Objekt der uebergeben Rollen-ID erstellen
$role = new Role($g_db, $role_id);

// nur Moderatoren duerfen Rollen zuweisen
// nur Webmaster duerfen die Rolle Webmaster zuweisen
// beide muessen mitglied der richtigen Gliedgemeinschaft sein
if(  (!$g_current_user->assignRoles()
   && !isGroupLeader($g_current_user->getValue("usr_id"), $role_id) 
   && !$g_current_user->editUser()) 
|| (  !$g_current_user->isWebmaster()
   && $role->getValue("rol_name") == "Webmaster") 
|| $role->getValue("cat_org_id") != $g_current_organization->getValue("org_id"))
{
   $g_message->show("norights");
}

//Veraarbeitung der Daten
//Abfrag aller Datensaetze die mit der Rolle zu tun haben
$sql =" SELECT *
        FROM ". TBL_MEMBERS. "
        WHERE mem_rol_id = $role_id";
$result_mem_role = $g_db->query($sql);

//Schreiben der Datensaetze in Array sortiert nach zugewiesenen Benutzern (id)
$mitglieder_array= array(array());
for($x=0; $mem_role= $g_db->fetch_array($result_mem_role); $x++)
{
    for($y=0; $y<=6; $y++)
    {
        $mitglieder_array["$mem_role[2]"][$y]=$mem_role[$y];
    }
}

//Aufrufen alle Leute aus der Datenbank
$sql =" SELECT *
        FROM ". TBL_USERS. "
        WHERE usr_valid = 1 ";
$result_user = $g_db->query($sql);

//Kontrolle ob nicht am ende die Mitgliederzahl ueberstigen wird
if($role->getValue("rol_max_members") != NULL)
{
    //Zaehler fuer die Mitgliederzahl
    $counter=0;
    while($user= $g_db->fetch_array($result_user))
    {
        if ($_POST["member_".$user["usr_id"]]==true && $_POST["leader_".$user["usr_id"]]==false)
        {
            $counter++;
        }
    }
    if($counter>$role->getValue("rol_max_members"))
    {
        $g_message->show("max_members");
    }

    //Dateizeiger zurueck zum Anfang
    $g_db->data_seek($result_user,0);
}

//Kontrolle der member und leader Felder
while($user= $g_db->fetch_array($result_user))
{
    //Kontrolle für membervariablen
    if(!isset($_POST["member_".$user["usr_id"]]))
    {
        $_POST["member_".$user["usr_id"]]=false;
    }

    //Kontrolle für leadervariablen
    if(!isset($_POST["leader_".$user["usr_id"]]))
    {
        $_POST["leader_".$user["usr_id"]]=false;
    }
}
//Dateizeiger zurueck zum Anfang
$g_db->data_seek($result_user,0);



//Datensaetze durchgehen und sehen ob faer den Benutzer eine aenderung vorliegt
while($user= $g_db->fetch_array($result_user))
{

    $parentRoles = array();
    
    //Falls User Mitglied der Rolle ist oder schonmal war
    if(isset($_POST["member_".$user["usr_id"]]) && array_key_exists($user["usr_id"], $mitglieder_array))
    {
        //Kontolle ob Zuweisung geaendert wurde wen ja entsprechenden SQL-Befehl zusammensetzen

        //Falls abgewaehlt wurde (automatisch auch als Leiter abmelden)
        if($mitglieder_array[$user["usr_id"]][5]==1 && $_POST["member_".$user["usr_id"]]==false)
        {
            $mem_id = $mitglieder_array[$user["usr_id"]][0];
            $sql =" UPDATE ". TBL_MEMBERS. "
                       SET mem_valid  = 0
                         , mem_end    = NOW()
                         , mem_leader = 0
                     WHERE mem_id     = $mem_id ";
            $result = $g_db->query($sql);
        }

        //Falls wieder angemeldet wurde
        if($mitglieder_array[$user["usr_id"]][5]==0 && $_POST["member_".$user["usr_id"]]==true)
        {
            $mem_id = $mitglieder_array[$user["usr_id"]][0];
            $sql =" UPDATE ". TBL_MEMBERS. "
                    SET mem_valid = 1,
                        mem_end   = '0000-00-00'";

            //Falls jemand auch Leiter werden soll
            if($_POST["leader_".$user["usr_id"]]==true)
            {
                $sql .=", mem_leader = 1 ";
            }

            $sql .= "WHERE mem_id = $mem_id ";
            $result = $g_db->query($sql);
            
            // abhaengige Rollen finden
            $tmpRoles = RoleDependency::getParentRoles($g_db,$role_id);
            foreach($tmpRoles as $tmpRole)
            {
                if(!in_array($tmpRole,$parentRoles))
                $parentRoles[] = $tmpRole;
            }
            
        }

        //Falls nur Leiterfunktion hinzugefuegt/entfernt werden soll under der user Mitglied ist/bleibt
        if($mitglieder_array[$user["usr_id"]][5]==1 && $_POST["member_".$user["usr_id"]]==true)
        {
            $mem_id = $mitglieder_array[$user["usr_id"]][0];

            //Falls Leiter hinzugefuegt werden soll
                if($_POST["leader_".$user["usr_id"]]==true && $mitglieder_array[$user["usr_id"]][6]==0)
                {
                    $sql =" UPDATE ". TBL_MEMBERS. " SET mem_leader  = 1
                            WHERE mem_id = $mem_id ";
                    $result = $g_db->query($sql);
                }

                //Falls Leiter entfernt werden soll
                if($_POST["leader_".$user["usr_id"]]==false && $mitglieder_array[$user["usr_id"]][6]==1)
                {
                    $sql =" UPDATE ". TBL_MEMBERS. " SET mem_leader  = 0
                            WHERE mem_id = $mem_id ";
                    $result = $g_db->query($sql);
                }
        }
    }

    //Falls noch nie angemeldet gewesen aber jetzt werden soll
    else if(!array_key_exists($user["usr_id"], $mitglieder_array) && $_POST["member_".$user["usr_id"]]==true)
    {
        $usr_id = $user["usr_id"];
        $sql="  INSERT INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin, mem_valid, mem_leader)
                VALUES ($role_id, $usr_id, NOW(), 1";

        //Falls jemand direkt Leiter werden soll
        if($_POST["leader_".$user["usr_id"]]==true)
        {
            $sql .=", 1) ";
        }
        else 
        {
            $sql .=", 0) ";
        }
        $result = $g_db->query($sql);
        
        // abhaengige Rollen finden
        $tmpRoles = RoleDependency::getParentRoles($g_db,$role_id);
        foreach($tmpRoles as $tmpRole)
        {
            if(!in_array($tmpRole,$parentRoles))
            $parentRoles[] = $tmpRole;
        }
    }
    
    if(count($parentRoles) > 0 )
    {
        $sql = "REPLACE INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin,mem_end, mem_valid, mem_leader) VALUES ";

        // alle einzufuegenden Rollen anhaengen
        foreach($parentRoles as $actRole)
        {
            $sql .= " ($actRole, ". $user['usr_id']. ", NOW(), NULL, 1, 0),";
        }

        //Das letzte Komma wieder wegschneiden
        $sql = substr($sql,0,-1);
        
        $result = $g_db->query($sql);
    }
    
}

//Zurueck zur Herkunftsseite
$g_message->setForwardUrl("$g_root_path/adm_program/system/back.php", 2000);
$g_message->show("members_changed");

