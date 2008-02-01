<?php
/******************************************************************************
 * Uebersicht und Pflege aller Kategorien
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * type : (Pflichtuebergabe) 
 *        Typ der Kategorien, die gepflegt werden sollen
 *        ROL = Rollenkategorien
 *        LNK = Linkkategorien
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

$_SESSION['navigation']->addUrl(CURRENT_URL);
unset($_SESSION['categories_request']);

// Html-Kopf ausgeben
$g_layout['title']  = "Kategorien";
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
            var this_orga = 0;
            
            if(element.id == 'cat_list')
            {
                this_orga = 1;
            }
                        
            for(i=0;i < childs.length; i++)
            {
                var id = childs[i].getAttribute('id');
                var cat_id = id.substr(4);
                var sequence = i + 1;
                // Synchroner Request, da ansonsten Scriptaculous verrueckt spielt
                resObject.open('GET', '$g_root_path/adm_program/administration/roles/categories_function.php?cat_id=' + cat_id + '&type=". $_GET['type']. "&mode=4&sequence=' + sequence + '&this_orga=' + this_orga, false);
                resObject.send(null);
            }
        }
    --></script>";
    
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<h1 class=\"moduleHeadline\">Kategorien</h1>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/administration/roles/categories_new.php?type=$req_type\"><img 
            src=\"". THEME_PATH. "/icons/add.png\" alt=\"Kategorie anlegen\" /></a>
            <a href=\"$g_root_path/adm_program/administration/roles/categories_new.php?type=$req_type\">Kategorie anlegen</a>
        </span>
    </li>
</ul>

<table class=\"tableList\" style=\"width: 300px;\" cellspacing=\"0\">
    <thead>
        <tr>
            <th colspan=\"2\">Bezeichnung</th>
            <th><img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/user_key.png\" alt=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\" title=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\" /></th>
            <th>&nbsp;</th>
        </tr>
    </thead>";
    
    $sql = "SELECT * FROM ". TBL_CATEGORIES. "
             WHERE (  cat_org_id  = ". $g_current_organization->getValue("org_id"). "
                   OR cat_org_id IS NULL )
               AND cat_type   = '$req_type'
             ORDER BY cat_sequence ASC ";
    $cat_result = $g_db->query($sql);
    $write_tbody = false;
    $write_all_orgas = false;

    while($cat_row = $g_db->fetch_array($cat_result))
    {
        if($cat_row['cat_name'] == "Stammdaten" && $_GET['type'] == "USF")
        {
                // da bei USF die Kategorie Stammdaten nicht verschoben werden darf, muss hier ein bischen herumgewurschtelt werden
            $drag_icon = "&nbsp;";
            echo "<tbody id=\"cat_stammdaten\">";
        }
        elseif($cat_row['cat_org_id'] == 0 && $_GET['type'] == "USF")
        {
                // Kategorien über alle Organisationen kommen immer zuerst
            if($write_all_orgas == false)
            {
                $write_all_orgas = true;
                echo "</tbody>
                <tbody id=\"cat_all_orgas\">";
            }
            $drag_icon = "<img class=\"dragable\" src=\"". THEME_PATH. "/icons/arrow_out.png\" alt=\"Reihenfolge ändern\" title=\"Reihenfolge ändern\" />";             
        }
        else
        {
            if($write_tbody == false)
            {
                $write_tbody = true;
                if($_GET['type'] == "USF")
                {
                    echo "</tbody>";
                }
                echo "<tbody id=\"cat_list\">";
            }
            $drag_icon = "<img class=\"dragable\" src=\"". THEME_PATH. "/icons/arrow_out.png\" alt=\"Reihenfolge ändern\" title=\"Reihenfolge ändern\" />";
        }
        echo "
        <tr id=\"row_". $cat_row['cat_id']. "\" class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
            <td style=\"width: 5%;\">$drag_icon</td>
            <td><a href=\"$g_root_path/adm_program/administration/roles/categories_new.php?cat_id=". $cat_row['cat_id']. "&amp;type=$req_type\">". $cat_row['cat_name']. "</a></td>
            <td>";
                if($cat_row['cat_hidden'] == 1)
                {
                    echo "<img class=\"iconInformation\" src=\"". THEME_PATH. "/icons/user_key.png\" alt=\"Kategorie nur für eingeloggte Benutzer sichtbar\" title=\"Kategorie nur für eingeloggte Benutzer sichtbar\" />";
                }
                else
                {
                    echo "&nbsp;";
                }
            echo "</td>
            <td style=\"text-align: right; width: 45px;\">
                <span class=\"iconLink\">
                    <a href=\"$g_root_path/adm_program/administration/roles/categories_new.php?cat_id=". $cat_row['cat_id']. "&amp;type=$req_type\"><img 
                    src=\"". THEME_PATH. "/icons/edit.png\" alt=\"Bearbeiten\" title=\"Bearbeiten\" /></a>
                </span>";

                if($cat_row['cat_system'] == 1)
                {
                    echo "
                    <span class=\"iconLink\">
                        <img src=\"". THEME_PATH. "/icons/dummy.png\" alt=\"dummy\" />
                    </span>";
                }
                else
                {
                    echo "
                    <span class=\"iconLink\">
                        <a href=\"$g_root_path/adm_program/administration/roles/categories_function.php?cat_id=". $cat_row['cat_id']. "&amp;mode=3&amp;type=$req_type\"><img
                        src=\"". THEME_PATH. "/icons/cross.png\" alt=\"Löschen\" title=\"Löschen\" /></a>
                    </span>";
                }
            echo "</td>
        </tr>";
    }
    echo "</tbody>
</table>

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
        Sortable.create('cat_all_orgas',{tag:'tr',onUpdate:updateDB,ghosting:true,dropOnEmpty:true,containment:['cat_all_orgas'],hoverclass:'drag'});
    Sortable.create('cat_list',{tag:'tr',onUpdate:updateDB,ghosting:true,dropOnEmpty:true,containment:['cat_list'],hoverclass:'drag'});
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>