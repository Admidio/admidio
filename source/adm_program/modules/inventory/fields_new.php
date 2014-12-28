<?php
/******************************************************************************
 * Create and edit profile fields
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * inf_id : profile field id that should be edited
 *
 ****************************************************************************/
 
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getInfId = admFuncVariableIsValid($_GET, 'inf_id', 'numeric', 0);

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isWebmaster())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
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
    if($itemField->getValue('cat_org_id') >  0
    && $itemField->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
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
$page = new HtmlPage();

$page->addJavascript('
	function setValueList() {
		if($("#inf_type").val() == "DROPDOWN" || $("#inf_type").val() == "RADIO_BUTTON") {
			$("#inf_value_list_group").show("slow");
            $("#inf_value_list").attr("required", "required");
		}
		else {
            $("#inf_value_list").removeAttr("required");
			$("#inf_value_list_group").hide();
		}
	}
    
    setValueList();
    $("#inf_type").click(function() {setValueList();});', true);

// add headline and title of module
$page->addHeadline($headline);

// create module menu with back link
$profileFieldsEditMenu = new HtmlNavbar('menu_profile_fields_edit', $headline, $page);
$profileFieldsEditMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
$page->addHtml($profileFieldsEditMenu->show(false));

// show form
$form = new HtmlForm('profile_fields_edit_form', $g_root_path.'/adm_program/modules/inventory/fields_function.php?inf_id='.$getInfId.'&amp;mode=1', $page);
$form->openGroupBox('gb_designation', $gL10n->get('SYS_DESIGNATION'));
    if($itemField->getValue('inf_system') == 1)
    {
        $form->addTextInput('inf_name', $gL10n->get('SYS_NAME'), $itemField->getValue('inf_name', 'database'), 100, FIELD_DISABLED);
    }
    else
    {
        $form->addTextInput('inf_name', $gL10n->get('SYS_NAME'), $itemField->getValue('inf_name', 'database'), 100, FIELD_MANDATORY);
    }
    
    // show internal field name for information
    if($getInfId > 0)
    {
        $form->addTextInput('inf_name_intern', $gL10n->get('SYS_INTERNAL_NAME'), $itemField->getValue('inf_name_intern'), 100, FIELD_DISABLED, 'text', 'SYS_INTERNAL_NAME_DESC');
    }
    
    if($itemField->getValue('inf_system') == 1)
    {
        $form->addTextInput('inf_cat_id', $gL10n->get('SYS_CATEGORY'), $itemField->getValue('cat_name'), 100, FIELD_DISABLED);
    }
    else
    {
        $form->addSelectBoxForCategories('inf_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'INF', 'EDIT_CATEGORIES', FIELD_MANDATORY, $itemField->getValue('cat_name'));
    }
$form->closeGroupBox();
$form->openGroupBox('gb_presentation', $gL10n->get('SYS_PRESENTATION'));
    $itemFieldText = array('CHECKBOX' => $gL10n->get('SYS_CHECKBOX'),
	                       'DATE'     => $gL10n->get('SYS_DATE'),
                           'DROPDOWN' => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                           'EMAIL'    => $gL10n->get('SYS_EMAIL'),
                           'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
                           'TEXT'     => $gL10n->get('SYS_TEXT').' (100 '.$gL10n->get('SYS_CHARACTERS').')',
                           'TEXT_BIG' => $gL10n->get('SYS_TEXT').' (4000 '.$gL10n->get('SYS_CHARACTERS').')',
                           'URL'      => $gL10n->get('ORG_URL'),
                           'NUMBER'   => $gL10n->get('SYS_NUMBER'),
                           'DECIMAL'  => $gL10n->get('SYS_DECIMAL_NUMBER'));

    if($itemField->getValue('inf_system') == 1)
    {
        // bei Systemfeldern darf der Datentyp nicht mehr veraendert werden
        $form->addTextInput('inf_type', $gL10n->get('ORG_DATATYPE'), $itemFieldText[$itemField->getValue('inf_type')], 30, FIELD_DISABLED);
    }
    else
    {
        // fuer jeden Feldtypen einen Eintrag in der Combobox anlegen
        $form->addSelectBox('inf_type', $gL10n->get('ORG_DATATYPE'), $itemFieldText, FIELD_MANDATORY, $itemField->getValue('inf_type'));
    }
	$form->addMultilineTextInput('inf_value_list', $gL10n->get('ORG_VALUE_LIST'), $itemField->getValue('inf_value_list', 'database'), 6, 0, FIELD_MANDATORY, 'ORG_VALUE_LIST_DESC');
$form->closeGroupBox();
$form->openGroupBox('gb_authorization', $gL10n->get('SYS_AUTHORIZATION'));
    $form->addCheckbox('inf_hidden', $gL10n->get('ORG_FIELD_NOT_HIDDEN'), $itemField->getValue('inf_hidden'), FIELD_DEFAULT, 'ORG_FIELD_HIDDEN_DESC', null, 'eye.png');
    $form->addCheckbox('inf_disabled', $gL10n->get('ORG_FIELD_DISABLED', $gL10n->get('ROL_RIGHT_EDIT_USER')), $itemField->getValue('inf_disabled'), FIELD_DEFAULT, 'ORG_FIELD_DISABLED_DESC', null, 'textfield_key.png');
    $form->addCheckbox('inf_mandatory', $gL10n->get('ORG_FIELD_MANDATORY'), $itemField->getValue('inf_mandatory'), FIELD_DEFAULT, 'ORG_FIELD_MANDATORY_DESC', null, 'asterisk_yellow.png');
$form->closeGroupBox();
$form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'));
    $form->addEditor('inf_description', null, $itemField->getValue('inf_description'), FIELD_DEFAULT, 'AdmidioDefault', '200px');
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png');
$form->addHtml(admFuncShowCreateChangeInfoById($itemField->getValue('inf_usr_id_create'), $itemField->getValue('inf_timestamp_create'), $itemField->getValue('inf_usr_id_change'), $itemField->getValue('inf_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();

?>