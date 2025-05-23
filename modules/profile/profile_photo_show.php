<?php
/**
 ***********************************************************************************************
 * Show current profile photo or uploaded session photo
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * usr_uuid  : Uuid of user whose photo should be changed
 * new_photo : false - show current stored user photo
 *             true  - show uploaded photo of current session
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Image;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');

    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('requireValue' => true));
    $getNewPhoto = admFuncVariableIsValid($_GET, 'new_photo', 'bool');

    // read user data and show error if user doesn't exist
    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

    if ((int)$user->getValue('usr_id') === 0) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    // Initialize local variables of the transfer variables
    $image = null;
    if ((int) $user->getValue('GENDER', 'database') === 2) {
        $photoPath = getThemedFile('/images/profile-photo-female.png');
    } else {
        $photoPath = getThemedFile('/images/profile-photo-male.png');
    }

    if ($gCurrentUser->hasRightViewProfile($user)) {
        if ($getNewPhoto) {
            // show temporary saved new photo from upload in database
            if ((int)$gSettingsManager->get('profile_photo_storage') === 0) {
                $image = new Image();
                $image->setImageFromData($gCurrentSession->getValue('ses_binary'));
            } // show temporary saved new photo from upload in filesystem
            else {
                $photoPath = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '_new.jpg';
                $image = new Image($photoPath);
            }
        } else {
            // show photo from database
            if ((int)$gSettingsManager->get('profile_photo_storage') === 0) {
                if ((string)$user->getValue('usr_photo') !== '') {
                    $image = new Image();
                    $image->setImageFromData($user->getValue('usr_photo'));
                } else {
                    $image = new Image($photoPath);
                }
            } // show photo from folder adm_my_files
            else {
                $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '.jpg';
                if (is_file($file)) {
                    $photoPath = $file;
                }
                $image = new Image($photoPath);
            }
        }
    } else {
        // if user has no right to view profile then show dummy photo
        $image = new Image($photoPath);
    }

    header('Content-Type: ' . $image->getMimeType());
    // Caching-Header setzen
    header("Last-Modified: " . $user->getValue('usr_timestamp_changed', 'D, d M Y H:i:s') . " GMT");
    header("ETag: " . md5_file($photoPath));

    $image->copyToBrowser();
    $image->delete();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
