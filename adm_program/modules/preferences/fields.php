<?php
/******************************************************************************
 * Overview and maintenance of all profile fields
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 ****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isWebmaster())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set module headline
$headline = $gL10n->get('ORG_PROFILE_FIELDS');

$gNavigation->addUrl(CURRENT_URL, $headline);

unset($_SESSION['fields_request']);

// create html page object
$page = new HtmlPage($headline);

$page->addJavascript('
    $(".admidio-group-heading").click(function() {
        showHideBlock($(this).attr("id"));
    });',
    true
);
$page->addJavascript('
    function moveCategory(direction, usfID) {
        var actRow = document.getElementById("row_usf_" + usfID);
        var childs = actRow.parentNode.childNodes;
        var prevNode    = null;
        var nextNode    = null;
        var actRowCount = 0;
        var actSequence = 0;
        var secondSequence = 0;

        // erst einmal aktuelle Sequenz und vorherigen/naechsten Knoten ermitteln
        for(i=0;i < childs.length; i++) {
            if(childs[i].tagName == "TR") {
                actRowCount++;
                if(actSequence > 0 && nextNode == null) {
                    nextNode = childs[i];
                }

                if(childs[i].id == "row_usf_" + usfID) {
                    actSequence = actRowCount;
                }

                if(actSequence == 0) {
                    prevNode = childs[i];
                }
            }
        }

        // entsprechende Werte zum Hoch- bzw. Runterverschieben ermitteln
        if(direction == "up") {
            if(prevNode != null) {
                actRow.parentNode.insertBefore(actRow, prevNode);
                secondSequence = actSequence - 1;
            }
        }
        else {
            if(nextNode != null) {
                actRow.parentNode.insertBefore(nextNode, actRow);
                secondSequence = actSequence + 1;
            }
        }

        if(secondSequence > 0) {
            // Nun erst mal die neue Position von dem gewaehlten Feld aktualisieren
            $.get(gRootPath + "/adm_program/modules/preferences/fields_function.php?usf_id=" + usfID + "&mode=4&sequence=" + direction);
        }
    }'
);

// get module menu
$fieldsMenu = $page->getMenu();

// show back link
$fieldsMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// define link to create new profile field
$fieldsMenu->addItem('menu_item_new_field', $g_root_path.'/adm_program/modules/preferences/fields_new.php',
                     $gL10n->get('ORG_CREATE_PROFILE_FIELD'), 'add.png');
// define link to maintain categories
$fieldsMenu->addItem('menu_item_maintain_category', $g_root_path.'/adm_program/modules/categories/categories.php?type=USF',
                     $gL10n->get('SYS_MAINTAIN_CATEGORIES'), 'application_double.png');

$sql = 'SELECT * FROM '.TBL_CATEGORIES.', '.TBL_USER_FIELDS.'
         WHERE cat_type   = \'USF\'
           AND usf_cat_id = cat_id
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL )
         ORDER BY cat_sequence ASC, usf_sequence ASC ';
$result = $gDb->query($sql);

// Create table
$table = new HtmlTable('tbl_profile_fields', $page, true);
$table->setMessageIfNoRowsFound('ORG_NO_FIELD_CREATED');

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_FIELD').
    '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
        href="'.$g_root_path.'/adm_program/system/msg_window.php?message_id=ORG_FIELD_DESCRIPTION&amp;inline=true"><img
        src="'.THEME_PATH.'/icons/help.png" alt="Help" /></a>',
    '&nbsp;',
    $gL10n->get('SYS_DESCRIPTION'),
    '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/eye.png" alt="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" title="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" />',
    '<img class="admidio-icon-info" data-html="true" src="'.THEME_PATH.'/icons/textfield_key.png" alt="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" title="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" />',
    '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/asterisk_yellow.png" alt="'.$gL10n->get('ORG_FIELD_REQUIRED').'" title="'.$gL10n->get('ORG_FIELD_REQUIRED').'" />',
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

while($row = $gDb->fetch_array($result))
{
    $userField->clear();
    $userField->setArray($row);

    if($categoryId != $userField->getValue('cat_id'))
    {
        $block_id = 'admCategory'.$userField->getValue('usf_cat_id');

        $table->addTableBody();
        $table->addRow('', array('class' => 'admidio-group-heading'));
        $table->addColumn('<span id="caret_'.$block_id.'" class="caret"></span>'.$userField->getValue('cat_name'),
                          array('id' => 'group_'.$block_id, 'colspan' => '8'), 'td');
        $table->addTableBody('id', $block_id);

        $categoryId = $userField->getValue('usf_cat_id');
    }

    // cut long text strings and provide tooltip
    if(strlen($userField->getValue('usf_description')) > 22)
    {
        $description = substr($userField->getValue('usf_description', 'database'), 0, 22).'
            <a data-toggle="modal" data-target="#admidio_modal"
                href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=user_field_description&amp;message_var1='.$userField->getValue('usf_name_intern').'&amp;inline=true"><span  data-html="true" data-toggle="tooltip" data-original-title="'.str_replace('"', '\'', $userField->getValue('usf_description')).'">[..]</span></a>';
    }
    elseif(strlen($userField->getValue('usf_description')== 0))
    {
        $description = '&nbsp;';
    }
    else
    {
        $description = $userField->getValue('usf_description');
    }

    if($userField->getValue('usf_hidden') == 1)
    {
        $hidden = '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/eye_gray.png" alt="'.$gL10n->get('ORG_FIELD_HIDDEN').'" title="'.$gL10n->get('ORG_FIELD_HIDDEN').'" />';
    }
    else
    {
        $hidden = '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/eye.png" alt="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" title="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" />';
    }

    if($userField->getValue('usf_disabled') == 1)
    {
        $disable = '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/textfield_key.png" alt="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" title="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" />';
    }
    else
    {
        $disable = '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/textfield.png" alt="'.$gL10n->get('ORG_FIELD_NOT_DISABLED').'" title="'.$gL10n->get('ORG_FIELD_NOT_DISABLED').'" />';
    }

    if($userField->getValue('usf_mandatory') == 1)
    {
        $mandatory = '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/asterisk_yellow.png" alt="'.$gL10n->get('ORG_FIELD_REQUIRED').'" title="'.$gL10n->get('ORG_FIELD_REQUIRED').'" />';
    }
    else
    {
        $mandatory = '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/asterisk_gray.png" alt="'.$gL10n->get('ORG_FIELD_NOT_MANDATORY').'" title="'.$gL10n->get('ORG_FIELD_NOT_MANDATORY').'" />';
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

    $usfSystem = '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/preferences/fields_new.php?usf_id='.$userField->getValue('usf_id').'"><img
                    src="'.THEME_PATH.'/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';

    if($userField->getValue('usf_system') == 1)
    {
        $usfSystem .= '<img class="admidio-icon-link" src="'.THEME_PATH.'/icons/dummy.png" alt="dummy" />';
    }
    else
    {
        $usfSystem .='<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                        href="'.$g_root_path.'/adm_program/system/popup_message.php?type=usf&amp;element_id=row_usf_'.
                        $userField->getValue('usf_id').'&amp;name='.urlencode($userField->getValue('usf_name')).'&amp;database_id='.$userField->getValue('usf_id').'"><img
                        src="'.THEME_PATH.'/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.$g_root_path.'/adm_program/modules/preferences/fields_new.php?usf_id='.$userField->getValue('usf_id').'">'.$userField->getValue('usf_name').'</a>',
        '<a class="admidio-icon-link" href="javascript:moveCategory(\'up\', '.$userField->getValue('usf_id').')"><img
            src="'.THEME_PATH.'/icons/arrow_up.png" alt="'.$gL10n->get('ORG_FIELD_UP').'" title="'.$gL10n->get('ORG_FIELD_UP').'" /></a>
        <a class="admidio-icon-link" href="javascript:moveCategory(\'down\', '.$userField->getValue('usf_id').')"><img
            src="'.THEME_PATH.'/icons/arrow_down.png" alt="'.$gL10n->get('ORG_FIELD_DOWN').'" title="'.$gL10n->get('ORG_FIELD_DOWN').'" /></a>',
        $description,
        $hidden,
        $disable,
        $mandatory,
        $userFieldText[$userField->getValue('usf_type')],
        $usfSystem
    );
    $table->addRowByArray($columnValues, 'row_usf_'.$userField->getValue('usf_id'));
}

$page->addHtml($table->show(false));
$page->show();

?>
