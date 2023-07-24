<?php
/**
 ***********************************************************************************************
 * Photoresizer
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * photo_uuid : UUID of the album from which the image should be shown
 * photo_nr   : Number of the image to be displayed
 * max_width  : maximum width to which the image can be scaled,
 *              if not set, the default size is taken
 * max_height : maximum height to which the image can be scaled,
 *              if not set, the default size is taken
 * thumb      : If is set to true a thumbnail in the size of the default
 *              size is returned
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getPhotoUuid = admFuncVariableIsValid($_GET, 'photo_uuid', 'string');
$getPhotoNr   = admFuncVariableIsValid($_GET, 'photo_nr', 'int');
$getMaxWidth  = admFuncVariableIsValid($_GET, 'max_width', 'int', array('defaultValue' => 0));
$getMaxHeight = admFuncVariableIsValid($_GET, 'max_height', 'int', array('defaultValue' => 0));
$getThumbnail = admFuncVariableIsValid($_GET, 'thumb', 'bool');
$image        = null;

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_photo_module') === 0) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
// => EXIT
} elseif ((int) $gSettingsManager->get('enable_photo_module') === 2) {
    // only logged in users can access the module
    require(__DIR__ . '/../../system/login_valid.php');
}

// read album data out of session or database
if (isset($_SESSION['photo_album']) && (int) $_SESSION['photo_album']->getValue('pho_uuid') === $getPhotoUuid) {
    $photoAlbum =& $_SESSION['photo_album'];
} else {
    $photoAlbum = new TablePhotos($gDb);
    $photoAlbum->readDataByUuid($getPhotoUuid);
    $_SESSION['photo_album'] = $photoAlbum;
}

// check if the current user could view this photo album
if (!$photoAlbum->isVisible()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// compose image path
$albumFolder  = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $photoAlbum->getValue('pho_id');
$picPath      = $albumFolder . '/' . $getPhotoNr . '.jpg';
$picThumbPath = $albumFolder . '/thumbnails/' . $getPhotoNr . '.jpg';

// output the determined image path in debug mode
$gLogger->info('ImagePath: ' . $picPath);

if ($getThumbnail) {
    if ($getPhotoNr > 0) {
        $thumbLength = null;

        // If thumbnail exists detect longer page
        if (is_file($picThumbPath)) {
            $imageProperties = getimagesize($picThumbPath);
            if (is_array($imageProperties)) {
                $thumbLength = max($imageProperties[0], $imageProperties[1]);
            }
        }

        // Check whether the image is stored as a thumbnail in the appropriate size, if not, create it.
        if (!is_file($picThumbPath) || $thumbLength !== $gSettingsManager->getInt('photo_thumbs_scale')) {
            try {
                // If thumnail directory does not exist, create one
                FileSystemUtils::createDirectoryIfNotExists($albumFolder . '/thumbnails');
            } catch (\RuntimeException $exception) {
                $gLogger->error('Could not create directory!', array('directoryPath' => $albumFolder . '/thumbnails'));
                // TODO
            }

            // now create thumbnail
            $image = new Image($picPath);
            $image->scaleLargerSide($gSettingsManager->getInt('photo_thumbs_scale'));
            $image->copyToFile(null, $picThumbPath);
        } else {
            header('Content-Type: image/jpeg');
            readfile($picThumbPath);
        }
    } else {
        // if no image was passed, then display NoPix
        $image = new Image(THEME_PATH . '/images/no_photo_found.png');
        $image->scaleLargerSide($gSettingsManager->getInt('photo_thumbs_scale'));
    }
} else {
    if (!is_file($picPath)) {
        $picPath = THEME_PATH . '/images/no_photo_found.png';
    }

    // read image from filesystem and scale it if necessary
    $image = new Image($picPath);
    if ($getMaxWidth > 0 || $getMaxHeight > 0) {
        $image->scale($getMaxWidth, $getMaxHeight);
    } else {
        // image should not be scaled so read the size of the image
        list($getMaxWidth, $getMaxHeight) = $image->getImageSize();
    }
}

if ($image !== null) {
    // insert copyright text into photo if photo size is larger than 200px
    if (($getMaxWidth > 200) && $gSettingsManager->getString('photo_image_text') !== '') {
        if ($gSettingsManager->getInt('photo_image_text_size') > 0) {
            $fontSize = $getMaxWidth / $gSettingsManager->getInt('photo_image_text_size');
        } else {
            $fontSize = $getMaxWidth / 40;
        }
        $imageSize = $image->getImageSize();
        $fontX = $fontSize;
        $fontY = $imageSize[1] - $fontSize;
        $fontColor = imagecolorallocate($image->getImageResource(), 255, 255, 255);
        $fontTtf = ADMIDIO_PATH . '/adm_program/system/fonts/mrphone.ttf';

        // show name of photograph if set otherwise show name of organization
        if ((string) $photoAlbum->getValue('pho_photographers') !== '') {
            $text = '© ' . $photoAlbum->getValue('pho_photographers', 'database');
        } else {
            $text = $gSettingsManager->getString('photo_image_text');
        }

        imagettftext($image->getImageResource(), $fontSize, 0, (int) $fontX, (int) $fontY, $fontColor, $fontTtf, $text);
    }

    // Return of the new image
    header('Content-Type: '. $image->getMimeType());
    $image->copyToBrowser();
    $image->delete();
}
