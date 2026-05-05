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

    // Default photo path based on gender (no DB BLOB involved yet).
    if ((int) $user->getValue('GENDER', 'database') === 2) {
        $photoPath = getThemedFile('/images/profile-photo-female.png');
    } else {
        $photoPath = getThemedFile('/images/profile-photo-male.png');
    }

    // For the new-photo preview case, we need the binary data from the session
    // before we release the session write-lock.
    $sessionPhotoData = null;
    if ($getNewPhoto
        && $gCurrentUser->hasRightViewProfile($user)
        && (int)$gSettingsManager->get('profile_photo_storage') === 0) {
        $sessionPhotoData = $gCurrentSession->getValue('ses_binary');
    }

    // Release the PHP session write-lock now so other concurrent requests
    // (e.g. the nav-bar photo) are not queued behind the BLOB fetch below.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Fast 304 check before any expensive I/O.
    $etag = '"' . sha1($user->getValue('usr_id') . '|' . $user->getValue('usr_timestamp_changed', 'Y-m-d H:i:s') . '|' . (int)$getNewPhoto) . '"';
    $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) : '';
    header('ETag: ' . $etag);
    header('Last-Modified: ' . $user->getValue('usr_timestamp_changed', 'D, d M Y H:i:s') . ' GMT');
    if ($ifNoneMatch === $etag) {
        http_response_code(304);
        exit;
    }

    // Load the image — BLOB fetch (if DB storage) happens here, after session is released.
    $image = null;
    if ($gCurrentUser->hasRightViewProfile($user)) {
        if ($getNewPhoto) {
            if ((int)$gSettingsManager->get('profile_photo_storage') === 0) {
                $image = new Image();
                $image->setImageFromData((string)$sessionPhotoData);
            } else {
                $photoPath = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '_new.jpg';
                $image = new Image($photoPath);
            }
        } else {
            if ((int)$gSettingsManager->get('profile_photo_storage') === 0) {
                // getValue('usr_photo') triggers a single lazy-fetch of the BLOB column.
                $photoData = $user->getValue('usr_photo');
                if ((string)$photoData !== '') {
                    $image = new Image();
                    $image->setImageFromData($photoData);
                } else {
                    $image = new Image($photoPath);
                }
            } else {
                $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '.jpg';
                if (is_file($file)) {
                    $photoPath = $file;
                }
                $image = new Image($photoPath);
            }
        }
    } else {
        $image = new Image($photoPath);
    }

    header('Content-Type: ' . $image->getMimeType());
    $image->copyToBrowser();
    $image->delete();
} catch (Throwable $e) {
    handleException($e);
}
