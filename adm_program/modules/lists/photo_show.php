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
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

if(isset($_GET['usr_id']) && is_numeric($_GET['usr_id']))
{
    // pruefen, ob Profilfoto aus DB oder Filesystem kommt
    if($g_preferences['profile_photo_storage'] == 0)
    {
        // Profilbild aus DB einlesen
        $sql = 'SELECT usr_photo FROM '.TBL_USERS.' WHERE usr_id = '.$_GET['usr_id'];
        $g_db->query($sql);
        $row = $g_db->fetch_array();

        if(strlen($row['usr_photo']) > 0)
        {
            header('Content-Type: image/jpeg');
            echo $row['usr_photo'];
        }
        else
        {
            // der Benutzer besitzt kein Bild => Default-Bild anzeigen
            header('Content-Type: image/png');
            readfile(THEME_SERVER_PATH. '/images/no_profile_pic.png');
        }
    }
    else
    {
        // Profilbild aus dem Filesystem einlesen bzw. Default-Bild anzeigen
        if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$_GET['usr_id'].'.jpg'))
        {
            header('Content-Type: image/jpeg');
            readfile(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$_GET['usr_id'].'.jpg');
        }
        else
        {
            header('Content-Type: image/png');
            readfile(THEME_SERVER_PATH. '/images/no_profile_pic.png');
        }
    }
}
?>
