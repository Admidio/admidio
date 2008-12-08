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
require("../../system/classes/image.php");

// lokale Variablen der Uebergabevariablen initialisieren
$req_usr_id    = 0;
$req_new_photo = 0;
$image         = null;

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
    $req_tmp_photo = 1;
}

//Testen ob Recht besteht Profil einzusehn
if(!$g_current_user->viewProfile($req_usr_id))
{
    $g_message->show("norights");
}

//Pfad zusammensetzen
if($req_tmp_photo == 1)
{
	$picpath = SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id."_new.jpg";
}
else
{
	$picpath = SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id.".jpg";
}

//Nachsehen ob angefordertes Bild existiert
if(file_exists($picpath))
{
	$image = new Image($picpath);
}
else
{
	$image = new Image( THEME_SERVER_PATH. "/images/no_profile_pic.png");
}
header("Content-Type: ". $image->getMimeType());
$image->copyToBrowser();
$image->delete();
?>
