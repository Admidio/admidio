<?php
/**
 ***********************************************************************************************
 * This script must be inserted with require() if the user MUST be logged in to call a page.
 *
 * If the user is not logged in, he will be automatically redirected to the login page.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

try {
    if (basename($_SERVER['SCRIPT_FILENAME']) === 'login_valid.php') {
        exit('This page may not be called directly!');
    }

    if (!$gValidLogin) {
        if (!isset($_SESSION['login_forward_url'])) {
            // remember requested URL, so we could redirect to this URL again after login
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            // if it was no ajax request, we can store the current URL, otherwise we have to store the previous URL set as header from the ajax request
            if ($isAjax) {
                $previousUrl = $_SERVER['HTTP_X_AJAX_PREVIOUS_URL'] ?? null;
                if ($previousUrl && filter_var($previousUrl, FILTER_VALIDATE_URL)) {
                    $_SESSION['login_forward_url'] = $previousUrl;
                }
            } else {
                $_SESSION['login_forward_url'] = CURRENT_URL;
            }
        }

        // User not logged in -> Request login site
        admRedirect(ADMIDIO_URL . FOLDER_SYSTEM . '/login.php');
        // => EXIT
    }
} catch (Throwable $e) {
    echo $e->getMessage();
}
