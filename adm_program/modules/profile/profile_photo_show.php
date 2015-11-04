<?php
/******************************************************************************
 * Show current profile photo or uploaded session photo
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id    : id of user whose photo should be changed
 * new_photo : 0 (Default) show current stored user photo
 *             1 show uploaded photo of current session
 *
 *****************************************************************************/
require('../../system/common.php');
require('../../system/login_valid.php');

// Initialize and check the parameters
$getUserId   = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', array('requireValue' => true));
$getNewPhoto = admFuncVariableIsValid($_GET, 'new_photo', 'boolean');

// lokale Variablen der Uebergabevariablen initialisieren
$image         = null;
$picpath       = THEME_SERVER_PATH. '/images/no_profile_pic.png';

// read user data and show error if user doesn't exists
$user = new User($gDb, $gProfileFields, $getUserId);

if($user->getValue('usr_id') == 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

//Testen ob Recht besteht Profil einzusehn
if(!$gCurrentUser->hasRightViewProfile($user))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

//Foto aus adm_my_files
if($gPreferences['profile_photo_storage'] == 1 && $getNewPhoto == 0)
{
    if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'.jpg'))
    {
        $picpath = SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'.jpg';
    }
    $image = new Image($picpath);
}
//Foto aus der Datenbank
elseif($gPreferences['profile_photo_storage'] == 0 && $getNewPhoto == 0)
{
    if(strlen($user->getValue('usr_photo')) != NULL)
    {
        $image = new Image();
        $image->setImageFromData($user->getValue('usr_photo'));
    }
    else
    {
        $image = new Image($picpath);
    }
}
//neues Foto, Ordnerspeicherung
elseif($gPreferences['profile_photo_storage'] == 1 && $getNewPhoto == 1)
{
    $picpath = SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'_new.jpg';
    $image = new Image($picpath);
}
//neues Foto, Datenbankspeicherung
elseif($gPreferences['profile_photo_storage'] == 0 && $getNewPhoto == 1)
{
    $image = new Image();
    $image->setImageFromData($gCurrentSession->getValue('ses_binary'));
}

header('Content-Type: '. $image->getMimeType());
$image->copyToBrowser();
$image->delete();
