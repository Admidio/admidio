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

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getLinkUuid = admFuncVariableIsValid($_GET, 'link_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('create', 'delete')));

    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

    // check if the module is enabled for use
    if ((int)$gSettingsManager->get('enable_weblinks_module') === 0) {
        throw new AdmException('SYS_MODULE_DISABLED');
    }

    // create weblink object
    $link = new TableWeblink($gDb);

    if ($getLinkUuid !== '') {
        $link->readDataByUuid($getLinkUuid);

        // check if the current user could edit this weblink
        if (!$link->isEditable()) {
            throw new AdmException('SYS_NO_RIGHTS');
        }
    } else {
        // check if the user has the right to edit at least one category
        if (count($gCurrentUser->getAllEditableCategories('LNK')) === 0) {
            throw new AdmException('SYS_NO_RIGHTS');
        }
    }

    if ($getMode === 'create') {
        if (isset($_SESSION['linksEditForm'])) {
            $linksEditForm = $_SESSION['linksEditForm'];
            $linksEditForm->validate($_POST);
        } else {
            throw new AdmException('SYS_INVALID_PAGE_VIEW');
        }

        // POST variables to the announcements object
        foreach ($_POST as $key => $value) { // TODO possible security issue
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
        // delete current announcements, right checks were done before
        $link->delete();

        // Delete successful -> Return for XMLHttpRequest
        echo 'done';
    }
} catch (AdmException|Exception $e) {
    if ($getMode === 'create') {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        echo $e->getMessage();
    }

}
