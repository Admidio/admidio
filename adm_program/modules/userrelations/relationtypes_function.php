<?php
/**
 ***********************************************************************************************
 * Various functions for relation types
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * urt_uuid : UUID of the relation type that should be edited
 * mode     : edit   - Create or edit relation type
 *            delete - Delete relation type
 *
 *****************************************************************************/
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Users\Entity\UserRelationType;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getUrtUuid = admFuncVariableIsValid($_GET, 'urt_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete')));

    if (!$gSettingsManager->getBool('contacts_user_relations_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $relationType = new UserRelationType($gDb);

    if ($getUrtUuid !== '') {
        $relationType->readDataByUuid($getUrtUuid);
    }

    if ($getMode === 'edit') {
        // create or edit relation type

        // check form field input and sanitized it from malicious content
        $userRelationsTypeEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $userRelationsTypeEditForm->validate($_POST);

        $relationType2 = new UserRelationType($gDb);
        if ($getUrtUuid !== '') {
            $formValues['relation_type'] = $relationType->getRelationTypeString();
            $relationType2->readDataById((int)$relationType->getValue('urt_id_inverse'));
        }

        $relationType->setValue('urt_name', $formValues['urt_name']);
        $relationType->setValue('urt_name_male', empty($formValues['urt_name_male']) ? $formValues['urt_name'] : $formValues['urt_name_male']);
        $relationType->setValue('urt_name_female', empty($formValues['urt_name_female']) ? $formValues['urt_name'] : $formValues['urt_name_female']);
        $relationType->setValue('urt_edit_user', $formValues['urt_edit_user']);

        if ($formValues['relation_type'] === UserRelationType::USER_RELATION_TYPE_ASYMMETRICAL) {
            $relationType2->setValue('urt_name', $formValues['urt_name_inverse']);
            $relationType2->setValue('urt_name_male', empty($formValues['urt_name_male_inverse']) ? $formValues['urt_name_inverse'] : $formValues['urt_name_male_inverse']);
            $relationType2->setValue('urt_name_female', empty($formValues['urt_name_female_inverse']) ? $formValues['urt_name_inverse'] : $formValues['urt_name_female_inverse']);
            $relationType2->setValue('urt_edit_user', $formValues['urt_edit_user_inverse']);
        }

        // write data into database
        $gDb->startTransaction();

        $relationType->save();

        if ($formValues['relation_type'] === UserRelationType::USER_RELATION_TYPE_ASYMMETRICAL) {
            if ($getUrtUuid === '') {
                $relationType2->setValue('urt_id_inverse', (int)$relationType->getValue('urt_id'));
            }

            $relationType2->save();

            if ($getUrtUuid === '') {
                $relationType->setValue('urt_id_inverse', (int)$relationType2->getValue('urt_id'));
                $relationType->save();
            }
        } elseif ($formValues['relation_type'] === UserRelationType::USER_RELATION_TYPE_SYMMETRICAL) {
            $relationType->setValue('urt_id_inverse', (int)$relationType->getValue('urt_id'));
            $relationType->save();
        }

        $gDb->endTransaction();

        $gNavigation->deleteLastUrl();
        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } elseif ($getMode === 'delete') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        // delete relation type
        $relationType->delete();

        echo json_encode(array('status' => 'success'));
        exit();
    }
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
