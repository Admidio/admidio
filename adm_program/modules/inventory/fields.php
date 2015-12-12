<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all profile fields
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// only users with the right to edit inventory could use this script
if (!$gCurrentUser->editInventory())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set module headline
$headline = $gL10n->get('ORG_ITEM_FIELDS');

$gNavigation->addUrl(CURRENT_URL, $headline);

unset($_SESSION['fields_request']);

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$page->addJavascript('$(".admidio-group-heading").click(function() { showHideBlock($(this).attr("id")); });', true);
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
            if(childs[i].tagName === "TR") {
                actRowCount++;
                if(actSequence > 0 && nextNode == null) {
                    nextNode = childs[i];
                }

                if(childs[i].id === "row_usf_" + usfID) {
                    actSequence = actRowCount;
                }

                if(actSequence === 0) {
                    prevNode = childs[i];
                }
            }
        }

        // entsprechende Werte zum Hoch- bzw. Runterverschieben ermitteln
        if(direction === "up") {
            if(prevNode != null) {
                actRow.parentNode.insertBefore(actRow, prevNode);
                secondSequence = actSequence - 1;
            }
        } else {
            if(nextNode != null) {
                actRow.parentNode.insertBefore(nextNode, actRow);
                secondSequence = actSequence + 1;
            }
        }

        if(secondSequence > 0) {
            // Nun erst mal die neue Position von dem gewaehlten Feld aktualisieren
            $.get(gRootPath + "/adm_program/modules/inventory/fields_function.php?usf_id=" + usfID + "&mode=4&sequence=" + direction);
        }
    }');

// get module menu
$fieldsMenu = $page->getMenu();

// show back link
$fieldsMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// define link to create new profile field
$fieldsMenu->addItem('menu_item_new_field', $g_root_path.'/adm_program/modules/inventory/fields_new.php',
                            $gL10n->get('ORG_CREATE_ITEM_FIELD'), 'add.png');
// define link to maintain categories
$fieldsMenu->addItem('menu_item_maintain_category', $g_root_path.'/adm_program/modules/categories/categories.php?type=INF',
                            $gL10n->get('SYS_MAINTAIN_CATEGORIES'), 'application_double.png');

$sql = 'SELECT * FROM '. TBL_CATEGORIES. ', '. TBL_INVENT_FIELDS. '
         WHERE cat_type   = \'INF\'
           AND inf_cat_id = cat_id
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL )
         ORDER BY cat_sequence ASC, inf_sequence ASC ';
$statement = $gDb->query($sql);

