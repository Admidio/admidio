<?php
/******************************************************************************
 * Funktionen des Benutzers speichern
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * user_id: Benutzer deren Zuordnung geaendert werden soll
 * url:     URL auf die danach weitergeleitet wird
 * role_id: Rolle zu denen die Zuordnug geaendert werden soll
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

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

//Erfassen der uebergeben Rolle
$sql="SELECT * FROM ". TBL_ROLES. "
      WHERE rol_id = {0}";
$sql    = prepareSQL($sql, array($role_id));
$result_role = mysql_query($sql, $g_adm_con);
db_error($result,__FILE__,__LINE__);
$role = mysql_fetch_object($result_role);

// nur Moderatoren duerfen Rollen zuweisen
// nur Webmaster duerfen die Rolle Webmaster zuweisen
// beide muessen mitglied der richtigen Gliedgemeinschaft sein
if(  (!isModerator() 
   && !isGroupLeader($role_id) 
   && !$g_current_user->editUser()) 
|| (  !$g_current_user->isWebmaster()
   && $role->rol_name=="Webmaster") 
|| $role->rol_org_shortname!=$g_organization)
{
   $g_message->show("norights");
}

//Veraarbeitung der Daten
//Abfrag aller Datensaetze die mit der Rolle zu tun haben
$sql =" SELECT *
        FROM ". TBL_MEMBERS. "
        WHERE mem_rol_id = {0}";
$sql    = prepareSQL($sql, array($role_id));
$result_mem_role = mysql_query($sql, $g_adm_con);
db_error($result_mem_role,__FILE__,__LINE__);

//Schreiben der Datensaetze in Array sortiert nach zugewiesenen Benutzern (id)
$mitglieder_array= array(array());
for($x=0; $mem_role= mysql_fetch_array($result_mem_role); $x++)
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
$result_user = mysql_query($sql, $g_adm_con);
db_error($result_user,__FILE__,__LINE__);

//Kontrolle ob nicht am ende die Mitgliederzahl ueberstigen wird
if($role->rol_max_members!=NULL)
{
    //Zaehler fuer die Mitgliederzahl
    $counter=0;
    while($user= mysql_fetch_array($result_user))
    {
        if ($_POST["member_".$user["usr_id"]]==true && $_POST["leader_".$user["usr_id"]]==false)
        {
            $counter++;
        }
    }
    if($counter>$role->rol_max_members)
    {
        $g_message->show("max_members");
    }

    //Dateizeiger zurueck zum Anfang
    mysql_data_seek($result_user,0);
}

//Kontrolle der member und leader Felder
while($user= mysql_fetch_array($result_user))
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
mysql_data_seek($result_user,0);



//Datensaetze durchgehen und sehen ob faer den Benutzer eine aenderung vorliegt
while($user= mysql_fetch_array($result_user))
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
                    SET mem_valid  = 0,
                        mem_end   = NOW(),
                        mem_leader = 0
                    WHERE mem_id = {0}";
            $sql    = prepareSQL($sql, array($mem_id));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
        }

        //Falls wieder angemeldet wurde
        if($mitglieder_array[$user["usr_id"]][5]==0 && $_POST["member_".$user["usr_id"]]==true)
        {
            $mem_id = $mitglieder_array[$user["usr_id"]][0];
            $sql =" UPDATE ". TBL_MEMBERS. "
                    SET mem_valid  = 1,
                        mem_end   = '0000-00-00'";

            //Falls jemand auch Leiter werden soll
            if($_POST["leader_".$user["usr_id"]]==true)
            {
                $sql .=", mem_leader = 1 ";
            }

            $sql .= "WHERE mem_id = {0}";
            $sql    = prepareSQL($sql, array($mem_id));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
            
            // abhaengige Rollen finden
            $tmpRoles = RoleDependency::getParentRoles($g_adm_con,$role_id);
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
                            WHERE mem_id = {0}";
                    $sql    = prepareSQL($sql, array($mem_id));
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result,__FILE__,__LINE__);
                }

                //Falls Leiter entfernt werden soll
                if($_POST["leader_".$user["usr_id"]]==false && $mitglieder_array[$user["usr_id"]][6]==1)
                {
                    $sql =" UPDATE ". TBL_MEMBERS. " SET mem_leader  = 0
                            WHERE mem_id = {0}";
                    $sql    = prepareSQL($sql, array($mem_id));
                    $result = mysql_query($sql, $g_adm_con);
                db_error($result,__FILE__,__LINE__);
                }
        }
    }

    //Falls noch nie angemeldet gewesen aber jetzt werden soll
    else if(!array_key_exists($user["usr_id"], $mitglieder_array) && $_POST["member_".$user["usr_id"]]==true)
    {
        $usr_id = $user["usr_id"];
        $sql="  INSERT INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin, mem_valid, mem_leader)
                VALUES ({0}, {1}, NOW(), 1";

        //Falls jemand direkt Leiter werden soll
        if($_POST["leader_".$user["usr_id"]]==true)
        {
            $sql .=", 1) ";
        }
        else $sql .=", 0) ";
        $sql    = prepareSQL($sql, array($role_id, $usr_id));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);
        
        // abhaengige Rollen finden
        $tmpRoles = RoleDependency::getParentRoles($g_adm_con,$role_id);
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
            $sql .= " ($actRole, {0}, NOW(), NULL, 1, 0),";
        }

        //Das letzte Komma wieder wegschneiden
        $sql = substr($sql,0,-1);
        
        $sql    = prepareSQL($sql, array($user["usr_id"]));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);
    }
    
}

//Zurueck zur Herkunftsseite
$g_message->setForwardUrl("$g_root_path/adm_program/system/back.php", 2000);
$g_message->show("members_changed");

