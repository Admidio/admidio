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

$user     = new User($g_adm_con);
$user->GetUser($req_usr_id);
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

echo "
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>Rollen zuordnen</title>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\">
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

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
        
        function showHideCategory(category_name)
        {
            var block_element = 'cat_' + category_name;
            var link_element  = 'lnk_' + category_name;
            var image_element = 'img_' + category_name;
            
            if(document.getElementById(block_element).style.visibility == 'hidden')
            {
                document.getElementById(block_element).style.visibility = 'visible';
                document.getElementById(block_element).style.display    = '';
                document.getElementById(link_element).innerHTML         = 'ausblenden';
                document.images[image_element].src = '$g_root_path/adm_program/images/bullet_toggle_minus.png';
            }
            else
            {
                document.getElementById(block_element).style.visibility = 'hidden';
                document.getElementById(block_element).style.display    = 'none';
                document.getElementById(link_element).innerHTML         = 'einblenden';
                document.images[image_element].src = '$g_root_path/adm_program/images/bullet_toggle_plus.png';
            }
        }
    </script>

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";
    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
    <h1>Rollen f&uuml;r $user->first_name $user->last_name zuordnen</h1>

    <form action=\"roles_save.php?user_id=$req_usr_id&amp;new_user=$req_new_user\" method=\"post\" name=\"Funktionen\">
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

            if(isModerator())
            {
                // Alle Rollen der Gruppierung auflisten
                $sql    = "SELECT cat_name, rol_name, rol_description, mem_usr_id, mem_leader, rol_id
                             FROM ". TBL_CATEGORIES. ", ". TBL_ROLES. " 
                             LEFT JOIN ". TBL_MEMBERS. "
                               ON rol_id     = mem_rol_id
                              AND mem_usr_id = {0}
                              AND mem_valid  = 1
                            WHERE rol_org_shortname = '$g_organization'
                              AND rol_valid  = 1
                              AND rol_cat_id = cat_id
                            ORDER BY cat_name, rol_name";
            }
            elseif(isGroupLeader())
            {
                // Alle Rollen auflisten, bei denen das Mitglied Leiter ist
                $sql    = "SELECT cat_name, br.rol_name, br.rol_description, br.rol_id, mgl.mem_usr_id, mgl.mem_leader
                             FROM ". TBL_MEMBERS. " bm, ". TBL_CATEGORIES. ", ". TBL_ROLES. " br 
                             LEFT JOIN ". TBL_MEMBERS. " mgl
                               ON br.rol_id      = mgl.mem_rol_id
                              AND mgl.mem_usr_id = {0}
                              AND mgl.mem_valid  = 1
                            WHERE bm.mem_usr_id  = $g_current_user->id
                              AND bm.mem_valid   = 1
                              AND bm.mem_leader  = 1
                              AND br.rol_id      = bm.mem_rol_id
                              AND br.rol_org_shortname = '$g_organization'
                              AND br.rol_valid   = 1
                              AND br.rol_locked  = 0
                              AND br.rol_cat_id = cat_id
                            ORDER BY cat_name, br.rol_name";
            }
            elseif($g_current_user->editUser())
            {
                // Alle Rollen auflisten, die keinen Moderatorenstatus haben
                $sql    = "SELECT cat_name, rol_name, rol_description, rol_id, mem_usr_id, mem_leader
                             FROM ". TBL_CATEGORIES. ", ". TBL_ROLES. " 
                             LEFT JOIN ". TBL_MEMBERS. "
                               ON rol_id     = mem_rol_id
                              AND mem_usr_id = {0}
                              AND mem_valid  = 1
                            WHERE rol_org_shortname = '$g_organization'
                              AND rol_valid      = 1
                              AND rol_moderation = 0
                              AND rol_locked     = 0
                              AND rol_cat_id = cat_id
                            ORDER BY cat_name, rol_name";
            }
            $sql    = prepareSQL($sql, array($req_usr_id));
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
                    echo "<tbody>
                        <tr>
                            <td class=\"tableSubHeader\" colspan=\"4\">
                                <div class=\"tableSubHeaderFont\" style=\"float: left;\"><a 
                                    href=\"javascript:showHideCategory('$row->cat_name')\"><img name=\"img_$row->cat_name\" src=\"$g_root_path/adm_program/images/bullet_toggle_minus.png\" 
                                    style=\"vertical-align: middle;\" border=\"0\" alt=\"ausblenden\"></a>$row->cat_name</div>
                                <div class=\"smallFontSize\" style=\"text-align: right;\"><a id=\"lnk_$row->cat_name\"
                                    href=\"javascript:showHideCategory('$row->cat_name')\">ausblenden</a>&nbsp;</div>
                            </td>
                        </tr>
                    </tbody>
                    <tbody id=\"cat_$row->cat_name\">";
                
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
                <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                  &nbsp;Zur&uuml;ck</button>
                  
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                &nbsp;Speichern</button>
        </div>
    </form>

    </div>";
    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>