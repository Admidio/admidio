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
if(!$g_current_user->assignRoles() && !isGroupLeader() && !$g_current_user->editUser())
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

if($g_current_user->assignRoles() || $g_current_user->editUser())
{
    // Benutzer mit Rollenrechten darf ALLE Rollen zuordnen
    // Benutzer mit Benutzereditierrechten darf versteckte und 
    // Rollen mit Rollenvergaberechten nicht sehen
    $sql_roles_condition = "";
    if($g_current_user->editUser() && !$g_current_user->assignRoles())
    {
        $sql_roles_condition = "AND rol_assign_roles = 0
                                AND rol_locked       = 0 ";
    }

    $sql    = "SELECT rol_id, rol_name, rol_max_members, mem_usr_id, mem_leader, mem_valid
                 FROM ". TBL_CATEGORIES. ", ". TBL_ROLES. " 
                 LEFT JOIN ". TBL_MEMBERS. "
                   ON rol_id     = mem_rol_id
                  AND mem_usr_id = $req_usr_id
                WHERE rol_valid  = 1
                      $sql_roles_condition
                  AND rol_cat_id = cat_id
                  AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
                ORDER BY cat_sequence, rol_name";
}
else
{
    // Ein Leiter darf nur Rollen zuordnen, bei denen er auch Leiter ist
    $sql    = "SELECT rol_id, rol_name, rol_max_members,
                      mgl.mem_usr_id as mem_usr_id, mgl.mem_leader as mem_leader, mgl.mem_valid as mem_valid
                 FROM ". TBL_MEMBERS. " bm, ". TBL_CATEGORIES. ", ". TBL_ROLES. "
                 LEFT JOIN ". TBL_MEMBERS. " mgl
                   ON rol_id         = mgl.mem_rol_id
                  AND mgl.mem_usr_id = $req_usr_id
                  AND mgl.mem_valid  = 1
                WHERE bm.mem_usr_id  = ". $g_current_user->getValue("usr_id"). "
                  AND bm.mem_valid   = 1
                  AND bm.mem_leader  = 1
                  AND rol_id         = bm.mem_rol_id
                  AND rol_valid      = 1
                  AND rol_locked     = 0
                  AND rol_cat_id     = cat_id
                  AND cat_org_id     = ". $g_current_organization->getValue("org_id"). "
                ORDER BY cat_sequence, rol_name";
}
$result_rolle = $g_db->query($sql);

$count_assigned = 0;
$parentRoles = array();

// Ergebnisse durchlaufen und kontrollieren ob maximale Teilnehmerzahl ueberschritten wuerde
while($row = $g_db->fetch_object($result_rolle))
{
    if($row->rol_max_members > 0)
    {
        // erst einmal schauen, ob der Benutzer dieser Rolle bereits zugeordnet ist
        $sql    =   "SELECT COUNT(*)
                       FROM ". TBL_MEMBERS. "
                      WHERE mem_rol_id = $row->rol_id
                        AND mem_usr_id = $req_usr_id
                        AND mem_leader = 0
                        AND mem_valid  = 1";
        $g_db->query($sql);

        $row_usr = $g_db->fetch_array();

        if($row_usr[0] == 0)
        {
            // Benutzer ist der Rolle noch nicht zugeordnet, dann schauen, ob die Anzahl ueberschritten wird
            $sql    =   "SELECT COUNT(*)
                           FROM ". TBL_MEMBERS. "
                          WHERE mem_rol_id = $row->rol_id
                            AND mem_leader = 0
                            AND mem_valid  = 1";
            $g_db->query($sql);

            $row_members = $g_db->fetch_array();

            //Bedingungen fuer Abbruch und Abbruch
            if($row_members[0] >= $row->rol_max_members
            && isset($_POST["leader-$row->rol_id"]) && $_POST["leader-$row->rol_id"] == false
            && isset($_POST["role-$row->rol_id"])   && $_POST["role-$row->rol_id"]   == true)
            {
                $g_message->show("max_members_profile", utf8_encode($row->rol_name));
            }
        }
    }
}

//Dateizeiger auf erstes Element zurueck setzen
if($g_db->num_rows($result_rolle)>0)
{
    $g_db->data_seek($result_rolle, 0);
}

// Ergebnisse durchlaufen und Datenbankupdate durchfuehren
while($row = $g_db->fetch_object($result_rolle))
{
    // der Webmaster-Rolle duerfen nur Webmaster neue Mitglieder zuweisen
    if($row->rol_name != 'Webmaster' || $g_current_user->isWebmaster())
    {
        $role_assign = 0;
        if(isset($_POST["role-$row->rol_id"]) && $_POST["role-$row->rol_id"] == 1)
        {
            $role_assign = 1;
        }

        $role_leader = 0;
        if(isset($_POST["leader-$row->rol_id"]) && $_POST["leader-$row->rol_id"] == 1)
        {
            $role_leader = 1;
        }

        // Rollenmitgliedschaften aktualisieren
        if(is_null($row->mem_usr_id))
        {
            // neue Mitgliederdaten einfuegen, aber nur, wenn auch ein Haeckchen da ist
            if($role_assign == 1)
            {
                $sql = "INSERT INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin,mem_end, mem_valid, mem_leader)
                          VALUES ($row->rol_id, $req_usr_id, NOW(),NULL, 1, $role_leader) ";
                $g_db->query($sql);
                $count_assigned++;
            }
        }
        else
        {
            // neue Rollenmitgliederdaten zurueckschreiben, falls sich diese geaendert haben
            if($role_assign == 1)
            {
                if($row->mem_valid == 0)
                {
                    $sql = "UPDATE ". TBL_MEMBERS. " SET mem_valid  = 1
                                                       , mem_end    = NULL
                                                       , mem_leader = $role_leader
                                WHERE mem_rol_id = $row->rol_id
                                  AND mem_usr_id = $req_usr_id ";
                    $g_db->query($sql);
                    $count_assigned++;
                }
            }
            else
            {
                if($row->mem_valid == 1)
                {
                    $sql = "UPDATE ". TBL_MEMBERS. " SET mem_valid  = 0
                                                       , mem_end    = NOW()
                                                       , mem_leader = $role_leader
                                WHERE mem_rol_id = $row->rol_id
                                  AND mem_usr_id = $req_usr_id ";
                    $g_db->query($sql);
                }
            }
        }

        // find the parent roles
        if($role_assign == 1)
        {
            $tmpRoles = RoleDependency::getParentRoles($g_db,$row->rol_id);
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
if($g_current_user->getValue("usr_id") != $req_usr_id)
{
    $g_current_user->clearRights();
}

if(count($parentRoles) > 0 )
{
    $sql = "REPLACE INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin,mem_end, mem_valid, mem_leader) VALUES ";

    // alle einzufuegenden Rollen anhaengen
    foreach($parentRoles as $actRole)
    {
        $sql .= " ($actRole, $req_usr_id, NOW(), NULL, 1, 0),";
    }

    // Das letzte Komma wieder wegschneiden
    $sql = substr($sql,0,-1);
    
    $g_db->query($sql);
}

if($req_new_user == 1 && $count_assigned == 0)
{
    // Neuem User wurden keine Rollen zugewiesen
    $g_message->show("norolle");
}

// zur Ausgangsseite zurueck
if(strpos($_SESSION['navigation']->getUrl(), "new_user_assign.php") > 0)
{
    // von hier aus direkt zur Registrierungsuebersicht zurueck
    $_SESSION['navigation']->deleteLastUrl();
}
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show("save");