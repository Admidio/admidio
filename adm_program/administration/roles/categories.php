<?php
/******************************************************************************
 * Uebersicht und Pflege aller Kategorien
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * type : (Pflichtuebergabe) 
 *        Typ der Kategorien, die gepflegt werden sollen
 *        ROL = Rollenkategorien
 *        LNK = Linkkategorien
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
 ****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");

// nur Moderatoren duerfen Kategorien erfassen & verwalten
if(!isModerator())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET['type']))
{
    if($_GET['type'] != "ROL" && $_GET['type'] != "LNK")
    {
        $g_message->show("invalid");
    }
}
else
{
    $g_message->show("invalid");
}

$_SESSION['navigation']->addUrl($g_current_url);
unset($_SESSION['categories_request']);

echo "
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Kategorien</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <h1>Kategorien</h1>

        <p>
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/roles/categories_new.php?type=". $_GET['type']. "\"><img 
                src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Kategorie anlegen\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/roles/categories_new.php?type=". $_GET['type']. "\">Kategorie anlegen</a>
            </span>
        </p>

        <table class=\"tableList\" style=\"width: 300px;\" cellpadding=\"2\" cellspacing=\"0\">
            <tr>
                <th class=\"tableHeader\" style=\"text-align: left;\">Bezeichnung</th>
                <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\" title=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\"></th>
                <th class=\"tableHeader\">&nbsp;</th>
            </tr>";
            
            $sql = "SELECT * FROM ". TBL_CATEGORIES. "
                     WHERE cat_org_id = $g_current_organization->id
                       AND cat_type   = {0}
                     ORDER BY cat_name ASC ";
            $sql = prepareSQL($sql, array($_GET['type']));
            $cat_result = mysql_query($sql, $g_adm_con);
            db_error($cat_result);

            while($cat_row = mysql_fetch_object($cat_result))
            {
                // schauen, ob Rollen zu dieser Kategorie existieren
                $sql = "SELECT * FROM ". TBL_ROLES. "
                         WHERE rol_cat_id = $cat_row->cat_id ";
                $result = mysql_query($sql, $g_adm_con);
                db_error($result);
                $row_num = mysql_num_rows($result);

                echo "
                <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                    <td style=\"text-align: left;\"><a href=\"$g_root_path/adm_program/administration/roles/categories_new.php?cat_id=$cat_row->cat_id&amp;type=". $_GET['type']. "\">$cat_row->cat_name</a></td>
                    <td style=\"text-align: center;\">";
                        if($cat_row->cat_hidden == 1)
                        {
                            echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\" title=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\">";
                        }
                        else
                        {
                            echo "&nbsp;";
                        }
                    echo "</td>
                    <td style=\"text-align: right; width: 45px;\">
                        <a href=\"$g_root_path/adm_program/administration/roles/categories_new.php?cat_id=$cat_row->cat_id&amp;type=". $_GET['type']. "\">
                        <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"></a>";
                        // nur Kategorien loeschen, die keine Rollen zugeordnet sind
                        if($row_num == 0)
                        {
                            echo "&nbsp;<a href=\"$g_root_path/adm_program/administration/roles/categories_function.php?cat_id=$cat_row->cat_id&amp;mode=3\"><img
                            src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"></a>";
                        }
                        else
                        {
                            echo "&nbsp;<img src=\"$g_root_path/adm_program/images/dummy.gif\" width=\"16\" border=\"0\" alt=\"Dummy\">";
                        }
                    echo "</td>
                </tr>";
            }
        echo "</table>

        <p>
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\"><img
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Zur&uuml;ck\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
            </span>
        </p>
    </div>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>