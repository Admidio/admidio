<?php
/******************************************************************************
 * Anzeigen von Listen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * start    - Angabe, ab welchem Datensatz Links angezeigt werden sollen
 * category - Kategorie der Rollen, die angezeigt werden sollen
 *            Wird keine Kategorie uebergeben, werden alle Rollen angezeigt
 * category-selection: yes - (Default) Anzeige der Combobox mit den verschiedenen
 *                           Rollen-Kategorien
 *                     no  - Combobox nicht anzeigen
 * active_role : 1 - (Default) aktive Rollen auflisten
 *               0 - inaktive Rollen auflisten
 *
 *****************************************************************************/

require("../../system/common.php");

unset($_SESSION['mylist_request']);

// Uebergabevariablen pruefen und ggf. vorbelegen

if(array_key_exists("start", $_GET))
{
    if (is_numeric($_GET["start"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["start"] = 0;
}

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

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// SQL-Statement zusammensetzen

$sql = "SELECT * FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
         WHERE rol_valid  = $active_role
           AND rol_cat_id = cat_id 
           AND cat_org_id = ". $g_current_organization->getValue("org_id");
if(!$g_current_user->assignRoles())
{
    // wenn nicht Moderator, dann keine versteckten Rollen anzeigen
    $sql .= " AND rol_locked = 0 ";
}
if($g_valid_login == false)
{
    $sql .= " AND cat_hidden = 0 ";
}
if(strlen($category) > 0 && $category != "Alle")
{
    // wenn eine Kategorie uebergeben wurde, dann nur Rollen dieser anzeigen
    $sql .= " AND cat_type   = 'ROL'
              AND cat_name   = '$category' ";
}
$sql .= " ORDER BY cat_sequence, rol_name ";

$result_lst = $g_db->query($sql);
$num_roles  = $g_db->num_rows($result_lst);

if($num_roles == 0)
{
    if($g_valid_login == true)
    {
        // wenn User eingeloggt, dann Meldung, dass keine Rollen in der Kategorie existieren
        if($active_role == 0)
        {
            $g_message->show("no_old_roles");
        }
        else
        {
            $g_message->addVariableContent("$g_root_path/adm_program/administration/roles/roles.php", 1, false);
            $g_message->show("no_old_roles");
        }
    }
    else
    {
        // wenn User ausgeloggt, dann Login-Bildschirm anzeigen
        require("../../system/login_valid.php");
    }
}

// Html-Kopf ausgeben
$g_layout['title'] = "Listen";
$g_layout['header'] = "
    <script type=\"text/javascript\"><!--
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
                self.location.href = '$g_root_path/adm_program/modules/lists/lists_show.php?type=address&mode=html&rol_id=' + rol_id;
            }
            else if(sel_list == 'telefon')
            {
                self.location.href = '$g_root_path/adm_program/modules/lists/lists_show.php?type=telephone&mode=html&rol_id=' + rol_id;
            }
            else if(sel_list == 'mylist')
            {
                self.location.href = '$g_root_path/adm_program/modules/lists/mylist.php?rol_id=' + rol_id";
            if($active_role)
                $g_layout['header'] = $g_layout['header']. ";";
            else
                $g_layout['header'] = $g_layout['header']. " + '&active_role=0&active_member=0';";
            $g_layout['header'] = $g_layout['header']. "
            }
            else if(sel_list == 'former')
            {
                self.location.href = '$g_root_path/adm_program/modules/lists/lists_show.php?type=former&mode=html&rol_id=' + rol_id;
            }
        }
    //--></script>";

require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<h1 class=\"moduleHeadline\">";
    if($active_role)
    {
        echo "Aktive Rollen";
    }
    else
    {
        echo "Inaktive Rollen";
    }
echo '</h1>';

if($show_ctg_sel == 1)
{
    // Combobox mit allen Kategorien anzeigen
    $sql = "SELECT * FROM ". TBL_CATEGORIES. "
             WHERE cat_org_id = ". $g_current_organization->getValue("org_id"). "
               AND cat_type   = 'ROL' ";
    if($g_valid_login == false)
    {
        $sql .= " AND cat_hidden = 0 ";
    }
    $sql .= " ORDER BY cat_sequence ASC ";
    $result = $g_db->query($sql);

    if($g_db->num_rows($result) > 0)
    {
        echo '<p>Kategorie w&auml;hlen:&nbsp;&nbsp;
        <select size="1" id="category" onchange="showCategory()">
            <option value="Alle" ';
            if(strlen($category) == 0)
            {
                echo " selected=\"selected\" ";
            }
            echo '>Alle</option>';

            while($row = $g_db->fetch_object($result))
            {
                echo '<option value="'. urlencode($row->cat_name). '"';
                if($category == $row->cat_name)
                {
                    echo " selected=\"selected\" ";
                }
                echo ">$row->cat_name</option>";
            }
        echo '</select></p>';
    }
}


$previous_cat_id   = 0;
$count_cat_entries = 0;
// jetzt erst einmal zu dem ersten relevanten Datensatz springen
if(!$g_db->data_seek($result_lst, $_GET["start"]))
{
    $g_message->show("invalid");
}

// Anzahl Rollen pro Seite
if($g_preferences['lists_roles_per_page'] > 0)
{
    $roles_per_page = $g_preferences['lists_roles_per_page'];
}
else
{
    $roles_per_page = $num_roles;
}

for($i = 0; $i < $roles_per_page && $i + $_GET["start"] < $num_roles; $i++)
{
    if($row_lst = $g_db->fetch_array($result_lst))
    {
        // Anzahl Mitglieder ermitteln die keine Leiter sind
        $sql = "SELECT COUNT(*) as count
                  FROM ". TBL_MEMBERS. "
                 WHERE mem_rol_id = ". $row_lst['rol_id']. "
                   AND mem_valid  = $active_role
                   AND mem_leader = 0";
        $result = $g_db->query($sql);
        $row    = $g_db->fetch_array($result);
        $num_member = $row['count'];

         // Anzahl Mitglieder ermitteln die Leiter sind
        $sql = "SELECT COUNT(*) as count
                  FROM ". TBL_MEMBERS. "
                 WHERE mem_rol_id = ". $row_lst['rol_id']. "
                   AND mem_valid  = $active_role
                   AND mem_leader = 1";
        $result = $g_db->query($sql);
        $row    = $g_db->fetch_array($result);
        $num_leader = $row['count'];

        if($active_role)
        {
            // Anzahl ehemaliger Mitglieder ermitteln
            $sql = "SELECT COUNT(*) as count
                      FROM ". TBL_MEMBERS. "
                     WHERE mem_rol_id = ". $row_lst['rol_id']. "
                       AND mem_valid  = 0 ";
            $result = $g_db->query($sql);
            $row    = $g_db->fetch_array($result);
            $num_former = $row['count'];
        }

        if($previous_cat_id != $row_lst['cat_id'])
        {
            if($i > 0)
            {
                echo "</div></div><br />";
            }
            echo "<div class=\"formLayout\" id=\"lists_overview\">
                <div class=\"formHead\">". $row_lst['cat_name']. "</div>
                <div class=\"formBody\">";
            $previous_cat_id = $row_lst['cat_id'];
            $count_cat_entries = 0;
        }

        if($count_cat_entries > 0)
        {
            echo"<hr />";
        }

        echo "
        <div>
            <div style=\"float: left;\">";
                // Link nur anzeigen, wenn Rolle auch Mitglieder hat
                if($num_member > 0 || $num_leader > 0)
                {
                    echo "<a href=\"$g_root_path/adm_program/modules/lists/lists_show.php?type=";
                    if($active_role)
                    {
                        echo "address";
                    }
                    else
                    {
                        echo "former";
                    }
                    echo "&amp;mode=html&amp;rol_id=". $row_lst['rol_id']. "\">". $row_lst['rol_name']. "</a>";
                }
                else
                {
                    echo "<strong>". $row_lst['rol_name']. "</strong>";
                }

                if($g_current_user->assignRoles() 
                || isGroupLeader($g_current_user->getValue("usr_id"), $row_lst['rol_id']) 
                || $g_current_user->editUser())
                {
                    if($row_lst['rol_name'] != "Webmaster"
                    || ($row_lst['rol_name'] == "Webmaster" && $g_current_user->isWebmaster()))
                    {
                        if($g_current_user->assignRoles())
                        {
                            // nur Moderatoren duerfen Rollen editieren
                            echo "
                            <span class=\"iconLink\">
                                <a href=\"$g_root_path/adm_program/administration/roles/roles_new.php?rol_id=". $row_lst['rol_id']. "\"><img
                                src=\"". THEME_PATH. "/icons/edit.png\" alt=\"Einstellungen\" title=\"Einstellungen\" /></a>
                            </span>";
                        }

                        // Gruppenleiter und Moderatoren duerfen Mitglieder zuordnen oder entfernen (nicht bei Ehemaligen Rollen)
                        if($row_lst['rol_valid'] == 1)
                        {
                            echo "
                            <span class=\"iconLink\">
                                <a href=\"$g_root_path/adm_program/modules/lists/members.php?rol_id=". $row_lst['rol_id']. "\"><img 
                                src=\"". THEME_PATH. "/icons/add.png\" alt=\"Mitglieder zuordnen\" title=\"Mitglieder zuordnen\" /></a>
                            </span>";
                        }
                    }
                }
            echo "</div>
            <div style=\"text-align: right;\">";
                // Kombobox mit Listen nur anzeigen, wenn die Rolle Mitglieder hat
                if($num_member > 0 || $num_leader > 0)
                {
                    echo "
                    <select size=\"1\" name=\"list$i\" onchange=\"showList(this, ". $row_lst['rol_id']. ")\">
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
        </div>
        
        <ul class=\"formFieldList\">";
            if(strlen($row_lst['rol_description']) > 0)
            {
                echo "
                <li>
                    <dl>
                        <dt>Beschreibung:</dt>
                        <dd>". $row_lst['rol_description']. "</dd>
                    </dl>
                </li>";
            }

            if(strlen($row_lst['rol_start_date']) > 0)
            {
                echo "
                <li>
                    <dl>
                        <dt>Zeitraum:</dt>
                        <dd>". mysqldate("d.m.y", $row_lst['rol_start_date']). " bis ". mysqldate("d.m.y", $row_lst['rol_end_date']). "</dd>
                    </dl>
                </li>";
            }
            if($row_lst['rol_weekday'] > 0
            || strlen($row_lst['rol_start_time']) > 0 )
            {
                echo "
                <li>
                    <dl>
                        <dt>Gruppenstunde:</dt>
                        <dd>"; 
                            if($row_lst['rol_weekday'] > 0)
                            {
                                echo $arrDay[$row_lst['rol_weekday']-1];
                            }
                            if(strlen($row_lst['rol_start_time']) > 0)
                            {
                                echo " von ". mysqltime("h:i", $row_lst['rol_start_time']). " bis ". mysqltime("h:i", $row_lst['rol_end_time']);
                            }
                        echo "</dd>
                    </dl>
                </li>";
            }
            //Treffpunkt
            if(strlen($row_lst['rol_location']) > 0)
            {
                echo "
                <li>
                    <dl>
                        <dt>Treffpunkt:</dt>
                        <dd>". $row_lst['rol_location']. "</dd>
                    </dl>
                </li>";
            }
            //Teinehmer
            echo "
            <li>
                <dl>
                    <dt>Teilnehmer:</dt>
                    <dd>$num_member";
                        if($row_lst['rol_max_members'] > 0)
                        {
                            echo " von max. ". $row_lst['rol_max_members'];
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
                    echo "</dd>
                </dl>
            </li>";

            //Leiter
            if($num_leader>0)
            {
                echo "
                <li>
                    <dl>
                        <dt>Leiter:</dt>
                        <dd>$num_leader</dd>
                    </dl>
                </li>";
            }

            //Beitrag
            if(strlen($row_lst['rol_cost']) > 0)
            {
                echo "
                <li>
                    <dl>
                        <dt>Beitrag:</dt>
                        <dd>". $row_lst['rol_cost']. " &euro;</dd>
                    </dl>
                </li>";
            }
        echo "</ul>";
        $count_cat_entries++;
    }
}
echo "</div></div>";

// Navigation mit Vor- und Zurueck-Buttons
$base_url = "$g_root_path/adm_program/modules/lists/lists.php?category=$category&category-selection=". $_GET['category-selection']. "&active_role=$active_role";
echo generatePagination($base_url, $num_roles, $roles_per_page, $_GET["start"], TRUE);

require(THEME_SERVER_PATH. "/overall_footer.php");

?>