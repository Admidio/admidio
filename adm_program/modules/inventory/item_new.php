<?php
/**
 ***********************************************************************************************
 * Create or edit a inventory profile
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * item_id    : ID of the item who should be edited
 * new_item   : 0 - Edit item of the item id
 *              1 - Create a new item
 ***********************************************************************************************
 */
require_once('../../system/common.php');

// Initialize and check the parameters
$getItemId  = admFuncVariableIsValid($_GET, 'item_id',  'int');
$getNewItem = admFuncVariableIsValid($_GET, 'new_item', 'int');

$registrationOrgId = $gCurrentOrganization->getValue('org_id');

// only users with the right to edit inventory could use this script
if (!$gCurrentUser->editInventory())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// if new_inventory isn't set and no inventory id is set then show dialog to create a inventory
if($getItemId === 0 && $getNewItem === 0)
{
    $getNewItem = 1;
}

// set headline of the script
if($getNewItem === 1)
{
    $headline = $gL10n->get('PRO_ADD_inventory');
}
else
{
    $headline = $gL10n->get('PRO_EDIT_PROFILE');
}

// inventory-ID nur uebernehmen, wenn ein vorhandener Benutzer auch bearbeitet wird
if($getItemId > 0 && $getNewItem === 1)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}
// => EXIT

// read inventory data
$gInventoryFields = new InventoryFields($gDb, $gCurrentOrganization->getValue('org_id'));
$inventory = new Inventory($gDb, $gInventoryFields, $getItemId);

