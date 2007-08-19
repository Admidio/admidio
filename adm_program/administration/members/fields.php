<?php
/******************************************************************************
 * Uebersicht und Pflege aller organisationsspezifischen Profilfelder
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
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
    <script src=\"$g_root_path/adm_program/libs/script.aculo.us/scriptaculous.js?load=effects,dragdrop\" type=\"text/javascript\"></script>
    
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
                resObject.open('GET', '$g_root_path/adm_program/administration/members/fields_function.php?usf_id=' + usf_id + '&mode=4&sequence=' + sequence, false);
                resObject.send(null);
            }
        }
    </script>";
    
// Html-Kopf ausgeben
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

echo "
<h1 class=\"moduleHeadline\">Profilfelder</h1>

<ul class=\"iconTextLink\">
    <li>
        <a href=\"$g_root_path/adm_program/administration/members/fields_new.php\"><img 
        src=\"$g_root_path/adm_program/images/add.png\" alt=\"Profilfeld anlegen\"></a>
        <a href=\"$g_root_path/adm_program/administration/members/fields_new.php\">Profilfeld anlegen</a>
    </li>
    <li>
        <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=USF\"><img
        src=\"$g_root_path/adm_program/images/application_double.png\" alt=\"Kategorien pflegen\"></a>
        <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=USF\">Kategorien pflegen</a>
    </li>
</ul>";

$sql = "SELECT * FROM ". TBL_CATEGORIES. ", ". TBL_USER_FIELDS. "
         WHERE cat_type   = 'USF'
           AND usf_cat_id = cat_id
           AND (  cat_org_id = ". $g_current_organization->getValue("org_id"). "
               OR cat_org_id IS NULL )
         ORDER BY cat_sequence ASC, usf_sequence ASC ";
$result = $g_db->query($sql);

$js_drag_drop = "";

echo "
<table class=\"tableList\" cellspacing=\"0\">
    <thead>
        <tr>
            <th colspan=\"2\">Feld<img 
                class=\"iconHelpLink\" src=\"$g_root_path/adm_program/images/help.png\" alt=\"Hilfe\" title=\"Hilfe\"
                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=field','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\"></th>
            <th>Beschreibung</th>
            <th><img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/eye.png\" alt=\"Feld f&uuml;r alle Benutzer bzw. nur berechtigte Nutzer sichtbar\" title=\"Feld f&uuml;r alle Benutzer bzw. nur berechtigte Nutzer sichtbar\"></th>
            <th><img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/textfield_key.png\" alt=\"Feld nur f&uuml;r berechtigte Benutzer (Rollenrecht bzw. eigenes Profil) editierbar\" title=\"Feld nur f&uuml;r berechtigte Benutzer (Rollenrecht bzw. eigenes Profil) editierbar\"></th>
            <th><img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/asterisk_yellow.png\" alt=\"Pflichtfeld, muss vom Benutzer gef&uuml;llt werden\" title=\"Pflichtfeld, muss vom Benutzer gef&uuml;llt werden\"></th>
            <th>Datentyp</th>
            <th style=\"width: 40px;\">&nbsp;</th>
        </tr>
    </thead>";
    
    $cat_id = 0;

    if($g_db->num_rows($result) > 0)
    {
        while($row = $g_db->fetch_object($result))
        {
            if($cat_id != $row->cat_id)
            {
                if($cat_id > 0)
                {
                    echo "</tbody>";
                }
                $block_id = "cat_$row->cat_id";
                echo "<tbody>
                    <tr>
                        <td class=\"tableSubHeader\" colspan=\"8\">
                            <a
                                href=\"javascript:showHideBlock('$block_id', '$g_root_path')\"><img name=\"img_$block_id\" 
                                style=\"vertical-align: middle; padding: 1px 5px 2px 3px;\" src=\"$g_root_path/adm_program/images/triangle_open.gif\" 
                                border=\"0\" alt=\"ausblenden\"></a>$row->cat_name
                        </td>
                    </tr>
                </tbody>
                <tbody id=\"$block_id\">";
                $js_drag_drop = $js_drag_drop. " Sortable.create('$block_id',{tag:'tr',onUpdate:updateDB,ghosting:true,dropOnEmpty:true,containment:['$block_id'],hoverclass:'drag'}); ";

                $cat_id = $row->cat_id;
            }           
            echo "
            <tr id=\"row_$row->usf_id\" class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                <td style=\"width: 18px;\"><img class=\"dragable\" src=\"$g_root_path/adm_program/images/arrow_out.png\" alt=\"Reihenfolge &auml;ndern\" title=\"Reihenfolge &auml;ndern\"></td>
                <td><a href=\"$g_root_path/adm_program/administration/members/fields_new.php?usf_id=$row->usf_id\">$row->usf_name</a></td>
                <td>$row->usf_description</td>
                <td>";
                    if($row->usf_hidden == 1)
                    {
                        echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/eye_gray.png\" alt=\"Feld nur f&uuml;r berechtigte Benutzer (eigenes Profil &amp; Rollenrecht) sichtbar\" title=\"Feld nur f&uuml;r berechtigte Benutzer (eigenes Profil &amp; Rollenrecht) sichtbar\">";
                    }
                    else
                    {
                        echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/eye.png\" alt=\"Feld f&uuml;r alle Benutzer sichtbar\" title=\"Feld f&uuml;r alle Benutzer sichtbar\">";
                    }
                echo "</td>
                <td>";
                    if($row->usf_disabled == 1)
                    {
                        echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/textfield_key.png\" alt=\"Feld nur f&uuml;r berechtigte Benutzer editierbar\" title=\"Feld nur f&uuml;r berechtigte Benutzer editierbar\">";
                    }
                    else
                    {
                        echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/textfield.png\" alt=\"Feld im eigenen Profil und f&uuml;r berechtigte Benutzer editierbar\" title=\"Feld im eigenen Profil und f&uuml;r berechtigte Benutzer editierbar\">";
                    }
                echo "</td>
                <td>";
                    if($row->usf_mandatory == 1)
                    {
                        echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/asterisk_yellow.png\" alt=\"Pflichtfeld, muss vom Benutzer gef&uuml;llt werden\" title=\"Pflichtfeld, muss vom Benutzer gef&uuml;llt werden\">";
                    }
                    else
                    {
                        echo "<img class=\"iconInformation\" src=\"$g_root_path/adm_program/images/asterisk_gray.png\" alt=\"Feld muss nicht zwingend vom Benutzer gef&uuml;llt werden\" title=\"Feld muss nicht zwingend vom Benutzer gef&uuml;llt werden\">";
                    }
                echo "</td>
                <td>";
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
                <td style=\"text-align: right;\">
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

<ul class=\"iconTextLink\">
    <li>
        <a href=\"$g_root_path/adm_program/system/back.php\"><img 
        src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
        <a href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
    </li>
</ul>

<script type=\"text/javascript\"><!--
    $js_drag_drop
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>