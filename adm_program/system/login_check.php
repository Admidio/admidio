<?php
/**
 ***********************************************************************************************
 * Validate login data, create cookie and sign in the user to Admidio
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/login_func.php');

try {
    createUserObjectFromPost();
} catch (AdmException $e) {
    $gMessage->show($e->getText());
    // => EXIT
}

// check if browser can set cookies and throw error if not
if (!array_key_exists(COOKIE_PREFIX . '_SESSION_ID', $_COOKIE)) {
    $gMessage->show($gL10n->get('SYS_COOKIE_NOT_SET', array(DOMAIN)));
    // => EXIT
}

// remove login page from navigation stack
try {
    if (str_ends_with($gNavigation->getUrl(), '/login.php')) {
        $gNavigation->deleteLastUrl();
    }
} catch (AdmException $e) {
    $gNavigation->clear();
}

// If no forward url has been set, then refer to the start page after login
if (array_key_exists('login_forward_url', $_SESSION)) {
    $forwardUrl = $_SESSION['login_forward_url'];
} else {
    $forwardUrl = ADMIDIO_URL . '/' . $gSettingsManager->getString('homepage_login');
}

unset($_SESSION['login_forward_url']);

admRedirect($forwardUrl);
// => EXIT
