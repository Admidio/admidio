<?php
/**
 ***********************************************************************************************
 * Several functions for weblinks module
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * link_uuid - UUID of the weblink that should be edited
 * mode      - create : Create or edit a weblink
 *             delete : Delete link
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Weblinks\Entity\Weblink;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getLinkUuid = admFuncVariableIsValid($_GET, 'link_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('create', 'delete')));

    // check if the module is enabled for use
    if ($gSettingsManager->getInt('weblinks_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // create weblink object
    $link = new Weblink($gDb);

    if ($getLinkUuid !== '') {
        $link->readDataByUuid($getLinkUuid);

        // check if the current user could edit this weblink
        if (!$link->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    } else {
        // check if the user has the right to edit at least one category
        if (count($gCurrentUser->getAllEditableCategories('LNK')) === 0) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    if ($getMode === 'create') {
        // check form field input and sanitized it from malicious content
        $linksEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $linksEditForm->validate($_POST);

        // write form values in weblinks object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'lnk_')) {
                $link->setValue($key, $value);
            }
        }

        if ($link->save()) {
            // Notification an email for new or changed entries to all members of the notification role
            $link->sendNotification();
        }

        $gNavigation->deleteLastUrl();
        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } elseif ($getMode === 'delete') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        // delete current announcements, right checks were done before
        $link->delete();
        echo json_encode(array('status' => 'success'));
        exit();
    }
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
