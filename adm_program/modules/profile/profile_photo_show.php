<?php
   /******************************************************************************
 * Profilphotoausgabe 
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id : die ID des Users dessen Foto angezeigt werden soll
 * new_photo : 0 (Default) es wird das aktuelle Foto angezeigt
 *             1 es wird das temporaere grade hochgeladene Foto angezeigt
 *
 *****************************************************************************/
require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/image.php');

// Uebergabevariablen pruefen und ggf. initialisieren
$get_usr_id    = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', null, true);
$get_new_photo = admFuncVariableIsValid($_GET, 'new_photo', 'boolean', 0);

// lokale Variablen der Uebergabevariablen initialisieren
$image         = null;
$picpath       = THEME_SERVER_PATH. '/images/no_profile_pic.png';

//Testen ob Recht besteht Profil einzusehn
if(!$g_current_user->viewProfile($get_usr_id))
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

//Foto aus adm_my_files
if($g_preferences['profile_photo_storage'] == 1 && $get_new_photo == 0)
{
	if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$get_usr_id.'.jpg'))
	{
		$picpath = SERVER_PATH. '/adm_my_files/user_profile_photos/'.$get_usr_id.'.jpg';
	}
	$image = new Image($picpath);
}
//Foto aus der Datenbank
elseif($g_preferences['profile_photo_storage'] == 0 && $get_new_photo == 0)
{
	$user = new User($g_db, $get_usr_id);
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
elseif($g_preferences['profile_photo_storage'] == 1 && $get_new_photo == 1)
{
	$picpath = SERVER_PATH. '/adm_my_files/user_profile_photos/'.$get_usr_id.'_new.jpg';
	$image = new Image($picpath);
}
//neues Foto, Datenbankspeicherung
elseif($g_preferences['profile_photo_storage'] == 0 && $get_new_photo == 1)
{
   	$image = new Image();
    //$image->setImageFromData(addslashes(pack('H*', $g_current_session->getValue('ses_binary'))));
    $image->setImageFromData(addslashes($g_current_session->getValue('ses_binary')));   
}

header('Content-Type: '. $image->getMimeType());
$image->copyToBrowser();
$image->delete();
?>
