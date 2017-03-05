<?php
/**
 ***********************************************************************************************
 * Create and edit profile fields
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * inf_id : profile field id that should be edited
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getInfId = admFuncVariableIsValid($_GET, 'inf_id', 'int');

// only users with the right to edit inventory could use this script
if (!$gCurrentUser->editInventory())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// set headline of the script
if($getInfId > 0)
{
    $headline = $gL10n->get('ORG_EDIT_PROFILE_FIELD');
}
else
{
    $headline = $gL10n->get('ORG_CREATE_PROFILE_FIELD');
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// benutzerdefiniertes Feldobjekt anlegen
$itemField = new TableInventoryField($gDb);

if($getInfId > 0)
{
    $itemField->readDataById($getInfId);

    // hidden must be 0, if the flag should be set
    if($itemField->getValue('inf_hidden') == 1)
    {
        $itemField->setValue('inf_hidden', 0);
    }
    else
    {
        $itemField->setValue('inf_hidden', 1);
    }

    // Pruefung, ob das Feld zur aktuellen Organisation gehoert
    if($itemField->getValue('cat_org_id') > 0
    && (int) $itemField->getValue('cat_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}
else
{
    // default values for a new field
    $itemField->setValue('inf_hidden', 1);
}

if(isset($_SESSION['fields_request']))
{
    // hidden must be 0, if the flag should be set
    if($_SESSION['fields_request']['inf_hidden'] == 1)
    {
        $_SESSION['fields_request']['inf_hidden'] = 0;
    }
    else
    {
        $_SESSION['fields_request']['inf_hidden'] = 1;
    }

    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $itemField->setArray($_SESSION['fields_request']);
    unset($_SESSION['fields_request']);
}

// create html page object
$page = new HtmlPage($headline);

$page->addJavascript('
    function setValueList() {
        if ($("#inf_type").val() === "DROPDOWN" || $("#inf_type").val() === "RADIO_BUTTON") {
            $("#inf_value_list_group").show("slow");
            $("#inf_value_list").attr("required", "required");
        } else {
            $("#inf_value_list").removeAttr("required");
            $("#inf_value_list_group").hide();
        }
    }

    setValueList();
    $("#inf_type").click(function() { setValueList(); });', true);

// add back link to module menu
$profileFieldsEditMenu = $page->getMenu();
$profileFieldsEditMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('profile_fields_edit_form', ADMIDIO_URL.FOLDER_MODULES.'/inventory/fields_function.php?inf_id='.$getInfId.'&amp;mode=1', $page);
$form->openGroupBox('gb_designation', $gL10n->get('SYS_DESIGNATION'));
    if($itemField->getValue('inf_system') == 1)
    {
        $form->addInput('inf_name', $gL10n->get('SYS_NAME'), $itemField->getValue('inf_name', 'database'), array('maxLength' => 100, 'property' => FIELD_DISABLED));
    }
    else
    {
        $form->addInput('inf_name', $gL10n->get('SYS_NAME'), $itemField->getValue('inf_name', 'database'), array('maxLength' => 100, 'property' => FIELD_REQUIRED));
    }

    // show internal field name for information
    if($getInfId > 0)
    {
        $form->addInput('inf_name_intern', $gL10n->get('SYS_INTERNAL_NAME'), $itemField->getValue('inf_name_intern'),  array('maxLength' => 100, 'property' => FIELD_DISABLED, 'helpTextIdLabel' => 'SYS_INTERNAL_NAME_DESC'));
    }

    if($itemField->getValue('inf_system') == 1)
    {
        $form->addInput('inf_cat_id', $gL10n->get('SYS_CATEGORY'), $itemField->getValue('cat_name'),  array('maxLength' => 100, 'property' => FIELD_DISABLED));
    }
    else
    {
        $form->addSelectBoxForCategories('inf_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'INF', 'EDIT_CATEGORIES', array('property' => FIELD_REQUIRED, 'defaultValue' => $itemField->getValue('cat_name')));
    }
$form->closeGroupBox();
$form->openGroupBox('gb_presentation', $gL10n->get('SYS_PRESENTATION'));
    $itemFieldText = array('CHECKBOX'     => $gL10n->get('SYS_CHECKBOX'),
                           'DATE'         => $gL10n->get('SYS_DATE'),
                           'DROPDOWN'     => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                           'EMAIL'        => $gL10n->get('SYS_EMAIL'),
                           'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
                           'TEXT'         => $gL10n->get('SYS_TEXT').' (100 '.$gL10n->get('SYS_CHARACTERS').')',
                           'TEXT_BIG'     => $gL10n->get('SYS_TEXT').' (4000 '.$gL10n->get('SYS_CHARACTERS').')',
                           'URL'          => $gL10n->get('ORG_URL'),
                           'NUMBER'       => $gL10n->get('SYS_NUMBER'),
                           'DECIMAL'      => $gL10n->get('SYS_DECIMAL_NUMBER'));

    if($itemField->getValue('inf_system') == 1)
    {
        // bei Systemfeldern darf der Datentyp nicht mehr veraendert werden
        $form->addInput('inf_type', $gL10n->get('ORG_DATATYPE'), $itemFieldText[$itemField->getValue('inf_type')], array('maxLength' => 30, 'property' => FIELD_DISABLED));
    }
    else
    {
        // fuer jeden Feldtypen einen Eintrag in der Combobox anlegen
        $form->addSelectBox('inf_type', $gL10n->get('ORG_DATATYPE'), $itemFieldText, array('property' => FIELD_REQUIRED, 'defaultValue' => $itemField->getValue('inf_type')));
    }
    $form->addMultilineTextInput('inf_value_list', $gL10n->get('ORG_VALUE_LIST'), $itemField->getValue('inf_value_list', 'database'), 6, array('property' => FIELD_REQUIRED, 'helpTextIdLabel' => 'ORG_VALUE_LIST_DESC'));
$form->closeGroupBox();
$form->openGroupBox('gb_authorization', $gL10n->get('SYS_AUTHORIZATION'));
    $form->addCheckbox('inf_hidden', $gL10n->get('ORG_FIELD_NOT_HIDDEN'), (bool) $itemField->getValue('inf_hidden'), array('property' => FIELD_DEFAULT, 'helpTextIdLabel' => 'ORG_FIELD_HIDDEN_DESC', 'icon' => 'eye.png'));
    $form->addCheckbox('inf_disabled', $gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')), (bool) $itemField->getValue('inf_disabled'), array('property' => FIELD_DEFAULT, 'helpTextIdLabel' => 'ORG_FIELD_DISABLED_DESC', 'icon' => 'textfield_key.png'));
    $form->addCheckbox('inf_mandatory', $gL10n->get('ORG_FIELD_REQUIRED'), (bool) $itemField->getValue('inf_mandatory'), array('property' => FIELD_DEFAULT, 'helpTextIdLabel' => 'ORG_FIELD_REQUIRED_DESC', 'icon' => 'asterisk_yellow.png'));
$form->closeGroupBox();
$form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'), 'admidio-panel-editor');
    $form->addEditor('inf_description', '', $itemField->getValue('inf_description'), array('property' => FIELD_DEFAULT, 'toolbar' => 'AdmidioDefault', 'height' => '200px'));
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($itemField->getValue('inf_usr_id_create'), $itemField->getValue('inf_timestamp_create'), $itemField->getValue('inf_usr_id_change'), $itemField->getValue('inf_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
