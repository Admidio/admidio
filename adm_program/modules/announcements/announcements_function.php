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
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('announcements_module_enabled') === 0) {
        throw new AdmException('SYS_MODULE_DISABLED');
    }

    // Initialize and check the parameters
    $getAnnUuid = admFuncVariableIsValid($_GET, 'ann_uuid', 'string');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete')));

    // create announcement object
    $announcement = new TableAnnouncement($gDb);

    if ($getAnnUuid !== '') {
        $announcement->readDataByUuid($getAnnUuid);

        // check if the user has the right to edit this announcement
        if (!$announcement->isEditable()) {
            throw new AdmException('SYS_NO_RIGHTS');
        }
    } else {
        // check if the user has the right to edit at least one category
        if (count($gCurrentUser->getAllEditableCategories('ANN')) === 0) {
            throw new AdmException('SYS_NO_RIGHTS');
        }
    }

    $_SESSION['announcements_request'] = $_POST;

    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

    if ($getMode === 'edit') {
        if (strlen($_POST['ann_headline']) === 0) {
            throw new AdmException('SYS_FIELD_EMPTY', array('SYS_HEADLINE'));
        }
        if (strlen($_POST['ann_description']) === 0) {
            throw new AdmException('SYS_FIELD_EMPTY', array('SYS_TEXT'));
        }
        if (strlen($_POST['ann_cat_id']) === 0) {
            throw new AdmException('SYS_FIELD_EMPTY', array('SYS_CATEGORY'));
        }

        // make html in description secure
        $_POST['ann_description'] = admFuncVariableIsValid($_POST, 'ann_description', 'html');

        // write POST parameters in announcement object
        foreach ($_POST as $key => $value) { // TODO possible security issue
            if (str_starts_with($key, 'ann_')) {
                $announcement->setValue($key, $value);
            }
        }

        if ($announcement->save()) {
            // Notification email for new or changed entries to all members of the notification role
            $announcement->sendNotification();
        }

        unset($_SESSION['announcements_request']);
        $gNavigation->deleteLastUrl();

        admRedirect($gNavigation->getUrl());
        // => EXIT
    } elseif ($getMode === 'delete') {
        // delete current announcements, right checks were done before
        $announcement->delete();

        // Delete successful -> Return for XMLHttpRequest
        echo 'done';
    }
} catch (AdmException|Exception $e) {
    if ($getMode === 'edit') {
        $gMessage->show($e->getMessage());
    } else {
        echo $e->getMessage();
    }
}
