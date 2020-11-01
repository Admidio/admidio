<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all profile fields
 *
 * @copyright 2004-2020 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set module headline
$headline = $gL10n->get('ORG_PROFILE_FIELDS');

$gNavigation->addUrl(CURRENT_URL, $headline);

unset($_SESSION['fields_request']);

// create html page object
$page = new HtmlPage('admidio-profile-fields', $headline);

$page->addJavascript('
    $(".admidio-group-heading").click(function() {
        showHideBlock($(this).attr("id"));
    });',
    true
);
$page->addJavascript('
    /**
     * @param {string} direction
     * @param {int}    usfID
     */
    function moveCategory(direction, usfID) {
        var actRow = document.getElementById("row_usf_" + usfID);
        var childs = actRow.parentNode.childNodes;
        var prevNode    = null;
        var nextNode    = null;
        var actRowCount = 0;
        var actSequence = 0;
        var secondSequence = 0;

        // erst einmal aktuelle Sequenz und vorherigen/naechsten Knoten ermitteln
        for (var i = 0; i < childs.length; i++) {
            if (childs[i].tagName === "TR") {
                actRowCount++;
                if (actSequence > 0 && nextNode === null) {
                    nextNode = childs[i];
                }

                if (childs[i].id === "row_usf_" + usfID) {
                    actSequence = actRowCount;
                }

                if (actSequence === 0) {
                    prevNode = childs[i];
                }
            }
        }

        // entsprechende Werte zum Hoch- bzw. Runterverschieben ermitteln
        if (direction === "UP") {
            if (prevNode !== null) {
                actRow.parentNode.insertBefore(actRow, prevNode);
                secondSequence = actSequence - 1;
            }
        } else {
            if (nextNode !== null) {
                actRow.parentNode.insertBefore(nextNode, actRow);
                secondSequence = actSequence + 1;
            }
        }

        if (secondSequence > 0) {
            // Nun erst mal die neue Position von dem gewaehlten Feld aktualisieren
            $.get("' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile-fields/profile_fields_function.php', array('mode' => 4)) . '&usf_id=" + usfID + "&sequence=" + direction);
        }
    }
');

// define link to create new profile field
$page->addPageFunctionsMenuItem('menu_item_new_field', $gL10n->get('ORG_CREATE_PROFILE_FIELD'),
    ADMIDIO_URL.FOLDER_MODULES.'/profile-fields/profile_fields_new.php', 'fa-plus-circle');

// define link to maintain categories
$page->addPageFunctionsMenuItem('menu_item_maintain_category', $gL10n->get('SYS_MAINTAIN_CATEGORIES'),
    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/categories/categories.php', array('type' => 'USF')),
    'fa-th-large');

$sql = 'SELECT *
          FROM '.TBL_USER_FIELDS.'
    INNER JOIN '.TBL_CATEGORIES.'
            ON cat_id = usf_cat_id
         WHERE cat_type = \'USF\'
           AND (  cat_org_id = ?
               OR cat_org_id IS NULL )
      ORDER BY cat_sequence ASC, usf_sequence ASC';
$statement = $gDb->queryPrepared($sql, array((int) $gCurrentOrganization->getValue('org_id')));

// Create table
$table = new HtmlTable('tbl_profile_fields', $page, true);
$table->setMessageIfNoRowsFound('ORG_NO_FIELD_CREATED');

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_FIELD').HtmlForm::getHelpTextIcon('ORG_FIELD_DESCRIPTION'),
    '&nbsp;',
    $gL10n->get('SYS_DESCRIPTION'),
    '<i class="fas fa-eye" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'"></i>',
    '<i class="fas fa-key" data-toggle="tooltip" data-html="true" title="'.$gL10n->get('ORG_FIELD_DISABLED', array($gL10n->get('SYS_RIGHT_EDIT_USER'))).'"></i>',
    '<i class="fas fa-asterisk" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_REQUIRED').'"></i>',
    '<i class="fas fa-address-card" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_REGISTRATION').'"></i>',
    $gL10n->get('ORG_DATATYPE'),
    '&nbsp;'
);
$table->addRowHeadingByArray($columnHeading);

$categoryId = 0;
$userField  = new TableUserField($gDb);

// Intialize variables
$description = '';
$hidden      = '';
$disable     = '';
$mandatory   = '';
$usfSystem   = '';

