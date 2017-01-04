<?php
/**
 ***********************************************************************************************
 * Assign columns of csv file to database fields
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require('../../system/common.php');
require('../../system/login_valid.php');

// nur berechtigte User duerfen User importieren
if(!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if(count($_SESSION['file_lines']) === 0)
{
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
    // => EXIT
}

$headline = $gL10n->get('MEM_ASSIGN_FIELDS');

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// feststellen, welches Trennzeichen in der Datei verwendet wurde
$count_comma     = 0;
$count_semicolon = 0;
$count_tabulator = 0;

$line = reset($_SESSION['file_lines']);
for($i = 0, $iMax = count($_SESSION['file_lines']); $i < $iMax; ++$i)
{
    $count = substr_count($line, ',');
    $count_comma += $count;
    $count = substr_count($line, ';');
    $count_semicolon += $count;
    $count = substr_count($line, "\t");
    $count_tabulator += $count;

    $line = next($_SESSION['file_lines']);
}

if($count_semicolon > $count_comma && $count_semicolon > $count_tabulator)
{
    $_SESSION['value_separator'] = ';';
}
elseif($count_tabulator > $count_semicolon && $count_tabulator > $count_comma)
{
    $_SESSION['value_separator'] = "\t";
}
else
{
    $_SESSION['value_separator'] = ',';
}

if(isset($_SESSION['import_csv_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $form_values = $_SESSION['import_csv_request'];
    unset($_SESSION['import_csv_request']);
    if(!isset($form['first_row']))
    {
        $form_values['first_row'] = false;
    }
}
else
{
    $form_values['first_row'] = true;
    $form_values['import_coding']  = 'iso-8859-1';
    $form_values['import_role_id'] = 0;
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$importCsvConfigMenu = $page->getMenu();
$importCsvConfigMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

$page->addHtml('<p class="lead">'.$gL10n->get('MEM_ASSIGN_FIELDS_DESC').'</p>');

// show form
$form = new HtmlForm('import_assign_fields_form', ADMIDIO_URL. FOLDER_MODULES.'/members/import_csv.php', $page, array('type' => 'vertical'));
$form->addCheckbox('first_row', $gL10n->get('MEM_FIRST_LINE_COLUMN_NAME'), $form_values['first_row']);
$htmlFieldTable = '
    <table class="table table-condensed">
        <thead>
            <tr>
                <th>'.$gL10n->get('MEM_PROFILE_FIELD').'</th>
                <th>'.$gL10n->get('MEM_FILE_COLUMN').'</th>
            </tr>
        </thead>';

        $line = reset($_SESSION['file_lines']);
        $arrayCsvColumns = explode($_SESSION['value_separator'], $line);
        $categoryId = null;

        // jedes Benutzerfeld aus der Datenbank auflisten

        foreach($gProfileFields->mProfileFields as $field)
        {
            $catId = (int) $field->getValue('cat_id');
            if($categoryId !== $catId)
            {
                if($categoryId !== null)
                {
                    $htmlFieldTable .= '</tbody>';
                }
                $htmlFieldTable .= '<tbody>
                    <tr class="admidio-group-heading">
                        <td colspan="4">'.$field->getValue('cat_name').'</td>
                    </tr>
                </tbody>
                <tbody id="admCategory'.$catId.'">';

                $categoryId = $catId;
            }
            $htmlFieldTable .= '<tr>
                <td><label for="usf-'. $field->getValue('usf_id'). '">'.$field->getValue('usf_name');
                    // Lastname und first name are mandatory fields
                    if($field->getValue('usf_name_intern') === 'LAST_NAME' || $field->getValue('usf_name_intern') === 'FIRST_NAME')
                    {
                        $htmlFieldTable .= '&nbsp;&nbsp;<span class="text-danger">('.$gL10n->get('SYS_MANDATORY_FIELD').')</span>';
                    }
                    $htmlFieldTable .= '</label></td>
                <td>
                    <select class="form-control" size="1" id="usf-'. $field->getValue('usf_id'). '" name="usf-'. $field->getValue('usf_id'). '" style="width: 90%;">';
                        if(isset($form_values['usf-'.$field->getValue('usf_id')]) && $form_values['usf-'. $field->getValue('usf_id')] > 0)
                        {
                            $htmlFieldTable .= '<option value=""></option>';
                        }
                        else
                        {
                            $htmlFieldTable .= '<option value="" selected="selected"></option>';
                        }

                        // Alle Spalten aus der Datei in Combobox auflisten
                        foreach($arrayCsvColumns as $col_key => $col_value)
                        {
                            $col_value = trim(strip_tags(str_replace('"', '', $col_value)));

                            if(isset($form_values['usf-'. $field->getValue('usf_id')])
                            && strlen($form_values['usf-'. $field->getValue('usf_id')]) > 0
                            && $form_values['usf-'. $field->getValue('usf_id')] == $col_key)
                            {
                                $htmlFieldTable .= '<option value="'.$col_key.'" selected="selected">'.$col_value.'</option>';
                            }
                            else
                            {
                                $htmlFieldTable .= '<option value="'.$col_key.'">'.$col_value.'</option>';
                            }
                        }
                    $htmlFieldTable .= '</select>
                </td>
            </tr>';
        }
    $htmlFieldTable .= '</tbody>
    </table>';
$form->addDescription($htmlFieldTable);
$form->addSubmitButton('btn_forward', $gL10n->get('MEM_IMPORT'), array('icon' => THEME_URL.'/icons/database_in.png'));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
