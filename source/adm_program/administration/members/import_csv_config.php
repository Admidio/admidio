<?php
/******************************************************************************
 * Spalten einer CSV-Datei werden Datenbankfeldern zugeordnet
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
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
<div class=\"formLayout\" id=\"import_csv_form\">
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

        <table class=\"tableList\" style=\"width: 80%;\" cellspacing=\"0\">
            <thead>
                <tr>
                    <th>Datenbankfeld</th>
                    <th>Dateispalte</th>
                </tr>
            </thead>";

            $line = reset($_SESSION["file_lines"]);
            $arr_columns = explode($_SESSION["value_separator"], $line);
            $category = "";

            // jedes Benutzerfeld aus der Datenbank auflisten
            
            foreach($g_current_user->db_user_fields as $key => $value)
            {
                if($category != $value['cat_id'])
                {
                    if(strlen($category) > 0)
                    {
                        echo "</tbody>";
                    }
                    $block_id = "cat_". $value['cat_id'];
                    echo "<tbody>
                        <tr>
                            <td class=\"tableSubHeader\" colspan=\"4\">
                                <a class=\"iconShowHide\" href=\"javascript:showHideBlock('$block_id', '$g_root_path')\"><img 
                                name=\"img_$block_id\" src=\"$g_root_path/adm_program/images/triangle_open.gif\" alt=\"ausblenden\"></a>". $value['cat_name']. "
                            </td>
                        </tr>
                    </tbody>
                    <tbody id=\"$block_id\">";

                    $category = $value['cat_id'];
                }             
                echo "<tr>
                    <td><label for=\"usf-". $value['usf_id']. "\">". $value['usf_name']. ":</label></td>
                    <td>
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

        <div class=\"formSubmit\">
            <button name=\"back\" type=\"button\" onclick=\"history.back()\">
            <img src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id=\"import\" type=\"submit\">
            <img src=\"$g_root_path/adm_program/images/database_in.png\" alt=\"Weiter\">
            &nbsp;Importieren</button>
        </div>
    </div>
</div>
</form>

<script type=\"text/javascript\"><!--
    document.getElementById('first_row').focus();
--></script>";
    
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>