<?php
/**
 ***********************************************************************************************
 * Assign columns of import file to profile fields
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// only authorized users can import users
if (!$gCurrentUser->editUsers()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if (count($_SESSION['import_data']) === 0) {
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
    // => EXIT
}

$headline = $gL10n->get('SYS_ASSIGN_FIELDS');

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

if (isset($_SESSION['import_csv_request'])) {
    // due to incorrect input the user has returned to this form
    // now write the previously entered contents into the object
    $formValues = SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['import_csv_request']));
    unset($_SESSION['import_csv_request']);
    if (!isset($form['first_row'])) {
        $formValues['first_row'] = false;
    }
} else {
    $formValues['first_row'] = true;
}
/**
 * Function creates the html for each assignment of a profile field to a column of the import file.
 * @param array $arrayColumnList The array contains the following elements cat_name, cat_tooltip, id, name, name_intern, tooltip
 * @param array $arrayCsvColumns An array with the names of the columns from the import file.
 * @return string Returns the HTML of a table with all profile fields and possible assigned columns of the import file.
 */
function getColumnAssignmentHtml(array $arrayColumnList, array $arrayCsvColumns): string
{
    global $gL10n;

    $categoryName = null;
    $html = '';

    foreach ($arrayColumnList as $column) {
        if ($categoryName !== $column['cat_name']) {
            if ($categoryName !== null) {
                $html .= '</tbody>';
            }

            $html .= '<tbody>
                    <tr class="admidio-group-heading">
                        <td colspan="4">' . $column['cat_name'] . HtmlForm::getHelpTextIcon((string) $column['cat_tooltip']) . '</td>
                    </tr>
                </tbody>
                <tbody>';

            $categoryName = $column['cat_name'];
        }

        $html .= '<tr>
                <td><label for="'. $column['id']. '" title="'.$column['tooltip'].'">'.$column['name'];
        // Lastname und first name are mandatory fields
        if ($column['name_intern'] === 'LAST_NAME' || $column['name_intern'] === 'FIRST_NAME') {
            $html .= '&nbsp;&nbsp;<span class="text-danger">('.$gL10n->get('SYS_REQUIRED_INPUT').')</span>';
        }
        $html .= '</label></td>
            <td>';

        $selectEntries = '';
        // list all columns of the file
        $found = false;
        foreach ($arrayCsvColumns as $colKey => $colValue) {
            $colValue = trim(strip_tags(str_replace('"', '', $colValue)));

            $selected = '';
            // If the user is returned to the form (e.g. a required
            // field was not selected), the $formValues['usf-#']
            // array is populated, so use the assignments from the previous
            // config page, so the config is preserved:
            if (isset($formValues[$column['id']])) {
                if (strlen($formValues[$column['id']]) > 0 && $formValues[$column['id']] == $colKey) {
                    $selected .= ' selected="selected"';
                    $found = true;
                }
            }
            // Otherwise, detect the entry where the column header
            // matches the Admidio field name or internal field name (case-insensitive)
            elseif (strtolower($colValue) == strtolower($column['name'])
                || strtolower($colValue) == strtolower($column['name_intern'])) {
                $selected .= ' selected="selected"';
                $found = true;
            }
            $selectEntries .= '<option value="'.$colKey.'"'.$selected.'>'.$colValue.'</option>';
        }
        // add html for select box
        // Insert default (empty) entry and select if no other item is selected
        $html .= '
        <select class="form-control admidio-import-field" size="1" id="'. $column['id']. '" name="'. $column['id']. '">
            <option value=""'.($found ? ' selected="selected"' : '').'></option>
            ' . $selectEntries . '
        </select>

        </td></tr>';
    }
    return $html;
}

// create html page object
$page = new HtmlPage('admidio-members-import-csv', $headline);

$page->addHtml('<p class="lead">'.$gL10n->get('SYS_ASSIGN_FIELDS_DESC').'</p>');

