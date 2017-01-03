<?php
/**
 ***********************************************************************************************
 * Show current profile photo or uploaded session photo
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * usr_id    : id of user whose photo should be changed
 * new_photo : false - show current stored user photo
 *             true  - show uploaded photo of current session
 ***********************************************************************************************
 */
require('../../system/common.php');

// Initialize and check the parameters
$getUserId   = admFuncVariableIsValid($_GET, 'usr_id',    'int', array('requireValue' => true));
$getNewPhoto = admFuncVariableIsValid($_GET, 'new_photo', 'bool');

// lokale Variablen der Uebergabevariablen initialisieren
$image   = null;
$picPath = THEME_ADMIDIO_PATH. '/images/no_profile_pic.png';

// read user data and show error if user doesn't exists
$user = new User($gDb, $gProfileFields, $getUserId);

if((int) $user->getValue('usr_id') === 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// if user has no right to view profile then show dummy photo
if(!$gCurrentUser->hasRightViewProfile($user))
{
    $image = new Image($picPath);
}
else
{
    // show photo from folder adm_my_files
    if($gPreferences['profile_photo_storage'] == 1 && !$getNewPhoto)
    {
        $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $getUserId . '.jpg';
        if(file_exists($file))
        {
            $picPath = $file;
        }
        $image = new Image($picPath);
    }
    // show photo from database
    elseif($gPreferences['profile_photo_storage'] == 0 && !$getNewPhoto)
    {
        if(strlen($user->getValue('usr_photo')) != null)
        {
            $image = new Image();
            $image->setImageFromData($user->getValue('usr_photo'));
        }
        else
        {
            $image = new Image($picPath);
        }
    }
    // show temporary saved new photo from upload in filesystem
    elseif($gPreferences['profile_photo_storage'] == 1 && $getNewPhoto)
    {
        $picPath = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $getUserId . '_new.jpg';
        $image = new Image($picPath);
    }
    // show temporary saved new photo from upload in database
    elseif($gPreferences['profile_photo_storage'] == 0 && $getNewPhoto)
    {
        $image = new Image();
        $image->setImageFromData($gCurrentSession->getValue('ses_binary'));
    }
}

header('Content-Type: '. $image->getMimeType());
$image->copyToBrowser();
$image->delete();
