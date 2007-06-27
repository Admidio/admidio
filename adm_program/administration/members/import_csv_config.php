<?php
/******************************************************************************
 * Spalten einer CSV-Datei werden Datenbankfeldern zugeordnet
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
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

// nur berechtigte User duerfen User importieren
if(!$g_current_user->editUser())
{
    $g_message->show("norights");
}

if(count($_SESSION['file_lines']) == 0)
{
    $g_message->show("file_not_exist");
}

// feststellen, welches Trennzeichen in der Datei verwendet wurde
$count_comma     = 0;
$count_semicolon = 0;
$count_tabulator = 0;

$line = reset($_SESSION["file_lines"]);
for($i = 0; $i < count($_SESSION["file_lines"]); $i++)
{
    $count = substr_count($line, ",");
    $count_comma += $count;
    $count = substr_count($line, ";");
    $count_semicolon += $count;
    $count = substr_count($line, "\t");
    $count_tabulator += $count;

    $line = next($_SESSION["file_lines"]);
}

if($count_semicolon > $count_comma && $count_semicolon > $count_tabulator)
{
    $_SESSION["value_separator"] = ";";
}
elseif($count_tabulator > $count_semicolon && $count_tabulator > $count_comma)
{
    $_SESSION["value_separator"] = "\t";
}
else
{
    $_SESSION["value_separator"] = ",";
}

// Html-Kopf ausgeben
$g_layout['title'] = "Benutzer importieren";
$g_layout['header'] = "<script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/show_hide_block.js\"></script>";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<form action=\"$g_root_path/adm_program/administration/members/import_csv.php\" method=\"post\">
    <div class=\"formHead\">Felder zuordnen</div>
    <div class=\"formBody\">
        <div style=\"text-align: center; width: 100%;\">
            <p>Ordne den Datenbankfeldern, wenn m&ouml;glich eine Spalte aus der Datei zu.</p>
            <p>Auf der linken Seite stehen alle m&ouml;glichen Datenbankfelder und auf der
            rechten Seite sind jeweils alle Spalten aus der ausgew&auml;hlten Datei
            aufgelistet. Falls nicht alle Datenbankfelder in der Datei vorhanden sind, k&ouml;nnen
            diese Felder einfach leer gelassen werden.</p>
        </div>

        <div style=\"margin-top: 6px; margin-bottom: 10px;\">
            <input type=\"checkbox\" id=\"first_row\" name=\"first_row\" style=\"vertical-align: middle;\" checked value=\"1\" />&nbsp;
            <label for=\"first_row\">Erste Zeile beinhaltet die Spaltenbezeichnungen</label>
        </div>

        <table class=\"tableList\" style=\"width: 75%;\" cellpadding=\"2\" cellspacing=\"0\">
            <thead>
                <tr>
                    <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Datenbankfeld</th>
                    <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Dateispalte</th>
                </tr>
            </thead>";

            $line = reset($_SESSION["file_lines"]);
            $arr_columns = explode($_SESSION["value_separator"], $line);
            $category_merker = "";

            // jedes Benutzerfeld aus der Datenbank auflisten
            
            foreach($g_current_user->db_user_fields as $key => $value)
            {
                if($category_merker != $value['cat_name'])
                {
                    if(strlen($category_merker) > 0)
                    {
                        echo "</tbody>";
                    }
                    echo "<tbody>
                        <tr>
                            <td class=\"tableSubHeader\" colspan=\"4\">
                                <a href=\"javascript:showHideBlock('". $value['cat_name']. "', '$g_root_path')\"><img name=\"img_". $value['cat_name']. "\" 
                                    style=\"padding: 2px 5px 1px 3px;\" src=\"$g_root_path/adm_program/images/triangle_open.gif\" 
                                    style=\"vertical-align: middle;\" border=\"0\" alt=\"ausblenden\"></a>". $value['cat_name']. "
                            </td>
                        </tr>
                    </tbody>
                    <tbody id=\"cat_". $value['cat_name']. "\">";

                    $category_merker = $value['cat_name'];
                }             
                echo "<tr>
                    <td style=\"text-align: left;\">&nbsp;". $value['usf_name']. ":</td>
                    <td style=\"text-align: left;\">&nbsp;
                        <select size=\"1\" id=\"usf-". $value['usf_id']. "\" name=\"usf-". $value['usf_id']. "\">
                            <option value=\"\" selected=\"selected\"></option>";

                            // Alle Spalten aus der Datei in Combobox auflisten
                            foreach($arr_columns as $col_key => $col_value)
                            {
                                $col_value = trim(strip_tags(str_replace("\"", "", $col_value)));
                                echo "<option value=\"$col_key\">$col_value</option>";
                            }
                        echo "</select>";
                        // Nachname und Vorname als Pflichtfelder kennzeichnen
                        if($value['usf_mandatory'] == 1)
                        {
                            echo "&nbsp;<span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>";
                        }
                    echo "</td>
                </tr>";
            }
        echo "</tbody>
        </table>

        <div style=\"margin-top: 6px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id=\"weiter\" type=\"submit\" value=\"weiter\" tabindex=\"2\">Weiter&nbsp;
            <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Weiter\"></button>
        </div>
    </div>
</form>

<script type=\"text/javascript\"><!--
    document.getElementById('first_row').focus();
--></script>";
    
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>