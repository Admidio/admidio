<?php
   /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
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
        $no_profile_pic = imagecreatefrompng($g_root_path. "/adm_program/images/no_profile_pic.png");
        echo imagepng($no_profile_pic);
    }
}

?>
