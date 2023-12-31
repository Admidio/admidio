<?php
/**
 ***********************************************************************************************
 * Various functions for profile fields
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * uuid     : UUID of the profile field that should be edited
 * mode     : edit     - create or edit profile field
 *            delete   - delete profile field
 *            sequence - change sequence of profile field
 * direction : Direction to change the sequence of the category
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$postUsfUUID  = admFuncVariableIsValid($_POST, 'uuid', 'string');
$postMode     = admFuncVariableIsValid($_POST, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'sequence')));
$postOrder    = admFuncVariableIsValid($_POST, 'order', 'array');

if (in_array($postMode, array('delete', 'sequence'))) {
    $gMessage->showHtmlTextOnly();
}

if ($postMode !== 'sequence' || empty($postOrder)) {
    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        if ($postMode === 'edit') {
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

if ($postUsfUUID !== '') {
    $userField->readDataByUuid($postUsfUUID);

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

if ($postMode === 'edit') {
    // Create or edit profile field

    $_SESSION['fields_request'] = $_POST;

    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        $exception->showHtml();
        // => EXIT
    }

    // Check if mandatory fields are filled
    // (in case of system fields, these fields must not be changed)
    if ($userField->getValue('usf_system') == 0 && $_POST['usf_name'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }

    if ($userField->getValue('usf_system') == 0 && $_POST['usf_type'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_DATATYPE'))));
        // => EXIT
    }

    if ($userField->getValue('usf_system') == 0 && strlen($_POST['usf_cat_id']) === 0) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_CATEGORY'))));
        // => EXIT
    }

    if (($_POST['usf_type'] === 'DROPDOWN' || $_POST['usf_type'] === 'RADIO_BUTTON')
    && $_POST['usf_value_list'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_VALUE_LIST'))));
        // => EXIT
    }

    if ($_POST['usf_url'] !== '' && !StringUtils::strValidCharacters($_POST['usf_url'], 'url')) {
        $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', array($gL10n->get('SYS_URL'))));
        // => EXIT
    }

    if ($_POST['usf_default_value'] !== '' && $_POST['usf_default_value'] !== $userField->getValue('usf_default_value')) {
        try {
            $profileFields = new ProfileFields($gDb, $gCurrentOrgId);
            $profileFields->setValue($userField->getValue('usf_name_intern'), $_POST['usf_default_value']);
        } catch (AdmException $e) {
            $e->showHtml();
        }
    }

    // lastname and firstname must always be mandatory fields and visible in registration dialog
    if ($userField->getValue('usf_name_intern') === 'LAST_NAME'
    || $userField->getValue('usf_name_intern') === 'FIRST_NAME') {
        $_POST['usf_required_input'] = 1;
        $_POST['usf_registration'] = 1;
    }

    // email must always be visible in registration dialog
    if ($userField->getValue('usf_name_intern') === 'EMAIL') {
        $_POST['usf_registration'] = 1;
    }

    if (isset($_POST['usf_name']) && $userField->getValue('usf_name') !== $_POST['usf_name']) {
        // See if the field already exists
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_USER_FIELDS.'
                 WHERE usf_name   = ? -- $_POST[\'usf_name\']
                   AND usf_cat_id = ? -- $_POST[\'usf_cat_id\']
                   AND usf_uuid  <> ? -- $postUsfUUID';
        $pdoStatement = $gDb->queryPrepared($sql, array($_POST['usf_name'], (int) $_POST['usf_cat_id'], $postUsfUUID));

        if ($pdoStatement->fetchColumn() > 0) {
            $gMessage->show($gL10n->get('ORG_FIELD_EXIST'));
            // => EXIT
        }
    }

    // Swap input, because the field name is different from the dialog
    if (isset($_POST['usf_hidden'])) {
        $_POST['usf_hidden'] = 0;
    } else {
        $_POST['usf_hidden'] = 1;
    }
    if (!isset($_POST['usf_disabled'])) {
        $_POST['usf_disabled'] = 0;
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

        $userField->save();
    } catch (AdmException $e) {
        $e->showHtml();
    }

    $gNavigation->deleteLastUrl();
    unset($_SESSION['fields_request']);

    // return to the members management settings
    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
// => EXIT
} elseif ($postMode === 'delete') {
    // delete profile field

    if ($userField->getValue('usf_system') == 1) {
        // System fields must not be deleted
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    if ($userField->delete()) {
        // Delete successful -> return for XMLHttpRequest
        echo 'done';
    }
    exit();
} elseif ($postMode === 'sequence') {
    // update field order
    $postSequence = admFuncVariableIsValid($_POST, 'direction', 'string', array('requireValue' => true, 'validValues' => array(TableUserField::MOVE_UP, TableUserField::MOVE_DOWN)));

    try {
        if (!empty($postOrder)) {
            // set new order (drag and drop)
            $userField->setSequence($postOrder);
        } else {
            // move field up/down by one
            if ($userField->moveSequence($postSequence)) {
                echo 'done';
            } else {
                echo 'Sequence could not be changed.';
            }
        }
    } catch (AdmException $e) {
        echo $e->getMessage();
    }
    exit();
}
