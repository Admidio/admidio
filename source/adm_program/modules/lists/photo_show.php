<?php
 /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id : die ID des Users dessen Bild angezeigt werden soll
 *
 *****************************************************************************/
require("../../system/common.php");
require("../../system/login_valid.php");

if(is_numeric($_GET['usr_id']))
{
	if($g_preferences['profile_photo_storage'] == 0)
    {
    	if(isset($_SESSION['profilphoto'][$_GET['usr_id']]) && $_SESSION['profilphoto'][$_GET['usr_id']] != "")
		{
			header("Content-Type: image/jpeg");
			// Ausgabe des Bildes aus dem Sessionarray
    		echo $_SESSION['profilphoto'][$_GET['usr_id']];
    		unset($_SESSION['profilphoto'][$_GET['usr_id']]);
		}
		else
		{
			header("Content-Type: image/png");
			readfile(THEME_SERVER_PATH. "/images/no_profile_pic.png");
		}
    }
    else
    {
     	if(file_exists(SERVER_PATH. "/adm_my_files/user_profile_photos/".$_GET['usr_id'].".jpg"))
     	{
     		header("Content-Type: image/jpeg");
     		readfile(SERVER_PATH. "/adm_my_files/user_profile_photos/".$_GET['usr_id'].".jpg");
     	}
     	else
     	{
     		header("Content-Type: image/png");
			readfile(THEME_SERVER_PATH. "/images/no_profile_pic.png");
     	}   	
    }
}
?>
