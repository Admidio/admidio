<?php
/**
 ***********************************************************************************************
 * Photofunktionen
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * photo_uuid: UUID of the photo album
 * mode:       delete - Delete the photo
 *             rotate - Rotate the photo
 * direction:  left  - Rotate image to the left
 *             right - Rotate image to the right
 * photo_nr:   Number of the photo that should be shown
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Photos\Entity\Album;
use Admidio\Photos\Service\PhotoService;

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // Initialize and check the parameters
    $getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'uuid', array('requireValue' => true));
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('delete', 'rotate')));
    $getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'int', array('requireValue' => true));
    $getDirection = admFuncVariableIsValid($_GET, 'direction', 'string', array('validValues' => array('left', 'right')));

    if ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // check if current user has right to upload photos
    if (!$gCurrentUser->isAdministratorPhotos()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // create photo album object
    $photoAlbum = new Album($gDb);
    $photoAlbum->readDataByUuid($getPhotoUuid);

    // check if the user is allowed to edit this photo album
    if (!$photoAlbum->isEditable()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

    // Rotate the photo by 90°
    if ($getMode === 'rotate') {
        if ($getDirection !== '') {
            (new PhotoService($gDb, $photoAlbum))->rotatePhoto($getPhotoNr, $getDirection);
        }

        echo 'done';
        exit();
    } // delete photo from filesystem and update photo album
    elseif ($getMode === 'delete') {
        (new PhotoService($gDb, $photoAlbum))->deletePhoto($getPhotoNr);

        $_SESSION['photo_album'] = $photoAlbum;

        // Delete successful -> return for XMLHttpRequest
        echo 'done';
        exit();
    }
} catch (Throwable $e) {
    handleException($e);
}
