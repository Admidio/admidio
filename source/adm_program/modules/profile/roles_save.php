<?php
/******************************************************************************
 * Funktionen des Benutzers speichern 
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * user_id     - Funktionen der uebergebenen ID aendern
 * new_user: 0 - (Default) Daten eines vorhandenen Users werden bearbeitet
 *           1 - Der User ist gerade angelegt worden -> Rollen muessen zugeordnet werden
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/role_dependency.php");


// nur Webmaster & Moderatoren duerfen Rollen zuweisen
if(!$g_current_user->assignRoles() && !isGroupLeader($g_current_user->getValue("usr_id")) && !$g_current_user->editUsers())
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

if($g_current_user->assignRoles() || $g_current_user->editUsers())
{
    // Benutzer mit Rollenrechten darf ALLE Rollen zuordnen
    // Benutzer ohne Rollenvergaberechte, duerfen nur Rollen zuordnen, die sie sehen duerfen
    // aber auch keine Rollen mit Rollenvergaberechten 
    $sql_roles_condition = "";
    if($g_current_user->editUsers() && !$g_current_user->viewAllLists())
    {
        $sql_roles_condition .= " AND rol_this_list_view > 0 ";
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
                $g_message->show("max_members_profile", $row->rol_name);
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
    if($g_current_user->assignRoles() || $g_current_user->viewRole($row->rol_id))
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
            if($row->mem_usr_id > 0)
            {
                // neue Rollenmitgliederdaten zurueckschreiben, falls sich diese geaendert haben
                if($role_assign == 1)
                {
                    $sql = "UPDATE ". TBL_MEMBERS. " SET mem_valid  = 1
                                                       , mem_end    = NULL
                                                       , mem_leader = $role_leader
                                WHERE mem_rol_id = $row->rol_id
                                  AND mem_usr_id = $req_usr_id ";
                    $g_db->query($sql);
                    $count_assigned++;
                }
                else
                {
                    $sql = "UPDATE ". TBL_MEMBERS. " SET mem_valid  = 0
                                                       , mem_end    = '".date("Y-m-d", time())."'
                                                       , mem_leader = $role_leader
                                WHERE mem_rol_id = $row->rol_id
                                  AND mem_usr_id = $req_usr_id ";
                    $g_db->query($sql);
                }
            }
            else
            {
                // neue Mitgliederdaten einfuegen, aber nur, wenn auch ein Haeckchen da ist
                if($role_assign == 1)
                {
                    $sql = "INSERT INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin,mem_end, mem_valid, mem_leader)
                              VALUES ($row->rol_id, $req_usr_id, '".date("Y-m-d", time())."',NULL, 1, $role_leader) ";
                    $g_db->query($sql);
                    $count_assigned++;
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
}

$_SESSION['navigation']->deleteLastUrl();

// falls Rollen dem eingeloggten User neu zugewiesen wurden, 
// dann muessen die Rechte in den Session-Variablen neu eingelesen werden
$g_current_session->renewUserObject();

if(count($parentRoles) > 0 )
{
    $sql = "REPLACE INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin,mem_end, mem_valid, mem_leader) VALUES ";

    // alle einzufuegenden Rollen anhaengen
    foreach($parentRoles as $actRole)
    {
        $sql .= " ($actRole, $req_usr_id, '".date("Y-m-d", time())."', NULL, 1, 0),";
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