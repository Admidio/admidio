<?php
/**
 ***********************************************************************************************
 * Show user photo
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * usr_id : Id of the user whose photo should be shown
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'int', array('requireValue' => true, 'directOutput' => true));

$user      = new User($gDb, $gProfileFields, $getUserId);
$userPhoto = $user->getValue('usr_photo');

// if user has no photo or current user is not allowed to see photos then show default photo
if(strlen($userPhoto) === 0 || !$gCurrentUser->hasRightViewProfile($user))
{
    header('Content-Type: image/png');
    readfile(THEME_PATH. '/images/no_profile_pic.png');
}
else
{
    header('Content-Type: image/jpeg');
    echo $userPhoto;
}
