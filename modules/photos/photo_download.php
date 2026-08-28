<?php
/**
 ***********************************************************************************************
 * Photo download
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Creates a zip file on the fly with all photos including sub-albums and returns it
 *
 * Parameters:
 *
 * photo_uuid : UUID of album to download
 * photo_nr   : Number of photo that should be downloaded
 *
 *****************************************************************************/
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Photos\Entity\Album;
use Admidio\Photos\Service\PhotoService;

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // Initialize and check the parameters
    $getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'uuid', array('requireValue' => true));
    $getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'int');

    // check if the module is enabled and disallow access if it's disabled
    if ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    // check if download function is enabled
    if (!$gSettingsManager->getBool('photo_download_enabled')) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // create photo album object
    $photoAlbum = new Album($gDb);

    // get id of album
    $photoAlbum->readDataByUuid($getPhotoUuid);

    // check if the current user could view this photo album
    if (!$photoAlbum->isVisible()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $photoService = new PhotoService($gDb, $photoAlbum);
    $temporaryFile = false;

    if ($getPhotoNr == null) {
        $download = $photoService->createAlbumArchive();
        $temporaryFile = true;
    } else {
        $download = $photoService->getDownloadFile($getPhotoNr);
    }

    $fileSize = filesize($download['path']);
    if ($fileSize === false) {
        throw new Exception('SYS_FILE_NOT_EXIST');
    }

    header('Content-Type: ' . $download['contentType']);
    header('Content-Length: ' . $fileSize);
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename="' . $download['filename'] . '"');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: private');

    $file = fopen($download['path'], 'rb');
    if ($file === false) {
        throw new Exception('SYS_FILE_NOT_EXIST');
    }

    fpassthru($file);
    fclose($file);

    if ($temporaryFile) {
        try {
            FileSystemUtils::deleteFileIfExists($download['path']);
        } catch (RuntimeException $exception) {
            $gLogger->error('Could not delete file!', array('filePath' => $download['path']));
        }
    }
} catch (Throwable $e) {
    handleException($e);
}
