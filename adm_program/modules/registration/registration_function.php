<?php
/**
 ***********************************************************************************************
 * Approve new users - functions
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode: 1 - Assign registration to a user who is already a member of the Orga
 *       2 - Assign registration to a user who is NOT yet a member of the Orga
 *       3 - Notification to the user that he/she is now unlocked for the current orga
 *       4 - Delete user account
 *       5 - Create new user and assign roles automatically without dialog
 *       6 - Registration does not need to be assigned, simply send login data
 * new_user_id: Id of the new registered user to be processed
 * user_id:     Id of the user to whom the new login should be assigned
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getMode      = admFuncVariableIsValid($_GET, 'mode',        'int', array('requireValue' => true));
$getNewUserId = admFuncVariableIsValid($_GET, 'new_user_id', 'int', array('requireValue' => true));
$getUserId    = admFuncVariableIsValid($_GET, 'user_id',     'int');

// only administrators could approve new users
if(!$gCurrentUser->approveUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// module must be enabled in the settings
if(!$gSettingsManager->getBool('registration_enable_module'))
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// create user objects
$registrationUser = new UserRegistration($gDb, $gProfileFields, $getNewUserId);

if($getMode === 1 || $getMode === 2)
{
    try
    {
        $user = new User($gDb, $gProfileFields, $getUserId);

        // adopt the data of the registration user to the existing user account
        $registrationUser->adoptUser($user);

        // first delete the new user set, then update the old one to avoid a
        // duplicate key because of the login name
        $registrationUser->notSendEmail();
        $registrationUser->delete();
        $user->save();

        // every new user to the organization will get the default roles for registration
        if($getMode === 1)
        {
            $user->assignDefaultRoles();
        }
    }
    catch(AdmException $e)
    {
        // exception is thrown when email couldn't be send
        // so save user data and then show error
        $user->save();
        $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
        $e->showHtml();
        // => EXIT
    }

    // if current user has the right to assign roles then show roles dialog
    // otherwise go to previous url (default roles are assigned automatically)
    if($gCurrentUser->manageRoles())
    {
        // User already exists, but is not yet a member of the current organization, so first assign roles and then send mail later
        $gNavigation->addUrl(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_function.php', array('mode' => '3', 'user_id' => $getUserId, 'new_user_id' => $getNewUserId)));
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/roles.php', array('usr_id' => $getUserId)));
        // => EXIT
    }
    else
    {
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_function.php', array('mode' => '3', 'user_id' => $getUserId, 'new_user_id' => $getNewUserId)));
        // => EXIT
    }
}
elseif($getMode === 3)
{
    $user = new User($gDb, $gProfileFields, $getUserId);

    $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration.php');

    // execute only if system mails are supported
    if($gSettingsManager->getBool('enable_system_mails'))
    {
        try
        {
            // Send mail to the user to confirm the registration or the assignment to the new organization
            $systemMail = new SystemMail($gDb);
            $systemMail->addRecipientsByUserId($getUserId);
            $systemMail->sendSystemMail('SYSMAIL_REGISTRATION_USER', $user);

            $gMessage->show($gL10n->get('NWU_ASSIGN_LOGIN_EMAIL', array($user->getValue('EMAIL'))));
            // => EXIT
        }
        catch(AdmException $e)
        {
            $e->showHtml();
            // => EXIT
        }
    }
    else
    {
        $gMessage->show($gL10n->get('NWU_ASSIGN_LOGIN_SUCCESSFUL'));
        // => EXIT
    }
}
elseif($getMode === 4)
{
    try
    {
        // delete registration
        $registrationUser->delete();
    }
    catch(AdmException $e)
    {
        $e->showText();
        // => EXIT
    }

    // return successful delete for XMLHttpRequest
    echo 'done';
}
elseif($getMode === 5)
{
    try
    {
        // accept a registration, assign necessary roles and send a notification email
        $registrationUser->acceptRegistration();
    }
    catch(AdmException $e)
    {
        $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
        $e->showHtml();
        // => EXIT
    }

    // if current user has the right to assign roles then show roles dialog
    // otherwise go to previous url (default roles are assigned automatically)
    if($gCurrentUser->manageRoles())
    {
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/roles.php', array('new_user' => '3', 'usr_id' => (int) $registrationUser->getValue('usr_id'))));
        // => EXIT
    }
    else
    {
        $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
        $gMessage->show($gL10n->get('PRO_ASSIGN_REGISTRATION_SUCCESSFUL'));
        // => EXIT
    }
}
elseif($getMode === 6)
{
    // Der User existiert schon und besitzt auch ein Login

    try
    {
        // delete registration
        $registrationUser->delete();
    }
    catch(AdmException $e)
    {
        $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
        $e->showHtml();
        // => EXIT
    }

    // Zugangsdaten neu verschicken
    $gNavigation->addUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration.php');
    admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/members/members_function.php', array('mode' => '4', 'usr_id' => $getUserId)));
    // => EXIT
}
