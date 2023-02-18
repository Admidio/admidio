<?php
/**
 ***********************************************************************************************
 * Show current profile photo or uploaded session photo
 *
 * @copyright 2004-2023 The Admidio Team
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
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string', array('requireValue' => true));
$getNewPhoto = admFuncVariableIsValid($_GET, 'new_photo', 'bool');

// read user data and show error if user doesn't exists
$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

if ((int) $user->getValue('usr_id') === 0) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// lokale Variablen der Uebergabevariablen initialisieren
$image   = null;
$picPath = THEME_PATH. '/images/no_profile_pic.png';

if ($gCurrentUser->hasRightViewProfile($user)) {
    if ($getNewPhoto) {
        // show temporary saved new photo from upload in database
        if ((int) $gSettingsManager->get('profile_photo_storage') === 0) {
            $image = new Image();
            $image->setImageFromData($gCurrentSession->getValue('ses_binary'));
        }
        // show temporary saved new photo from upload in filesystem
        else {
            $picPath = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '_new.jpg';
            $image = new Image($picPath);
        }
    } else {
        // show photo from database
        if ((int) $gSettingsManager->get('profile_photo_storage') === 0) {
            if ((string) $user->getValue('usr_photo') !== '') {
                $image = new Image();
                $image->setImageFromData($user->getValue('usr_photo'));
            } else {
                $image = new Image($picPath);
            }
        }
        // show photo from folder adm_my_files
        else {
            $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '.jpg';
            if (is_file($file)) {
                $picPath = $file;
            }
            $image = new Image($picPath);
        }
    }
} else {
    // if user has no right to view profile then show dummy photo
    $image = new Image($picPath);
}

header('Content-Type: '. $image->getMimeType());
$image->copyToBrowser();
$image->delete();
