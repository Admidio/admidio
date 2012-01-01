<?php
/******************************************************************************
 * Assign columns of csv file to database fields
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/login_valid.php');

// nur berechtigte User duerfen User importieren
if(!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if(count($_SESSION['file_lines']) == 0)
{
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
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

if(isset($_SESSION['import_csv_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $form = $_SESSION['import_csv_request'];
    unset($_SESSION['import_csv_request']);
	if(isset($form['first_row']) == false)
	{
		$form['first_row'] = 0;
	}
}
else
{
	$form['first_row'] = 1;
	$form['import_coding']    = 'iso-8859-1';
	$form['import_role_id']   = 0;
}

// Html-Kopf ausgeben
$gLayout['title']  = $gL10n->get('MEM_ASSIGN_FIELDS');
$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#first_row").focus();
        }); 
    //--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<form action="'. $g_root_path. '/adm_program/administration/members/import_csv.php" method="post">
<div class="formLayout" id="import_csv_form">
    <div class="formHead">'.$gLayout['title'].'</div>
    <div class="formBody"><p>'.$gL10n->get('MEM_ASSIGN_FIELDS_DESC').'</p>
        <p style="margin-bottom: 10px;">
            <input type="checkbox" id="first_row" name="first_row" style="vertical-align: middle;" ';
			if($form['first_row'] == 1)
			{
				echo ' checked="checked" ';
			}
			echo 'value="1" />&nbsp;
            <label for="first_row">'.$gL10n->get('MEM_FIRST_LINE_COLUMN_NAME').'</label>
        </p>

        <table class="tableList" style="width: 80%;" cellspacing="0">
            <thead>
                <tr>
                    <th>'.$gL10n->get('MEM_PROFILE_FIELD').'</th>
                    <th>'.$gL10n->get('MEM_FILE_COLUMN').'</th>
                </tr>
            </thead>';

            $line = reset($_SESSION['file_lines']);
            $arrayCsvColumns = explode($_SESSION['value_separator'], $line);
            $category = '';

            // jedes Benutzerfeld aus der Datenbank auflisten
            
            foreach($gProfileFields->mProfileFields as $field)
            {
                if($category != $field->getValue('cat_id'))
                {
                    if(strlen($category) > 0)
                    {
                        echo '</tbody>';
                    }
                    $block_id = 'admCategory'. $field->getValue('cat_id');
                    echo '<tbody>
                        <tr>
                            <td class="tableSubHeader" colspan="4">
                                <a class="iconShowHide" href="javascript:showHideBlock(\''. $block_id. '\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img 
                                id="'. $block_id. 'Image" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'. $field->getValue('cat_name'). '
                            </td>
                        </tr>
                    </tbody>
                    <tbody id="'.$block_id.'">';

                    $category = $field->getValue('cat_id');
                }             
                echo '<tr>
                    <td><label for="usf-'. $field->getValue('usf_id'). '">'. $field->getValue('usf_name'). ':</label></td>
                    <td>
                        <select size="1" id="usf-'. $field->getValue('usf_id'). '" name="usf-'. $field->getValue('usf_id'). '" style="width: 90%;">';
							if(isset($form['usf-'.$field->getValue('usf_id')]) && $form['usf-'. $field->getValue('usf_id')] > 0)
							{
								echo '<option value=""></option>';
							}
							else
							{
								echo '<option value="" selected="selected"></option>';
							}

                            // Alle Spalten aus der Datei in Combobox auflisten
                            foreach($arrayCsvColumns as $col_key => $col_value)
                            {
                                $col_value = trim(strip_tags(str_replace('"', '', $col_value)));

								if(isset($form['usf-'. $field->getValue('usf_id')]) 
								&& strlen($form['usf-'. $field->getValue('usf_id')]) > 0
								&& $form['usf-'. $field->getValue('usf_id')] == $col_key)
								{
									echo '<option value="'.$col_key.'" selected="selected">'.$col_value.'</option>';
								}
								else
								{
									echo '<option value="'.$col_key.'">'.$col_value.'</option>';
								}
                            }
                        echo '</select>';

                        // Lastname und first name are mandatory fields
                        if($field->getValue('usf_name_intern') == 'LAST_NAME' || $field->getValue('usf_name_intern') == 'FIRST_NAME')
                        {
                            echo '&nbsp;<span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>';
                        }
                    echo '</td>
                </tr>';
            }
        echo '</tbody>
        </table>

        <div class="formSubmit">
            <button id="btnBack" type="button" onclick="history.back()"><img src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" />&nbsp;'.$gL10n->get('SYS_BACK').'</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button id="btnForward" type="submit"><img src="'. THEME_PATH. '/icons/database_in.png" alt="'.$gL10n->get('MEM_IMPORT').'" />&nbsp;'.$gL10n->get('MEM_IMPORT').'</button>
        </div>
    </div>
</div>
</form>';
    
require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>