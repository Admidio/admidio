<?php
/******************************************************************************
 * Funktionen zuordnen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id     - Funktionen der uebergebenen user_id aendern
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

$user     = new User($g_adm_con, $req_usr_id);
$_SESSION['navigation']->addUrl($g_current_url);

//Testen ob Feste Rolle gesetzt ist
if(isset($_SESSION['set_rol_id']))
{
    $set_rol_id = $_SESSION['set_rol_id'];
    unset($_SESSION['set_rol_id']);
}
else
{
    $set_rol_id = NULL;
}

// Html-Kopf ausgeben
$g_layout['title']  = "Rollen zuordnen";
$g_layout['header'] = "
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/show_hide_block.js\"></script>
    <script type=\"text/javascript\">
        function markMember(element)
        {
            if(element.checked == true)
            {
                var name   = element.name;
                var pos_number = name.search('-') + 1;
                var number = name.substr(pos_number, name.length - pos_number);
                var role_name = 'role-' + number;
                document.getElementById(role_name).checked = true;
            }
        }

        function unmarkLeader(element)
        {
            if(element.checked == false)
            {
                var name   = element.name;
                var pos_number = name.search('-') + 1;
                var number = name.substr(pos_number, name.length - pos_number);
                var role_name = 'leader-' + number;
                document.getElementById(role_name).checked = false;
            }
        }
    </script>";

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

echo "
<h1 class=\"moduleHeadline\">Rollen zuordnen</h1>
<h3>Profil von ". $user->getValue("Vorname"). " ". $user->getValue("Nachname"). "</h3>

<form action=\"$g_root_path/adm_program/modules/profile/roles_save.php?user_id=$req_usr_id&amp;new_user=$req_new_user\" method=\"post\" name=\"Funktionen\">
    <table class=\"tableList\" cellpadding=\"3\" cellspacing=\"0\">
        <thead>
            <tr>
                <th class=\"tableHeader\">&nbsp;</th>
                <th class=\"tableHeader\" style=\"text-align: left;\">Rolle</th>
                <th class=\"tableHeader\" style=\"text-align: left;\">Beschreibung</th>
                <th class=\"tableHeader\" style=\"text-align: center; width: 80px;\">Leiter
                    <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle; padding-bottom: 1px;\"
                    width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                    onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=leader','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                </th>
            </tr>
        </thead>";

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
            
            $sql    = "SELECT cat_id, cat_name, rol_name, rol_description, rol_id, mem_usr_id, mem_leader
                         FROM ". TBL_CATEGORIES. ", ". TBL_ROLES. " 
                         LEFT JOIN ". TBL_MEMBERS. "
                           ON rol_id     = mem_rol_id
                          AND mem_usr_id = $req_usr_id
                          AND mem_valid  = 1
                        WHERE rol_valid  = 1
                              $sql_roles_condition
                          AND rol_cat_id = cat_id
                          AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
                        ORDER BY cat_sequence, rol_name";
        }
        else
        {
            // Ein Leiter darf nur Rollen zuordnen, bei denen er auch Leiter ist
            $sql    = "SELECT cat_id, cat_name, rol_name, rol_description, rol_id, 
                              mgl.mem_usr_id as mem_usr_id, mgl.mem_leader as mem_leader
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
        error_log($sql);
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);
        $category = "";

        while($row = mysql_fetch_object($result))
        {
            if($category != $row->cat_name)
            {
                if(strlen($category) > 0)
                {
                    echo "</tbody>";
                }
                $block_id = "cat_$row->cat_id";
                echo "<tbody>
                    <tr>
                        <td class=\"tableSubHeader\" colspan=\"4\">
                            <a href=\"javascript:showHideBlock('$block_id','$g_root_path')\"><img name=\"img_$block_id\" 
                                style=\"padding: 1px 5px 2px 3px; vertical-align: middle;\" src=\"$g_root_path/adm_program/images/triangle_open.gif\" 
                                border=\"0\" alt=\"ausblenden\"></a>$row->cat_name
                        </td>
                    </tr>
                </tbody>
                <tbody id=\"$block_id\">";

                $category = $row->cat_name;
            }
            echo "
            <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
               <td style=\"text-align: center; vertical-align: top;\">
                  <input type=\"checkbox\" id=\"role-$row->rol_id\" name=\"role-$row->rol_id\" ";
                     if($row->mem_usr_id > 0)
                     {
                        echo " checked ";
                     }

                     // wenn der User aus der Mitgliederzuordnung heraus neu angelegt wurde
                     // entsprechende Rolle sofort hinzufuegen
                     if($row->rol_id == $set_rol_id)
                     {
                        echo " checked ";
                     }

                     // die Funktion Webmaster darf nur von einem Webmaster vergeben werden
                     if($row->rol_name == 'Webmaster' && !$g_current_user->isWebmaster())
                     {
                        echo " disabled ";
                     }

                     echo " onclick=\"unmarkLeader(this)\" value=\"1\" />
               </td>
               <td style=\"text-align: left; vertical-align: top;\"><label for=\"role-$row->rol_id\">$row->rol_name</label></td>
               <td style=\"text-align: left; vertical-align: top;\">$row->rol_description</td>
               <td style=\"text-align: center; vertical-align: top;\">
                        <input type=\"checkbox\" id=\"leader-$row->rol_id\" name=\"leader-$row->rol_id\" ";
                        if($row->mem_leader > 0)
                        {
                            echo " checked ";
                        }

                        // die Funktion Webmaster darf nur von einem Webmaster vergeben werden
                        if($row->rol_name == 'Webmaster' && !$g_current_user->isWebmaster())
                        {
                            echo " disabled ";
                        }

                        echo " onclick=\"markMember(this)\" value=\"1\" />
               </td>
            </tr>";
        }
    echo "</table>

    <div style=\"margin: 8px;\">";
        echo "<button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\">
            <img src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\">
              &nbsp;Zur&uuml;ck</button>

        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

        <button name=\"speichern\" type=\"submit\" value=\"speichern\">
            <img src=\"$g_root_path/adm_program/images/disk.png\" alt=\"Speichern\">
            &nbsp;Speichern</button>
    </div>
</form>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>