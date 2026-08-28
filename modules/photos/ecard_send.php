<?php
/**
 ***********************************************************************************************
 * Send ecard to users and show status message
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Photos\Service\ECardService;
use Ramsey\Uuid\Uuid;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // check if the photo module is enabled and eCard is enabled
    if (!$gSettingsManager->getBool('photo_ecard_enabled')) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ((int)$gSettingsManager->get('photo_module_enabled') === 2) {
        // only logged-in users can access the module
        require(__DIR__ . '/../../system/login_valid.php');
    }

    // Initialize and check the parameters
    $postTemplateName = admFuncVariableIsValid($_POST, 'ecard_template', 'file', array('requireValue' => true));

    // check form field input and sanitized it from malicious content
    $photosEcardSendForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
    $formValues = $photosEcardSendForm->validate($_POST);

    $roleUuids = array();
    $userUuids = array();

    foreach ($formValues['ecard_recipients'] as $value) {
        if (str_contains($value, 'groupID')) {
            $roleUuid = substr($value, 9);
            if (Uuid::isValid($roleUuid)) {
                $roleUuids[] = $roleUuid;
            }
        } elseif (Uuid::isValid($value)) {
            $userUuids[] = $value;
        }
    }

    $ecardService = new ECardService($gDb);
    $ecardService->send(
        $formValues['photo_uuid'],
        (int)$formValues['photo_nr'],
        $postTemplateName,
        $formValues['ecard_message'],
        $roleUuids,
        $userUuids
    );

    echo json_encode(array(
        'status' => 'success',
        'message' => $gL10n->get('SYS_ECARD_SUCCESSFULLY_SEND'),
        'url' => $gNavigation->getPreviousUrl()
    ));
} catch (Throwable $e) {
    handleException($e, true);
}
