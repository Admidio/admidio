<?php
/******************************************************************************
 * Various functions for item fields
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * inf_id   : item field id
 * mode     : 1 - create or edit item field
 *            2 - delete item field
 *            4 - change sequence of item field
 * sequence : new sequence for item field
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getInfId    = admFuncVariableIsValid($_GET, 'inf_id', 'numeric');
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('requireValue' => true));
$getSequence = admFuncVariableIsValid($_GET, 'sequence', 'string', array('validValues' => array('UP', 'DOWN')));

// only users with the right to edit inventory could use this script
if ($gCurrentUser->editInventory() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// create item field object
$itemField = new TableInventoryField($gDb);

if($getInfId > 0)
{
    $itemField->readDataById($getInfId);

    // check if profile field belongs to actual organization
    if($itemField->getValue('cat_org_id') >  0
    && $itemField->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // if system profile field then set usf_type to default
    if($itemField->getValue('inf_system') == 1)
    {
        $_POST['inf_type'] = $itemField->getValue('inf_type');
    }
}

if($getMode == 1)
{
   // Feld anlegen oder updaten

    $_SESSION['fields_request'] = $_POST;

    // pruefen, ob Pflichtfelder gefuellt sind
    // (bei Systemfeldern duerfen diese Felder nicht veraendert werden)
    if($itemField->getValue('inf_system') == 0 && strlen($_POST['inf_name']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
    }

    if($itemField->getValue('inf_system') == 0 && strlen($_POST['inf_type']) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_DATATYPE')));
    }

    if($itemField->getValue('inf_system') == 0 && $_POST['inf_cat_id'] == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_CATEGORY')));
    }

    if(isset($_POST['inf_name']) && $itemField->getValue('inf_name') != $_POST['inf_name'])
    {
        // Schauen, ob das Feld bereits existiert
        $sql    = 'SELECT COUNT(*) as count
                     FROM '. TBL_INVENT_FIELDS. '
                    WHERE inf_name LIKE \''.$_POST['inf_name'].'\'
                      AND inf_cat_id  = '.$_POST['inf_cat_id'].'
                      AND inf_id     <> '.$getInfId;
        $result = $gDb->query($sql);
        $row    = $gDb->fetch_array($result);

        if($row['count'] > 0)
        {
            $gMessage->show($gL10n->get('ORG_FIELD_EXIST'));
        }
    }

    // Eingabe verdrehen, da der Feldname anders als im Dialog ist
    if(isset($_POST['inf_hidden']))
    {
        $_POST['inf_hidden'] = 0;
    }
    else
    {
        $_POST['inf_hidden'] = 1;
    }
    if(isset($_POST['inf_disabled']) == false)
    {
        $_POST['inf_disabled'] = 0;
    }
    if(isset($_POST['inf_mandatory']) == false)
    {
        $_POST['inf_mandatory'] = 0;
    }

    // make html in description secure
    $_POST['inf_description'] = admFuncVariableIsValid($_POST, 'inf_description', 'html');

    // POST Variablen in das itemField-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'inf_') === 0)
        {
            $itemField->setValue($key, $value);
        }
    }

    // Daten in Datenbank schreiben
    $return_code = $itemField->save();

    if($return_code < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    $gNavigation->deleteLastUrl();
    unset($_SESSION['fields_request']);

    // zu den Organisationseinstellungen zurueck
    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}
elseif($getMode == 2)
{
    if($itemField->getValue('inf_system') == 1)
    {
        // Systemfelder duerfen nicht geloescht werden
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    // Feld loeschen
    if($itemField->delete())
    {
        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
    exit();
}
elseif($getMode == 4)
{
    // Feldreihenfolge aktualisieren
    $itemField->moveSequence($getSequence);
    exit();
}
