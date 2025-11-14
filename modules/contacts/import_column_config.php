<?php
/**
 ***********************************************************************************************
 * Assign columns of import file to profile fields
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // only authorized users can import users
    if (!$gCurrentUser->isAdministratorUsers()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if (count($_SESSION['import_data']) === 0) {
        throw new Exception('SYS_FILE_NOT_EXIST');
    }

    $headline = $gL10n->get('SYS_ASSIGN_FIELDS');

    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-members-import-csv', $headline);

    // show form
    $form = new FormPresenter(
        'adm_contacts_import_assign_fields_form',
        'modules/contacts.import.assign-fields.tpl',
        ADMIDIO_URL . FOLDER_MODULES . '/contacts/import_user.php?mode=import',
        $page
    );
    $form->addCheckbox('first_row', $gL10n->get('SYS_FIRST_LINE_COLUMN_NAME'), true);
    $page->addJavascript(
        '
        $(".admidio-import-field").change(function() {
            var available = [];
            $("#adm_contacts_import_assign_fields_form .admidio-import-field").first().children("option").each(function() {
                if ($(this).val() != "" && !$(this).prop("hidden")) {
                    available.push($(this).text());
                }
            });
            var used = [];
            $("#adm_contacts_import_assign_fields_form .admidio-import-field").children("option:selected").each(function() {
                if ($(this).val() != "" && !$(this).prop("hidden")) {
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

    $arrayCsvColumns = $_SESSION['import_data'][0];
    $categoryId = null;
    $arrayImportableFields = array();

    // Cleanup CSV columns: If a column does not have a header (null value), use its position instead
    foreach ($arrayCsvColumns as $pos => $column) {
        if (empty($column)) {
          $arrayCsvColumns[$pos] =  $gL10n->get('SYS_COLUMN_POS', array($pos));
        }
    }

    $arrayImportableFields[] = array(
        'cat_name' => $gL10n->get('SYS_BASIC_DATA'),
        'cat_tooltip' => '',
        'id' => 'usr_uuid',
        'name' => $gL10n->get('SYS_UNIQUE_ID'),
        'name_intern' => $gL10n->get('SYS_UNIQUE_ID'),
        'tooltip' => $gL10n->get('SYS_POSSIBLE_FIELDNAMES',
            array($gL10n->get('SYS_UNIQUE_ID')))
    );

    // create array with all fields that could be imported
    foreach ($gProfileFields->getProfileFields() as $field) {
        $arrayImportableFields[] = array(
            'cat_name' => $field->getValue('cat_name'),
            'cat_tooltip' => '',
            'id' => $field->getValue('usf_uuid'),
            'name' => $field->getValue('usf_name'),
            'name_intern' => $field->getValue('usf_name_intern'),
            'tooltip' => $gL10n->get('SYS_POSSIBLE_FIELDNAMES',
                array(
                    $field->getValue('usf_name') . ', ' .
                    $field->getValue('usf_name_intern'))
            )
        );
    }

    // administrator could also import login name and password
    if ($gCurrentUser->isAdministrator()) {
        $arrayImportableFields[] = array(
            'cat_name' => $gL10n->get('SYS_ASSIGN_LOGIN_INFORMATION'),
            'cat_tooltip' => $gL10n->get('SYS_IMPORT_LOGIN_DATA_DESC'),
            'id' => 'usr_login_name',
            'name' => $gL10n->get('SYS_USERNAME'),
            'name_intern' => $gL10n->get('SYS_USERNAME'),
            'tooltip' => $gL10n->get('SYS_POSSIBLE_FIELDNAMES',
                array($gL10n->get('SYS_USERNAME')))
        );
        $arrayImportableFields[] = array(
            'cat_name' => $gL10n->get('SYS_ASSIGN_LOGIN_INFORMATION'),
            'cat_tooltip' => $gL10n->get('SYS_IMPORT_LOGIN_DATA_DESC'),
            'id' => 'usr_password',
            'name' => $gL10n->get('SYS_PASSWORD'),
            'name_intern' => $gL10n->get('SYS_PASSWORD'),
            'tooltip' => $gL10n->get('SYS_POSSIBLE_FIELDNAMES',
                array($gL10n->get('SYS_PASSWORD')))
        );
    }

    foreach ($arrayImportableFields as $column) {
        // identify existing profile fields that matches a column of the importing file
        $fieldDefaultValue = '';
        if (in_array($column['name'], $arrayCsvColumns)) {
            $fieldDefaultValue = $column['name'];
        }

        // set required fields
        $fieldProperty = FormPresenter::FIELD_DEFAULT;
        if ($column['name_intern'] === 'LAST_NAME' || $column['name_intern'] === 'FIRST_NAME') {
            $fieldProperty = FormPresenter::FIELD_REQUIRED;
        }

        $form->addSelectBox(
            $column['id'],
            $column['name'],
            $arrayCsvColumns,
            array(
                'category' => $column['cat_name'],
                'property' => $fieldProperty,
                'defaultValue' => $fieldDefaultValue,
                'firstEntry' => $gL10n->get('SYS_ASSIGN_FILE_COLUMN'),
                'class' => 'admidio-import-field'
            )
        );
    }

    $form->addSubmitButton('btn_forward', $gL10n->get('SYS_IMPORT'), array('icon' => 'bi-upload'));

    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