// Create table
$table = new HtmlTable('tbl_profile_fields', $page, true);
$table->setMessageIfNoRowsFound('ORG_NO_FIELD_CREATED');

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_FIELD').HtmlForm::getHelpTextIcon('ORG_FIELD_DESCRIPTION'),
    '&nbsp;',
    $gL10n->get('SYS_DESCRIPTION'),
    '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/eye.png" alt="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" title="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" />',
    '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/textfield_key.png" alt="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" title="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" />',
    '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/asterisk_yellow.png" alt="'.$gL10n->get('ORG_FIELD_REQUIRED').'" title="'.$gL10n->get('ORG_FIELD_REQUIRED').'" />',
    $gL10n->get('ORG_DATATYPE'),
    '&nbsp;');
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

    if($categoryId != $userField->getValue('cat_id'))
    {
        $block_id = 'admCategory'.$userField->getValue('inf_cat_id');

        $table->addTableBody();
        $table->addRow();
        $table->addColumn('', array('class' => 'admidio-group-heading', 'id' => 'group_'.$block_id), 'td');
        $table->addAttribute('colspan', '8');
        $table->addData('<span id="caret_'.$block_id.'" class="caret"></span>'.$userField->getValue('cat_name'));
        $table->addTableBody('id', $block_id);

        $categoryId = $userField->getValue('inf_cat_id');
    }

    // cut long text strings and provide tooltip
    if(strlen($userField->getValue('inf_description')) > 22)
    {
        $description = substr($userField->getValue('inf_description', 'database'), 0, 22).'
            <a class="colorbox-dialog" data-html="true" data-toggle="tooltip" data-original-title="'.str_replace('"', '\'', $userField->getValue('inf_description')).'" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=user_field_description&amp;message_var1='. $userField->getValue('inf_name_intern'). '&amp;inline=true">[..]</a>';
    }
    elseif(strlen($userField->getValue('inf_description')== 0))
    {
        $description = '&nbsp;';
    }
    else
    {
        $description = $userField->getValue('inf_description');
    }

    if($userField->getValue('inf_hidden') == 1)
    {
        $hidden = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/eye_gray.png" alt="'.$gL10n->get('ORG_FIELD_HIDDEN').'" title="'.$gL10n->get('ORG_FIELD_HIDDEN').'" />';
    }
    else
    {
        $hidden = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/eye.png" alt="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" title="'.$gL10n->get('ORG_FIELD_NOT_HIDDEN').'" />';
    }

    if($userField->getValue('inf_disabled') == 1)
    {
        $disable = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/textfield_key.png" alt="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" title="'.$gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')).'" />';
    }
    else
    {
        $disable = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/textfield.png" alt="'.$gL10n->get('ORG_FIELD_NOT_DISABLED').'" title="'.$gL10n->get('ORG_FIELD_NOT_DISABLED').'" />';
    }

    if($userField->getValue('inf_mandatory') == 1)
    {
        $mandatory = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/asterisk_yellow.png" alt="'.$gL10n->get('ORG_FIELD_REQUIRED').'" title="'.$gL10n->get('ORG_FIELD_REQUIRED').'" />';
    }
    else
    {
        $mandatory = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/asterisk_gray.png" alt="'.$gL10n->get('ORG_FIELD_NOT_MANDATORY').'" title="'.$gL10n->get('ORG_FIELD_NOT_MANDATORY').'" />';
    }

    $userFieldText = array('CHECKBOX'      => $gL10n->get('SYS_CHECKBOX'),
                            'DATE'         => $gL10n->get('SYS_DATE'),
                            'DROPDOWN'     => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                            'EMAIL'        => $gL10n->get('SYS_EMAIL'),
                            'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
                            'TEXT'         => $gL10n->get('SYS_TEXT').' (50)',
                            'TEXT_BIG'     => $gL10n->get('SYS_TEXT').' (255)',
                            'URL'          => $gL10n->get('ORG_URL'),
                            'NUMBER'       => $gL10n->get('SYS_NUMBER'),
                            'DECIMAL'      => $gL10n->get('SYS_DECIMAL_NUMBER'));

    $usfSystem = '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/inventory/fields_new.php?inf_id='.$userField->getValue('inf_id').'"><img
                    src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';

    if($userField->getValue('inf_system') == 1)
    {
        $usfSystem .= '<img class="admidio-icon-link" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
    }
    else
    {
        $usfSystem .='<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                        href="'.$g_root_path.'/adm_program/system/popup_message.php?type=inf&amp;element_id=row_inf_'.
                        $userField->getValue('inf_id').'&amp;name='.urlencode($userField->getValue('inf_name')).'&amp;database_id='.$userField->getValue('inf_id').'"><img
                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
    }

    // create array with all column values
    $columnValues = array(
        '<a href="'.$g_root_path.'/adm_program/modules/inventory/fields_new.php?inf_id='.$userField->getValue('inf_id').'">'.$userField->getValue('inf_name').'</a>',
        '<a class="admidio-icon-link" href="javascript:moveCategory(\'up\', '.$userField->getValue('inf_id').')"><img
            src="'. THEME_PATH. '/icons/arrow_up.png" alt="'.$gL10n->get('ORG_FIELD_UP').'" title="'.$gL10n->get('ORG_FIELD_UP').'" /></a>
        <a class="admidio-icon-link" href="javascript:moveCategory(\'down\', '.$userField->getValue('inf_id').')"><img
            src="'. THEME_PATH. '/icons/arrow_down.png" alt="'.$gL10n->get('ORG_FIELD_DOWN').'" title="'.$gL10n->get('ORG_FIELD_DOWN').'" /></a>',
        $description,
        $hidden,
        $disable,
        $mandatory,
        $userFieldText[$userField->getValue('inf_type')],
        $usfSystem
    );
    $table->addRowByArray($columnValues, 'row_inf_'.$userField->getValue('inf_id'));
}

$page->addHtml($table->show(false));
$page->show();