// pruefen, ob Modul aufgerufen werden darf
switch($getNewItem)
{
    case 0:
        // prueft, ob der user die notwendigen Rechte hat, das entsprechende Profil zu aendern
        if(!$gCurrentUser->editInventory($inventory))
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
        break;

    case 1:
        // prueft, ob der inventory die notwendigen Rechte hat, neue items anzulegen
        if(!$gCurrentUser->editInventory())
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
        break;
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// Formular wurde ueber "Zurueck"-Funktion aufgerufen, also alle Felder mit den vorherigen Werten fuellen
if(isset($_SESSION['profile_request']))
{
    $inventory->noValueCheck();

    foreach($gInventoryFields->mInventoryFields as $field)
    {
        $field_name = 'inf-'. $field->getValue('inf_id');
        if(isset($_SESSION['profile_request'][$field_name]))
        {
            $inventory->setValue($field->getValue('inf_name_intern'), $_SESSION['profile_request'][$field_name]);
        }
    }

    if(isset($_SESSION['profile_request']['usr_login_name']))
    {
        $inventory->setArray(array('usr_login_name' => $_SESSION['profile_request']['usr_login_name']));
    }
    if(isset($_SESSION['profile_request']['reg_org_id']))
    {
        $registrationOrgId = $_SESSION['profile_request']['reg_org_id'];
    }

    unset($_SESSION['profile_request']);
}

// create html page object
$page = new HtmlPage($headline);

$page->addJavascript('
    var profileJS = new ProfileJS(gRootPath);
    profileJS.init();', true);

// add back link to module menu
$profileEditMenu = $page->getMenu();
$profileEditMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// create html form
$form = new HtmlForm('edit_item_form', ADMIDIO_URL.FOLDER_MODULES.'/inventory/item_save.php?item_id='.$getItemId.'&amp;new_item='.$getNewItem, $page);

// *******************************************************************************
// Schleife ueber alle Kategorien und Felder ausser den Stammdaten
// *******************************************************************************

$category = '';

foreach($gInventoryFields->mInventoryFields as $field)
{

    // Kategorienwechsel den Kategorienheader anzeigen
    // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
    if($category !== $field->getValue('cat_name'))
    {
        if($category !== '')
        {
            // div-Container admGroupBoxBody und admGroupBox schliessen
            $form->closeGroupBox();
        }
        $category = $field->getValue('cat_name');

        $form->addHtml('<a name="cat-'. $field->getValue('cat_id'). '"></a>');
        $form->openGroupBox('gb_category_name', $field->getValue('cat_name'));

        if($field->getValue('cat_name_intern') === 'MASTER_DATA' && $getItemId > 0)
        {
            // add inventoryname to form
            $fieldProperty = FIELD_DEFAULT;
            $fieldHelpId   = null;

            if(!$gCurrentUser->isAdministrator() && $getNewItem === 0)
            {
                $fieldProperty = FIELD_DISABLED;
            }
            elseif($getNewItem > 0)
            {
                $fieldProperty = FIELD_REQUIRED;
                $fieldHelpId   = 'PRO_inventoryNAME_DESCRIPTION';
            }

            $form->addLine();
        }
    }

        // add profile fields to form
        $fieldProperty = FIELD_DEFAULT;
        $helpId        = null;

        if($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_disabled') == 1 && !$gCurrentUser->editUsers() && $getNewUser == 0)
        {
            // disable field if this is configured in profile field configuration
            $fieldProperty = FIELD_DISABLED;
        }
        elseif($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_mandatory') == 1)
        {
            // set mandatory field
            $fieldProperty = FIELD_REQUIRED;
        }

        if(strlen($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_description')) > 0)
        {
            $helpId = array('item_field_description', $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_name_intern'));
        }

        // code for different field types

        if($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_type') === 'CHECKBOX')
        {
            // TODO fix parameters
            $form->addCheckbox(
                'inf-'. $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_id'),
                $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_name'),
                (bool) $inventory->getValue($field->getValue('inf_name_intern')),
                $fieldProperty, $helpId, null,
                $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_icon', 'database')
            );
        }
        elseif($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_type') === 'DROPDOWN'
            || $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_name_intern') === 'ROOM_ID')
        {
            // set array with values and set default value
            if($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_name_intern') === 'ROOM_ID')
            {
                if($gDbType === 'mysql')
                {
                    $sql = 'SELECT room_id, CONCAT(room_name, \' (\', room_capacity, \'+\', IFNULL(room_overhang, \'0\'), \')\')
                              FROM '.TBL_ROOMS.'
                          ORDER BY room_name';
                }
                else
                {
                    $sql = 'SELECT room_id, room_name || \' (\' || room_capacity || \'+\' || COALESCE(room_overhang, \'0\') || \')\'
                              FROM '.TBL_ROOMS.'
                          ORDER BY room_name';
                }
                $defaultValue = '';
                if($getNewItem === 0)
                {
                    $defaultValue = $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_id');
                }

                $form->addSelectBoxFromSql(
                    'inf-'. $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_id'),
                    $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_name'), $gDb, $sql,
                    array('property' => $fieldProperty, 'showContextDependentFirstEntry' => true, 'defaultValue' => $defaultValue));
            }
            else
            {
                $arrListValues = $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_value_list');
                $defaultValue  = $inventory->getValue($field->getValue('inf_name_intern'), 'database');
                // TODO fix parameters
                $form->addSelectBox(
                    'inf-'. $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_id'),
                    $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_name'),
                    $arrListValues, $fieldProperty, $defaultValue, true, $helpId, null,
                    $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_icon', 'database')
                );
            }

        }
        elseif($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_type') === 'RADIO_BUTTON')
        {
            $arrListValues        = $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_value_list');
            $showDummyRadioButton = false;

            if($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_mandatory') == 0)
            {
                $showDummyRadioButton = true;
            }

            // TODO fix parameters
            $form->addRadioButton(
                'inf-'.$gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_id'),
                $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_name'),
                $arrListValues, $fieldProperty,
                $inventory->getValue($field->getValue('inf_name_intern'), 'database'),
                $showDummyRadioButton, $helpId,
                $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_icon', 'database')
            );
        }
        elseif($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_type') === 'TEXT_BIG')
        {
            // TODO fix parameters
            $form->addMultilineTextInput(
                'inf-'. $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_id'),
                $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_name'),
                $inventory->getValue($field->getValue('inf_name_intern')),
                3, 4000, $fieldProperty, $helpId,
                $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_icon', 'database')
            );
        }
        else
        {
            $fieldType = 'text';

            if($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_type') === 'DATE')
            {
                $fieldType = 'date';
                $maxlength = '10';
            }
            elseif($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_type') === 'EMAIL')
            {
                // email could not be longer than 254 characters
                $fieldType = 'email';
                $maxlength = '254';
            }
            elseif($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_type') === 'URL')
            {
                // maximal browser compatible url length will be 2000 characters
                $maxlength = '2000';
            }
            elseif($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_type') === 'NUMBER')
            {
                $fieldType = 'number';
                $maxlength = array(0, 9999999999, 1);
            }
            elseif($gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'cat_name_intern') === 'SOCIAL_NETWORKS')
            {
                $maxlength = '255';
            }
            else
            {
                $maxlength = '50';
            }

            $form->addInput('inf-'. $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_id'), $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_name'),
                $inventory->getValue($field->getValue('inf_name_intern')), array('type' => $fieldType, 'maxlength' => $maxlength, 'property' => $fieldProperty, 'helpTextIdInline' => $helpId, 'class' => $gInventoryFields->getProperty($field->getValue('inf_name_intern'), 'inf_icon')));
        }

}

// div-Container admGroupBoxBody und admGroupBox schliessen
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_URL.'/icons/disk.png'));

if($getNewItem == 0)
{
    // show information about inventory who creates the recordset and changed it
    $form->addHtml(admFuncShowCreateChangeInfoById($inventory->getValue('inv_usr_id_create'), $inventory->getValue('inv_timestamp_create'), $inventory->getValue('inv_usr_id_change'), $inventory->getValue('inv_timestamp_change')));
}

$page->addHtml($form->show(false));
$page->show();
