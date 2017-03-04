<?php
/**
 ***********************************************************************************************
 * Various functions for item fields
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * inf_id   : item field id
 * mode     : 1 - create or edit item field
 *            2 - delete item field
 *            4 - change sequence of item field
 * sequence : new sequence for item field
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getInfId    = admFuncVariableIsValid($_GET, 'inf_id',   'int');
$getMode     = admFuncVariableIsValid($_GET, 'mode',     'int',    array('requireValue' => true));
$getSequence = admFuncVariableIsValid($_GET, 'sequence', 'string', array('validValues' => array('UP', 'DOWN')));

// only users with the right to edit inventory could use this script
if (!$gCurrentUser->editInventory())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// create item field object
$itemField = new TableInventoryField($gDb);

if($getInfId > 0)
{
    $itemField->readDataById($getInfId);

    // check if profile field belongs to actual organization
    if($itemField->getValue('cat_org_id') > 0
    && (int) $itemField->getValue('cat_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // if system profile field then set usf_type to default
    if($itemField->getValue('inf_system') == 1)
    {
        $_POST['inf_type'] = $itemField->getValue('inf_type');
    }
}

if($getMode === 1)
{
    // Feld anlegen oder updaten

    $_SESSION['fields_request'] = $_POST;

    // pruefen, ob Pflichtfelder gefuellt sind
    // (bei Systemfeldern duerfen diese Felder nicht veraendert werden)
    if($itemField->getValue('inf_system') == 0 && strlen($_POST['inf_name']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
        // => EXIT
    }

    if($itemField->getValue('inf_system') == 0 && strlen($_POST['inf_type']) === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_DATATYPE')));
        // => EXIT
    }

    if($itemField->getValue('inf_system') == 0 && (int) $_POST['inf_cat_id'] === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_CATEGORY')));
        // => EXIT
    }

    if(isset($_POST['inf_name']) && $itemField->getValue('inf_name') !== $_POST['inf_name'])
    {
        // Schauen, ob das Feld bereits existiert
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_INVENT_FIELDS.'
                 WHERE inf_name LIKE \''.$_POST['inf_name'].'\'
                   AND inf_cat_id  = '.(int) $_POST['inf_cat_id'].'
                   AND inf_id     <> '.$getInfId;
        $statement = $gDb->query($sql);

        if($statement->fetchColumn() > 0)
        {
            $gMessage->show($gL10n->get('ORG_FIELD_EXIST'));
            // => EXIT
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
    if(!isset($_POST['inf_disabled']))
    {
        $_POST['inf_disabled'] = 0;
    }
    if(!isset($_POST['inf_mandatory']))
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
        // => EXIT
    }

    $gNavigation->deleteLastUrl();
    unset($_SESSION['fields_request']);

    // zu den Organisationseinstellungen zurueck
    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
    // => EXIT
}
elseif($getMode === 2)
{
    if($itemField->getValue('inf_system') == 1)
    {
        // Systemfelder duerfen nicht geloescht werden
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    // Feld loeschen
    if($itemField->delete())
    {
        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
    exit();
}
elseif($getMode === 4)
{
    // Feldreihenfolge aktualisieren
    $itemField->moveSequence($getSequence);
    exit();
}
