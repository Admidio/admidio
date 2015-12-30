<?php
/**
 ***********************************************************************************************
 * Create and edit profile fields
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * usf_id : profile field id that should be edited
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getUsfId = admFuncVariableIsValid($_GET, 'usf_id', 'numeric');

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isWebmaster())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set headline of the script
if($getUsfId > 0)
{
    $headline = $gL10n->get('ORG_EDIT_PROFILE_FIELD');
}
else
{
    $headline = $gL10n->get('ORG_CREATE_PROFILE_FIELD');
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// benutzerdefiniertes Feldobjekt anlegen
$userField = new TableUserField($gDb);

if($getUsfId > 0)
{
    $userField->readDataById($getUsfId);

    // hidden must be 0, if the flag should be set
    if($userField->getValue('usf_hidden') == 1)
    {
        $userField->setValue('usf_hidden', 0);
    }
    else
    {
        $userField->setValue('usf_hidden', 1);
    }

    // Pruefung, ob das Feld zur aktuellen Organisation gehoert
    if($userField->getValue('cat_org_id') > 0
    && $userField->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}
else
{
    // default values for a new field
    $userField->setValue('usf_hidden', 1);
}

if(isset($_SESSION['fields_request']))
{
    // hidden must be 0, if the flag should be set
    if($_SESSION['fields_request']['usf_hidden'] == 1)
    {
        $_SESSION['fields_request']['usf_hidden'] = 0;
    }
    else
    {
        $_SESSION['fields_request']['usf_hidden'] = 1;
    }

    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $userField->setArray($_SESSION['fields_request']);
    unset($_SESSION['fields_request']);
}

// create html page object
$page = new HtmlPage($headline);

$page->addJavascript('
    function setValueList() {
        if ($("#usf_type").val() === "DROPDOWN" || $("#usf_type").val() === "RADIO_BUTTON") {
            $("#usf_value_list_group").show("slow");
            $("#usf_value_list").attr("required", "required");
        } else {
            $("#usf_value_list").removeAttr("required");
            $("#usf_value_list_group").hide();
        }
    }

    setValueList();
    $("#usf_type").click(function() { setValueList(); });',
    true
);

// add back link to module menu
$profileFieldsEditMenu = $page->getMenu();
$profileFieldsEditMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('profile_fields_edit_form',
                     $g_root_path.'/adm_program/modules/preferences/fields_function.php?usf_id='.$getUsfId.'&amp;mode=1', $page);
$form->openGroupBox('gb_designation', $gL10n->get('SYS_DESIGNATION'));
if($userField->getValue('usf_system') == 1)
{
    $form->addInput('usf_name', $gL10n->get('SYS_NAME'), $userField->getValue('usf_name', 'database'),
                    array('maxLength' => 100, 'property' => FIELD_DISABLED));
}
else
{
    $form->addInput('usf_name', $gL10n->get('SYS_NAME'), $userField->getValue('usf_name', 'database'),
                    array('maxLength' => 100, 'property' => FIELD_REQUIRED));
}

// show internal field name for information
if($getUsfId > 0)
{
    $form->addInput('usf_name_intern', $gL10n->get('SYS_INTERNAL_NAME'), $userField->getValue('usf_name_intern'),
                    array('maxLength' => 100, 'property' => FIELD_DISABLED, 'helpTextIdLabel' => 'SYS_INTERNAL_NAME_DESC'));
}

if($userField->getValue('usf_system') == 1)
{
    $form->addInput('usf_cat_id', $gL10n->get('SYS_CATEGORY'), $userField->getValue('cat_name'),
                    array('maxLength' => 100, 'property' => FIELD_DISABLED));
}
else
{
    $form->addSelectBoxForCategories('usf_cat_id', $gL10n->get('SYS_CATEGORY'), $gDb, 'USF', 'EDIT_CATEGORIES',
                                     array('property' => FIELD_REQUIRED, 'defaultValue' => $userField->getValue('usf_cat_id')));
}
$form->closeGroupBox();
$form->openGroupBox('gb_presentation', $gL10n->get('SYS_PRESENTATION'));
$userFieldText = array('CHECKBOX'     => $gL10n->get('SYS_CHECKBOX'),
                       'DATE'         => $gL10n->get('SYS_DATE'),
                       'DECIMAL'      => $gL10n->get('SYS_DECIMAL_NUMBER'),
                       'DROPDOWN'     => $gL10n->get('SYS_DROPDOWN_LISTBOX'),
                       'EMAIL'        => $gL10n->get('SYS_EMAIL'),
                       'NUMBER'       => $gL10n->get('SYS_NUMBER'),
                       'PHONE'        => $gL10n->get('SYS_PHONE'),
                       'RADIO_BUTTON' => $gL10n->get('SYS_RADIO_BUTTON'),
                       'TEXT'         => $gL10n->get('SYS_TEXT').' (100 '.$gL10n->get('SYS_CHARACTERS').')',
                       'TEXT_BIG'     => $gL10n->get('SYS_TEXT').' (4000 '.$gL10n->get('SYS_CHARACTERS').')',
                       'URL'          => $gL10n->get('ORG_URL'));
asort($userFieldText);

if($userField->getValue('usf_system') == 1)
{
    // bei Systemfeldern darf der Datentyp nicht mehr veraendert werden
    $form->addInput('usf_type', $gL10n->get('ORG_DATATYPE'), $userFieldText[$userField->getValue('usf_type')],
                    array('maxLength' => 30, 'property' => FIELD_DISABLED));
}
else
{
    // fuer jeden Feldtypen einen Eintrag in der Combobox anlegen
    $form->addSelectBox('usf_type', $gL10n->get('ORG_DATATYPE'), $userFieldText,
                        array('property' => FIELD_REQUIRED, 'defaultValue' => $userField->getValue('usf_type')));
}
$form->addMultilineTextInput('usf_value_list', $gL10n->get('ORG_VALUE_LIST'),
                             $userField->getValue('usf_value_list', 'database'), 6,
                             array('property' => FIELD_REQUIRED, 'helpTextIdLabel' => 'ORG_VALUE_LIST_DESC'));
$form->addInput('usf_icon', $gL10n->get('SYS_ICON'), $userField->getValue('usf_icon', 'database'),
                array('maxLength' => 2000));
$form->addInput('usf_url', $gL10n->get('ORG_URL'), $userField->getValue('usf_url'),
                array('maxLength' => 2000, 'helpTextIdLabel' => 'ORG_FIELD_URL_DESC'));
$form->closeGroupBox();
$form->openGroupBox('gb_authorization', $gL10n->get('SYS_AUTHORIZATION'));
$form->addCheckbox('usf_hidden', $gL10n->get('ORG_FIELD_NOT_HIDDEN'), $userField->getValue('usf_hidden'),
                   array('helpTextIdLabel' => 'ORG_FIELD_HIDDEN_DESC', 'icon' => 'eye.png'));
$form->addCheckbox('usf_disabled', $gL10n->get('ORG_FIELD_DISABLED',
                   $gL10n->get('ROL_RIGHT_EDIT_USER')), $userField->getValue('usf_disabled'),
                   array('helpTextIdLabel' => 'ORG_FIELD_DISABLED_DESC', 'icon' => 'textfield_key.png'));

if($userField->getValue('usf_name_intern') === 'LAST_NAME' || $userField->getValue('usf_name_intern') === 'FIRST_NAME')
{
    $form->addCheckbox('usf_mandatory', $gL10n->get('ORG_FIELD_REQUIRED'), $userField->getValue('usf_mandatory'),
                       array('property' => FIELD_DISABLED, 'helpTextIdLabel' => 'ORG_FIELD_REQUIRED_DESC', 'icon' => 'asterisk_yellow.png'));
}
else
{
    $form->addCheckbox('usf_mandatory', $gL10n->get('ORG_FIELD_REQUIRED'), $userField->getValue('usf_mandatory'),
                       array('helpTextIdLabel' => 'ORG_FIELD_REQUIRED_DESC', 'icon' => 'asterisk_yellow.png'));
}
$form->closeGroupBox();
$form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'), 'admidio-panel-editor');
$form->addEditor('usf_description', null, $userField->getValue('usf_description'), array('height' => '200px'));
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));
$form->addHtml(admFuncShowCreateChangeInfoById($userField->getValue('usf_usr_id_create'),
               $userField->getValue('usf_timestamp_create'), $userField->getValue('usf_usr_id_change'),
               $userField->getValue('usf_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
