<?php
/**
 ***********************************************************************************************
 * Various functions for photo albums
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * photo_uuid    : UUID of photo album that should be edited
 * mode - new    : create a new photo album
 *      - change : edit a photo album
 *      - delete : delete a photo album
 *      - lock   : lock a photo album
 *      - unlock : unlock a photo album
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'string');
$getMode      = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('new', 'change', 'delete', 'lock', 'unlock')));

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_photo_module') === 0) {
    // check if the module is activated
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$_SESSION['photo_album_request'] = $_POST;

// create photo album object
$photoAlbum = new TablePhotos($gDb);

if ($getPhotoUuid !== '') {
    $photoAlbum->readDataByUuid($getPhotoUuid);
}

// check if the user is allowed to edit this photo album
if (!$photoAlbum->isEditable()) {
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    // => EXIT
}

// Location with the path from the database
$albumPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $photoAlbum->getValue('pho_id');

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    $exception->showText();
    // => EXIT
}

if ($getMode === 'new' || $getMode === 'change') {
    // Release (must be done first as this may not be set)
    if (!isset($_POST['pho_locked'])) {
        $_POST['pho_locked'] = 0;
    }

    if (strlen($_POST['pho_name']) === 0) {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('PHO_ALBUM'))));
        // => EXIT
    }

    if (strlen($_POST['pho_begin']) > 0) {
        $startDate = \DateTime::createFromFormat('Y-m-d', $_POST['pho_begin']);
        if ($startDate === false) {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_START'), 'YYYY-MM-DD')));
        // => EXIT
        } else {
            $_POST['pho_begin'] = $startDate->format('Y-m-d');
        }
    } else {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_START'))));
        // => EXIT
    }

    if (strlen($_POST['pho_end']) > 0) {
        $endDate = \DateTime::createFromFormat('Y-m-d', $_POST['pho_end']);
        if ($endDate === false) {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_END'), 'YYYY-MM-DD')));
        // => EXIT
        } else {
            $_POST['pho_end'] = $endDate->format('Y-m-d');
        }
    } else {
        $_POST['pho_end'] = $_POST['pho_begin'];
    }

    // Start must be before or equal to end
    if (strlen($_POST['pho_end']) > 0 && $_POST['pho_end'] < $_POST['pho_begin']) {
        $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
        // => EXIT
    }

    // set parent photo id
    $photoAlbumParent = new TablePhotos($gDb);
    $photoAlbumParent->readDataByUuid($_POST['parent_album_uuid']);
    $_POST['pho_pho_id_parent'] = $photoAlbumParent->getValue('pho_id');

    //  POST Write variables to the Role object
    foreach ($_POST as $key => $value) { // TODO possible security issue
        if (str_starts_with($key, 'pho_')) {
            $photoAlbum->setValue($key, $value);
        }
    }

    if ($getMode === 'new') {
        // write recordset with new album into database
        $photoAlbum->save();

        $error = $photoAlbum->createFolder();

        if (is_array($error)) {
            $photoAlbum->delete();

            // the corresponding folder could not be created
            $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php');
            $gMessage->show($gL10n->get($error['text'], array($error['path'], '<a href="mailto:'.$gSettingsManager->getString('email_administrator').'">', '</a>')));
            // => EXIT
        }

        if ($error === null && $gSettingsManager->getBool('system_notifications_new_entries')) {
            // Notification email for new entries
            $notification = new Email();
            try {
                $message = $gL10n->get('PHO_EMAIL_NOTIFICATION_MESSAGE', array($gCurrentOrganization->getValue('org_longname'), $_POST['pho_name'], $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME'), date($gSettingsManager->getString('system_date'))));
                $notification->sendNotification($gL10n->get('PHO_EMAIL_NOTIFICATION_TITLE'), $message);
            } catch (AdmException $e) {
                $e->showHtml();
            }
        }
    } else {
        // if begin date changed than the folder must also be changed
        if ($albumPath !== ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $_POST['pho_begin'] . '_' . $photoAlbum->getValue('pho_id')) {
            $newFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $_POST['pho_begin'] . '_' . $photoAlbum->getValue('pho_id');

            // das komplette Album in den neuen Ordner verschieben
            try {
                FileSystemUtils::moveDirectory($albumPath, $newFolder);
            } catch (\RuntimeException $exception) {
                $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php');
                $gMessage->show($gL10n->get('SYS_FOLDER_WRITE_ACCESS', array($newFolder, '<a href="mailto:'.$gSettingsManager->getString('email_administrator').'">', '</a>')));
                // => EXIT
            }
        }

        // now save changes to database
        $photoAlbum->save();
    }

    unset($_SESSION['photo_album_request'], $_SESSION['photo_album']);

    $gNavigation->deleteLastUrl();

    if ($getMode === 'new') {
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/photos/photos.php', array('photo_uuid' => $photoAlbum->getValue('pho_uuid'))));
    // => EXIT
    } else {
        admRedirect($gNavigation->getUrl());
        // => EXIT
    }
}



// delete photo album
elseif ($getMode === 'delete') {
    if ($photoAlbum->delete()) {
        echo 'done';
    }
    exit();
}

// lock photo album
elseif ($getMode === 'lock') {
    $photoAlbum->setValue('pho_locked', 1);
    $photoAlbum->save();

    echo 'done';
    exit();
}

// unlock photo album
elseif ($getMode === 'unlock') {
    $photoAlbum->setValue('pho_locked', 0);
    $photoAlbum->save();

    echo 'done';
    exit();
}
