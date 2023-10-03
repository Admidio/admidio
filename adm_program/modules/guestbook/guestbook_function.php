<?php
/**
 ***********************************************************************************************
 * Various functions for guestbook module
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * gbo_uuid : UUID of one guestbook entry that should be edited
 * gbc_uuid : UUID of one comment that should be edited
 * mode :   1 - Create new guestbook entry
 *          2 - Delete guestbook entry
 *          3 - Edit guestbook entry
 *          4 - Create new guestbook comment
 *          5 - Delete guestbook comment
 *          8 - Edit guestbook comment
 *          9 - Moderate guestbook entry
 *          10 - Moderate guestbook comment
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getGboUuid  = admFuncVariableIsValid($_GET, 'gbo_uuid', 'string');
$getGbcUuid  = admFuncVariableIsValid($_GET, 'gbc_uuid', 'string');
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'int', array('requireValue' => true, 'validValues' => array(1, 2, 3, 4, 5, 6, 8, 9, 10)));

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_guestbook_module') === 0) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
// => EXIT
} elseif ((int) $gSettingsManager->get('enable_guestbook_module') === 2) {
    // only logged-in users can access the module
    require(__DIR__ . '/../../system/login_valid.php');
}

// check the CSRF token of the form against the session token
if (in_array($getMode, array(1, 2, 3, 4, 5, 8))) {
    try {
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        if ($getMode === 2 || $getMode === 5) {
            $exception->showText();
        } else {
            $exception->showHtml();
        }
        // => EXIT
    }
}

if ($getMode === 4) {
    $guestbook = new TableGuestbook($gDb);
    $guestbook->readDataByUuid($getGboUuid);

    // check if only logged-in users could create a comment
    if (!$gSettingsManager->getBool('enable_gbook_comments4all')) {
        require(__DIR__ . '/../../system/login_valid.php');

        if (!$gCurrentUser->commentGuestbookRight()) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    }
} elseif (in_array($getMode, array(2, 3, 5, 8), true)) {
    // For these modes the user must have a valid login and the necessary rights
    require(__DIR__ . '/../../system/login_valid.php');

    if (!$gCurrentUser->editGuestbookRight()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if (in_array($getMode, array(1, 2, 3, 9), true)) {
    $guestbook = new TableGuestbook($gDb);

    if ($getGboUuid !== '') {
        $guestbook->readDataByUuid($getGboUuid);

        // Check if the entry belongs to the current organization
        if ((int) $guestbook->getValue('gbo_org_id') !== $gCurrentOrgId) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
    }
} elseif (in_array($getMode, array(4, 5, 8, 10), true)) {
    $gbComment = new TableGuestbookComment($gDb);

    if ($getGbcUuid !== '' && $getMode !== 4) {
        $gbComment->readDataByUuid($getGbcUuid);

        // Check if the entry belongs to the current organization
        if ((int) $gbComment->getValue('gbo_org_id') !== $gCurrentOrgId) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
    }
}

if ($getMode === 1 || $getMode === 3) {
    $_SESSION['guestbook_entry_request'] = $_POST;

    if ($getMode === 1) {
        // if login and new entry then fill name with login user
        if ($gCurrentUserId > 0) {
            $_POST['gbo_name'] = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
        }

        // if user is not logged in and captcha is activated then check captcha
        if (!$gValidLogin && $gSettingsManager->getBool('enable_guestbook_captcha')) {
            try {
                FormValidation::checkCaptcha($_POST['captcha_code']);
            } catch (AdmException $e) {
                $e->showHtml();
                // => EXIT
            }
        }
    }

    if ((string) $_POST['gbo_name'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }
    if ((string) $_POST['gbo_text'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_TEXT'))));
        // => EXIT
    }

    // make html in description secure
    $_POST['gbo_text'] = admFuncVariableIsValid($_POST, 'gbo_text', 'html');

    try {
        // write POST parameters in guestbook object
        foreach ($_POST as $key => $value) { // TODO possible security issue
            if (str_starts_with($key, 'gbo_')) {
                if (!$guestbook->setValue($key, $value)) {
                    // show error message if an invalid email address should be saved
                    if ($key === 'gbo_email') {
                        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', array($gL10n->get('SYS_EMAIL'))));
                    // => EXIT
                    } elseif ($key === 'gbo_homepage') {
                        $gMessage->show($gL10n->get('SYS_URL_INVALID_CHAR', array($gL10n->get('SYS_WEBSITE'))));
                        // => EXIT
                    }
                }
            }
        }

        if ($gValidLogin) {
            // If the user is logged in, the current UserId and the correct name will be saved
            $guestbook->setValue('gbo_name', $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'));
        } else {
            if ($gSettingsManager->getInt('flooding_protection_time') > 0) {
                // If the user is not logged in, the program will check if the user has already created a guestbook
                // entry under his IP address within a certain time period before saving and show an error message
                // if this is the case.
                $sql = 'SELECT COUNT(*) AS count
                          FROM '.TBL_GUESTBOOK.'
                         WHERE unix_timestamp(gbo_timestamp_create) > unix_timestamp() - ? -- $gSettingsManager->getInt(\'flooding_protection_time\')
                           AND gbo_org_id     = ? -- $gCurrentOrgId
                           AND gbo_ip_address = ? -- $guestbook->getValue(\'gbo_ip_address\')';
                $queryParams = array($gSettingsManager->getInt('flooding_protection_time'), $gCurrentOrgId, $guestbook->getValue('gbo_ip_address'));
                $pdoStatement = $gDb->queryPrepared($sql, $queryParams);

                if ($pdoStatement->fetchColumn() > 0) {
                    $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', array($gSettingsManager->getInt('flooding_protection_time'))));
                    // => EXIT
                }
            }
        }

        // In case of moderation the message will be published later
        if (((int) $gSettingsManager->get('enable_guestbook_moderation') === 1 && !$gValidLogin)
        ||  ((int) $gSettingsManager->get('enable_guestbook_moderation') === 2 && !$gCurrentUser->editGuestbookRight())) {
            $guestbook->setValue('gbo_locked', '1');
        }

        if ($guestbook->save()){
            // Notification email for new or changed entries to all members of the notification role
            $guestbook->sendNotification();
        }
    } catch (AdmException $e) {
        $e->showHtml();
    }

    unset($_SESSION['guestbook_entry_request']);
    $gNavigation->deleteLastUrl();

    $url = ADMIDIO_URL . FOLDER_MODULES.'/guestbook/guestbook.php';

    // In the case of moderation, output a note that the message still has to be checked first
    if (((int) $gSettingsManager->get('enable_guestbook_moderation') === 1 && !$gValidLogin)
    ||  ((int) $gSettingsManager->get('enable_guestbook_moderation') === 2 && !$gCurrentUser->editGuestbookRight())) {
        $gMessage->setForwardUrl($url);
        $gMessage->show($gL10n->get('GBO_ENTRY_QUEUED'));
        // => EXIT
    }

    admRedirect($url);
    // => EXIT
} elseif ($getMode === 2) {
    // delete guestbook entry
    $guestbook->delete();

    // Delete successful -> return for XMLHttpRequest
    echo 'done';
} elseif ($getMode === 5) {
    // delete guestbook comment
    $gbComment->delete();

    // Delete successful -> return for XMLHttpRequest
    echo 'done';
} elseif ($getMode === 9) {
    // unlock the guestbook entry
    $guestbook->moderate();
    // Activation successful -> Return for XMLHttpRequest
    echo 'done';
} elseif ($getMode === 10) {
    // unlock the guestbook comment
    $gbComment->moderate();
    // Activation successful -> Return for XMLHttpRequest
    echo 'done';
} elseif ($getMode === 4 || $getMode === 8) {
    $_SESSION['guestbook_comment_request'] = $_POST;

    if ($getMode === 4) {
        // if login then fill name with login user
        if ($gCurrentUserId > 0) {
            $_POST['gbc_name'] = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
        }

        // if user is not logged in and captcha is activated then check captcha
        if (!$gValidLogin && $gSettingsManager->getBool('enable_guestbook_captcha')) {
            try {
                FormValidation::checkCaptcha($_POST['captcha_code']);
            } catch (AdmException $e) {
                $e->showHtml();
                // => EXIT
            }
        }
    }

    if ((string) $_POST['gbc_name'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }
    if ((string) $_POST['gbc_text'] === '') {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_COMMENT'))));
        // => EXIT
    }

    try {
        // make html in description secure
        $_POST['gbc_text'] = admFuncVariableIsValid($_POST, 'gbc_text', 'html');

        // POST variables to the guestbook comment object
        foreach ($_POST as $key => $value) { // TODO possible security issue
            if (str_starts_with($key, 'gbc_')) {
                if (!$gbComment->setValue($key, $value)) {
                    // Data was not transferred, output note
                    if ($key === 'gbc_email') {
                        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', array($gL10n->get('SYS_EMAIL'))));
                        // => EXIT
                    }
                }
            }
        }

        if ($getMode === 4) {
            $gbComment->setValue('gbc_gbo_id', $guestbook->getValue('gbo_id'));
        }

        if ($gValidLogin) {
            // If the user is logged in, the current UserId and the correct name will be saved
            $gbComment->setValue('gbc_name', $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME'));
        } else {
            if ($gSettingsManager->getInt('flooding_protection_time') > 0) {
                // If the user is not logged in, the program will check if the user has already created a guestbook
                // comment under his IP address within a certain time period before saving and show an error message
                // if this is the case.
                $sql = 'SELECT COUNT(*) AS count
                          FROM '.TBL_GUESTBOOK_COMMENTS.'
                         WHERE unix_timestamp(gbc_timestamp_create) > unix_timestamp() - ? -- $gSettingsManager->getInt(\'flooding_protection_time\')
                           AND gbc_ip_address = ? -- $gbComment->getValue(\'gbc_ip_address\')';
                $pdoStatement = $gDb->queryPrepared($sql, array($gSettingsManager->getInt('flooding_protection_time'), $gbComment->getValue('gbc_ip_address')));

                if ($pdoStatement->fetchColumn() > 0) {
                    $gMessage->show($gL10n->get('GBO_FLOODING_PROTECTION', array($gSettingsManager->getInt('flooding_protection_time'))));
                    // => EXIT
                }
            }
        }

        // In case of moderation the message will be published later
        if (((int) $gSettingsManager->get('enable_guestbook_moderation') === 1 && !$gValidLogin)
        ||  ((int) $gSettingsManager->get('enable_guestbook_moderation') === 2 && !$gCurrentUser->editGuestbookRight())) {
            $gbComment->setValue('gbc_locked', '1');
        }

        if ($gbComment->save()) {
            // Notification email for new or changed entries to all members of the notification role
            $gbComment->sendNotification();
        }

        unset($_SESSION['guestbook_comment_request']);
        $gNavigation->deleteLastUrl();

        $url = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook.php', array('id' => (int) $gbComment->getValue('gbc_gbo_id')));

        // In the case of moderation, output a note that the comment still has to be checked first
        if (((int) $gSettingsManager->get('enable_guestbook_moderation') === 1 && !$gValidLogin)
        ||  ((int) $gSettingsManager->get('enable_guestbook_moderation') === 2 && !$gCurrentUser->editGuestbookRight())) {
            $gMessage->setForwardUrl($url);
            $gMessage->show($gL10n->get('GBO_ENTRY_QUEUED'));
            // => EXIT
        }

        admRedirect($url);
        // => EXIT
    } catch (AdmException $e) {
        $e->showHtml();
    }
} else {
    // Falls der Mode unbekannt ist, ist natürlich auch Ende...
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}
