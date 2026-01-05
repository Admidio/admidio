<?php
/**
 ***********************************************************************************************
 * various functions for the profile handling
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode   :  export          - Export vCard of user
 *           save_membership - save membership data
 *           stop_membership - Stop membership of role
 *           remove_former_membership   - Remove former membership of role
 *           reload_current_memberships - reload Role Memberships
 *           reload_former_memberships  - reload former role memberships
 *           reload_future_memberships  - reload future role memberships
 * user_uuid   : UUID of the user to be edited
 * member_uuid : UUID of role membership that should be edited
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require_once(__DIR__ . '/roles_functions.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getMemberUuid = admFuncVariableIsValid($_GET, 'member_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('export', 'stop_membership', 'remove_former_membership', 'reload_current_memberships', 'reload_former_memberships', 'reload_future_memberships', 'save_membership')));

    if (in_array($getMode, array('stop_membership', 'remove_former_membership'))) {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
    }

    // create user object
    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

    if ($getMode === 'export') {
        // Export vCard of user

        if (!$gCurrentUser->hasRightViewProfile($user)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $filename = $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');

        $filename = FileSystemUtils::getSanitizedPathEntry($filename) . '.vcf';

        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // necessary for IE, because without it the download with SSL has problems
        header('Cache-Control: private');
        header('Pragma: public');

        // create vcard and check if user is allowed to edit profile, so he can see more data
        echo $user->getVCard();
    } elseif ($getMode === 'stop_membership') {
        // Cancel membership of role
        $member = new Membership($gDb);
        $member->readDataByUuid($getMemberUuid);
        $role = new Role($gDb, (int)$member->getValue('mem_rol_id'));

        // if user has the right then cancel membership
        if ($role->allowedToAssignMembers($gCurrentUser)) {
            $role->stopMembership($member->getValue('mem_usr_id'));

            echo 'done';
        } else {
            throw new Exception('SYS_NO_RIGHTS');
        }
    } elseif ($getMode === 'remove_former_membership') {
        // Remove former membership of role
        if ($gCurrentUser->isAdministrator()) {
            $member = new Membership($gDb);
            $member->readDataByUuid($getMemberUuid);
            $member->delete();

            echo 'done';
        }
    } elseif ($getMode === 'reload_current_memberships') {
        // reload role memberships
        $roleStatement = getRolesFromDatabase($user->getValue('usr_id'));
        $countRole = $roleStatement->rowCount();
        echo getRoleMemberships('role_list', $user, $roleStatement);
    } elseif ($getMode === 'reload_former_memberships') {
        // reload former role memberships
        $roleStatement = getFormerRolesFromDatabase($user->getValue('usr_id'));
        $countRole = $roleStatement->rowCount();
        echo getRoleMemberships('former_role_list', $user, $roleStatement);

        if ($countRole === 0) {
            /* Tabs */
            echo '<script type="text/javascript">$("#adm_profile_role_memberships_former_pane_content").css({ \'display\':\'none\' })</script>';
            /* Accordions */
            echo '<script type="text/javascript">$("#adm_profile_role_memberships_former_accordion_content").css({ \'display\':\'none\' })</script>';
        } else {
            /* Tabs */
            echo '<script type="text/javascript">$("#adm_profile_role_memberships_former_pane_content").css({ \'display\':\'block\' })</script>';
            /* Accordions */
            echo '<script type="text/javascript">$("#adm_profile_role_memberships_former_accordion_content").css({ \'display\':\'block\' })</script>';
        }
    } elseif ($getMode === 'reload_future_memberships') {
        // reload future role memberships
        $roleStatement = getFutureRolesFromDatabase($user->getValue('usr_id'));
        $countRole = $roleStatement->rowCount();
        echo getRoleMemberships('future_role_list', $user, $roleStatement);

        if ($countRole === 0) {
            /* Tabs */
            echo '<script type="text/javascript">$("#adm_profile_role_memberships_future_pane_content").css({ \'display\':\'none\' })</script>';
            /* Accordions */
            echo '<script type="text/javascript">$("#adm_profile_role_memberships_future_accordion_content").css({ \'display\':\'none\' })</script>';
        } else {
            /* Tabs */
            echo '<script type="text/javascript">$("#adm_profile_role_memberships_future_pane_content").css({ \'display\':\'block\' })</script>';
            /* Accordions */
            echo '<script type="text/javascript">$("#adm_profile_role_memberships_future_accordion_content").css({ \'display\':\'block\' })</script>';
        }
    } elseif ($getMode === 'save_membership') {
        // save membership date changes
        $postMembershipStart = admFuncVariableIsValid($_POST, 'adm_membership_start_date', 'date', array('requireValue' => true));
        $postMembershipEnd = admFuncVariableIsValid($_POST, 'adm_membership_end_date', 'date', array('requireValue' => true));

        $member = new Membership($gDb);
        $member->readDataByUuid($getMemberUuid);
        $role = new Role($gDb, (int)$member->getValue('mem_rol_id'));

        // check if user has the right to edit this membership
        if (!$role->allowedToAssignMembers($gCurrentUser)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // Check the start date
        $startDate = DateTime::createFromFormat('Y-m-d', $postMembershipStart);
        if ($startDate === false) {
            throw new Exception('SYS_DATE_INVALID', array('SYS_START', $gSettingsManager->getString('system_date')));
        }

        // If set, the end date is checked
        if ($postMembershipEnd !== '') {
            $endDate = DateTime::createFromFormat('Y-m-d', $postMembershipEnd);
            if ($endDate === false) {
                throw new Exception('SYS_DATE_INVALID', array('SYS_END', $gSettingsManager->getString('system_date')));
            }

            // If start-date is later/bigger or on same day than end-date we show an error
            if ($startDate > $endDate) {
                throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
            }
        } else {
            $postMembershipEnd = DATE_MAX;
        }

        // save role membership
        $role->setMembership($user->getValue('usr_id'), $postMembershipStart, $postMembershipEnd, $member->getValue('mem_leader'), true);

        echo 'success';
    }
} catch (Throwable $e) {
    handleException($e, in_array($getMode, array('stop_membership', 'remove_former_membership', 'save_membership')));
}
