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
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserRelation;
use Admidio\Users\Entity\UserRelationType;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getUreUUID = admFuncVariableIsValid($_GET, 'ure_uuid', 'uuid');
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('create', 'delete')));

    if (!$gSettingsManager->getBool('contacts_user_relations_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // only users who can edit all users are allowed to create user relations
    if (!$gCurrentUser->isAdministratorUsers()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $relation = new UserRelation($gDb);
    $user1 = new User($gDb, $gProfileFields);
    $user2 = new User($gDb, $gProfileFields);

    if ($getUreUUID !== '') {
        $relation->readDataByUuid($getUreUUID);
        $user1->readDataById($relation->getValue('ure_usr_id1'));
        $user2->readDataById($relation->getValue('ure_usr_id2'));
        if (!$gCurrentUser->hasRightEditProfile($user1) || !$gCurrentUser->hasRightEditProfile($user2)) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    if ($getMode === 'create') {
        // check form field input and sanitized it from malicious content
        $userRelationsEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $userRelationsEditForm->validate($_POST);

        $user1->readDataByUuid($getUserUuid);

        if ($user1->isNewRecord()) {
            throw new Exception('SYS_NO_ENTRY');
        }

        if (!$gCurrentUser->hasRightEditProfile($user1)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $postUsrId2 = admFuncVariableIsValid($_POST, 'usr_uuid2', 'uuid');
        $user2->readDataByUuid($postUsrId2);

        if ($user2->isNewRecord()) {
            throw new Exception('SYS_NO_ENTRY');
        }

        if (!$gCurrentUser->hasRightEditProfile($user2)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $postUrtUUID = admFuncVariableIsValid($_POST, 'urt_uuid', 'uuid');
        $relationType = new UserRelationType($gDb);
        $relationType->readDataByUuid($postUrtUUID);

        if ($relationType->isNewRecord()) {
            throw new Exception('SYS_NO_ENTRY');
        }

        $gDb->startTransaction();

        $relation1 = new UserRelation($gDb);
        $relation1->setValue('ure_urt_id', (int)$relationType->getValue('urt_id'));
        $relation1->setValue('ure_usr_id1', (int)$user1->getValue('usr_id'));
        $relation1->setValue('ure_usr_id2', (int)$user2->getValue('usr_id'));
        $relation1->save();

        if (!$relationType->isUnidirectional()) {
            $relation2 = new UserRelation($gDb);
            $relation2->setValue('ure_urt_id', (int)$relationType->getValue('urt_id_inverse'));
            $relation2->setValue('ure_usr_id1', (int)$user2->getValue('usr_id'));
            $relation2->setValue('ure_usr_id2', (int)$user1->getValue('usr_id'));
            $relation2->save();
        }

        $gDb->endTransaction();

        $gNavigation->deleteLastUrl();
        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } elseif ($getMode === 'delete') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        // delete relation
        $relation->delete();

        echo json_encode(array('status' => 'success'));
        exit();
    }
} catch (Throwable $e) {
    handleException($e, true);
}
