<?php
/**
 ***********************************************************************************************
 * Search for existing usernames and show contacts with similar names
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    $postLastname = strip_tags($_POST['lastname']);
    $postFirstname = strip_tags($_POST['firstname']);

    // only legitimate users are allowed to call the user management
    if (!$gCurrentUser->isAdministratorUsers()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // check form field input and sanitized it from malicious content
    $contactsNewForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
    $formValues = $contactsNewForm->validate($_POST);

    // create an HTML page object
    $page = new ModuleContacts('admidio-registration-assign', $gL10n->get('SYS_ASSIGN_REGISTRATION'));
    $newUser = new User($gDb, $gProfileFields);
    $newUser->setValue('LAST_NAME', $postLastname);
    $newUser->setValue('FIRST_NAME', $postFirstname);
    $page->createContentAssignUser($newUser);
    echo $page->getPageContent();
} catch (Throwable $e) {
    if ($e->getMessage() === 'No similar users found.') {
        echo json_encode(array(
            'status' => 'success',
            'message' => $gL10n->get('SYS_USER_COULD_BE_CREATED'),
            'url' => ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_new.php?lastname=' . urlencode($postLastname) . '&firstname=' . urlencode($postFirstname))
        );
        exit();
    } else {
        handleException($e, true);
    }
}
