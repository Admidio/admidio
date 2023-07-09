<?php
/**
 ***********************************************************************************************
 * Photofunktionen
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * photo_uuid: UUID of the photo album
 * job:      delete - Delete the photo
 *           rotate - Rotate the photo
 * direction: left  - Rotate image to the left
 *            right - Rotate image to the right
 * photo_nr:  Number of the photo that should be shown
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'string', array('requireValue' => true));
$getJob       = admFuncVariableIsValid($_GET, 'job', 'string', array('requireValue' => true, 'validValues' => array('delete', 'rotate')));
$getPhotoNr   = admFuncVariableIsValid($_GET, 'photo_nr', 'int', array('requireValue' => true));
$getDirection = admFuncVariableIsValid($_GET, 'direction', 'string', array('validValues' => array('left', 'right')));

if ((int) $gSettingsManager->get('enable_photo_module') === 0) {
    // check if the module is activated
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// check if current user has right to upload photos
if (!$gCurrentUser->editPhotoRight()) {
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    // => EXIT
}

/**
 * Loeschen eines Thumbnails
 * @param TablePhotos $photoAlbum Referenz auf Objekt des relevanten Albums
 * @param int         $picNr      Nr des Bildes dessen Thumbnail geloescht werden soll
 */
function deleteThumbnail(TablePhotos $photoAlbum, $picNr)
{
    // Ordnerpfad zusammensetzen
    $photoPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/'.$photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . (int) $photoAlbum->getValue('pho_id') . '/thumbnails/' . $picNr . '.jpg';
    try {
        FileSystemUtils::deleteFileIfExists($photoPath);
    } catch (\RuntimeException $exception) {
        $GLOBALS['gLogger']->error('Could not delete file!', array('filePath' => $photoPath));
        // TODO
    }
}

/**
 * Delete the photo from the filesystem and update number of photos in database.
 * @param TablePhotos $photoAlbum
 * @param int $picNr
 */
function deletePhoto(TablePhotos $photoAlbum, $picNr)
{
    // get album folder path
    $albumPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . (int) $photoAlbum->getValue('pho_id');

    // delete photos
    try {
        FileSystemUtils::deleteFileIfExists($albumPath.'/'.$picNr.'.jpg');
        FileSystemUtils::deleteFileIfExists($albumPath.'/originals/'.$picNr.'.jpg');
        FileSystemUtils::deleteFileIfExists($albumPath.'/originals/'.$picNr.'.png');
    } catch (\RuntimeException $exception) {
        $GLOBALS['gLogger']->error(
            'Could not delete file!',
            array('filePaths' => array(
                $albumPath.'/'.$picNr.'.jpg',
                $albumPath.'/originals/'.$picNr.'.jpg',
                $albumPath.'/originals/'.$picNr.'.png'
            ))
        );
        // TODO
    }

    // Rename the remaining images and delete thumbnails
    $newPicNr = $picNr;
    $thumbnailDelete = false;

    for ($actPicNr = 1; $actPicNr <= (int) $photoAlbum->getValue('pho_quantity'); ++$actPicNr) {
        if (is_file($albumPath.'/'.$actPicNr.'.jpg')) {
            if ($actPicNr > $newPicNr) {
                try {
                    FileSystemUtils::moveFile($albumPath.'/'.$actPicNr.'.jpg', $albumPath.'/'.$newPicNr.'.jpg');
                    FileSystemUtils::moveFile($albumPath.'/originals/'.$actPicNr.'.jpg', $albumPath.'/originals/'.$newPicNr.'.jpg');
                    FileSystemUtils::moveFile($albumPath.'/originals/'.$actPicNr.'.png', $albumPath.'/originals/'.$newPicNr.'.png');
                } catch (\RuntimeException $exception) {
                    $GLOBALS['gLogger']->error(
                        'Could not move file!',
                        array(
                            'from' => array(
                                $albumPath.'/'.$actPicNr.'.jpg',
                                $albumPath.'/originals/'.$actPicNr.'.jpg',
                                $albumPath.'/originals/'.$actPicNr.'.png'
                            ),
                            'to' => array(
                                $albumPath.'/'.$newPicNr.'.jpg',
                                $albumPath.'/originals/'.$newPicNr.'.jpg',
                                $albumPath.'/originals/'.$newPicNr.'.png'
                            )
                        )
                    );
                    // TODO
                }
                ++$newPicNr;
            }
        } else {
            $thumbnailDelete = true;
        }

        if ($thumbnailDelete) {
            // Delete all thumbnails starting from the deleted image
            deleteThumbnail($photoAlbum, $actPicNr);
        }
    }//for

    // change quantity of images within the album
    $photoAlbum->setValue('pho_quantity', (int) $photoAlbum->getValue('pho_quantity') - 1);
    $photoAlbum->save();
}

// create photo album object
$photoAlbum = new TablePhotos($gDb);
$photoAlbum->readDataByUuid($getPhotoUuid);

// check if the user is allowed to edit this photo album
if (!$photoAlbum->isEditable()) {
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    // => EXIT
}

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    $exception->showText();
    // => EXIT
}

// Rotate the photo by 90°
if ($getJob === 'rotate') {
    if ($getDirection !== '') {
        deleteThumbnail($photoAlbum, $getPhotoNr);

        // get album folder path with image file name
        $photoPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . (int) $photoAlbum->getValue('pho_id') . '/' . $getPhotoNr . '.jpg';

        // rotate image
        $image = new Image($photoPath);
        $image->rotate($getDirection);
        $image->delete();
    }

    echo 'done';
    exit();
}
// delete photo from filesystem and update photo album
elseif ($getJob === 'delete') {
    deletePhoto($photoAlbum, $getPhotoNr);

    $_SESSION['photo_album'] = $photoAlbum;

    // Delete successful -> return for XMLHttpRequest
    echo 'done';
    exit();
}
