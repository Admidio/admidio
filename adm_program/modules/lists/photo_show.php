<?php
 /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id : die ID des Users dessen Bild angezeigt werden soll
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', null, true, null, true);

// pruefen, ob Profilfoto aus DB oder Filesystem kommt
if($gPreferences['profile_photo_storage'] == 0)
{
    // Profilbild aus DB einlesen
    $sql = 'SELECT usr_photo FROM '.TBL_USERS.' WHERE usr_id = '.$getUserId;
    $gDb->query($sql);
    $row = $gDb->fetch_array();

    if(strlen($row['usr_photo']) > 0)
    {
        header('Content-Type: image/jpeg');
        echo $row['usr_photo'];
        exit();
    }
}
else
{
    // Profilbild aus dem Filesystem einlesen bzw. Default-Bild anzeigen
    if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'.jpg'))
    {
        header('Content-Type: image/jpeg');
        readfile(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'.jpg');
        exit();
    }
}

// wurde kein Userbild gefunden, dann immer das Default-Bild ausgeben
header('Content-Type: image/png');
readfile(THEME_SERVER_PATH. '/images/no_profile_pic.png');

?>
