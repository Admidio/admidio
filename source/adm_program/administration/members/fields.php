<?php
/******************************************************************************
 * Uebersicht und Pflege aller organisationsspezifischen Profilfelder
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 ****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$g_current_user->isWebmaster())
{
    $g_message->show("norights");
}

$_SESSION['navigation']->addUrl(CURRENT_URL);
unset($_SESSION['fields_request']);

// zusaetzliche Daten fuer den Html-Kopf setzen
$g_layout['title']  = "Profilfelder";
$g_layout['header'] = "
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/ajax.js\"></script>
    <script src=\"$g_root_path/adm_program/libs/script.aculo.us/prototype.js\" type=\"text/javascript\"></script>
    <script src=\"$g_root_path/adm_program/libs/script.aculo.us/scriptaculous.js?load=effects,dragdrop\" type=\"text/javascript\"></script>
    
    <style type=\"text/css\">
        .drag {
            background-color: #e9ec79;
        }
    </style>
    
    <script type=\"text/javascript\"><!--
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
    --></script>";
    
// Html-Kopf ausgeben
require(THEME_SERVER_PATH. "/overall_header.php");

echo "
<h1 class=\"moduleHeadline\">Profilfelder</h1>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/administration/members/fields_new.php\"><img 
            src=\"". THEME_PATH. "/icons/add.png\" alt=\"Profilfeld anlegen\" /></a>
            <a href=\"$g_root_path/adm_program/administration/members/fields_new.php\">Profilfeld anlegen</a>
        </span>
    </li>
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=USF\"><img
            src=\"". THEME_PATH. "/icons/application_double.png\" alt=\"Kategorien pflegen\" /></a>
            <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=USF\">Kategorien pflegen</a>
        </span>
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
                class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=field&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\"  onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=field',this);\" onmouseout=\"ajax_hideTooltip()\" /></th>
            <th>Beschreibung</th>
            <th><img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/eye.png\" alt=\"Feld für alle Benutzer bzw. nur berechtigte Nutzer sichtbar\" title=\"Feld für alle Benutzer bzw. nur berechtigte Nutzer sichtbar\" /></th>
            <th><img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/textfield_key.png\" alt=\"Feld nur für berechtigte Benutzer (Recht: Alle Benutzer bearbeiten) editierbar\" title=\"Feld nur für berechtigte Benutzer (Recht: Alle Benutzer bearbeiten) editierbar\" /></th>
            <th><img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/asterisk_yellow.png\" alt=\"Pflichtfeld, muss vom Benutzer gefüllt werden\" title=\"Pflichtfeld, muss vom Benutzer gefüllt werden\" /></th>
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
                            <a class=\"iconShowHide\" href=\"javascript:showHideBlock('$block_id', '". THEME_PATH. "')\"><img 
                            id=\"img_$block_id\" src=\"". THEME_PATH. "/icons/triangle_open.gif\" alt=\"ausblenden\" /></a>$row->cat_name
                        </td>
                    </tr>
                </tbody>
                <tbody id=\"$block_id\">";
                $js_drag_drop = $js_drag_drop. " Sortable.create('$block_id',{tag:'tr',onUpdate:updateDB,ghosting:true,dropOnEmpty:true,containment:['$block_id'],hoverclass:'drag'}); ";

                $cat_id = $row->cat_id;
            }           
            echo "
            <tr id=\"row_$row->usf_id\" class=\"tableMouseOver\">
                <td style=\"width: 18px;\"><img class=\"dragable\" src=\"". THEME_PATH. "/icons/arrow_out.png\" alt=\"Reihenfolge &auml;ndern\" title=\"Reihenfolge &auml;ndern\" /></td>
                <td><a href=\"$g_root_path/adm_program/administration/members/fields_new.php?usf_id=$row->usf_id\">$row->usf_name</a></td>
                <td>$row->usf_description</td>
                <td>";
                    if($row->usf_hidden == 1)
                    {
                        echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/eye_gray.png\" alt=\"Feld nur f&uuml;r berechtigte Benutzer (eigenes Profil &amp; Rollenrecht) sichtbar\" title=\"Feld nur f&uuml;r berechtigte Benutzer (eigenes Profil &amp; Rollenrecht) sichtbar\" />";
                    }
                    else
                    {
                        echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/eye.png\" alt=\"Feld f&uuml;r alle Benutzer sichtbar\" title=\"Feld f&uuml;r alle Benutzer sichtbar\" />";
                    }
                echo "</td>
                <td>";
                    if($row->usf_disabled == 1)
                    {
                        echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/textfield_key.png\" alt=\"Feld nur f&uuml;r berechtigte Benutzer editierbar\" title=\"Feld nur f&uuml;r berechtigte Benutzer editierbar\" />";
                    }
                    else
                    {
                        echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/textfield.png\" alt=\"Feld nur für berechtigte Benutzer (Recht: Alle Benutzer bearbeiten) editierbar\" title=\"Feld nur für berechtigte Benutzer (Recht: Alle Benutzer bearbeiten) editierbar\" />";
                    }
                echo "</td>
                <td>";
                    if($row->usf_mandatory == 1)
                    {
                        echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/asterisk_yellow.png\" alt=\"Pflichtfeld, muss vom Benutzer gef&uuml;llt werden\" title=\"Pflichtfeld, muss vom Benutzer gef&uuml;llt werden\" />";
                    }
                    else
                    {
                        echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/asterisk_gray.png\" alt=\"Feld muss nicht zwingend vom Benutzer gef&uuml;llt werden\" title=\"Feld muss nicht zwingend vom Benutzer gef&uuml;llt werden\" />";
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
                <td style=\"text-align: right; width: 45px;\">
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/fields_new.php?usf_id=$row->usf_id\"><img 
                        src=\"". THEME_PATH. "/icons/edit.png\" alt=\"Bearbeiten\" title=\"Bearbeiten\" /></a>";
                    if($row->usf_system == 1)
                    {
                        echo "
                        <span class=\"iconLink\">
                            <img src=\"". THEME_PATH. "/icons/dummy.png\" alt=\"dummy\" />
                        </span>";
                    }
                    else
                    {
                        echo "
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/fields_function.php?mode=3&amp;usf_id=$row->usf_id\"><img
                            src=\"". THEME_PATH. "/icons/cross.png\" alt=\"Löschen\" title=\"Löschen\" /></a>";
                    }
                echo "</td>
            </tr>";
        }
        echo "</tbody>";
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

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
        </span>
    </li>
</ul>

<script type=\"text/javascript\"><!--
    $js_drag_drop
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>