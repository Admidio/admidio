<?php
/**
 ***********************************************************************************************
 * Various functions for profile fields
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * usf_id   : profile field id
 * mode     : 1 - create or edit profile field
 *            2 - delete profile field
 *            4 - change sequence of profile field
 * sequence : new sequence für profile field
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getUsfId    = admFuncVariableIsValid($_GET, 'usf_id',   'int');
$getMode     = admFuncVariableIsValid($_GET, 'mode',     'int',    array('requireValue' => true));
$getSequence = admFuncVariableIsValid($_GET, 'sequence', 'string', array('validValues' => array('UP', 'DOWN')));

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isWebmaster())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// create user field object
$userField = new TableUserField($gDb);

if($getUsfId > 0)
{
    $userField->readDataById($getUsfId);

    // check if profile field belongs to actual organization
    if($userField->getValue('cat_org_id') >  0
    && $userField->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // if system profile field then set usf_type to default
    if($userField->getValue('usf_system') == 1)
    {
        $_POST['usf_type'] = $userField->getValue('usf_type');
    }
}

if($getMode === 1)
{
    // Feld anlegen oder updaten

    $_SESSION['fields_request'] = $_POST;

    // pruefen, ob Pflichtfelder gefuellt sind
    // (bei Systemfeldern duerfen diese Felder nicht veraendert werden)
    if($userField->getValue('usf_system') == 0 && $_POST['usf_name'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
    }

    if($userField->getValue('usf_system') == 0 && $_POST['usf_type'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_DATATYPE')));
    }

    if($userField->getValue('usf_system') == 0 && $_POST['usf_cat_id'] == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_CATEGORY')));
    }

    if(($_POST['usf_type'] === 'DROPDOWN' || $_POST['usf_type'] === 'RADIO_BUTTON')
    && $_POST['usf_value_list'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ORG_VALUE_LIST')));
    }

    // Nachname und Vorname sollen immer Pflichtfeld bleiben
    if($userField->getValue('usf_name_intern') === 'LAST_NAME'
    || $userField->getValue('usf_name_intern') === 'FIRST_NAME')
    {
        $_POST['usf_mandatory'] = 1;
    }

    if(isset($_POST['usf_name']) && $userField->getValue('usf_name') !== $_POST['usf_name'])
    {
        // Schauen, ob das Feld bereits existiert
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_USER_FIELDS.'
                 WHERE usf_name LIKE \''.$_POST['usf_name'].'\'
                   AND usf_cat_id  = '.$_POST['usf_cat_id'].'
                   AND usf_id     <> '.$getUsfId;
        $statement = $gDb->query($sql);
        $row = $statement->fetch();

        if($row['count'] > 0)
        {
            $gMessage->show($gL10n->get('ORG_FIELD_EXIST'));
        }
    }

    // Eingabe verdrehen, da der Feldname anders als im Dialog ist
    if(isset($_POST['usf_hidden']))
    {
        $_POST['usf_hidden'] = 0;
    }
    else
    {
        $_POST['usf_hidden'] = 1;
    }
    if(!isset($_POST['usf_disabled']))
    {
        $_POST['usf_disabled'] = 0;
    }
    if(!isset($_POST['usf_mandatory']))
    {
        $_POST['usf_mandatory'] = 0;
    }

    // make html in description secure
    $_POST['usf_description'] = admFuncVariableIsValid($_POST, 'usf_description', 'html');

    // POST Variablen in das UserField-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'usf_') === 0)
        {
            if(!$userField->setValue($key, $value))
            {
                // Daten wurden nicht uebernommen, Hinweis ausgeben
                if($key === 'usf_url')
                {
                    $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', $gL10n->get('ORG_URL')));
                }
            }
        }
    }

    // Daten in Datenbank schreiben
    $return_code = $userField->save();

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
elseif($getMode === 2)
{
    if($userField->getValue('usf_system') == 1)
    {
        // Systemfelder duerfen nicht geloescht werden
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    // Feld loeschen
    if($userField->delete())
    {
        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
    exit();
}
elseif($getMode === 4)
{
    // Feldreihenfolge aktualisieren
    $userField->moveSequence($getSequence);
    exit();
}
