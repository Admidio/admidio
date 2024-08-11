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

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getUsfUUID = admFuncVariableIsValid($_GET, 'uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'sequence')));
    $getOrder = admFuncVariableIsValid($_GET, 'order', 'array');

    // only authorized users can edit the profile fields
    if (!$gCurrentUser->isAdministrator()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    // create user field object
    $userField = new TableUserField($gDb);

    if ($getUsfUUID !== '') {
        $userField->readDataByUuid($getUsfUUID);

        // check if profile field belongs to actual organization
        if ($userField->getValue('cat_org_id') > 0
            && (int)$userField->getValue('cat_org_id') !== $gCurrentOrgId) {
            throw new AdmException('SYS_NO_RIGHTS');
        }

        // if system profile field then set usf_type to default
        if ($userField->getValue('usf_system') == 1) {
            $_POST['usf_type'] = $userField->getValue('usf_type');
        }
    }

    if ($getMode === 'edit') {
        // Create or edit profile field

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

        // Swap input, because the field name is different from the dialog
        if (isset($_POST['usf_hidden'])) {
            $_POST['usf_hidden'] = 0;
        } else {
            $_POST['usf_hidden'] = 1;
        }

        // check form field input and sanitized it from malicious content
        if (isset($_SESSION['profileFieldsEditForm'])) {
            $profileFieldsEditForm = $_SESSION['profileFieldsEditForm'];
            $formValues = $profileFieldsEditForm->validate($_POST);
        } else {
            throw new AdmException('SYS_INVALID_PAGE_VIEW');
        }

        if (isset($_POST['usf_name']) && $userField->getValue('usf_name') !== $_POST['usf_name']) {
            // See if the field already exists
            $sql = 'SELECT COUNT(*) AS count
                  FROM ' . TBL_USER_FIELDS . '
                 WHERE usf_name   = ? -- $_POST[\'usf_name\']
                   AND usf_cat_id = ? -- $_POST[\'usf_cat_id\']
                   AND usf_uuid  <> ? -- $getUsfUUID';
            $pdoStatement = $gDb->queryPrepared($sql, array($_POST['usf_name'], (int)$_POST['usf_cat_id'], $getUsfUUID));

            if ($pdoStatement->fetchColumn() > 0) {
                throw new AdmException('ORG_FIELD_EXIST');
            }
        }

        // write form values in user field object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'usf_')) {
                $userField->setValue($key, $value);
            }
        }

        $userField->save();

        $gNavigation->deleteLastUrl();
        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } elseif ($getMode === 'delete') {
        // delete profile field

        if ($userField->getValue('usf_system') == 1) {
            // System fields must not be deleted
            throw new AdmException('SYS_INVALID_PAGE_VIEW');
        }

        $userField->delete();

        echo json_encode(array('status' => 'success'));
        exit();
    } elseif ($getMode === 'sequence') {
        // update field order
        $postSequence = admFuncVariableIsValid($_POST, 'direction', 'string', array('validValues' => array(TableUserField::MOVE_UP, TableUserField::MOVE_DOWN)));

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

        if (!empty($getOrder)) {
            // set new order (drag and drop)
            $userField->setSequence(explode(',', $getOrder));
        } else {
            // move field up/down by one
            if ($userField->moveSequence($postSequence)) {
                echo 'done';
            } else {
                throw new AdmException('Sequence could not be changed.');
            }
        }
        exit();
    }
} catch (AdmException|Exception $e) {
    if ($getMode === 'sequence') {
        echo $e->getMessage();
    } else {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    }
}
