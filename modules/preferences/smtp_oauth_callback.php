<?php
/**
 ***********************************************************************************************
 * The OAuth 2.0 provider of the SMTP server returns to this page after the sign-in of the
 * mailbox user. This URL is registered as redirect URI in the app at the provider.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * state             : The state of the sign-in that was started in the preferences
 * code              : The authorization code that is redeemed for the refresh token
 * error             : The error code if the sign-in failed
 * error_description : The description of the error
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\SmtpOAuth;
use Admidio\Infrastructure\Utils\SecurityUtils;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // only administrators are allowed to connect the account of the email dispatch
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $getState = admFuncVariableIsValid($_GET, 'state', 'string');
    $getCode = admFuncVariableIsValid($_GET, 'code', 'string');
    $getError = admFuncVariableIsValid($_GET, 'error', 'string');
    $getErrorDescription = admFuncVariableIsValid($_GET, 'error_description', 'string');

    $smtpOAuth = new SmtpOAuth();
    $smtpOAuth->handleAuthorizationResponse($getState, $getCode, $getError, $getErrorDescription);

    admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('panel' => 'email_dispatch')));
    // => EXIT
} catch (Throwable $e) {
    handleException($e);
}
