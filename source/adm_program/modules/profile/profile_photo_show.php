<?php
   /******************************************************************************
 * Profilphotoausgabe 
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id : die ID des Users dessen Bild angezeigt werden soll
 * new_photo : 0 (Default) es wird das aktuelle Foto angezeigt
 *             1 es wird das temporaere grade hochgeladene Foto angezeigt
 *
 *****************************************************************************/
require("../../system/common.php");
require("../../system/login_valid.php");
//require("../../system/classes/image.php");

// lokale Variablen der Uebergabevariablen initialisieren
$req_usr_id    = 0;
$req_tmp_photo = 0;

// Uebergabevariablen pruefen

if(  (isset($_GET["usr_id"]) 
   && is_numeric($_GET["usr_id"]) == false)
|| isset($_GET["usr_id"]) == false)
{
    $g_message->show("invalid");
}
else
{
    $req_usr_id = $_GET["usr_id"];
}

if(isset($_GET["tmp_photo"]) && $_GET["tmp_photo"] == 1)
{
    $req_tmp_photo = 1;
}

//Testen ob Recht besteht Profil einzusehn
if(!$g_current_user->viewProfile($req_usr_id))
{
    $g_message->show("norights");
}
// Foto aus der Datenbank lesen und ausgeben

header("Content-Type: image/jpeg");
if($req_tmp_photo == true)
{
    echo $g_current_session->getValue("ses_blob");
}
else
{
    if($_GET["usr_id"] == $g_current_user->getValue("usr_id"))
    {
        $user = $g_current_user;
    }
    else
    {
        $user = new User($g_db, $_GET["usr_id"]);
    }
    
    if(strlen($user->getValue("usr_photo")) > 0)
    {
        echo $user->getValue("usr_photo");
    }
    else
    {
        // es wurde kein Bild gefunden, dann ein Dummy-Bild zurueckgeben
        $no_profile_pic = imagecreatefrompng(THEME_SERVER_PATH. "/images/no_profile_pic.png");
        echo imagepng($no_profile_pic);
    }
}

/*
// lokale Variablen der Uebergabevariablen initialisieren
$req_usr_id    = 0;
$req_new_photo = 0;
$image         = null;
$picpath       = THEME_SERVER_PATH. "/images/no_profile_pic.png";

// Uebergabevariablen pruefen

if(  (isset($_GET["usr_id"]) && is_numeric($_GET["usr_id"]) == false)|| isset($_GET["usr_id"]) == false)
{
    $g_message->show("invalid");
}
else
{
    $req_usr_id = $_GET["usr_id"];
}

if(isset($_GET["new_photo"]) && $_GET["new_photo"] == 1)
{
    $req_new_photo = 1;
}

//Testen ob Recht besteht Profil einzusehn
if(!$g_current_user->viewProfile($req_usr_id))
{
    $g_message->show("norights");
}


//Bild aus adm_my_files
if($g_preferences['profile_photo_storage'] == 1 && $req_new_photo == 0)
{
	$picpath = SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id.".jpg";
	$image = new Image($picpath);
}
//Bild der Datenbank
elseif($g_preferences['profile_photo_storage'] == 0 && $req_new_photo == 0)
{
	$user = new User($g_db, $req_usr_id);
	if(strlen($user->getValue("usr_photo")) != NULL)
    {
        header("Content-Type: image/jpeg");
        $test = $user->getValue("usr_photo");
        echo addslashes($test); 
        exit();
        $image = new Image();
        $image->setImageFromData(addslashes($user->getValue("usr_photo")));
    }
    else
    {
    	$image = new Image($picpath);
    }
}
//neues Bild
else
{
	$picpath = SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id."_new.jpg";
	$image = new Image($picpath);
}

//header("Content-Type: ". $image->getMimeType());
$image->copyToBrowser();
$image->delete();*/
?>
