<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all profile fields
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// only authorized users can edit the profile fields
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set module headline
$headline = $gL10n->get('ORG_PROFILE_FIELDS');

$gNavigation->addUrl(CURRENT_URL, $headline);

unset($_SESSION['fields_request']);

// create html page object
$page = new HtmlPage('admidio-profile-fields', $headline);

$page->addJavascript(
    '
    $(".admidio-open-close-caret").click(function() {
        showHideBlock($(this).attr("id"));
    });
    $("tbody.admidio-sortable").sortable({
        axis: "y",
        handle: ".handle",
        stop: function(event, ui) {
            var order = $(this).sortable("toArray", {attribute: "data-id"});
            var uid = ui.item.attr("data-id");
            $.get("'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile-fields/profile_fields_function.php', array('mode' => 4)).'",
            {usf_uuid: uid, order: order});
        }
    });
    ',
    true
);

// define link to create new profile field
$page->addPageFunctionsMenuItem(
    'menu_item_new_field',
    $gL10n->get('ORG_CREATE_PROFILE_FIELD'),
    ADMIDIO_URL.FOLDER_MODULES.'/profile-fields/profile_fields_new.php',
    'fa-plus-circle'
);

// define link to maintain categories
$page->addPageFunctionsMenuItem(
    'menu_item_maintain_category',
    $gL10n->get('SYS_EDIT_CATEGORIES'),
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php', array('type' => 'USF')),
    'fa-th-large'
);

$sql = 'SELECT *
          FROM '.TBL_USER_FIELDS.'
    INNER JOIN '.TBL_CATEGORIES.'
            ON cat_id = usf_cat_id
         WHERE cat_type = \'USF\'
           AND (  cat_org_id = ?
               OR cat_org_id IS NULL )
      ORDER BY cat_sequence ASC, usf_sequence ASC';
$statement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

// Create table
$table = new HtmlTable('tbl_profile_fields', $page, true);
$table->setMessageIfNoRowsFound('ORG_NO_FIELD_CREATED');

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_FIELD').HtmlForm::getHelpTextIcon('ORG_FIELD_DESCRIPTION'),
    '&nbsp;',
    $gL10n->get('ORG_DATATYPE'),
    '<i class="fas fa-eye" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'"></i>',
    '<i class="fas fa-key" data-toggle="tooltip" data-html="true" title="'.$gL10n->get('ORG_FIELD_DISABLED', array($gL10n->get('SYS_RIGHT_EDIT_USER'))).'"></i>',
    '<i class="fas fa-address-card" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_REGISTRATION').'"></i>',
    $gL10n->get('SYS_REQUIRED_INPUT'),
    $gL10n->get('SYS_DEFAULT_VALUE'),
    $gL10n->get('SYS_REGULAR_EXPRESSION'),
    '&nbsp;'
);
$table->addRowHeadingByArray($columnHeading);

$categoryId = 0;
$userField  = new TableUserField($gDb);

// Initialize variables
$hidden      = '';
$disable     = '';
$usfSystem   = '';

