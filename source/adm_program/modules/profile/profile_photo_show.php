<?php
   /******************************************************************************
 * Photoresizer 
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id : die ID des Users dessen Bild angezeigt werden soll
 * tmp_photo : 0 (Default) es wird das Foto aus der User-Tabelle angezeigt
 *             1 es wird das temporaere Foto aus der Session-Tabelle angezeigt
 *
 *****************************************************************************/
require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/image.php");

// lokale Variablen der Uebergabevariablen initialisieren
$req_usr_id    = 0;
$req_tmp_photo = 0;
$image         = null;

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

$image = new Image();

//Testen ob Recht besteht Profil einzusehn
if(!$g_current_user->viewProfile($req_usr_id))
{
    $g_message->show("norights");
}
// Foto aus der Datenbank lesen und ausgeben

if($req_tmp_photo == true)
{
    $image->setImageFromData(addslashes($g_current_session->getValue("ses_blob")));
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
        $image->setImageFromData(addslashes($user->getValue("usr_photo")));
    }
    else
    {
        // es wurde kein Bild gefunden, dann ein Dummy-Bild zurueckgeben
        
        $image->setImageFromPath(THEME_SERVER_PATH. "/images/no_profile_pic.png");
    }
}

header("Content-Type: ". $image->getMimeType());
$image->copyToBrowser();

?>
