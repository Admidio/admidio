<?php
/**
 ***********************************************************************************************
 * Various functions for profile fields
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
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

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUsfId    = admFuncVariableIsValid($_GET, 'usf_id',   'int');
$getMode     = admFuncVariableIsValid($_GET, 'mode',     'int',    array('requireValue' => true));
$getSequence = admFuncVariableIsValid($_GET, 'sequence', 'string', array('validValues' => array(TableUserField::MOVE_UP, TableUserField::MOVE_DOWN)));

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// create user field object
$userField = new TableUserField($gDb);

if($getUsfId > 0)
{
    $userField->readDataById($getUsfId);

    // check if profile field belongs to actual organization
    if($userField->getValue('cat_org_id') > 0
    && (int) $userField->getValue('cat_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
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

    // Check if mandatory fields are filled
    // (bei Systemfeldern duerfen diese Felder nicht veraendert werden)
    if($userField->getValue('usf_system') == 0 && $_POST['usf_name'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }

    if($userField->getValue('usf_system') == 0 && $_POST['usf_type'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_DATATYPE'))));
        // => EXIT
    }

    if($userField->getValue('usf_system') == 0 && (int) $_POST['usf_cat_id'] === 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_CATEGORY'))));
        // => EXIT
    }

    if(($_POST['usf_type'] === 'DROPDOWN' || $_POST['usf_type'] === 'RADIO_BUTTON')
    && $_POST['usf_value_list'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_VALUE_LIST'))));
        // => EXIT
    }

    if($_POST['usf_icon'] !== '' && StringUtils::strStartsWith($_POST['usf_icon'], 'http') && !strValidCharacters($_POST['usf_icon'], 'url'))
    {
        $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', array($gL10n->get('SYS_ICON'))));
        // => EXIT
    }
    elseif($_POST['usf_icon'] !== '' && !StringUtils::strStartsWith($_POST['usf_icon'], 'http'))
    {
        try
        {
            admStrIsValidFileName($_POST['usf_icon']);
        }
        catch (AdmException $e)
        {
            $e->showHtml();
            // => EXIT
        }
    }

    if($_POST['usf_url'] !== '' && !strValidCharacters($_POST['usf_url'], 'url'))
    {
        $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', array($gL10n->get('ORG_URL'))));
        // => EXIT
    }

    // lastname and firstname must always be mandatory fields and visible in registration dialog
    if($userField->getValue('usf_name_intern') === 'LAST_NAME'
    || $userField->getValue('usf_name_intern') === 'FIRST_NAME')
    {
        $_POST['usf_mandatory'] = 1;
        $_POST['usf_registration'] = 1;
    }

    // email must always be visible in registration dialog
    if($userField->getValue('usf_name_intern') === 'EMAIL')
    {
        $_POST['usf_registration'] = 1;
    }

    if(isset($_POST['usf_name']) && $userField->getValue('usf_name') !== $_POST['usf_name'])
    {
        // Schauen, ob das Feld bereits existiert
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_USER_FIELDS.'
                 WHERE usf_name   = ? -- $_POST[\'usf_name\']
                   AND usf_cat_id = ? -- $_POST[\'usf_cat_id\']
                   AND usf_id    <> ? -- $getUsfId';
        $pdoStatement = $gDb->queryPrepared($sql, array($_POST['usf_name'], (int) $_POST['usf_cat_id'], $getUsfId));

        if($pdoStatement->fetchColumn() > 0)
        {
            $gMessage->show($gL10n->get('ORG_FIELD_EXIST'));
            // => EXIT
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
    if(!isset($_POST['usf_registration']))
    {
        $_POST['usf_registration'] = 0;
    }

    // make html in description secure
    $_POST['usf_description'] = admFuncVariableIsValid($_POST, 'usf_description', 'html');

    try
    {
        // POST Variablen in das UserField-Objekt schreiben
        foreach($_POST as $key => $value)
        {
            if(StringUtils::strStartsWith($key, 'usf_')) // TODO possible security issue
            {
                $userField->setValue($key, $value);
            }
        }
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }

    // Daten in Datenbank schreiben
    $returnCode = $userField->save();

    if($returnCode < 0)
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
    if($userField->getValue('usf_system') == 1)
    {
        // Systemfelder duerfen nicht geloescht werden
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
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
