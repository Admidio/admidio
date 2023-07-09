<?php
/**
 ***********************************************************************************************
 * various functions for the profile handling
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode   :  1 - Export vCard of user
 *           2 - Cancel membership of role
 *           3 - Remove former membership of role
 *           4 - reload Role Memberships
 *           5 - reload former role memberships
 *           6 - reload future role memberships
 *           7 - save membership data
 * user_uuid   : UUID of the user to be edited
 * member_uuid : UUID of role membership that should be edited
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/roles_functions.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUserUuid   = admFuncVariableIsValid($_GET, 'user_uuid', 'string');
$getMemberUuid = admFuncVariableIsValid($_GET, 'member_uuid', 'string');
$getMode       = admFuncVariableIsValid($_GET, 'mode', 'int');

if (in_array($getMode, array(2, 3, 7))) {
    try {
        // in ajax mode only return simple text on error
        $gMessage->showHtmlTextOnly(true);

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        $exception->showText();
        // => EXIT
    }
}

// create user object
$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

if ($getMode === 1) {
    // Export vCard of user

    if (!$gCurrentUser->hasRightViewProfile($user)) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        exit();
    }

    $filename = $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME');

    $filename = FileSystemUtils::getSanitizedPathEntry($filename) . '.vcf';

    header('Content-Type: text/x-vcard; charset=iso-8859-1');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    // necessary for IE, because without it the download with SSL has problems
    header('Cache-Control: private');
    header('Pragma: public');

    // create vcard and check if user is allowed to edit profile, so he can see more data
    echo $user->getVCard();
} elseif ($getMode === 2) {
    // Cancel membership of role
    $member = new TableMembers($gDb);
    $member->readDataByUuid($getMemberUuid);
    $role   = new TableRoles($gDb, (int) $member->getValue('mem_rol_id'));

    // if user has the right then cancel membership
    if ($role->allowedToAssignMembers($gCurrentUser)) {
        try {
            $member->stopMembership();
        } catch (AdmException $e) {
            $e->showText();
            // => EXIT
        }

        // Beendigung erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    } else {
        echo $gL10n->get('SYS_NO_RIGHTS');
    }
} elseif ($getMode === 3) {
    // Remove former membership of role
    if ($gCurrentUser->isAdministrator()) {
        $member = new TableMembers($gDb);
        $member->readDataByUuid($getMemberUuid);
        $member->delete();

        // Entfernen erfolgreich -> Rueckgabe fuer XMLHttpRequest
        echo 'done';
    }
} elseif ($getMode === 4) {
    // reload role memberships
    $roleStatement  = getRolesFromDatabase($user->getValue('usr_id'));
    $countRole      = $roleStatement->rowCount();
    echo getRoleMemberships('role_list', $user, $roleStatement);
} elseif ($getMode === 5) {
    // reload former role memberships
    $roleStatement  = getFormerRolesFromDatabase($user->getValue('usr_id'));
    $countRole      = $roleStatement->rowCount();
    echo getRoleMemberships('former_role_list', $user, $roleStatement);

    if ($countRole === 0) {
        echo '<script type="text/javascript">$("#profile_former_roles_box").css({ \'display\':\'none\' })</script>';
    } else {
        echo '<script type="text/javascript">$("#profile_former_roles_box").css({ \'display\':\'block\' })</script>';
    }
} elseif ($getMode === 6) {
    // reload future role memberships
    $roleStatement  = getFutureRolesFromDatabase($user->getValue('usr_id'));
    $countRole      = $roleStatement->rowCount();
    echo getRoleMemberships('future_role_list', $user, $roleStatement);

    if ($countRole === 0) {
        echo '<script type="text/javascript">$("#profile_future_roles_box").css({ \'display\':\'none\' })</script>';
    } else {
        echo '<script type="text/javascript">$("#profile_future_roles_box").css({ \'display\':\'block\' })</script>';
    }
} elseif ($getMode === 7) {
    // save membership date changes
    $postMembershipStart = admFuncVariableIsValid($_POST, 'membership_start_date_'.$getMemberUuid, 'date', array('requireValue' => true));
    $postMembershipEnd   = admFuncVariableIsValid($_POST, 'membership_end_date_'.$getMemberUuid, 'date', array('requireValue' => true));

    $member = new TableMembers($gDb);
    $member->readDataByUuid($getMemberUuid);
    $role   = new TableRoles($gDb, (int) $member->getValue('mem_rol_id'));

    // check if user has the right to edit this membership
    if (!$role->allowedToAssignMembers($gCurrentUser)) {
        exit($gL10n->get('SYS_NO_RIGHTS'));
    }

    // Check das Beginn Datum
    $startDate = \DateTime::createFromFormat('Y-m-d', $postMembershipStart);
    if ($startDate === false) {
        exit($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_START'), $gSettingsManager->getString('system_date'))));
    }

    // Falls gesetzt wird das Enddatum gecheckt
    if ($postMembershipEnd !== '') {
        $endDate = \DateTime::createFromFormat('Y-m-d', $postMembershipEnd);
        if ($endDate === false) {
            exit($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_END'), $gSettingsManager->getString('system_date'))));
        }

        // If start-date is later/bigger or on same day than end-date we show an error
        if ($startDate > $endDate) {
            exit($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
        }
    } else {
        $postMembershipEnd = DATE_MAX;
    }

    // save role membership
    $user->editRoleMembership($member->getValue('mem_id'), $postMembershipStart, $postMembershipEnd);

    echo 'success';
}
