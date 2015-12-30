<?php
/**
 ***********************************************************************************************
 * Show current profile photo or uploaded session photo
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
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
require('../../system/login_valid.php');

// Initialize and check the parameters
$getUserId   = admFuncVariableIsValid($_GET, 'usr_id',    'int', array('requireValue' => true));
$getNewPhoto = admFuncVariableIsValid($_GET, 'new_photo', 'bool');

// lokale Variablen der Uebergabevariablen initialisieren
$image   = null;
$picPath = THEME_SERVER_PATH. '/images/no_profile_pic.png';

// read user data and show error if user doesn't exists
$user = new User($gDb, $gProfileFields, $getUserId);

if($user->getValue('usr_id') == 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Testen ob Recht besteht Profil einzusehn
if(!$gCurrentUser->hasRightViewProfile($user))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Foto aus adm_my_files
if($gPreferences['profile_photo_storage'] == 1 && !$getNewPhoto)
{
    if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'.jpg'))
    {
        $picPath = SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'.jpg';
    }
    $image = new Image($picPath);
}
// Foto aus der Datenbank
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
// neues Foto, Ordnerspeicherung
elseif($gPreferences['profile_photo_storage'] == 1 && $getNewPhoto)
{
    $picPath = SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'_new.jpg';
    $image = new Image($picPath);
}
// neues Foto, Datenbankspeicherung
elseif($gPreferences['profile_photo_storage'] == 0 && $getNewPhoto)
{
    $image = new Image();
    $image->setImageFromData($gCurrentSession->getValue('ses_binary'));
}

header('Content-Type: '. $image->getMimeType());
$image->copyToBrowser();
$image->delete();
