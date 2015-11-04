<?php
/******************************************************************************
 * Neuen User zuordnen - Funktionen
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
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
$getMode      = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('requireValue' => true));
$getNewUserId = admFuncVariableIsValid($_GET, 'new_user_id', 'numeric', array('requireValue' => true));
$getUserId    = admFuncVariableIsValid($_GET, 'user_id', 'numeric');

// nur Webmaster duerfen User bestaetigen, ansonsten Seite verlassen
if(!$gCurrentUser->approveUsers())
{
   $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// pruefen, ob Modul aufgerufen werden darf
if($gPreferences['registration_mode'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// create user objects
$registrationUser = new UserRegistration($gDb, $gProfileFields, $getNewUserId);

if($getUserId > 0)
{
    $user = new User($gDb, $gProfileFields, $getUserId);
}

if($getMode == 1 || $getMode == 2)
{
    // User-Account einem existierenden Mitglied zuordnen

    // Daten kopieren, aber nur, wenn noch keine Logindaten existieren
    if($user->getValue('usr_login_name') === '' && $user->getValue('usr_password') === '')
    {
        $user->setValue('EMAIL', $registrationUser->getValue('EMAIL'));
        $user->setValue('usr_login_name', $registrationUser->getValue('usr_login_name'));
        $user->setPassword($registrationUser->getValue('usr_password'));
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

if($getMode == 2)
{
    // User existiert bereits, ist aber bisher noch kein Mitglied der aktuellen Orga,
    // deshalb erst einmal Rollen zuordnen und dann spaeter eine Mail schicken
    $gNavigation->addUrl($g_root_path.'/adm_program/modules/registration/registration_function.php?mode=3&user_id='.$getUserId.'&new_user_id='.$getNewUserId);
    header('Location: '.$g_root_path.'/adm_program/modules/profile/roles.php?usr_id='.$getUserId);
    exit();
}

if($getMode == 1 || $getMode == 3)
{
    $gMessage->setForwardUrl($g_root_path.'/adm_program/modules/registration/registration.php');

    // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
    if($gPreferences['enable_system_mails'] == 1)
    {
        try
        {
            // Mail an den User schicken, um die Anmeldung bwz. die Zuordnung zur neuen Orga zu bestaetigen
            $sysmail = new SystemMail($gDb);
            $sysmail->addRecipient($user->getValue('EMAIL'), $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'));
            $sysmail->sendSystemMail('SYSMAIL_REGISTRATION_USER', $user);

            $gMessage->show($gL10n->get('NWU_ASSIGN_LOGIN_EMAIL', $user->getValue('EMAIL')));
        }
        catch(AdmException $e)
        {
            $e->showHtml();
        }
    }
    else
    {
        $gMessage->show($gL10n->get('NWU_ASSIGN_LOGIN_SUCCESSFUL'));
    }
}
elseif($getMode == 4)
{
    try
    {
        // delete registration
        $registrationUser->delete();
    }
    catch(AdmException $e)
    {
        $e->showText();
    }

    // return successful delete for XMLHttpRequest
    echo 'done';
}
elseif($getMode == 5)
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
        header('Location: roles.php?new_user=3&usr_id='. $registrationUser->getValue('usr_id'));
        exit();
    }
    else
    {
        $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
        $gMessage->show($gL10n->get('PRO_ASSIGN_REGISTRATION_SUCCESSFUL'));
    }
}
elseif($getMode == 6)
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
    $gNavigation->addUrl($g_root_path.'/adm_program/modules/registration/registration.php');
    header('Location: '.$g_root_path.'/adm_program/modules/members/members_function.php?mode=4&usr_id='.$getUserId);
    exit();
}
