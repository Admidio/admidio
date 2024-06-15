<?php
/**
 ***********************************************************************************************
 * Various functions for user relations
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * ure_uuid  : UUID of the user relation that should be edited
 * user_uuid : UUID of the first user in the new relation
 * mode      : create - Create relation
 *             delete - Delete relation
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // Initialize and check the parameters
    $getUreUUID = admFuncVariableIsValid($_GET, 'ure_uuid', 'uuid');
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('create', 'delete')));

    if (!$gSettingsManager->getBool('contacts_user_relations_enabled')) {
        throw new AdmException('SYS_MODULE_DISABLED');
    }

    // only users who can edit all users are allowed to create user relations
    if (!$gCurrentUser->editUsers()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

    $relation = new TableUserRelation($gDb);
    $user1 = new User($gDb, $gProfileFields);
    $user2 = new User($gDb, $gProfileFields);

    if ($getUreUUID !== '') {
        $relation->readDataByUuid($getUreUUID);
        $user1->readDataById($relation->getValue('ure_usr_id1'));
        $user2->readDataById($relation->getValue('ure_usr_id2'));
        if (!$gCurrentUser->hasRightEditProfile($user1) || !$gCurrentUser->hasRightEditProfile($user2)) {
            throw new AdmException('SYS_NO_RIGHTS');
        }
    }

    if ($getMode === 'create') {
        $user1->readDataByUuid($getUserUuid);

        if ($user1->isNewRecord()) {
            throw new AdmException('SYS_NO_ENTRY');
        }

        if (!$gCurrentUser->hasRightEditProfile($user1)) {
            throw new AdmException('SYS_NO_RIGHTS');
        }

        $postUsrId2 = admFuncVariableIsValid($_POST, 'usr_id2', 'int');
        $user2->readDataById($postUsrId2);

        if ($user2->isNewRecord()) {
            throw new AdmException('SYS_NO_ENTRY');
        }

        if (!$gCurrentUser->hasRightEditProfile($user2)) {
            throw new AdmException('SYS_NO_RIGHTS');
        }

        $postUrtId = admFuncVariableIsValid($_POST, 'urt_id', 'int');
        $relationType = new TableUserRelationType($gDb, $postUrtId);

        if ($relationType->isNewRecord()) {
            throw new AdmException('SYS_NO_ENTRY');
        }

        $gDb->startTransaction();

        $relation1 = new TableUserRelation($gDb);
        $relation1->setValue('ure_urt_id', (int)$relationType->getValue('urt_id'));
        $relation1->setValue('ure_usr_id1', (int)$user1->getValue('usr_id'));
        $relation1->setValue('ure_usr_id2', (int)$user2->getValue('usr_id'));
        $relation1->save();

        if (!$relationType->isUnidirectional()) {
            $relation2 = new TableUserRelation($gDb);
            $relation2->setValue('ure_urt_id', (int)$relationType->getValue('urt_id_inverse'));
            $relation2->setValue('ure_usr_id1', (int)$user2->getValue('usr_id'));
            $relation2->setValue('ure_usr_id2', (int)$user1->getValue('usr_id'));
            $relation2->save();
        }

        $gDb->endTransaction();

        $gNavigation->deleteLastUrl();
        admRedirect($gNavigation->getUrl());
        // => EXIT
    } elseif ($getMode === 'delete') {
        // delete relation
        if ($relation->delete()) {
            echo 'done';
        }
    }
} catch (AdmException|Exception $e) {
    if ($getMode === 'create') {
        $gMessage->show($e->getMessage());
    } else {
        echo $e->getMessage();
    }
}
