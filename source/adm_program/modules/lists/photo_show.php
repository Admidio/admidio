<?php
 /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
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
$user = new User($gDb, $gProfileFields, $getUserId);

$userPhoto = $user->getValue('usr_photo');

// wurde kein Userbild gefunden oder hat der User nicht das recht das Bild zu sehen, dann immer das Default-Bild ausgeben
if(strlen($userPhoto)==0 || !$gCurrentUser->viewProfile($user))
{
    header('Content-Type: image/png');
    readfile(THEME_SERVER_PATH. '/images/no_profile_pic.png');
}
else
{
	header('Content-Type: image/jpeg');
    echo $userPhoto;
}



?>
