<?php
   /******************************************************************************
 * Profilphotoausgabe 
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id : die ID des Users dessen Foto angezeigt werden soll
 * new_photo : 0 (Default) es wird das aktuelle Foto angezeigt
 *             1 es wird das temporaere grade hochgeladene Foto angezeigt
 *
 *****************************************************************************/
require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/image.php');

// Initialize and check the parameters
$getUserId   = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', null, true);
$getNewPhoto = admFuncVariableIsValid($_GET, 'new_photo', 'boolean', 0);

// lokale Variablen der Uebergabevariablen initialisieren
$image         = null;
$picpath       = THEME_SERVER_PATH. '/images/no_profile_pic.png';

//Testen ob Recht besteht Profil einzusehn
if(!$gCurrentUser->viewProfile($getUserId))
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
	$user = new User($gDb, $gProfileFields, $getUserId);
	if(strlen($user->getValue('usr_photo')) != NULL)
    {
        $image = new Image();
        $image->setImageFromData(addslashes($user->getValue('usr_photo')));
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
    //$image->setImageFromData(addslashes(pack('H*', $gCurrentSession->getValue('ses_binary'))));
    $image->setImageFromData(addslashes($gCurrentSession->getValue('ses_binary')));   
}

header('Content-Type: '. $image->getMimeType());
$image->copyToBrowser();
$image->delete();
?>
