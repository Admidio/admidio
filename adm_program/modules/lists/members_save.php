<?php
/******************************************************************************
 * Funktionen des Benutzers speichern
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * user_id: Benutzer deren zuordnung ge?ndert werden soll
 * url:     URL auf die danach weitergeleitet wird
 * role_id: Rolle zu denen die Zuordnug ge?ndert werden soll
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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
//Uebernahme der Rolle die bearbeitet werden soll
$role_id = $_GET["role_id"];

//Erfassen der uebergeben Rolle
$sql="SELECT * FROM ". TBL_ROLES. "
      WHERE rol_id = '$role_id'";
$result_role = mysql_query($sql, $g_adm_con);
db_error($result);
$role = mysql_fetch_object($result_role);

// nur Moderatoren duerfen Rollen zuweisen
// nur Webmaster duerfen die Rolle Webmaster zuweisen
// beide muessen mitglied der richtigen Gliedgemeinschaft sein
if((!isModerator() && !isGroupLeader($role_id) && !editUser()) || (!hasRole("Webmaster") && $role->rol_name=="Webmaster") || $role->rol_org_shortname!=$g_organization)
{
   $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

//Veraarbeitung der Daten
//Abfrag aller Datensaetze die mit der Rolle zu tun haben
$sql =" SELECT *
        FROM ". TBL_MEMBERS. "
        WHERE mem_rol_id = $role_id";
$result_mem_role = mysql_query($sql, $g_adm_con);
db_error($result_mem_role);
   
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
db_error($result_user);

//Kontrolle ob nicht am ende die Mitgliederzahl ueberstigen wird
if($role->rol_max_members!=NULL)
{   
    $counter=0;
    while($user= mysql_fetch_array($result_user))
    {    
        if ($_POST["member_".$user["usr_id"]]==true)
        {
            $counter++;
        }
    }
    if($counter>$role->rol_max_members)
    {
        $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=max_members";
        header($location);
        exit();
    }  
    
    //Dateizeiger zurueck zum Anfang
    mysql_data_seek($result_user,0);
}

//Datensaetze durchgehen und sehen ob faer den Benutzer eine aenderung vorliegt
while($user= mysql_fetch_array($result_user))
{
    //Falls User Mitglied der Rolle ist oder schonmal war
    if(array_key_exists($user["usr_id"], $mitglieder_array))
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
                    WHERE mem_id = '$mem_id'";                             
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
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
            
            $sql .= "WHERE mem_id = '$mem_id'";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);          
        }
            
        //Falls nur Leiterfunktion hinzugefuegt/entfernt werden soll under der user Mitglied ist/bleibt
        if($mitglieder_array[$user["usr_id"]][5]==1 && $_POST["member_".$user["usr_id"]]==true)
        {
            $mem_id = $mitglieder_array[$user["usr_id"]][0];
            
            //Falls Leiter hinzugefuegt werden soll
                if($_POST["leader_".$user["usr_id"]]==true && $mitglieder_array[$user["usr_id"]][6]==0)
                {
                    $sql =" UPDATE ". TBL_MEMBERS. " SET mem_leader  = 1 
                            WHERE mem_id = '$mem_id'";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result);
                }
                
                //Falls Leiter entfernt werden soll
                if($_POST["leader_".$user["usr_id"]]==false && $mitglieder_array[$user["usr_id"]][6]==1)
                {
                    $sql =" UPDATE ". TBL_MEMBERS. " SET mem_leader  = 0 
                            WHERE mem_id = '$mem_id'";
                    $result = mysql_query($sql, $g_adm_con);
                db_error($result);
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
        else $sql .=", 0) ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
    }
}


   echo "
   <?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?". ">
   <!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 TRANSITIONAL//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
   <html xmlns=\"http://www.w3.org/1999/xhtml\">
   <head>
      <!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->
      <title>Funktionen zuordnen</title>
      <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\" />
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\" />
      
      <!--[if lt IE 7]>
      <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->
   </head>

   <body>
      <div align=\"center\"><br />
         <div class=\"groupBox\" align=\"left\" style=\"padding: 10px\">
            <p>Die &Auml;nderungen wurden erfolgreich gespeichert.</p>
            <p>Bitte denk daran, die Listenauswahl im Browser neu zu laden,
            damit die ge&auml;nderten Daten angezeigt werden.</p>
         </div>
         <div style=\"padding-top: 10px;\" align=\"center\">
            <button name=\"schliessen\" type=\"button\" value=\"schliessen\" onclick=\"window.close()\">
            <img src=\"$g_root_path/adm_program/images/door_in.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\">
            &nbsp;Schlie&szlig;en</button>
         </div>
      </div>
   </body>
   </html>";