// show form
$form = new HtmlForm('import_assign_fields_form', ADMIDIO_URL. FOLDER_MODULES.'/members/import_user.php', $page, array('type' => 'vertical'));
$form->addCheckbox('first_row', $gL10n->get('SYS_FIRST_LINE_COLUMN_NAME'), $formValues['first_row']);
$form->addHtml('<div class="alert alert-warning alert-small" id="admidio-import-unused"><i class="fas fa-exclamation-triangle"></i>'.$gL10n->get('SYS_IMPORT_UNUSED_HEAD').'<div id="admidio-import-unused-fields">-</div></div>');
$page->addJavascript(
    '
    $(".admidio-import-field").change(function() {
        var available = [];
        $("#import_assign_fields_form .admidio-import-field").first().children("option").each(function() {
            if ($(this).text() != "") {
                available.push($(this).text());
            }
        });
        var used = [];
        $("#import_assign_fields_form .admidio-import-field").children("option:selected").each(function() {
            if ($(this).text() != "") {
                used.push($(this).text());
            }
        });
        var outstr = $(available).not(used).get().join(", ");
        if (outstr == "") {
            outstr = "-";
        }
        $("#admidio-import-unused #admidio-import-unused-fields").html(outstr);
    });
    $(".admidio-import-field").trigger("change");',
    true
);

$htmlFieldTable = '
    <table class="table table-condensed import-config import-config-csv">
        <thead>
            <tr>
                <th>'.$gL10n->get('SYS_PROFILE_FIELD').'</th>
                <th>'.$gL10n->get('SYS_FILE_COLUMN').'</th>
            </tr>
        </thead>';

        $arrayCsvColumns = $_SESSION['import_data'][0];
        $categoryId = null;
        $arrayImportableFields = array();

        $arrayImportableFields[] = array(
            'cat_name'    => $gL10n->get('SYS_BASIC_DATA'),
            'cat_tooltip' => '',
            'id'          => 'usr_uuid',
            'name'        => $gL10n->get('SYS_UNIQUE_ID'),
            'name_intern' => $gL10n->get('SYS_UNIQUE_ID'),
            'tooltip'     => $gL10n->get('SYS_POSSIBLE_FIELDNAMES',
                array($gL10n->get('SYS_UNIQUE_ID')))
        );

        // create array with all fields that could be imported
        foreach ($gProfileFields->getProfileFields() as $field) {
            $arrayImportableFields[] = array(
                'cat_name'    => $field->getValue('cat_name'),
                'cat_tooltip' => '',
                'id'          => $field->getValue('usf_uuid'),
                'name'        => $field->getValue('usf_name'),
                'name_intern' => $field->getValue('usf_name_intern'),
                'tooltip'     => $gL10n->get('SYS_POSSIBLE_FIELDNAMES',
                    array(
                        $field->getValue('usf_name') . ', ' .
                        $field->getValue('usf_name_intern'))
                )
            );
        }

        // administrator could also import login name and password
        if ($gCurrentUser->isAdministrator()) {
            $arrayImportableFields[] = array(
                'cat_name'    => $gL10n->get('SYS_ASSIGN_LOGIN_INFORMATION'),
                'cat_tooltip' => $gL10n->get('SYS_IMPORT_LOGIN_DATA_DESC'),
                'id'          => 'usr_login_name',
                'name'        => $gL10n->get('SYS_USERNAME'),
                'name_intern' => $gL10n->get('SYS_USERNAME'),
                'tooltip'     => $gL10n->get('SYS_POSSIBLE_FIELDNAMES',
                    array($gL10n->get('SYS_USERNAME')))
            );
            $arrayImportableFields[] = array(
                'cat_name'    => $gL10n->get('SYS_ASSIGN_LOGIN_INFORMATION'),
                'cat_tooltip' => $gL10n->get('SYS_IMPORT_LOGIN_DATA_DESC'),
                'id'          => 'usr_password',
                'name'        => $gL10n->get('SYS_PASSWORD'),
                'name_intern' => $gL10n->get('SYS_PASSWORD'),
                'tooltip'     => $gL10n->get('SYS_POSSIBLE_FIELDNAMES',
                    array($gL10n->get('SYS_PASSWORD')))
            );
        }

        $htmlFieldTable .= getColumnAssignmentHtml($arrayImportableFields, $arrayCsvColumns) .'
        </tbody>
    </table>';
$form->addHtml($htmlFieldTable);
$form->addSubmitButton('btn_forward', $gL10n->get('SYS_IMPORT'), array('icon' => 'fa-upload'));

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
