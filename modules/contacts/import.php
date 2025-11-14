<?php
/**
 ***********************************************************************************************
 * Import assistant for user data
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // only authorized users can import users
    if (!$gCurrentUser->isAdministratorUsers()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // check if file_uploads is set to ON in the current server settings...
    if (!PhpIniUtils::isFileUploadEnabled()) {
        throw new Exception('SYS_SERVER_NO_UPLOAD');
    }

    $headline = $gL10n->get('SYS_IMPORT_CONTACTS');

    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-members-import', $headline);

    // show form
    $form = new FormPresenter(
        'adm_contacts_import_form',
        'modules/contacts.import.tpl',
        ADMIDIO_URL . FOLDER_MODULES . '/contacts/import_read_file.php',
        $page,
        array('enableFileUpload' => true)
    );
    $formats = array(
        'AUTO' => $gL10n->get('SYS_AUTO_DETECT'),
        'XLSX' => $gL10n->get('SYS_EXCEL_2007_365'),
        'XLS' => $gL10n->get('SYS_EXCEL_97_2003'),
        'ODS' => $gL10n->get('SYS_ODF_SPREADSHEET'),
        'CSV' => $gL10n->get('SYS_COMMA_SEPARATED_FILE'),
        'HTML' => $gL10n->get('SYS_HTML_TABLE')
    );
    $form->addSelectBox(
        'format',
        $gL10n->get('SYS_FORMAT'),
        $formats,
        array(
            'showContextDependentFirstEntry' => false,
            'defaultValue' => 'AUTO',
            'property' => FormPresenter::FIELD_REQUIRED
        )
    );
    $page->addJavascript(
        '
    $("#format").change(function() {
        const format = $(this).children("option:selected").val();
         $(".import-setting").prop("disabled", true).parents("div.admidio-form-group").hide();
         $(".import-"+format).prop("disabled", false).parents("div.admidio-form-group").show("slow");
    });
    $("#format").trigger("change");',
        true
    );

    $form->addFileUpload(
        'userfile',
        $gL10n->get('SYS_CHOOSE_FILE'),
        array('property' => FormPresenter::FIELD_REQUIRED, 'allowedMimeTypes' => array('text/comma-separated-values',
            'text/html',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/vnd.oasis.opendocument.spreadsheet'
        )
        )
    );

    // Add format-specific settings (if specific format is selected)
    // o) Worksheet: AUTO, XLSX, XLS, ODS, HTML (not CSV)
    // o) Encoding (Default/Detect/UTF-8/ISO-8859-1/CP1252): CSV, HTML
    // o) Delimiter (Detect/Comma/Tab/Semicolon): CSV
    $form->addInput(
        'import_sheet',
        $gL10n->get('SYS_WORKSHEET_NAMEINDEX'),
        '',
        array('class' => 'import-setting import-XLSX import-XLS import-ODS import-HTML import-AUTO'));

    $selectBoxEntries = array(
        '' => $gL10n->get('SYS_DEFAULT_ENCODING_UTF8'),
        'GUESS' => $gL10n->get('SYS_ENCODING_GUESS'),
        'UTF-8' => $gL10n->get('SYS_UTF8'),
        'UTF-16BE' => $gL10n->get('SYS_UTF16BE'),
        'UTF-16LE' => $gL10n->get('SYS_UTF16LE'),
        'UTF-32BE' => $gL10n->get('SYS_UTF32BE'),
        'UTF-32LE' => $gL10n->get('SYS_UTF32LE'),
        'CP1252' => $gL10n->get('SYS_CP1252'),
        'ISO-8859-1' => $gL10n->get('SYS_ISO_8859_1')
    );
    $form->addSelectBox(
        'import_coding',
        $gL10n->get('SYS_CODING'),
        $selectBoxEntries,
        array(
            'showContextDependentFirstEntry' => false,
            'class' => 'import-setting import-CSV import-HTML'
        )
    );

    $selectBoxEntries = array(
        '' => $gL10n->get('SYS_AUTO_DETECT'),
        ',' => $gL10n->get('SYS_COMMA'),
        ';' => $gL10n->get('SYS_SEMICOLON'),
        '\t' => $gL10n->get('SYS_TAB'),
        '|' => $gL10n->get('SYS_PIPE')
    );
    $form->addSelectBox(
        'import_separator',
        $gL10n->get('SYS_SEPARATOR_FOR_CSV_FILE'),
        $selectBoxEntries,
        array(
            'showContextDependentFirstEntry' => false,
            'class' => 'import-setting import-CSV'
        )
    );

    $selectBoxEntries = array(
        'AUTO' => $gL10n->get('SYS_AUTO_DETECT'),
        '' => $gL10n->get('SYS_NO_QUOTATION'),
        '"' => $gL10n->get('SYS_DQUOTE'),
        '\'' => $gL10n->get('SYS_QUOTE')
    );
    $form->addSelectBox(
        'import_enclosure',
        $gL10n->get('SYS_FIELD_ENCLOSURE'),
        $selectBoxEntries,
        array(
            'showContextDependentFirstEntry' => false,
            'defaultValue' => 'AUTO',
            'class' => 'import-setting import-CSV'
        )
    );


    // add a selectbox to the form where the user can choose a role from all roles he could see
    // first read all relevant roles from database and create an array with them
    $condition = '';

    if (!$gCurrentUser->isAdministratorRoles()) {
        // keine Rollen mit Rollenzuordnungsrecht anzeigen
        $condition .= ' AND rol_assign_roles = false ';
    }
    if (!$gCurrentUser->isAdministrator()) {
        // Don't show administrator role
        $condition .= ' AND rol_administrator = false ';
    }

    $sql = 'SELECT rol_uuid, rol_name, cat_name
          FROM ' . TBL_ROLES . '
    INNER JOIN ' . TBL_CATEGORIES . '
            ON cat_id = rol_cat_id
         WHERE rol_valid   = true
           AND cat_name_intern <> \'EVENTS\'
           AND (  cat_org_id  = ? -- $gCurrentOrgId
               OR cat_org_id IS NULL )
               ' . $condition . '
      ORDER BY cat_sequence, rol_name';
    $statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));
    $roles = array();

    while ($row = $statement->fetch()) {
        $roles[] = array($row['rol_uuid'], $row['rol_name'], $row['cat_name']);
    }
    $form->addSelectBox(
        'import_role_uuid',
        $gL10n->get('SYS_ASSIGN_ROLE'),
        $roles,
        array(
            'property' => FormPresenter::FIELD_REQUIRED,
            'defaultValue' => 0,
            'helpTextId' => 'SYS_ASSIGN_ROLE_FOR_IMPORT'
        )
    );

    $selectBoxEntries = array(
        1 => $gL10n->get('SYS_DO_NOT_EDIT'),
        2 => $gL10n->get('SYS_DUPLICATE'),
        3 => $gL10n->get('SYS_REPLACE'),
        4 => $gL10n->get('SYS_COMPLEMENT')
    );
    $form->addSelectBox(
        'user_import_mode',
        $gL10n->get('SYS_EXISTING_CONTACTS'),
        $selectBoxEntries,
        array(
            'property' => FormPresenter::FIELD_REQUIRED,
            'defaultValue' => 1,
            'showContextDependentFirstEntry' => false,
            'helpTextId' => 'SYS_IDENTIFY_USERS'
        )
    );
    $form->addSubmitButton(
        'btn_forward',
        $gL10n->get('SYS_ASSIGN_FIELDS'),
        array('icon' => 'bi-arrow-right-circle-fill')
    );

    $form->addToHtmlPage();
    $gCurrentSession->addFormObject($form);
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
