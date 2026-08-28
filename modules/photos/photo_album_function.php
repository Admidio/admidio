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
use Admidio\Infrastructure\Exception;
use Admidio\Photos\Entity\Album;
use Admidio\Photos\Service\AlbumService;
use Admidio\Infrastructure\Utils\SecurityUtils;

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // Initialize and check the parameters
    $getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'uuid');
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true, 'validValues' => array('edit', 'delete', 'lock', 'unlock')));

    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // create photo album object
    $photoAlbum = new Album($gDb);

    if ($getPhotoUuid !== '') {
        $photoAlbum->readDataByUuid($getPhotoUuid);
    }

    // check if the user is allowed to edit this photo album
    if (!$photoAlbum->isEditable()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if ($getMode !== 'edit') {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
    }

    if ($getMode === 'edit') {
        // check form field input and sanitized it from malicious content
        $photosEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $photosEditForm->validate($_POST);

        (new AlbumService($gDb))->saveData(
            $photoAlbum,
            $formValues,
            (string)$formValues['parent_album_uuid']
        );

        unset($_SESSION['photo_album']);

        $gNavigation->deleteLastUrl();

        echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
        exit();
    } // delete photo album
    elseif ($getMode === 'delete') {
        $photoAlbum->delete();
        echo json_encode(array('status' => 'success'));
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
} catch (Throwable $e) {
    handleException($e, in_array($getMode, array('edit', 'delete')));
}
