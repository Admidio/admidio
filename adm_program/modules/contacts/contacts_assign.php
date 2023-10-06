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
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

$postLastname = admFuncVariableIsValid($_POST, 'lastname', 'string');
$postFirstname = admFuncVariableIsValid($_POST, 'firstname', 'string');

// this script should return errors in ajax mode
$gMessage->showHtmlTextOnly(true);

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    $exception->showText();
    // => EXIT
}

// only legitimate users are allowed to call the user management
if (!$gCurrentUser->editUsers()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if (strlen($_POST['lastname']) === 0) {
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_LASTNAME'))));
    // => EXIT
}
if (strlen($_POST['firstname']) === 0) {
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_FIRSTNAME'))));
    // => EXIT
}

try {
    // create html page object
    $page = new ModuleContacts('admidio-registration-assign', $gL10n->get('SYS_ASSIGN_REGISTRATION'));
    $newUser = new User($gDb, $gProfileFields);
    $newUser->setValue('LAST_NAME', $postLastname);
    $newUser->setValue('FIRST_NAME', $postFirstname);
    $page->createContentAssignUser($newUser);
    echo $page->getPageContent();
} catch (SmartyException $e) {
    $gMessage->show($e->getMessage());
} catch (AdmException $e) {
    if ($e->getMessage() === 'No similar users found.') {
        echo 'success';
    } else {
        $e->showText();
    }
}
