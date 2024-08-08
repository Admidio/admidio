<?php
/**
 ***********************************************************************************************
 * Various functions for photo albums
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * photo_uuid    : UUID of photo album that should be edited
 * mode - edit   : create or edit a photo album
 *      - delete : delete a photo album
 *      - lock   : lock a photo album
 *      - unlock : unlock a photo album
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // Initialize and check the parameters
    $getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'lock', 'unlock')));

    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new AdmException('SYS_MODULE_DISABLED');
    }

    // create photo album object
    $photoAlbum = new TablePhotos($gDb);

    if ($getPhotoUuid !== '') {
        $photoAlbum->readDataByUuid($getPhotoUuid);
    }

    // check if the user is allowed to edit this photo album
    if (!$photoAlbum->isEditable()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    if ($getMode !== 'edit') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    }

    if ($getMode === 'edit') {
        if (isset($_SESSION['photosEditForm'])) {
            $photosEditForm = $_SESSION['photosEditForm'];
            $photosEditForm->validate($_POST);
        } else {
            throw new AdmException('SYS_INVALID_PAGE_VIEW');
        }

        if (strlen($_POST['pho_begin']) > 0) {
            $startDate = DateTime::createFromFormat('Y-m-d', $_POST['pho_begin']);
            if ($startDate === false) {
                throw new AdmException('SYS_DATE_INVALID', array('SYS_START', 'YYYY-MM-DD'));
            } else {
                $_POST['pho_begin'] = $startDate->format('Y-m-d');
            }
        } else {
            throw new AdmException('SYS_FIELD_EMPTY', array('SYS_START'));
        }

        if (strlen($_POST['pho_end']) > 0) {
            $endDate = DateTime::createFromFormat('Y-m-d', $_POST['pho_end']);
            if ($endDate === false) {
                throw new AdmException('SYS_DATE_INVALID', array('SYS_END', 'YYYY-MM-DD'));
            } else {
                $_POST['pho_end'] = $endDate->format('Y-m-d');
            }
        } else {
            $_POST['pho_end'] = $_POST['pho_begin'];
        }

        // Start must be before or equal to end
        if (strlen($_POST['pho_end']) > 0 && $_POST['pho_end'] < $_POST['pho_begin']) {
            throw new AdmException('SYS_DATE_END_BEFORE_BEGIN');
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

        if ($getPhotoUuid === '') {
            // write recordset with new album into database
            if ($photoAlbum->save()) {
                $error = $photoAlbum->createFolder();

                if (is_array($error)) {
                    $photoAlbum->delete();

                    // the corresponding folder could not be created
                    $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photos.php');
                    throw new AdmException($error['text'], array($error['path'], '<a href="mailto:' . $gSettingsManager->getString('email_administrator') . '">', '</a>'));
                } else {
                    // Notification email for new or changed entries to all members of the notification role
                    $photoAlbum->sendNotification();
                }
            }
        } else {
            // Location with the path from the database
            $albumPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $photoAlbum->getValue('pho_id');

            // if begin date changed than the folder must also be changed
            if ($albumPath !== ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $_POST['pho_begin'] . '_' . $photoAlbum->getValue('pho_id')) {
                try {
                    // move the complete album to the new folder
                    $newFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $_POST['pho_begin'] . '_' . $photoAlbum->getValue('pho_id');
                    FileSystemUtils::moveDirectory($albumPath, $newFolder);
                } catch (RuntimeException $exception) {
                    $gMessage->setForwardUrl(ADMIDIO_URL . FOLDER_MODULES . '/photos/photos.php');
                    throw new AdmException('SYS_FOLDER_WRITE_ACCESS', array($newFolder, '<a href="mailto:' . $gSettingsManager->getString('email_administrator') . '">', '</a>'));
                }
            }

            if ($photoAlbum->save()) {
                // Notification email for new or changed entries to all members of the notification role
                $photoAlbum->sendNotification();
            }
        }

        unset($_SESSION['photo_album']);

        $gNavigation->deleteLastUrl();

        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } // delete photo album
    elseif ($getMode === 'delete') {
        if ($photoAlbum->delete()) {
            echo 'done';
        }
        exit();
    } // lock photo album
    elseif ($getMode === 'lock') {
        $photoAlbum->setValue('pho_locked', 1);
        $photoAlbum->save();

        echo 'done';
        exit();
    } // unlock photo album
    elseif ($getMode === 'unlock') {
        $photoAlbum->setValue('pho_locked', 0);
        $photoAlbum->save();

        echo 'done';
        exit();
    }
} catch (AdmException|Exception $e) {
    if ($getMode === 'edit') {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
