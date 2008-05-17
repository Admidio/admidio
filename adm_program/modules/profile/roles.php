<?php
/******************************************************************************
 * Funktionen zuordnen 
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * user_id     - Funktionen der uebergebenen user_id aendern
 * new_user: 0 - (Default) Daten eines vorhandenen Users werden bearbeitet
 *           1 - Der User ist gerade angelegt worden -> Rollen muessen zugeordnet werden
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

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

$user     = new User($g_db, $req_usr_id);
$_SESSION['navigation']->addUrl(CURRENT_URL);

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
$g_layout['title']  = "Rollenzuordnung für \"". $user->getValue("Vorname"). " ". $user->getValue("Nachname"). "\"";
$g_layout['header'] = "
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/show_hide_block.js\"></script>
    <script type=\"text/javascript\"><!--
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
    --></script>";

require(THEME_SERVER_PATH. "/overall_header.php");

echo "
<h1 class=\"moduleHeadline\">". $g_layout['title']. "</h1>

<form action=\"$g_root_path/adm_program/modules/profile/roles_save.php?user_id=$req_usr_id&amp;new_user=$req_new_user\" method=\"post\">
    <table class=\"tableList\" cellspacing=\"0\">
        <thead>
            <tr>
                <th>&nbsp;</th>
                <th>Rolle</th>
                <th>Beschreibung</th>
                <th style=\"text-align: center; width: 80px;\">Leiter<img 
                    class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"\" 
                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=leader&amp;window=true','Message','width=600,height=500,left=310,top=200,scrollbars=yes')\" 
                    onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=leader',this);\" onmouseout=\"ajax_hideTooltip()\"/>
                </th>
            </tr>
        </thead>";

        if($g_current_user->assignRoles() || $g_current_user->editUsers())
        {
            // Benutzer mit Rollenrechten darf ALLE Rollen zuordnen
            // Benutzer ohne Rollenvergaberechte, duerfen nur Rollen zuordnen, die sie sehen duerfen
            // aber auch keine Rollen mit Rollenvergaberechten 
            $sql_roles_condition = "";
            if($g_current_user->editUsers() && !$g_current_user->viewAllLists())
            {
                $sql_roles_condition = " AND rol_this_list_view > 0 ";
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
                          AND rol_cat_id     = cat_id
                          AND cat_org_id     = ". $g_current_organization->getValue("org_id"). "
                        ORDER BY cat_sequence, rol_name";
        }
        $result = $g_db->query($sql);
        $category = "";

        while($row = $g_db->fetch_object($result))
        {
            if($g_current_user->assignRoles() || $g_current_user->viewRole($row->rol_id))
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
                                <a class=\"iconShowHide\" href=\"javascript:showHideBlock('$block_id','". THEME_PATH. "')\"><img
                                id=\"img_$block_id\" src=\"". THEME_PATH. "/icons/triangle_open.gif\" alt=\"ausblenden\" /></a>$row->cat_name
                            </td>
                        </tr>
                    </tbody>
                    <tbody id=\"$block_id\">";

                    $category = $row->cat_name;
                }
                echo "
                <tr class=\"tableMouseOver\">
                   <td style=\"text-align: center;\">
                      <input type=\"checkbox\" id=\"role-$row->rol_id\" name=\"role-$row->rol_id\" ";
                         if($row->mem_usr_id > 0)
                         {
                            echo " checked=\"checked\" ";
                         }

                         // wenn der User aus der Mitgliederzuordnung heraus neu angelegt wurde
                         // entsprechende Rolle sofort hinzufuegen
                         if($row->rol_id == $set_rol_id)
                         {
                            echo " checked=\"checked\" ";
                         }

                         // die Funktion Webmaster darf nur von einem Webmaster vergeben werden
                         if($row->rol_name == 'Webmaster' && !$g_current_user->isWebmaster())
                         {
                            echo " disabled=\"disabled\" ";
                         }

                         echo " onclick=\"unmarkLeader(this)\" value=\"1\" />
                   </td>
                   <td><label for=\"role-$row->rol_id\">$row->rol_name</label></td>
                   <td>$row->rol_description</td>
                   <td style=\"text-align: center;\">
                            <input type=\"checkbox\" id=\"leader-$row->rol_id\" name=\"leader-$row->rol_id\" ";
                            if($row->mem_leader > 0)
                            {
                                echo " checked=\"checked\" ";
                            }

                            // die Funktion Webmaster darf nur von einem Webmaster vergeben werden
                            if($row->rol_name == 'Webmaster' && !$g_current_user->isWebmaster())
                            {
                                echo " disabled=\"disabled\" ";
                            }

                            echo " onclick=\"markMember(this)\" value=\"1\" />
                   </td>
                </tr>";
            }
        }
    echo "</table>

    <div class=\"formSubmit\">
        <button name=\"speichern\" type=\"submit\" value=\"speichern\"><img src=\"". THEME_PATH. "/icons/disk.png\" alt=\"Speichern\" />&nbsp;Speichern</button>
    </div>

    <ul class=\"iconTextLinkList\">
        <li>
            <span class=\"iconTextLink\">
                <a href=\"$g_root_path/adm_program/system/back.php\"><img 
                src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
                <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
            </span>
        </li>
    </ul>
</form>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>