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

// lokale Variablen der Uebergabevariablen initialisieren
$req_type = "";

// Uebergabevariablen pruefen

// Modus und Rechte pruefen
if(isset($_GET['type']))
{
    if($_GET['type'] != "ROL" && $_GET['type'] != "LNK" && $_GET['type'] != "USF")
    {
        $g_message->show("invalid");
    }
    if($_GET['type'] == "ROL" && $g_current_user->assignRoles() == false)
    {
        $g_message->show("norights");
    }
    if($_GET['type'] == "LNK" && $g_current_user->editWeblinksRight() == false)
    {
        $g_message->show("norights");
    }
    if($_GET['type'] == "USF" && $g_current_user->editUser() == false)
    {
        $g_message->show("norights");
    }
    $req_type = $_GET['type'];
}
else
{
    $g_message->show("invalid");
}

$_SESSION['navigation']->addUrl($g_current_url);
unset($_SESSION['categories_request']);

// Html-Kopf ausgeben
$g_layout['title'] = "Kategorien";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<h1 class=\"moduleHeadline\">Kategorien</h1>

<p>
    <span class=\"iconLink\">
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/roles/categories_new.php?type=$req_type\"><img 
        src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Kategorie anlegen\"></a>
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/roles/categories_new.php?type=$req_type\">Kategorie anlegen</a>
    </span>
</p>

<table class=\"tableList\" style=\"width: 300px;\" cellpadding=\"2\" cellspacing=\"0\">
    <tr>
        <th class=\"tableHeader\" style=\"text-align: left;\">Bezeichnung</th>
        <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\" title=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\"></th>
        <th class=\"tableHeader\">&nbsp;</th>
    </tr>";

    $sql = "SELECT * FROM ". TBL_CATEGORIES. "
             WHERE (  cat_org_id  = $g_current_organization->id
                   OR cat_org_id IS NULL )
               AND cat_type   = {0}
             ORDER BY cat_sequence ASC ";
    $sql = prepareSQL($sql, array($req_type));
    $cat_result = mysql_query($sql, $g_adm_con);
    db_error($cat_result,__FILE__,__LINE__);

    while($cat_row = mysql_fetch_object($cat_result))
    {
        echo "
        <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
            <td style=\"text-align: left;\"><a href=\"$g_root_path/adm_program/administration/roles/categories_new.php?cat_id=$cat_row->cat_id&amp;type=$req_type\">$cat_row->cat_name</a></td>
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
                <a href=\"$g_root_path/adm_program/administration/roles/categories_new.php?cat_id=$cat_row->cat_id&amp;type=$req_type\">
                <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"></a>&nbsp;";

                if($cat_row->cat_system == 1)
                {
                    echo "<img src=\"$g_root_path/adm_program/images/dummy.gif\" border=\"0\" alt=\"dummy\" style=\"width: 16px; height: 16px;\">";
                }
                else
                {
                    echo "<a href=\"$g_root_path/adm_program/administration/roles/categories_function.php?cat_id=$cat_row->cat_id&amp;mode=3&amp;type=$req_type\"><img
                    src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"></a>";
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
</p>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>