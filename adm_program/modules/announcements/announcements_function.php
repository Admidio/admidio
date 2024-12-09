<?php
/**
 ***********************************************************************************************
 * Several functions for announcement module
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * ann_uuid - UUID of the announcement that should be edited
 * mode     - edit : Create or edit announcement
 *            delete : Delete announcement
 ***********************************************************************************************
 */

use Admidio\Announcements\Entity\Announcement;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('announcements_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // Initialize and check the parameters
    $getAnnUuid = admFuncVariableIsValid($_GET, 'ann_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete')));

    // create announcement object
    $announcement = new Announcement($gDb);

    if ($getAnnUuid !== '') {
        $announcement->readDataByUuid($getAnnUuid);

        // check if the user has the right to edit this announcement
        if (!$announcement->isEditable()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    } else {
        // check if the user has the right to edit at least one category
        if (count($gCurrentUser->getAllEditableCategories('ANN')) === 0) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    if ($getMode === 'edit') {
        // check form field input and sanitized it from malicious content
        $announcementEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $announcementEditForm->validate($_POST);

        // write form values in announcement object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'ann_')) {
                $announcement->setValue($key, $value);
            }
        }

        if ($announcement->save()) {
            // Notification email for new or changed entries to all members of the notification role
            $announcement->sendNotification();
        }

        $gNavigation->deleteLastUrl();
        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } elseif ($getMode === 'delete') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        // delete current announcements, right checks were done before
        $announcement->delete();

        echo json_encode(array('status' => 'success'));
        exit();
    }
} catch (Exception $e) {
    echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
}
