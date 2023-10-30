<?php
/**
 ***********************************************************************************************
 * Import assistant for user data
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

// check if file_uploads is set to ON in the current server settings...
if (!PhpIniUtils::isFileUploadEnabled()) {
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
    // => EXIT
}

$headline = $gL10n->get('SYS_IMPORT_MEMBERS');

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

if (isset($_SESSION['import_request'])) {
    // due to incorrect input the user has returned to this form
    // now write the previously entered contents into the object
    $formValues = SecurityUtils::encodeHTML(StringUtils::strStripTags($_SESSION['import_request']));
    unset($_SESSION['import_request']);
}

// Make sure all potential form values have either a value from the previous request or the default
if (!isset($formValues['format'])) {
    $formValues['format'] = '';
}
if (!isset($formValues['import_sheet'])) {
    $formValues['import_sheet'] = '';
}
if (!isset($formValues['import_coding'])) {
    $formValues['import_coding'] = '';
}
if (!isset($formValues['import_separator'])) {
    $formValues['import_separator'] = '';
}
if (!isset($formValues['import_enclosure'])) {
    $formValues['import_enclosure'] = 'AUTO';
}
if (!isset($formValues['user_import_mode'])) {
    $formValues['user_import_mode'] = 1;
}
if (!isset($formValues['import_role_id'])) {
    $formValues['import_role_id'] = 0;
}

// create html page object
$page = new HtmlPage('admidio-members-import', $headline);

// show form
$form = new HtmlForm('import_users_form', ADMIDIO_URL.FOLDER_MODULES.'/members/import_read_file.php', $page, array('enableFileUpload' => true));
$formats = array(
    'AUTO' => $gL10n->get('SYS_AUTO_DETECT'),
    'XLSX' => $gL10n->get('SYS_EXCEL_2007_365'),
    'XLS'  => $gL10n->get('SYS_EXCEL_97_2003'),
    'ODS'  => $gL10n->get('SYS_ODF_SPREADSHEET'),
    'CSV'  => $gL10n->get('SYS_COMMA_SEPARATED_FILE'),
    'HTML' => $gL10n->get('SYS_HTML_TABLE')
);
$form->addSelectBox(
    'format',
    $gL10n->get('SYS_FORMAT'),
    $formats,
    array('showContextDependentFirstEntry' => false, 'property' => HtmlForm::FIELD_REQUIRED, 'defaultValue' => $formValues['format'])
);
$page->addJavascript(
    '
    $("#format").change(function() {
        var format = $(this).children("option:selected").val();
         $(".import-setting").prop("disabled", true).parents("div.form-group").hide();
         $(".import-"+format).prop("disabled", false).parents("div.form-group").show("slow");
    });
    $("#format").trigger("change");',
    true
);

$form->addFileUpload(
    'userfile',
    $gL10n->get('SYS_CHOOSE_FILE'),
    array('property' => HtmlForm::FIELD_REQUIRED, 'allowedMimeTypes' => array('text/comma-separated-values',
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
$form->addInput('import_sheet', $gL10n->get('SYS_WORKSHEET_NAMEINDEX'), '', array('class' => 'import-setting import-XLSX import-XLS import-ODS import-HTML import-AUTO'));

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
    array('showContextDependentFirstEntry' => false, 'defaultValue' => $formValues['import_coding'], 'class' => 'import-setting import-CSV import-HTML')
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
    array('showContextDependentFirstEntry' => false, 'defaultValue' => $formValues['import_separator'], 'class' => 'import-setting import-CSV')
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
    array('showContextDependentFirstEntry' => false, 'defaultValue' => $formValues['import_enclosure'], 'class' => 'import-setting import-CSV')
);


// add a selectbox to the form where the user can choose a role from all roles he could see
// first read all relevant roles from database and create an array with them
$condition = '';

if (!$gCurrentUser->manageRoles()) {
    // keine Rollen mit Rollenzuordnungsrecht anzeigen
    $condition .= ' AND rol_assign_roles = false ';
}
if (!$gCurrentUser->isAdministrator()) {
    // Don't show administrator role
    $condition .= ' AND rol_administrator = false ';
}

$sql = 'SELECT rol_id, rol_name, cat_name
          FROM '.TBL_ROLES.'
    INNER JOIN '.TBL_CATEGORIES.'
            ON cat_id = rol_cat_id
         WHERE rol_valid   = true
           AND cat_name_intern <> \'EVENTS\'
           AND (  cat_org_id  = ? -- $gCurrentOrgId
               OR cat_org_id IS NULL )
               '.$condition.'
      ORDER BY cat_sequence, rol_name';
$statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));
$roles = array();

while ($row = $statement->fetch()) {
    $roles[] = array($row['rol_id'], $row['rol_name'], $row['cat_name']);
}
$form->addSelectBox(
    'import_role_id',
    $gL10n->get('SYS_ASSIGN_ROLE'),
    $roles,
    array(
        'property'        => HtmlForm::FIELD_REQUIRED,
        'defaultValue'    => $formValues['import_role_id'],
        'helpTextIdLabel' => 'SYS_ASSIGN_ROLE_FOR_IMPORT'
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
    $gL10n->get('SYS_EXISTING_MEMBERS'),
    $selectBoxEntries,
    array(
        'property'                       => HtmlForm::FIELD_REQUIRED,
        'defaultValue'                   => $formValues['user_import_mode'],
        'showContextDependentFirstEntry' => false,
        'helpTextIdLabel'                => 'SYS_IDENTIFY_USERS'
    )
);
$form->addSubmitButton(
    'btn_forward',
    $gL10n->get('SYS_ASSIGN_FIELDS'),
    array('icon' => 'fa-arrow-circle-right', 'class' => ' offset-sm-3')
);

// add form to html page and show page
$page->addHtml($form->show());
$page->show();
