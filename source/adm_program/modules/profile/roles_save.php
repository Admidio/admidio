<?php
/******************************************************************************
 * Funktionen des Benutzers speichern
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id     - Funktionen der uebergebenen ID aendern
 * new_user: 0 - (Default) Daten eines vorhandenen Users werden bearbeitet
 *           1 - Der User ist gerade angelegt worden -> Rollen muessen zugeordnet werden
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
require("../../system/role_dependency_class.php");


// nur Webmaster & Moderatoren duerfen Rollen zuweisen
if(!isModerator() && !isGroupLeader() && !$g_current_user->editUser())
{
    $g_message->show("norights");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_usr_id   = 0;
$req_new_user = 0;

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]))
{
    if(is_numeric($_GET["user_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $req_usr_id = $_GET["user_id"];
}

if(isset($_GET["new_user"]))
{
    if(is_numeric($_GET["new_user"]) == false)
    {
        $g_message->show("invalid");
    }
    $req_new_usr = $_GET["new_user"];
}

if(isModerator())
{
    // Alle Rollen der Gruppierung auflisten
    $sql    = "SELECT rol_id, rol_name, rol_max_members
                 FROM ". TBL_ROLES. "
                WHERE rol_org_shortname = '$g_organization'
                  AND rol_valid        = 1
                ORDER BY rol_name";
}
elseif(isGroupLeader())
{
    // Alle Rollen auflisten, bei denen das Mitglied Leiter ist
    $sql    = "SELECT rol_id, rol_name, rol_max_members
                 FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                WHERE mem_usr_id  = $g_current_user->id
                  AND mem_valid  = 1
                  AND mem_leader = 1
                  AND rol_id     = mem_rol_id
                  AND rol_org_shortname = '$g_organization'
                  AND rol_valid        = 1
                  AND rol_locked     = 0
                ORDER BY rol_name";
}
elseif($g_current_user->editUser())
{
    // Alle Rollen auflisten, die keinen Moderatorenstatus haben
    $sql    = "SELECT rol_id, rol_name, rol_max_members
                 FROM ". TBL_ROLES. "
                WHERE rol_org_shortname = '$g_organization'
                  AND rol_valid        = 1
                  AND rol_moderation = 0
                  AND rol_locked     = 0
                ORDER BY rol_name";
}
$result_rolle = mysql_query($sql, $g_adm_con);
db_error($result_rolle,__FILE__,__LINE__);

$count_assigned = 0;
$parentRoles = array();

// Ergebnisse durchlaufen und kontrollieren ob maximale Teilnehmerzahl ueberschritten wuerde
while($row = mysql_fetch_object($result_rolle))
{
    if($row->rol_max_members > 0)
    {
        // erst einmal schauen, ob der Benutzer dieser Rolle bereits zugeordnet ist
        $sql    =   "SELECT COUNT(*)
                       FROM ". TBL_MEMBERS. "
                      WHERE mem_rol_id = $row->rol_id
                        AND mem_usr_id = {0}
                        AND mem_leader = 0
                        AND mem_valid  = 1";
        $sql    = prepareSQL($sql, array($req_usr_id));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);

        $row_usr = mysql_fetch_array($result);

        if($row_usr[0] == 0)
        {
            // Benutzer ist der Rolle noch nicht zugeordnet, dann schauen, ob die Anzahl ueberschritten wird
            $sql    =   "SELECT COUNT(*)
                           FROM ". TBL_MEMBERS. "
                          WHERE mem_rol_id = $row->rol_id
                            AND mem_leader = 0
                            AND mem_valid  = 1";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);

            $row_members = mysql_fetch_array($result);

            //Bedingungen fuer Abbruch und Abbruch
            if($row_members[0] >= $row->rol_max_members
            && $_POST["leader-$row->rol_id"] == false
            && $_POST["role-$row->rol_id"]   == true)
            {
                $g_message->show("max_members_profile", utf8_encode($row->rol_name));
            }
        }
    }
}

//Dateizeiger auf erstes Element zurueck setzen
if(mysql_num_rows($result_rolle)>0)
{
    mysql_data_seek($result_rolle, 0);
}

// Ergebnisse durchlaufen und Datenbankupdate durchfuehren
while($row = mysql_fetch_object($result_rolle))
{
    // der Webmaster-Rolle duerfen nur Webmaster neue Mitglieder zuweisen
    if($row->rol_name != 'Webmaster' || $g_current_user->isWebmaster())
    {
        if(isset($_POST["role-$row->rol_id"]) && $_POST["role-$row->rol_id"] == 1)
        {
            $function = 1;
        }
        else
        {
            $function = 0;
        }

        if(isset($_POST["leader-$row->rol_id"]) && $_POST["leader-$row->rol_id"] == 1)
        {
            $leiter   = 1;
        }
        else
        {
            $leiter   = 0;
        }

        $sql    = "SELECT * FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                    WHERE mem_rol_id = $row->rol_id
                      AND mem_usr_id = {0}
                      AND mem_rol_id = rol_id ";
        $sql    = prepareSQL($sql, array($req_usr_id));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);

        $user_found = mysql_num_rows($result);

        if($user_found > 0)
        {
            // neue Mitgliederdaten zurueckschreiben
            if($function == 1)
            {
                $sql = "UPDATE ". TBL_MEMBERS. " SET mem_valid  = 1
                                                   , mem_end    = NULL
                                                   , mem_leader = $leiter
                            WHERE mem_rol_id = $row->rol_id
                              AND mem_usr_id = {0}";
                $count_assigned++;
            }
            else
            {
                $sql = "UPDATE ". TBL_MEMBERS. " SET mem_valid  = 0
                                                   , mem_end    = NOW()
                                                   , mem_leader = $leiter
                            WHERE mem_rol_id = $row->rol_id
                              AND mem_usr_id = {0}";
            }
        }
        else
        {
            // neue Mitgliederdaten einfuegen, aber nur, wenn auch ein Haeckchen da ist
            if($function == 1)
            {
                $sql = "INSERT INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin,mem_end, mem_valid, mem_leader)
                          VALUES ($row->rol_id, {0}, NOW(),NULL, 1, $leiter) ";
                $count_assigned++;
            }
        }

        // Update aufueren
        $sql    = prepareSQL($sql, array($req_usr_id));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);

        // find the parent roles
        if($function == 1)
        {
            $tmpRoles = RoleDependency::getParentRoles($g_adm_con,$row->rol_id);
            foreach($tmpRoles as $tmpRole)
            {
                if(!in_array($tmpRole,$parentRoles))
                $parentRoles[] = $tmpRole;
            }

        }

    }
}

$_SESSION['navigation']->deleteLastUrl();

// falls Rollen dem eingeloggten User neu zugewiesen wurden, 
// dann muessen die Rechte in den Session-Variablen neu eingelesen werden
if($g_current_user->id != $req_usr_id)
{
    $g_current_user->clearRights();
    $_SESSION['g_current_user'] = $g_current_user;
}

if(count($parentRoles) > 0 )
{
    $sql = "REPLACE INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin,mem_end, mem_valid, mem_leader) VALUES ";

    // alle einzufuegenden Rollen anhaengen
    foreach($parentRoles as $actRole)
    {
        $sql .= " ($actRole, {0}, NOW(), NULL, 1, 0),";
    }

    // Das letzte Komma wieder wegschneiden
    $sql = substr($sql,0,-1);
    
    $sql    = prepareSQL($sql, array($req_usr_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);
}

if($req_new_user == 1 && $count_assigned == 0)
{
    // Neuem User wurden keine Rollen zugewiesen
    $g_message->show("norolle");
}

// zur Ausgangsseite zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show("save");