while ($row = $statement->fetch()) {
    $userField->clear();
    $userField->setArray($row);

    $usfUuid = $userField->getValue('usf_uuid');

    if ($categoryId !== (int) $userField->getValue('cat_id')) {
        $categoryId = (int) $userField->getValue('usf_cat_id');
        $blockId = 'admCategory'.$categoryId;

        $table->addTableBody();
        $table->addRow('', array('class' => 'admidio-group-heading', 'id' => 'admidio-group-row-'.$categoryId));
        $table->addColumn(
            '<a id="caret_'.$blockId.'" class="admidio-icon-link admidio-open-close-caret"><i class="fas fa-caret-down"></i></a>'.$userField->getValue('cat_name'),
            array('id' => 'group_'.$blockId, 'colspan' => '10')
        );
        $table->addTableBody('id', $blockId);
        $table->addAttribute('class', 'admidio-sortable');
    }

    if ($userField->getValue('usf_hidden') == 1) {
        $hidden = '<i class="fas fa-eye admidio-opacity-reduced" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_HIDDEN').'"></i>';
    } else {
        $hidden = '<i class="fas fa-eye" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'"></i>';
    }

    if ($userField->getValue('usf_disabled') == 1) {
        $disable = '<i class="fas fa-key" data-toggle="tooltip" data-html="true" title="'.$gL10n->get('ORG_FIELD_DISABLED', array($gL10n->get('SYS_RIGHT_EDIT_USER'))).'"></i>';
    } else {
        $disable = '<i class="fas fa-key admidio-opacity-reduced" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_NOT_DISABLED').'"></i>';
    }

    if ($userField->getValue('usf_registration') == 1) {
        $registration = '<i class="fas fa-address-card" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_REGISTRATION').'"></i>';
    } else {
        $registration = '<i class="fas fa-address-card admidio-opacity-reduced" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_NOT_REGISTRATION').'"></i>';
    }

    $userFieldText = array('CHECKBOX'     => $gL10n->get('SYS_CHECKBOX'),
                           'DATE'         => $gL10n->get('SYS_DATE'),
                           'DROPDOWN'     => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                           'EMAIL'        => $gL10n->get('SYS_EMAIL'),
                           'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
                           'PHONE'        => $gL10n->get('SYS_PHONE'),
                           'TEXT'         => $gL10n->get('SYS_TEXT').' (100)',
                           'TEXT_BIG'     => $gL10n->get('SYS_TEXT').' (4000)',
                           'URL'          => $gL10n->get('SYS_URL'),
                           'NUMBER'       => $gL10n->get('SYS_NUMBER'),
                           'DECIMAL'      => $gL10n->get('SYS_DECIMAL_NUMBER'));
    $mandatoryFieldValues = array(0 => 'SYS_NO',
                                  1 => 'SYS_YES',
                                  2 => 'SYS_ONLY_AT_REGISTRATION_AND_OWN_PROFILE',
                                  3 => 'SYS_NOT_AT_REGISTRATION');

    $usfSystem = '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile-fields/profile_fields_new.php', array('usf_uuid' => $usfUuid)).'">'.
                    '<i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';

    if ($userField->getValue('usf_system') == 1) {
        $usfSystem .= '<i class="fas fa-trash invisible"></i>';
    } else {
        $usfSystem .='<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                        data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'usf', 'element_id' => 'row_usf_'.$usfUuid,
                        'name' => $userField->getValue('usf_name'), 'database_id' => $usfUuid)).'">'.
                        '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile-fields/profile_fields_new.php', array('usf_uuid' => $usfUuid)).'">'.$userField->getValue('usf_name').'</a>' . HtmlForm::getHelpTextIcon((string) $userField->getValue('usf_description'), 'SYS_DESCRIPTION'),
        '<a class="admidio-icon-link" href="javascript:void(0)" onclick="moveTableRow(\''.TableUserField::MOVE_UP.'\', \'row_usf_'.$usfUuid.'\',
            \''.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile-fields/profile_fields_function.php', array('mode' => 4, 'usf_uuid' => $usfUuid, 'sequence' => TableUserField::MOVE_UP)) . '\',
            \''.$gCurrentSession->getCsrfToken().'\')">'.
            '<i class="fas fa-chevron-circle-up" data-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_UP', array('SYS_PROFILE_FIELD')) . '"></i></a>
        <a class="admidio-icon-link" href="javascript:void(0)" onclick="moveTableRow(\''.TableUserField::MOVE_DOWN.'\', \'row_usf_'.$usfUuid.'\',
            \''.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile-fields/profile_fields_function.php', array('mode' => 4, 'usf_uuid' => $usfUuid, 'sequence' => TableUserField::MOVE_DOWN)) . '\',
            \''.$gCurrentSession->getCsrfToken().'\')">'.
            '<i class="fas fa-chevron-circle-down" data-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_DOWN', array('SYS_PROFILE_FIELD')) . '"></i></a>
        <a class="admidio-icon-link">'.
            '<i class="fas fa-arrows-alt handle" data-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_VAR', array('SYS_PROFILE_FIELD')) . '"></i></a>
            ',
        $userFieldText[$userField->getValue('usf_type')],
        $hidden,
        $disable,
        $registration,
        $gL10n->get($mandatoryFieldValues[$userField->getValue('usf_required_input')]),
        $userField->getValue('usf_default_value'),
        $userField->getValue('usf_regex'),
        $usfSystem
    );
    $table->addRowByArray($columnValues, 'row_usf_'.$usfUuid, array('data-id' => $usfUuid));
}

$page->addHtml($table->show());
$page->show();
