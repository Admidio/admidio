<?php
/**
 ***********************************************************************************************
 * Various functions for profile fields
 *
 * @copyright 2004-2022 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * usf_uuid : UUID of the profile field that should be edited
 * mode     : 1 - create or edit profile field
 *            2 - delete profile field
 *            4 - change sequence of profile field
 * sequence : mode if the profile field move up or down, values are TableUserField::MOVE_UP, TableUserField::MOVE_DOWN
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUsfUuid  = admFuncVariableIsValid($_GET, 'usf_uuid', 'string');
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'int', array('requireValue' => true));
$getSequence = admFuncVariableIsValid($_GET, 'sequence', 'string', array('validValues' => array(TableUserField::MOVE_UP, TableUserField::MOVE_DOWN)));
$getOrder    = admFuncVariableIsValid($_GET, 'order', 'array');

if ($getMode !== 4 || empty($getOrder)) {
    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        if ($getMode === 1) {
            $exception->showHtml();
        } else {
            $exception->showText();
        }
        // => EXIT
    }
}

// only authorized users can edit the profile fields
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// create user field object
$userField = new TableUserField($gDb);

if ($getUsfUuid !== '') {
    $userField->readDataByUuid($getUsfUuid);

    // check if profile field belongs to actual organization
    if ($userField->getValue('cat_org_id') > 0
    && (int) $userField->getValue('cat_org_id') !== $gCurrentOrgId) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // if system profile field then set usf_type to default
    if ($userField->getValue('usf_system') == 1) {
        $_POST['usf_type'] = $userField->getValue('usf_type');
    }
}

if ($getMode === 1) {
    // Feld anlegen oder updaten

    $_SESSION['fields_request'] = $_POST;

    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        $exception->showHtml();
        // => EXIT
    }

    // Check if mandatory fields are filled
    // (bei Systemfeldern duerfen diese Felder nicht veraendert werden)
    if ($userField->getValue('usf_system') == 0 && $_POST['usf_name'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }

    if ($userField->getValue('usf_system') == 0 && $_POST['usf_type'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_DATATYPE'))));
        // => EXIT
    }

    if ($userField->getValue('usf_system') == 0 && (int) $_POST['usf_cat_id'] === 0) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_CATEGORY'))));
        // => EXIT
    }

    if (($_POST['usf_type'] === 'DROPDOWN' || $_POST['usf_type'] === 'RADIO_BUTTON')
    && $_POST['usf_value_list'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_VALUE_LIST'))));
        // => EXIT
    }

    // check if font awesome syntax is used or if its a valid filename syntax
    if ($_POST['usf_icon'] !== '' && !preg_match('/fa-[a-zA-z0-9]/', $_POST['usf_icon'])) {
        try {
            StringUtils::strIsValidFileName($_POST['usf_icon'], true);
        } catch (AdmException $e) {
            $gMessage->show($gL10n->get('SYS_INVALID_FONT_AWESOME'));
            // => EXIT
        }
    }

    if ($_POST['usf_url'] !== '' && !StringUtils::strValidCharacters($_POST['usf_url'], 'url')) {
        $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', array($gL10n->get('ORG_URL'))));
        // => EXIT
    }

    // lastname and firstname must always be mandatory fields and visible in registration dialog
    if ($userField->getValue('usf_name_intern') === 'LAST_NAME'
    || $userField->getValue('usf_name_intern') === 'FIRST_NAME') {
        $_POST['usf_mandatory'] = 1;
        $_POST['usf_registration'] = 1;
    }

    // email must always be visible in registration dialog
    if ($userField->getValue('usf_name_intern') === 'EMAIL') {
        $_POST['usf_registration'] = 1;
    }

    if (isset($_POST['usf_name']) && $userField->getValue('usf_name') !== $_POST['usf_name']) {
        // Schauen, ob das Feld bereits existiert
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_USER_FIELDS.'
                 WHERE usf_name   = ? -- $_POST[\'usf_name\']
                   AND usf_cat_id = ? -- $_POST[\'usf_cat_id\']
                   AND usf_uuid  <> ? -- $getUsfUuid';
        $pdoStatement = $gDb->queryPrepared($sql, array($_POST['usf_name'], (int) $_POST['usf_cat_id'], $getUsfUuid));

        if ($pdoStatement->fetchColumn() > 0) {
            $gMessage->show($gL10n->get('ORG_FIELD_EXIST'));
            // => EXIT
        }
    }

    // Eingabe verdrehen, da der Feldname anders als im Dialog ist
    if (isset($_POST['usf_hidden'])) {
        $_POST['usf_hidden'] = 0;
    } else {
        $_POST['usf_hidden'] = 1;
    }
    if (!isset($_POST['usf_disabled'])) {
        $_POST['usf_disabled'] = 0;
    }
    if (!isset($_POST['usf_mandatory'])) {
        $_POST['usf_mandatory'] = 0;
    }
    if (!isset($_POST['usf_registration'])) {
        $_POST['usf_registration'] = 0;
    }
    if (!isset($_POST['usf_description_inline'])) {
        $_POST['usf_description_inline'] = 0;
    }

    // make html in description secure
    $_POST['usf_description'] = admFuncVariableIsValid($_POST, 'usf_description', 'html');

    try {
        // POST Variablen in das UserField-Objekt schreiben
        foreach ($_POST as $key => $value) {
            if (str_starts_with($key, 'usf_')) { // TODO possible security issue
                $userField->setValue($key, $value);
            }
        }
    } catch (AdmException $e) {
        $e->showHtml();
    }

    $userField->save();

    $gNavigation->deleteLastUrl();
    unset($_SESSION['fields_request']);

    // return to the members management settings
    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
// => EXIT
} elseif ($getMode === 2) {
    if ($userField->getValue('usf_system') == 1) {
        // Systemfelder duerfen nicht geloescht werden
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    // Feld loeschen
    if ($userField->delete()) {
        // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
    exit();
} elseif ($getMode === 4) {
    // update field order

    if (!empty($getOrder)) {
        // set new order (drag'n'drop)
        $userField->setSequence($getOrder);
    } else {
        // move field up/down by one
        if ($userField->moveSequence($getSequence)) {
            echo 'done';
        } else {
            echo 'Sequence could not be changed.';
        }
    }
    exit();
}
