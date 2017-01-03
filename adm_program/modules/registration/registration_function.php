<?php
/**
 ***********************************************************************************************
 * Neuen User zuordnen - Funktionen
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * mode: 1 - Registrierung einem Benutzer zuordnen, der bereits Mitglied der Orga ist
 *       2 - Registrierung einem Benutzer zuordnen, der noch KEIN Mitglied der Orga ist
 *       3 - Benachrichtigung an den User, dass er nun fuer die aktuelle Orga freigeschaltet wurde
 *       4 - User-Account loeschen
 *       5 - Create new user and assign roles automatically without dialog
 *       6 - Registrierung muss nicht zugeordnet werden, einfach Logindaten verschicken
 * new_user_id: Id des Logins, das verarbeitet werden soll
 * user_id:     Id des Benutzers, dem das neue Login zugeordnet werden soll
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

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

// pruefen, ob Modul aufgerufen werden darf
if($gPreferences['registration_mode'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// create user objects
$registrationUser = new UserRegistration($gDb, $gProfileFields, $getNewUserId);

if($getUserId > 0)
{
    $user = new User($gDb, $gProfileFields, $getUserId);
}

if($getMode === 1 || $getMode === 2)
{
    // add new registration to an existing user account

    // Daten kopieren, aber nur, wenn noch keine Logindaten existieren
    if($user->getValue('usr_login_name') === '' && $user->getValue('usr_password') === '')
    {
        $user->setValue('EMAIL', $registrationUser->getValue('EMAIL'));
        $user->setValue('usr_login_name', $registrationUser->getValue('usr_login_name'));
        $user->setPassword($registrationUser->getValue('usr_password'), false, false);
    }

    try
    {
        // zuerst den neuen Usersatz loeschen, dann den alten Updaten,
        // damit kein Duplicate-Key wegen dem Loginnamen entsteht
        $registrationUser->notSendEmail();
        $registrationUser->delete();
        $user->save();
    }
    catch(AdmException $e)
    {
        // exception is thrown when email couldn't be send
        // so save user data and then show error
        $user->save();
        $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
        $e->showHtml();
    }
}

if($getMode === 2)
{
    // User existiert bereits, ist aber bisher noch kein Mitglied der aktuellen Orga,
    // deshalb erst einmal Rollen zuordnen und dann spaeter eine Mail schicken
    $gNavigation->addUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration_function.php?mode=3&user_id='.$getUserId.'&new_user_id='.$getNewUserId);
    admRedirect(ADMIDIO_URL . FOLDER_MODULES.'/profile/roles.php?usr_id=' . $getUserId);
    // => EXIT
}

if($getMode === 1 || $getMode === 3)
{
    $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration.php');

    // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
    if($gPreferences['enable_system_mails'] == 1)
    {
        try
        {
            // Mail an den User schicken, um die Anmeldung bwz. die Zuordnung zur neuen Orga zu bestaetigen
            $systemMail = new SystemMail($gDb);
            $systemMail->addRecipient($user->getValue('EMAIL'), $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'));
            $systemMail->sendSystemMail('SYSMAIL_REGISTRATION_USER', $user);

            $gMessage->show($gL10n->get('NWU_ASSIGN_LOGIN_EMAIL', $user->getValue('EMAIL')));
            // => EXIT
        }
        catch(AdmException $e)
        {
            $e->showHtml();
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
    }

    // if current user has the right to assign roles then show roles dialog
    // otherwise go to previous url (default roles are assigned automatically)
    if($gCurrentUser->manageRoles())
    {
        admRedirect(ADMIDIO_URL . FOLDER_MODULES.'/profile/roles.php?new_user=3&usr_id=' . $registrationUser->getValue('usr_id'));
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
    }

    // Zugangsdaten neu verschicken
    $gNavigation->addUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration.php');
    admRedirect(ADMIDIO_URL . FOLDER_MODULES.'/members/members_function.php?mode=4&usr_id=' . $getUserId);
    // => EXIT
}
