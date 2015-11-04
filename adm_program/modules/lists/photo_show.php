<?php
 /******************************************************************************
 * Show user photo
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id : Id of the user whose photo should be shown
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', array('requireValue' => true, 'directOutput' => true));

$user      = new User($gDb, $gProfileFields, $getUserId);
$userPhoto = $user->getValue('usr_photo');

// if user has no photo or current user is not allowed to see photos then show default photo
if(strlen($userPhoto)==0 || !$gCurrentUser->hasRightViewProfile($user))
{
    header('Content-Type: image/png');
    echo readfile(THEME_SERVER_PATH. '/images/no_profile_pic.png');
}
else
{
    header('Content-Type: image/jpeg');
    echo $userPhoto;
}
