<?php
/**
 ***********************************************************************************************
 * Several functions for weblinks module
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * link_uuid - UUID of the weblink that should be edited
 * mode      - 1 : Create or edit a weblink
 *             2 : Delete link
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getLinkUuid = admFuncVariableIsValid($_GET, 'link_uuid', 'string');
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'int', array('requireValue' => true));

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    if ($getMode === 1) {
        $exception->showHtml();
    } else {
        $exception->showText();
    }
    // => EXIT
}

// check if the module is enabled for use
if ((int) $gSettingsManager->get('enable_weblinks_module') === 0) {
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// create weblink object
$link = new TableWeblink($gDb);

if ($getLinkUuid !== '') {
    $link->readDataByUuid($getLinkUuid);

    // check if the current user could edit this weblink
    if (!$link->isEditable()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
} else {
    // check if the user has the right to edit at least one category
    if (count($gCurrentUser->getAllEditableCategories('LNK')) === 0) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

if ($getMode === 1) {
    $_SESSION['links_request'] = $_POST;
    $weblinkIsNew = $link->isNewRecord();

    if (strlen(StringUtils::strStripTags($_POST['lnk_name'])) === 0) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_LINK_NAME'))));
        // => EXIT
    }
    if (strlen(StringUtils::strStripTags($_POST['lnk_url'])) === 0) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_LINK_ADDRESS'))));
        // => EXIT
    }
    if (strlen($_POST['lnk_cat_id']) === 0) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_CATEGORY'))));
        // => EXIT
    }

    // make html in description secure
    $_POST['lnk_description'] = admFuncVariableIsValid($_POST, 'lnk_description', 'html');

    try {
        // POST variables to the announcements object
        foreach ($_POST as $key => $value) { // TODO possible security issue
            if (str_starts_with($key, 'lnk_')) {
                $link->setValue($key, $value);
            }
        }

        // Set link counter to 0
        if ($weblinkIsNew) {
            $link->setValue('lnk_counter', 0);
        }

        $returnCode = $link->save();
    } catch (AdmException $e) {
        $e->showHtml();
    }

    if ($returnCode === true && $weblinkIsNew && $gSettingsManager->getBool('system_notifications_new_entries')) {
        // Notification email for new entries
        $message = $gL10n->get('SYS_LINK_EMAIL_NOTIFICATION_MESSAGE', array($gCurrentOrganization->getValue('org_longname'), $_POST['lnk_url']. ' ('.$_POST['lnk_name'].')', $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gSettingsManager->getString('system_date'))));
        try {
            $notification = new Email();
            $notification->sendNotification($gL10n->get('SYS_LINK_EMAIL_NOTIFICATION_TITLE'), $message);
        } catch (AdmException $e) {
            $e->showHtml();
        }
    }

    unset($_SESSION['links_request']);
    $gNavigation->deleteLastUrl();

    admRedirect($gNavigation->getUrl());
// => EXIT
} elseif ($getMode === 2) {
    // delete current announcements, right checks were done before
    $link->delete();

    // Delete successful -> Return for XMLHttpRequest
    echo 'done';
} else {
    // Falls der mode unbekannt ist, ist natürlich Ende...
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}
