<?php
/**
 ***********************************************************************************************
 * Various user functions
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * mode: 1 - MsgBox explaining what effect the deletion has.
 *       2 - remove user ONLY from the member community
 *       3 - delete user from database
 *       4 - send user e-mail with new access data
 *       5 - Ask if access data should be sent
 *       6 - Ask if member should be deleted
 * user_uuid : UUID of the user, who should be edited
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'int', array('requireValue' => true));
$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string', array('requireValue' => true));

// Only users with user-edit rights are allowed
if (!$gCurrentUser->editUsers()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if ($getMode === 1) {
    // ask if user should only be removed from organization or completly deleted

    echo '
    <div class="modal-header">
        <h3 class="modal-title">'.$gL10n->get('SYS_REMOVE_USER').'</h3>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
    <div class="modal-body">
        <p><i class="fas fa-user-clock"></i>&nbsp;'.$gL10n->get('SYS_MAKE_FORMER').'</p>
        <p><i class="fas fa-trash-alt"></i>&nbsp;'.$gL10n->get('SYS_REMOVE_USER_DESC', array($gL10n->get('SYS_DELETE'))).'</p>

        <button id="btnFormer" type="button" class="btn btn-primary"
            onclick="self.location.href=\''.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('user_uuid' => $getUserUuid, 'mode' => 2)).'\'">
            <i class="fas fa-user-clock"></i>'.$gL10n->get('SYS_FORMER').'</button>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <button id="btnDelete" type="button" class="btn btn-primary"
            onclick="self.location.href=\''.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('user_uuid' => $getUserUuid, 'mode' => 3)).'\'">
            <i class="fas fa-trash-alt"></i>'.$gL10n->get('SYS_DELETE').'</button>
    </div>';

    exit();
}

// Create user-object
$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

if ($getMode === 3 || $getMode === 6) {
    // Check if user is also in other organizations
    $sql = 'SELECT COUNT(*) AS count
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE rol_valid   = true
               AND cat_org_id <> ? -- $gCurrentOrgId
               AND mem_begin  <= ? -- DATE_NOW
               AND mem_end     > ? -- DATE_NOW
               AND mem_usr_id  = ? -- $user->getValue(\'usr_id\')';
    $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, DATE_NOW, DATE_NOW, $user->getValue('usr_id')));
    $isAlsoInOtherOrgas = $pdoStatement->fetchColumn() > 0;
}

if ($getMode === 2) {
    // User has to be a member of this organization
    // User could not delete himself
    // Administrators could not be deleted
    if (!isMember($user->getValue('usr_id')) || $gCurrentUserId === (int) $user->getValue('usr_id')
    || (!$gCurrentUser->isAdministrator() && $user->isAdministrator())) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $member = new TableMembers($gDb);

    $sql = 'SELECT mem_id, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_leader
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE rol_valid  = true
               AND (  cat_org_id = ? -- $gCurrentOrgId
                   OR cat_org_id IS NULL )
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
               AND mem_usr_id = ? -- $user->getValue(\'usr_id\')';
    $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, DATE_NOW, DATE_NOW, $user->getValue('usr_id')));

    while ($row = $pdoStatement->fetch()) {
        // invalidate all roles of this organization
        $member->setArray($row);
        $member->stopMembership($row['mem_rol_id'], $row['mem_usr_id']);
    }

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_END_MEMBERSHIP_OF_USER_OK', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname'))));
// => EXIT
} elseif ($getMode === 3) {
    // User must not be in any other organization
    // User could not delete himself
    // Only administrators are allowed to do this
    if ($isAlsoInOtherOrgas || $gCurrentUserId === (int) $user->getValue('usr_id') || !$gCurrentUser->isAdministrator()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // Delete user from database
    $user->delete();

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_DELETE_DATA'));
// => EXIT
} elseif ($getMode === 4) {
    // User must be member of this organization
    // E-Mail support must be enabled
    // Only administrators are allowed to send new login data or users who want to approve login data
    if (isMember($user->getValue('usr_id'))
    && $gSettingsManager->getBool('system_notifications_enabled')
    && ($gCurrentUser->isAdministrator() || $gCurrentUser->approveUsers())) {
        try {
            // Generate new secure-random password and save it
            $password = SecurityUtils::getRandomString(PASSWORD_GEN_LENGTH, PASSWORD_GEN_CHARS);
            $user->setPassword($password);
            $user->save();

            // Send mail with login data to user
            $sysMail = new SystemMail($gDb);
            $sysMail->addRecipientsByUserId((int) $user->getValue('usr_id'));
            $sysMail->setVariable(1, $password);
            $sysMail->sendSystemMail('SYSMAIL_NEW_PASSWORD', $user);
        } catch (AdmException $e) {
            $e->showText();
            // => EXIT
        }
    } else {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $gMessage->setForwardUrl($gNavigation->getUrl());
    $gMessage->show($gL10n->get('SYS_EMAIL_SEND'));
// => EXIT
} elseif ($getMode === 5) {
    // Ask to send new login-data
    $gMessage->setForwardYesNo(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('user_uuid' => $getUserUuid, 'mode' => 4)));
    $gMessage->show($gL10n->get('SYS_SEND_NEW_LOGIN', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'))));
// => EXIT
} elseif ($getMode === 6) {
    $gMessage->showInModalWindow();

    if (!$isAlsoInOtherOrgas && $gCurrentUser->isAdministrator()) {
        if (isMember($user->getValue('usr_id'))) {
            // User is ONLY member of this organization -> ask if make to former member or delete completely
            admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/members/members_function.php', array('user_uuid' => $getUserUuid, 'mode' => 1)));
        // => EXIT
        } else {
            // User is not member of any organization -> ask if delete completely
            $gMessage->setForwardYesNo(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('user_uuid' => $getUserUuid, 'mode' => 3)));
            $gMessage->show($gL10n->get('SYS_USER_DELETE_DESC', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'))), $gL10n->get('SYS_DELETE'));
            // => EXIT
        }
    } else {
        // User could only be removed from this organization -> ask so
        $gMessage->setForwardYesNo(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('user_uuid' => $getUserUuid, 'mode' => 2)));
        $gMessage->show($gL10n->get('SYS_END_MEMBERSHIP_OF_USER', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname'))), $gL10n->get('SYS_REMOVE'));
        // => EXIT
    }
}

$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
// => EXIT
