<?php
/******************************************************************************
 * Uebersicht und Pflege aller organisationsspezifischen Profilfelder
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$g_current_user->editUser())
{
    $g_message->show("norights");
}

$_SESSION['navigation']->addUrl($g_current_url);
unset($_SESSION['fields_request']);

// zusaetzliche Daten fuer den Html-Kopf setzen
$g_layout['title']  = "Profilfelder";
$g_layout['header'] = "
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/ajax.js\"></script>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/show_hide_block.js\"></script>
    <script src=\"$g_root_path/adm_program/libs/script.aculo.us/prototype.js\" type=\"text/javascript\"></script>
    <script src=\"$g_root_path/adm_program/libs/script.aculo.us/scriptaculous.js\" type=\"text/javascript\"></script>
    
    <style type=\"text/css\">
        .drag {
            background-color: #e9ec79;
        }
    </style>
    
    <script type=\"text/javascript\">
        var resObject     = createXMLHttpRequest();
        
        function updateDB(element)
        {
            var childs = element.childNodes;

            for(i=0;i < childs.length; i++)
            {
                var id = childs[i].getAttribute('id');
                var usf_id = id.substr(4);
                var sequence = i + 1;

                // Synchroner Request, da ansonsten Scriptaculous verrueckt spielt
                resObject.open('GET', 'fields_function.php?usf_id=' + usf_id + '&mode=4&sequence=' + sequence, false);
                resObject.send(null);
            }
        }
    </script>";
    
// Html-Kopf ausgeben
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

echo "
<h1 class=\"moduleHeadline\">Profilfelder</h1>

<p>
    <span class=\"iconLink\">
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/fields_new.php\"><img 
        src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Profilfeld anlegen\"></a>
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/fields_new.php\">Profilfeld anlegen</a>
    </span>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <span class=\"iconLink\">
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/roles/categories.php?type=USF\"><img
        src=\"$g_root_path/adm_program/images/application_double.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Kategorien pflegen\"></a>
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/roles/categories.php?type=USF\">Kategorien pflegen</a>
    </span>
</p>";

$sql = "SELECT * FROM ". TBL_CATEGORIES. ", ". TBL_USER_FIELDS. "
         WHERE cat_type   = 'USF'
           AND usf_cat_id = cat_id
           AND (  cat_org_id = $g_current_organization->id
               OR cat_org_id IS NULL )
         ORDER BY cat_sequence ASC, usf_sequence ASC ";
$result = mysql_query($sql, $g_adm_con);
db_error($result,__FILE__,__LINE__);

$js_drag_drop = "";

echo "
<table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
    <thead>
        <tr>
            <th class=\"tableHeader\" style=\"text-align: left;\" colspan=\"2\">Feld <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=field','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\"></th>
            <th class=\"tableHeader\" style=\"text-align: left;\">Beschreibung</th>
            <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/eye.png\" alt=\"Feld f&uuml;r alle Benutzer bzw. nur berechtigte Nutzer sichtbar\" title=\"Feld f&uuml;r alle Benutzer bzw. nur berechtigte Nutzer sichtbar\"></th>
            <th class=\"tableHeader\"><img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/textfield_key.png\" alt=\"Feld nur f&uuml;r berechtigte Benutzer (Rollenrecht bzw. eigenes Profil) editierbar\" title=\"Feld nur f&uuml;r berechtigte Benutzer (Rollenrecht bzw. eigenes Profil) editierbar\"></th>
            <th class=\"tableHeader\" style=\"text-align: left;\">Datentyp</th>
            <th class=\"tableHeader\">&nbsp;</th>
        </tr>
    </thead>";
    
    $cat_id = 0;

    if(mysql_num_rows($result) > 0)
    {
        while($row = mysql_fetch_object($result))
        {
            if($cat_id != $row->cat_id)
            {
                if($cat_id > 0)
                {
                    echo "</tbody>";
                }
                echo "<tbody>
                    <tr>
                        <td class=\"tableSubHeader\" colspan=\"7\">
                            <div class=\"tableSubHeaderFont\" style=\"float: left;\"><a
                                href=\"javascript:showHideBlock('$row->cat_name', '$g_root_path')\"><img name=\"img_$row->cat_name\" src=\"$g_root_path/adm_program/images/bullet_toggle_minus.png\" 
                                style=\"vertical-align: middle;\" border=\"0\" alt=\"ausblenden\"></a>$row->cat_name</div>
                            <div class=\"smallFontSize\" style=\"text-align: right;\"><a id=\"lnk_$row->cat_name\"
                                href=\"javascript:showHideBlock('$row->cat_name', '$g_root_path')\">ausblenden</a>&nbsp;</div>
                        </td>
                    </tr>
                </tbody>
                <tbody id=\"cat_$row->cat_name\">";
                $js_drag_drop = $js_drag_drop. " Sortable.create('cat_$row->cat_name',{tag:'tr',onUpdate:updateDB,ghosting:true,dropOnEmpty:true,containment:['cat_$row->cat_name'],hoverclass:'drag'}); ";

                $cat_id = $row->cat_id;
            }           
            echo "
            <tr id=\"row_$row->usf_id\" class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                <td style=\"text-align: center\"><img class=\"dragable\" src=\"$g_root_path/adm_program/images/arrow_out.png\" style=\"cursor: move;\" border=\"0\" alt=\"Reihenfolge &auml;ndern\" title=\"Reihenfolge &auml;ndern\"></td>
                <td style=\"text-align: left;\"><a href=\"$g_root_path/adm_program/administration/members/fields_new.php?usf_id=$row->usf_id\">$row->usf_name</a></td>
                <td style=\"text-align: left;\">$row->usf_description</td>
                <td style=\"text-align: center;\">";
                    if($row->usf_hidden == 1)
                    {
                        echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/eye_gray.png\" alt=\"Feld nur f&uuml;r berechtigte Benutzer (eigenes Profil &amp; Rollenrecht) sichtbar\" title=\"Feld nur f&uuml;r berechtigte Benutzer (eigenes Profil &amp; Rollenrecht) sichtbar\">";
                    }
                    else
                    {
                        echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/eye.png\" alt=\"Feld f&uuml;r alle Benutzer sichtbar\" title=\"Feld f&uuml;r alle Benutzer sichtbar\">";
                    }
                echo "</td>
                <td style=\"text-align: center;\">";
                    if($row->usf_disabled == 1)
                    {
                        echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/textfield_key.png\" alt=\"Feld nur f&uuml;r berechtigte Benutzer editierbar\" title=\"Feld nur f&uuml;r berechtigte Benutzer editierbar\">";
                    }
                    else
                    {
                        echo "<img style=\"cursor: help;\" src=\"$g_root_path/adm_program/images/textfield.png\" alt=\"Feld im eigenen Profil und f&uuml;r berechtigte Benutzer editierbar\" title=\"Feld im eigenen Profil und f&uuml;r berechtigte Benutzer editierbar\">";
                    }
                echo "</td>
                <td style=\"text-align: left;\">";
                    if($row->usf_type == "DATE")
                    {
                        echo "Datum";
                    }
                    elseif($row->usf_type == "EMAIL")
                    {
                        echo "E-Mail";
                    }
                    elseif($row->usf_type == "CHECKBOX")
                    {
                        echo "Ja / Nein";
                    }
                    elseif($row->usf_type == "TEXT")
                    {
                        echo "Text (50)";
                    }
                    elseif($row->usf_type == "TEXT_BIG")
                    {
                        echo "Text (255)";
                    }
                    elseif($row->usf_type == "URL")
                    {
                        echo "URL";
                    }
                    elseif($row->usf_type == "NUMERIC")
                    {
                        echo "Zahl";
                    }
                echo "</td>
                <td style=\"text-align: center;\">
                    <a href=\"$g_root_path/adm_program/administration/members/fields_new.php?usf_id=$row->usf_id\">
                    <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"></a>&nbsp;";
                    if($row->usf_system == 1)
                    {
                        echo "<img src=\"$g_root_path/adm_program/images/dummy.gif\" border=\"0\" alt=\"dummy\" style=\"width: 16px; height: 16px;\">";
                    }
                    else
                    {
                        echo "<a href=\"$g_root_path/adm_program/administration/members/fields_function.php?mode=3&amp;usf_id=$row->usf_id\"><img
                        src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"></a>";
                    }
                echo "</td>
            </tr>";
        }
    }
    else
    {
        echo "<tr>
            <td colspan=\"5\" style=\"text-align: center;\">
                <p>Es wurden noch keine organisationsspezifischen Profilfelder angelegt !</p>
            </td>
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

<script type=\"text/javascript\"><!--
    $js_drag_drop
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>