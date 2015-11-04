<?php
/******************************************************************************
 * Save item data
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * item_id    : ID of the item, that should be edited
 * new_item   : 0 - Edit item with the given item id
 *              1 - Create a new item
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getItemId  = admFuncVariableIsValid($_GET, 'item_id', 'numeric');
$getNewItem = admFuncVariableIsValid($_GET, 'new_item', 'numeric');

// only users with the right to edit inventory could use this script
if ($gCurrentUser->editInventory() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// save form data in session for back navigation
$_SESSION['profile_request'] = $_POST;

if(!isset($_POST['reg_org_id']))
{
    $_POST['reg_org_id'] = $gCurrentOrganization->getValue('org_id');
}

// read item data
$gInventoryFields = new InventoryFields($gDb, $gCurrentOrganization->getValue('org_id'));
$inventory = new Inventory($gDb, $gInventoryFields, $getItemId);

// pruefen, ob Modul aufgerufen werden darf
switch($getNewItem)
{
    case 0:
        // prueft, ob der User die notwendigen Rechte hat, das entsprechende Item zu aendern
        if($gCurrentUser->editInventory($inventory) == false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
        break;

    case 1:
        // prueft, ob der User die notwendigen Rechte hat, neue Items anzulegen
        if($gCurrentUser->editInventory() == false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
        break;
}

/*------------------------------------------------------------*/
// Feldinhalte pruefen der User-Klasse zuordnen
/*------------------------------------------------------------*/

// nun alle Profilfelder pruefen
foreach($gInventoryFields->mInventoryFields as $field)
{
    $post_id = 'inf-'. $field->getValue('inf_id');

    // check and save only fields that aren't disabled
    if($gCurrentUser->editUsers() == true || $field->getValue('inf_disabled') == 0 || ($field->getValue('inf_disabled') == 1 && $getNewItem > 0))
    {
        if(isset($_POST[$post_id]))
        {
            // Pflichtfelder muessen gefuellt sein
            // E-Mail bei Registrierung immer !!!
            if($field->getValue('inf_mandatory') == 1 && strlen($_POST[$post_id]) == 0)
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $field->getValue('inf_name')));
            }

            // Wert aus Feld in das User-Klassenobjekt schreiben
            $returnCode = $inventory->setValue($field->getValue('inf_name_intern'), $_POST[$post_id]);

            // Ausgabe der Fehlermeldung je nach Datentyp
            if($returnCode == false)
            {
                if($field->getValue('inf_type') == 'CHECKBOX')
                {
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                }
                elseif($field->getValue('inf_type') == 'DATE')
                {
                    $gMessage->show($gL10n->get('SYS_DATE_INVALID', $field->getValue('inf_name'), $gPreferences['system_date']));
                }
                elseif($field->getValue('inf_type') == 'EMAIL')
                {
                    $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $field->getValue('inf_name')));
                }
                elseif($field->getValue('inf_type') == 'NUMBER' || $field->getValue('inf_type') == 'DECIMAL')
                {
                    $gMessage->show($gL10n->get('PRO_FIELD_NUMERIC', $field->getValue('inf_name')));
                }
                elseif($field->getValue('inf_type') == 'URL')
                {
                    $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', $field->getValue('inf_name')));
                }
            }
        }
        else
        {
            // Checkboxen uebergeben bei 0 keinen Wert, deshalb diesen hier setzen
            if($field->getValue('inf_type') == 'CHECKBOX')
            {
                $inventory->setValue($field->getValue('inf_name_intern'), '0');
            }
            elseif($field->getValue('inf_mandatory') == 1)
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $field->getValue('inf_name')));
            }
        }
    }
}

/*------------------------------------------------------------*/
// Benutzerdaten in Datenbank schreiben
/*------------------------------------------------------------*/
$gDb->startTransaction();

try
{
    // save changes; if it's a new registration than caught exception if email couldn't send

    if($inventory->getValue('inv_id') == 0)
    {
        // der User wird gerade angelegt und die ID kann erst danach in das Create-Feld gesetzt werden
        $inventory->save();

        if($getNewItem == 1)
        {
            $inventory->setValue('inv_usr_id_create', $gCurrentUser->getValue('inv_id'));
        }
        else
        {
            $inventory->setValue('inv_usr_id_create', $inventory->getValue('inv_id'));
        }
    }

    $ret_code = $inventory->save();
}
catch(AdmException $e)
{
    unset($_SESSION['profile_request']);
    $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
    $gNavigation->deleteLastUrl();
    $e->showHtml();
}

$gDb->endTransaction();

unset($_SESSION['profile_request']);
$gNavigation->deleteLastUrl();

/*------------------------------------------------------------*/
// je nach Aufrufmodus auf die richtige Seite weiterleiten
/*------------------------------------------------------------*/

if($getNewItem == 1)
{
    $gMessage->setForwardUrl($gNavigation->getPreviousUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}
else
{
    // go back to profile view
    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}