while($row = $statement->fetch())
{
    $userField->clear();
    $userField->setArray($row);

    if($categoryId !== (int) $userField->getValue('cat_id'))
    {
        $blockId = 'admCategory'.(int) $userField->getValue('usf_cat_id');

        $table->addTableBody();
        $table->addRow('', array('class' => 'admidio-group-heading'));
        $table->addColumn('<span id="caret_'.$blockId.'" class="caret"></span>'.$userField->getValue('cat_name'),
                          array('id' => 'group_'.$blockId, 'colspan' => '8'));
        $table->addTableBody('id', $blockId);

        $categoryId = (int) $userField->getValue('usf_cat_id');
    }

    // cut long text strings and provide tooltip
    if(strlen($userField->getValue('usf_description')) > 22)
    {
        $description = substr($userField->getValue('usf_description', 'database'), 0, 22).'
            <a class="openPopup" href="javascript:void(0);"
                data-href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. '/adm_program/system/msg_window.php', array('message_id' => 'user_field_description', 'message_var1' => $userField->getValue('usf_name_intern'), 'inline' => 'true')).'"><span
                data-html="true" data-toggle="tooltip" data-original-title="'.str_replace('"', '\'', $userField->getValue('usf_description')).'">[..]</span></a>';
    }
    elseif($userField->getValue('usf_description') === '')
    {
        $description = '&nbsp;';
    }
    else
    {
        $description = $userField->getValue('usf_description');
    }

    if($userField->getValue('usf_hidden') == 1)
    {
        $hidden = '<i class="fas fa-eye admidio-opacity-reduced" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_HIDDEN').'"></i>';
    }
    else
    {
        $hidden = '<i class="fas fa-eye" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'"></i>';
    }

    if($userField->getValue('usf_disabled') == 1)
    {
        $disable = '<i class="fas fa-key" data-toggle="tooltip" data-html="true" title="'.$gL10n->get('ORG_FIELD_DISABLED', array($gL10n->get('SYS_RIGHT_EDIT_USER'))).'"></i>';
    }
    else
    {
        $disable = '<i class="fas fa-key admidio-opacity-reduced" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_NOT_DISABLED').'"></i>';
    }

    if($userField->getValue('usf_mandatory') == 1)
    {
        $mandatory = '<i class="fas fa-asterisk" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_REQUIRED').'"></i>';
    }
    else
    {
        $mandatory = '<i class="fas fa-asterisk admidio-opacity-reduced" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_NOT_MANDATORY').'"></i>';
    }

    if($userField->getValue('usf_registration') == 1)
    {
        $registration = '<i class="fas fa-address-card" data-toggle="tooltip" title="'.$gL10n->get('ORG_FIELD_REGISTRATION').'"></i>';
    }
    else
    {
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
                           'URL'          => $gL10n->get('ORG_URL'),
                           'NUMBER'       => $gL10n->get('SYS_NUMBER'),
                           'DECIMAL'      => $gL10n->get('SYS_DECIMAL_NUMBER'));

    $usfId = (int) $userField->getValue('usf_id');

    $usfSystem = '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile-fields/profile_fields_new.php', array('usf_id' => $usfId)).'">'.
                    '<i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';

    if($userField->getValue('usf_system') == 1)
    {
        $usfSystem .= '<i class="fas fa-trash invisible"></i>';
    }
    else
    {
        $usfSystem .='<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                        data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'usf', 'element_id' => 'row_usf_'.$usfId,
                        'name' => $userField->getValue('usf_name'), 'database_id' => $usfId)).'">'.
                        '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile-fields/profile_fields_new.php', array('usf_id' => $usfId)).'">'.$userField->getValue('usf_name').'</a>',
        '<a class="admidio-icon-link" href="javascript:void(0)" onclick="moveCategory(\''.TableUserField::MOVE_UP.'\', '.$usfId.')">'.
            '<i class="fas fa-chevron-circle-up" data-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_UP', array('MEM_PROFILE_FIELD')) . '"></i></a>
        <a class="admidio-icon-link" href="javascript:void(0)" onclick="moveCategory(\''.TableUserField::MOVE_DOWN.'\', '.$usfId.')">'.
            '<i class="fas fa-chevron-circle-down" data-toggle="tooltip" title="' . $gL10n->get('SYS_MOVE_DOWN', array('MEM_PROFILE_FIELD')) . '"></i></a>',
        $description,
        $hidden,
        $disable,
        $mandatory,
        $registration,
        $userFieldText[$userField->getValue('usf_type')],
        $usfSystem
    );
    $table->addRowByArray($columnValues, 'row_usf_'.$usfId);
}

$page->addHtml($table->show());
$page->show();
