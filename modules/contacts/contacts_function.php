<?php
/**
 ***********************************************************************************************
 * Various contact functions
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * mode: remove - remove contact ONLY from the member community
 *       delete - delete contact from database
 *       delete_explain_msg - MessageBox that explains the effects of the deletion
 *       send_login - send contact e-mail with new access data
 * user_uuid : UUID of the contact, who should be edited
 * user_uuids: Array of UUIDs of the contacts, who should be edited
 * uuids: Array of UUIDs of the contacts, who should be edited (retrieved from JS-Function callUrlHideElements)
 *
 *****************************************************************************/
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Role;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('delete_explain_msg', 'remove', 'delete', 'send_login')));
    $showFormerButton = admFuncVariableIsValid($_GET, 'show_former_button', 'bool', array('defaultValue' => true));
    $getUserUuids = admFuncVariableIsValid($_GET, 'user_uuids', 'array', array('defaultValue' => array()));
    if (empty($getUserUuids)) {
        $getUserUuids = admFuncVariableIsValid($_POST, 'uuids', 'array', array('defaultValue' => array()));
        $getUserUuids = array_map(function($uuid) {
            return preg_replace('/row_members_/', '', $uuid);
        }, $getUserUuids);

        if (empty($getUserUuids)) {
            $getUserUuids = array(admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('requireValue' => true)));
        }
    }

    // Only users with user-edit rights are allowed
    if (!$gCurrentUser->isAdministratorUsers()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if ($getMode !== 'delete_explain_msg') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
    }

    if ($getMode === 'delete_explain_msg') {
        if (count($getUserUuids) > 1) {
            $formerOnClick = 'callUrlHideElements(\'row_members_\', [\'' . implode('\', \'', $getUserUuids) . '\'], \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('mode' => 'remove')) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')';
            $deleteOnClick = 'callUrlHideElements(\'row_members_\', [\'' . implode('\', \'', $getUserUuids) . '\'], \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('mode' => 'delete')) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')';
            $headerMsg = $gL10n->get('SYS_REMOVE_CONTACT_SELECTION');
            $formerMsg = $gL10n->get('SYS_MAKE_FORMER_SELECTION');
            $removeMsg = $gL10n->get('SYS_REMOVE_CONTACT_SELECTION_DESC', array($gL10n->get('SYS_DELETE_SELECTION')));
            $formerButtonText = $gL10n->get('SYS_FORMER_SELECTION');
            $deleteButtonText = $gL10n->get('SYS_DELETE_SELECTION');
        } else {
            $formerOnClick = 'callUrlHideElement(\'row_members_' . implode('\', \'', $getUserUuids) . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('mode' => 'remove', 'user_uuid' => $getUserUuids[0])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')';
            $deleteOnClick = 'callUrlHideElement(\'row_members_' . implode('\', \'', $getUserUuids) . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('mode' => 'delete', 'user_uuid' => $getUserUuids[0])) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')';
            $headerMsg = $gL10n->get('SYS_REMOVE_CONTACT');
            $formerMsg = $gL10n->get('SYS_MAKE_FORMER');
            $removeMsg = $gL10n->get('SYS_REMOVE_CONTACT_DESC', array($gL10n->get('SYS_DELETE')));
            $formerButtonText = $gL10n->get('SYS_FORMER');
            $deleteButtonText = $gL10n->get('SYS_DELETE');
        }

        // ask if contact/contact-selection should only be removed from organization or completely deleted
        echo '
            <div class="modal-header">
                <h3 class="modal-title">' . $headerMsg . '</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">';
        if ($showFormerButton) {
            echo '<p><i class="bi bi-person-fill-dash"></i>&nbsp;' . $formerMsg . '</p>';
        }
        echo '<p><i class="bi bi-trash"></i>&nbsp;' . $removeMsg . '</p>
            </div>
            <div class="modal-footer">';
        if ($showFormerButton) {
            echo '<button id="adm_button_former" type="button" class="btn btn-primary mr-4" onclick="' . $formerOnClick . '">
                    <i class="bi bi-person-fill-dash"></i>' . $formerButtonText . '</button>';
        }
        echo '<button id="adm_button_delete" type="button" class="btn btn-primary" onclick="' . $deleteOnClick . '">
                    <i class="bi bi-trash"></i>' . $deleteButtonText . '</button>
                <div id="adm_status_message" class="mt-4 w-100"></div>
            </div>';
        exit();
    }

    $statusData = array();
    $error = false;
    foreach ($getUserUuids as $userUuid) {
        // Create user-object
        $user = new User($gDb, $gProfileFields);
        $user->readDataByUuid($userUuid);

        $statusMsg = '';

        if ($getMode === 'delete') {
            // Check if user is also in other organizations
            $sql = 'SELECT COUNT(*) AS count
                FROM ' . TBL_MEMBERS . '
            INNER JOIN ' . TBL_ROLES . '
                    ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                WHERE rol_valid   = true
                AND cat_org_id <> ? -- $gCurrentOrgId
                AND mem_begin  <= ? -- DATE_NOW
                AND mem_end     > ? -- DATE_NOW
                AND mem_usr_id  = ? -- $user->getValue(\'usr_id\')';
            $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, DATE_NOW, DATE_NOW, $user->getValue('usr_id')));
            $isAlsoInOtherOrgas = $pdoStatement->fetchColumn() > 0;
        }

        if ($getMode === 'remove') {
            // User has to be a member of this organization
            // User could not delete himself
            // Administrators could not be deleted
            if (!isMember($user->getValue('usr_id')) || $gCurrentUserId === (int)$user->getValue('usr_id')
                || (!$gCurrentUser->isAdministrator() && $user->isAdministrator())) {
                $error = true;
                $statusData[$userUuid] = 'error';
                continue;
            }

            $sql = 'SELECT mem_id, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_leader
                    FROM ' . TBL_MEMBERS . '
                INNER JOIN ' . TBL_ROLES . '
                        ON rol_id = mem_rol_id
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                    WHERE rol_valid  = true
                    AND (  cat_org_id = ? -- $gCurrentOrgId
                        OR cat_org_id IS NULL )
                    AND mem_begin <= ? -- DATE_NOW
                    AND mem_end    > ? -- DATE_NOW
                    AND mem_usr_id = ? -- $user->getValue(\'usr_id\')';
            $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, DATE_NOW, DATE_NOW, $user->getValue('usr_id')));

            while ($row = $pdoStatement->fetch()) {
                // stop all role memberships of this organization
                $role = new Role($gDb, $row['mem_rol_id']);
                $role->stopMembership($row['mem_usr_id']);
            }

            $statusMsg = (count($getUserUuids) === 1) ? $gL10n->get('SYS_END_MEMBERSHIP_OF_USER_OK', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname'))) : $gL10n->get('SYS_END_MEMBERSHIP_OF_USERS_OK', array($gCurrentOrganization->getValue('org_longname')));
            $statusData[$userUuid] = 'success';
        } elseif ($getMode === 'delete') {
            // User must not be in any other organization
            // User could not delete himself
            // Only administrators are allowed to do this
            if ($isAlsoInOtherOrgas || $gCurrentUserId === (int)$user->getValue('usr_id') || !$gCurrentUser->isAdministrator()) {
                $error = true;
                $statusData[$userUuid] = 'error';
                continue;
            }
            // Delete user from database
            $user->delete();
            
            $statusMsg = $gL10n->get('SYS_DELETE_DATA');
            $statusData[$userUuid] = 'success';
        } elseif ($getMode === 'send_login') {
            // If User must be member of this organization than send a new password
            if (isMember($user->getValue('usr_id'))) {
                $user->sendNewPassword();

                $statusMsg = $gL10n->get('SYS_EMAIL_SEND');
            } else {
                throw new Exception('SYS_NO_RIGHTS');
            }
        }
    }

    if ($statusMsg !== '' && !$error) {
        echo json_encode(array('status' => 'success', 'message' => $statusMsg));
    } else {
        echo json_encode(array('status' => 'warning', 'statusData' => $statusData, 'message' => $gL10n->get('SYS_NO_RIGHTS')));
    }
} catch (Throwable $e) {
    if ($getMode == 'delete_explain_msg') {
        $gMessage->showInModalWindow();
        $gMessage->show($e->getMessage());
    } else {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    }
}
