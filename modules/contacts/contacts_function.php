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
 *
 *****************************************************************************/
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('delete_explain_msg', 'remove', 'delete', 'send_login')));
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('requireValue' => true));

    // Only users with user-edit rights are allowed
    if (!$gCurrentUser->isAdministratorUsers()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if ($getMode !== 'delete_explain_msg') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
    }

    if ($getMode === 'delete_explain_msg') {
        // ask if contact should only be removed from organization or completely deleted
        echo '
            <div class="modal-header">
                <h3 class="modal-title">' . $gL10n->get('SYS_REMOVE_CONTACT') . '</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><i class="bi bi-person-fill-dash"></i>&nbsp;' . $gL10n->get('SYS_MAKE_FORMER') . '</p>
                <p><i class="bi bi-trash"></i>&nbsp;' . $gL10n->get('SYS_REMOVE_CONTACT_DESC', array($gL10n->get('SYS_DELETE'))) . '</p>
            </div>
            <div class="modal-footer">
                <button id="adm_button_former" type="button" class="btn btn-primary mr-4" onclick="callUrlHideElement(\'row_members_' . $getUserUuid . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('user_uuid' => $getUserUuid, 'mode' => 'remove')) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                    <i class="bi bi-person-fill-dash"></i>' . $gL10n->get('SYS_FORMER') . '</button>
                <button id="adm_button_delete" type="button" class="btn btn-primary" onclick="callUrlHideElement(\'row_members_' . $getUserUuid . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('user_uuid' => $getUserUuid, 'mode' => 'delete')) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">
                    <i class="bi bi-trash"></i>' . $gL10n->get('SYS_DELETE') . '</button>
                <div id="adm_status_message" class="mt-4 w-100"></div>
            </div>';
        exit();
    }

    // Create user-object
    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

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
            throw new Exception('SYS_NO_RIGHTS');
        }

        $member = new Membership($gDb);

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

        echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_END_MEMBERSHIP_OF_USER_OK', array($user->getValue('FIRST_NAME') . ' contacts_function.php' . $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname')))));
        exit();
    } elseif ($getMode === 'delete') {
        // User must not be in any other organization
        // User could not delete himself
        // Only administrators are allowed to do this
        if ($isAlsoInOtherOrgas || $gCurrentUserId === (int)$user->getValue('usr_id') || !$gCurrentUser->isAdministrator()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        // Delete user from database
        $user->delete();
        echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_DELETE_DATA')));
        exit();
    } elseif ($getMode === 'send_login') {
        // If User must be member of this organization than send a new password
        if (isMember($user->getValue('usr_id'))) {
            $user->sendNewPassword();

            echo json_encode(array('status' => 'success', 'message' => $gL10n->get('SYS_EMAIL_SEND')));
        } else {
            throw new Exception('SYS_NO_RIGHTS');
        }
        exit();
    }

    throw new Exception('SYS_NO_RIGHTS');
} catch (Throwable $e) {
    if ($getMode == 'delete_explain_msg') {
        $gMessage->showInModalWindow();
        $gMessage->show($e->getMessage());
    } else {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    }
}
