<?php
/******************************************************************************
 * Spalten einer CSV-Datei werden Datenbankfeldern zugeordnet
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/login_valid.php');

// nur berechtigte User duerfen User importieren
if(!$g_current_user->editUsers())
{
    $g_message->show('norights');
}

if(count($_SESSION['file_lines']) == 0)
{
    $g_message->show('file_not_exist');
}

// feststellen, welches Trennzeichen in der Datei verwendet wurde
$count_comma     = 0;
$count_semicolon = 0;
$count_tabulator = 0;

$line = reset($_SESSION['file_lines']);
for($i = 0; $i < count($_SESSION['file_lines']); $i++)
{
    $count = substr_count($line, ",");
    $count_comma += $count;
    $count = substr_count($line, ";");
    $count_semicolon += $count;
    $count = substr_count($line, "\t");
    $count_tabulator += $count;

    $line = next($_SESSION['file_lines']);
}

if($count_semicolon > $count_comma && $count_semicolon > $count_tabulator)
{
    $_SESSION['value_separator'] = ";";
}
elseif($count_tabulator > $count_semicolon && $count_tabulator > $count_comma)
{
    $_SESSION['value_separator'] = "\t";
}
else
{
    $_SESSION['value_separator'] = ",";
}

// Html-Kopf ausgeben
$g_layout['title']  = 'Benutzer importieren';
$g_layout['header'] = '
	<script type="text/javascript"><!--
    	$(document).ready(function() 
		{
            $("#first_row").focus();
	 	}); 
	//--></script>';
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo '
<form action="'. $g_root_path. '/adm_program/administration/members/import_csv.php" method="post">
<div class="formLayout" id="import_csv_form">
    <div class="formHead">Felder zuordnen</div>
    <div class="formBody">
        <p>Ordne den Datenbankfeldern, wenn möglich eine Spalte aus der Datei zu.</p>
        <p>Auf der linken Seite stehen alle möglichen Datenbankfelder und auf der
        rechten Seite sind jeweils alle Spalten aus der ausgew&auml;hlten Datei
        aufgelistet. Falls nicht alle Datenbankfelder in der Datei vorhanden sind, k&ouml;nnen
        diese Felder einfach leer gelassen werden.</p>

        <p style="margin-bottom: 10px;">
            <input type="checkbox" id="first_row" name="first_row" style="vertical-align: middle;" checked="checked" value="1" />&nbsp;
            <label for="first_row">Erste Zeile beinhaltet die Spaltenbezeichnungen</label>
        </p>

        <table class="tableList" style="width: 80%;" cellspacing="0">
            <thead>
                <tr>
                    <th>Datenbankfeld</th>
                    <th>Dateispalte</th>
                </tr>
            </thead>';

            $line = reset($_SESSION['file_lines']);
            $arr_columns = explode($_SESSION['value_separator'], $line);
            $category = '';

            // jedes Benutzerfeld aus der Datenbank auflisten
            
            foreach($g_current_user->userFieldData as $field)
            {
                if($category != $field->getValue('cat_id'))
                {
                    if(strlen($category) > 0)
                    {
                        echo '</tbody>';
                    }
                    $block_id = 'cat_'. $field->getValue('cat_id');
                    echo '<tbody>
                        <tr>
                            <td class="tableSubHeader" colspan="4">
                                <a class="iconShowHide" href="javascript:showHideBlock(\''. $block_id. '\')"><img 
                                id="img_'. $block_id. '" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="ausblenden" /></a>'. $field->getValue('cat_name'). '
                            </td>
                        </tr>
                    </tbody>
                    <tbody id="'.$block_id.'">';

                    $category = $field->getValue('cat_id');
                }             
                echo '<tr>
                    <td><label for="usf-'. $field->getValue('usf_id'). '">'. $field->getValue('usf_name'). ':</label></td>
                    <td>
                        <select size="1" id="usf-'. $field->getValue('usf_id'). '" name="usf-'. $field->getValue('usf_id'). '" style="width: 95%;">
                            <option value="" selected="selected"></option>';

                            // Alle Spalten aus der Datei in Combobox auflisten
                            foreach($arr_columns as $col_key => $col_value)
                            {
                                $col_value = trim(strip_tags(str_replace('"', '', $col_value)));
                                echo '<option value="'.$col_key.'">'.$col_value.'</option>';
                            }
                        echo '</select>';
                        // Nachname und Vorname als Pflichtfelder kennzeichnen
                        if($field->getValue('usf_mandatory') == 1)
                        {
                            echo '&nbsp;<span title="Pflichtfeld" style="color: #990000;">*</span>';
                        }
                    echo '</td>
                </tr>';
            }
        echo '</tbody>
        </table>

        <div class="formSubmit">
            <button name="back" type="button" onclick="history.back()"><img src="'. THEME_PATH. '/icons/back.png" alt="Zurück" />&nbsp;Zurück</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id="import" type="submit"><img src="'. THEME_PATH. '/icons/database_in.png" alt="Weiter" />&nbsp;Importieren</button>
        </div>
    </div>
</div>
</form>';
    
require(THEME_SERVER_PATH. '/overall_footer.php');

?>