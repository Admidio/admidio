<?php
/******************************************************************************
 * Anzeigen von Listen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * category - Kategorie der Rollen, die angezeigt werden sollen
 *            Wird keine Kategorie uebergeben, werden alle Rollen angezeigt
 * category-selection: yes - (Default) Anzeige der Combobox mit den verschiedenen 
 *                           Rollen-Kategorien
 *                     no  - Combobox nicht anzeigen
 * active_role : 1 - (Default) aktive Rollen auflisten
 *               0 - Ehemalige Rollen auflisten
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

// Uebergabevariablen pruefen und ggf. vorbelegen

if(isset($_GET['category']))
{
    $category = strStripTags($_GET['category']);
}
else
{
    $category = "";
}

if(!isset($_GET['category-selection']))
{
    $_GET['category-selection'] = "yes";
    $show_ctg_sel = 1;
}
else
{
    if($_GET['category-selection'] == "yes")
    {
        $show_ctg_sel = 1;
    }
    else
    {
        $show_ctg_sel = 0;
    }
}

if(!isset($_GET['active_role']))
{
    $active_role = 1;
}
else
{
    if($_GET['active_role'] != 0
    && $_GET['active_role'] != 1)
    {
        $active_role = 1;
    }
    else
    {
        $active_role = $_GET['active_role'];
    }
}   

// SQL-Statement zusammensetzen

$sql = "SELECT * FROM ". TBL_ROLES. ", ". TBL_ROLE_CATEGORIES. "
         WHERE rol_org_shortname = '$g_organization' 
           AND rol_valid  = $active_role 
           AND rol_rlc_id = rlc_id ";
if(!isModerator()) 
{
    // wenn nicht Moderator, dann keine versteckten Rollen anzeigen
    $sql .= " AND rol_locked = 0 ";
}
if($g_session_valid == false)
{
    $sql .= " AND rlc_locked = 0 ";
}
if(strlen($category) > 0 && $category != "Alle")
{
    // wenn eine Kategorie uebergeben wurde, dann nur Rollen dieser anzeigen
    $sql .= " AND rlc_org_shortname = '$g_organization' 
              AND rlc_name          = '$category' ";
}
$sql .= "ORDER BY rol_name ";

$result_lst = mysql_query($sql, $g_adm_con);
db_error($result_lst);
$list_found = mysql_num_rows($result_lst);

if($list_found == 0)
{
    if($g_session_valid == true)
    {
        // wenn User eingeloggt, dann Meldung, dass keine Rollen in der Kategorie existieren
        if($active_role == 0)
        {
            $err_code = "no_old_roles";    
        }
        else
        {
            $err_code = "no_category_roles";    
        }
        $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_head=Hinweis";
        header($location);
        exit();
    }
    else
    {
        // wenn User ausgeloggt, dann Login-Bildschirm anzeigen
        require("../../system/login_valid.php");
    }
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Listen</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->

    <script language=\"JavaScript\" type=\"text/javascript\"><!--\n
        function showCategory()
        {
            var category = document.getElementById('category').value;
            self.location.href = 'lists.php?category=' + category + '&category-selection=". $_GET['category-selection']. "&active_role=$active_role';   
        }

        function showList(element, rol_id)
        {
            var sel_list = element.value;

            if(sel_list == 'address')
            {
                self.location.href = 'lists_show.php?type=address&mode=html&rol_id=' + rol_id;
            }
            else if(sel_list == 'telefon')
            {
                self.location.href = 'lists_show.php?type=telephone&mode=html&rol_id=' + rol_id;
            }
            else if(sel_list == 'mylist')
            {
                self.location.href = 'mylist.php?rol_id=' + rol_id";
            if($active_role)
                echo ";";
            else
                echo " + '&active_role=0&active_member=0';";
            echo "
            }
            else if(sel_list == 'former')
            {
                self.location.href = 'lists_show.php?type=former&mode=html&rol_id=' + rol_id;
            }
        }
    //--></script>";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo '<div style="margin-top: 10px; margin-bottom: 10px;" align="center">
        <h1>';
            if($active_role)
            {
                echo strspace("Aktive Rollen");
            }
            else
            {
                echo strspace("Ehemalige Rollen");
            }
        echo '</h1>';

        if($show_ctg_sel == 1)
        {
            // Combobox mit allen Kategorien anzeigen
            $sql = "SELECT * FROM ". TBL_ROLE_CATEGORIES. "
                     WHERE rlc_org_shortname = '$g_organization' ";
            if($g_session_valid == false)
            {
                $sql .= " AND rlc_locked = 0 ";
            }
            $sql .= " ORDER BY rlc_name ASC ";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);

            if(mysql_num_rows($result) > 0)
            {   
                echo '<p>Kategorie w&auml;hlen:&nbsp;&nbsp;
                <select size="1" id="category" onchange="showCategory()">
                    <option value="Alle" '; 
                    if(strlen($category) == 0)
                    {
                        echo " selected ";
                    }
                    echo '>Alle</option>';

                    while($row = mysql_fetch_object($result))
                    {
                        echo '<option value="'. urlencode($row->rlc_name). '"';
                        if($category == $row->rlc_name)
                        {
                            echo " selected ";
                        }
                        echo ">$row->rlc_name</option>";
                    }
                echo '</select></p>';
            }
        }

        echo "<div class=\"formHead\">";
            if(strlen($category) > 0)
            {
                echo strspace($category);
            }
            else
            {
                echo strspace("Alle");
            }
        echo "</div>

        <div class=\"formBody\">";
            $i = 0;

            while($row_lst = mysql_fetch_object($result_lst))
            {
                // Anzahl Mitglieder ermitteln
                $sql = "SELECT COUNT(*)
                          FROM ". TBL_MEMBERS. "
                         WHERE mem_rol_id = $row_lst->rol_id
                           AND mem_valid  = $active_role ";
                $result = mysql_query($sql, $g_adm_con);
                db_error($result);
                $row    = mysql_fetch_array($result);
                $num_member = $row[0];

                if($active_role)
                {
                    // Anzahl ehemaliger Mitglieder ermitteln
                    $sql = "SELECT COUNT(*)
                              FROM ". TBL_MEMBERS. "
                             WHERE mem_rol_id = $row_lst->rol_id
                               AND mem_valid  = 0 ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result);
                    $row    = mysql_fetch_array($result);
                    $num_former = $row[0];
                }

                if($i > 0)
                {
                    echo"<hr width=\"98%\" />";
                }

                echo "
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: left; float: left;\">&nbsp;";
                        // Link nur anzeigen, wenn Rolle auch Mitglieder hat
                        if($num_member > 0)
                        {
                            echo "<a href=\"lists_show.php?type=";
                            if($active_role)
                            {
                                echo "address";
                            }
                            else
                            {
                                echo "former";
                            }
                            echo "&amp;mode=html&amp;rol_id=$row_lst->rol_id\">$row_lst->rol_name</a>";
                        }
                        else
                        {
                            echo "<b>$row_lst->rol_name</b>";
                        }

                        if(isModerator() || isGroupLeader($row_lst->rol_id) || editUser())
                        {
                            if($row_lst->rol_name != "Webmaster"
                            || ($row_lst->rol_name == "Webmaster" && hasRole("Webmaster")))
                            {
                                if(isModerator())
                                {
                                    // nur Moderatoren duerfen Rollen editieren
                                    echo "&nbsp;<a href=\"$g_root_path/adm_program/administration/roles/roles_new.php?rol_id=$row_lst->rol_id\"><img 
                                        src=\"$g_root_path/adm_program/images/edit.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" 
                                        width=\"16\" height=\"16\" border=\"0\" alt=\"Einstellungen\" title=\"Einstellungen\"></a>";    
                                }

                                // Gruppenleiter und Moderatoren duerfen Mitglieder zuordnen oder entfernen (nicht bei Ehemaligen Rollen)
                                if($row_lst->rol_valid==1)
                                {
                                    echo "&nbsp;<img src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle; padding-bottom: 1px; cursor: pointer;\" 
                                        width=\"16\" height=\"16\" border=\"0\" alt=\"Mitglieder zuordnen\" title=\"Mitglieder zuordnen\" 
                                        onclick=\"window.open('$g_root_path/adm_program/modules/lists/members.php?rol_id=$row_lst->rol_id&amp;popup=1','Titel','width=550,height=550,left=310,top=100,scrollbars=yes,resizable=yes')\">";    
                                }
                            }
                        }
                    echo "</div>
                    <div style=\"text-align: right;\">";
                        // Kombobox mit Listen nur anzeigen, wenn die Rolle Mitglieder hat
                        if($num_member > 0)
                        {
                            echo "
                            <select size=\"1\" name=\"list$i\" onchange=\"showList(this, $row_lst->rol_id)\">
                                <option value=\"\" selected=\"selected\">Liste anzeigen ...</option>
                                <option value=\"address\">Adressliste</option>
                                <option value=\"telefon\">Telefonliste</option>";
                                if($active_role && $num_former > 0) 
                                {
                                    echo "<option value=\"former\">Ehemaligenliste</option>";
                                }
                                echo "<option value=\"mylist\">Eigene Liste ...</option>
                            </select>";
                        }
                        else
                        {
                            echo "&nbsp;";
                        }
                    echo "</div>
                </div>";

                if(strlen($row_lst->rol_description) > 0)
                {
                    echo "<div style=\"margin-top: 3px;\">
                        <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Beschreibung:</div>
                        <div style=\"margin-left: 160px; text-align: left;\">$row_lst->rol_description</div>
                    </div>";
                }

                if(strlen(mysqldate("d.m.y", $row_lst->rol_start_date)) > 0)
                {
                    echo "<div style=\"margin-top: 3px;\">
                        <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Zeitraum:</div>
                        <div style=\"text-align: left;\">". mysqldate("d.m.y", $row_lst->rol_start_date). " bis ". mysqldate("d.m.y", $row_lst->rol_end_date). "</div>";
                    echo "</div>";
                }
                if($row_lst->rol_weekday > 0
                || (  strcmp(mysqltime("h:i", $row_lst->rol_start_time), "00:00") != 0)
                && $row_lst->rol_start_time != NULL )
                {
                    echo "<div style=\"margin-top: 3px;\">
                        <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Gruppenstunde:</div>
                        <div style=\"text-align: left;\">". $arrDay[$row_lst->rol_weekday-1];
                            if(strcmp(mysqltime("h:i", $row_lst->rol_start_time), "00:00") != 0)
                            {
                                echo " von ". mysqltime("h:i", $row_lst->rol_start_time). " bis ". mysqltime("h:i", $row_lst->rol_end_time);
                            }
                        echo "</div>";
                    echo "</div>";
                }
                if(strlen($row_lst->rol_location) > 0)
                {
                    echo "<div style=\"margin-top: 3px;\">
                        <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Treffpunkt:</div>
                        <div style=\"text-align: left;\">$row_lst->rol_location</div>
                    </div>";
                }
                echo "
                <div style=\"margin-top: 3px;\">
                    <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Teilnehmer:</div>
                    <div style=\"text-align: left;\">$num_member";
                        if($row_lst->rol_max_members > 0)
                        {
                            echo " von max. $row_lst->rol_max_members";
                        }
                        if($active_role && $num_former > 0)
                        {
                            // Anzahl Ehemaliger anzeigen
                            if($num_former == 1)
                            {
                                echo "&nbsp;&nbsp;($num_former Ehemaliger) ";
                            }
                            else
                            {
                                echo "&nbsp;&nbsp;($num_former Ehemalige) ";
                            }
                        }
                    echo "</div>
                </div>";
                if(strlen($row_lst->rol_cost) > 0)
                {
                    echo "<div style=\"margin-top: 3px;\">
                        <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Beitrag:</div>
                        <div style=\"margin-left: 160px; text-align: left;\">$row_lst->rol_cost &euro;</div>
                    </div>";
                }
                $i++;
            }
        echo "</div>
    </div>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>