<?php
/******************************************************************************
 * Neuen User zuordnen - Funktionen
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * mode: 1 - Registrierung einem Benutzer zuordnen, der bereits Mitglied der Orga ist
 *       2 - Registrierung einem Benutzer zuordnen, der noch KEIN Mitglied der Orga ist
 *       3 - Benachrichtigung an den User, dass er nun fuer die aktuelle Orga freigeschaltet wurde
 *       4 - User-Account loeschen
 *       6 - Registrierung muss nicht zugeordnet werden, einfach Logindaten verschicken
 * new_user_id: Id des Logins, das verarbeitet werden soll
 * user_id:     Id des Benutzers, dem das neue Login zugeordnet werden soll
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/system_mail.php');

// nur Webmaster duerfen User bestaetigen, ansonsten Seite verlassen
if($g_current_user->approveUsers() == false)
{
   $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// pruefen, ob Modul aufgerufen werden darf
if($g_preferences['registration_mode'] == 0)
{
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_user_id     = 0;
$req_new_user_id = 0;
$req_mode        = 0;

// Uebergabevariablen pruefen

if(isset($_GET['user_id']))
{
    if(is_numeric($_GET['user_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_user_id = $_GET['user_id'];
}

if(isset($_GET['new_user_id']))
{
    if(is_numeric($_GET['new_user_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_new_user_id = $_GET['new_user_id'];
}

if(is_numeric($_GET['mode']) == false
|| $_GET['mode'] < 1 || $_GET['mode'] > 6)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}
else
{
    $req_mode = $_GET['mode'];
}

$err_code = '';
$err_text = '';

if($req_new_user_id > 0)
{
    $new_user = new User($g_db, $req_new_user_id);
}

if($req_user_id > 0)
{
    $user = new User($g_db, $req_user_id);
}

if($req_mode == 1 || $req_mode == 2)
{
    // User-Account einem existierenden Mitglied zuordnen

    // Daten kopieren, aber nur, wenn noch keine Logindaten existieren
    if(strlen($user->getValue('usr_login_name')) == 0 && strlen($user->getValue('usr_password')) == 0)
    {
        $user->setValue('E-Mail', $new_user->getValue('E-Mail'));
        $user->setValue('usr_login_name', $new_user->getValue('usr_login_name'));
        $user->setValue('usr_password', $new_user->getValue('usr_password'));
    }

    // zuerst den neuen Usersatz loeschen, dann den alten Updaten,
    // damit kein Duplicate-Key wegen dem Loginnamen entsteht
    $new_user->delete();
    $user->save();
}

if($req_mode == 2)
{
    // User existiert bereits, ist aber bisher noch kein Mitglied der aktuellen Orga,
    // deshalb erst einmal Rollen zuordnen und dann spaeter eine Mail schicken
    $_SESSION['navigation']->addUrl($g_root_path.'/adm_program/administration/new_user/new_user_function.php?mode=3&user_id='.$req_user_id.'&new_user_id='.$req_new_user_id);
    header('Location: '.$g_root_path.'/adm_program/modules/profile/roles.php?user_id='.$req_user_id);
    exit();
}

if($req_mode == 1 || $req_mode == 3)
{
    $g_message->setForwardUrl($g_root_path.'/adm_program/administration/new_user/new_user.php');

    // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
    if($g_preferences['enable_system_mails'] == 1)
    {
        // Mail an den User schicken, um die Anmeldung bwz. die Zuordnung zur neuen Orga zu bestaetigen
        $sysmail = new SystemMail($g_db);
        $sysmail->addRecipient($user->getValue('E-Mail'), $user->getValue('Vorname'). ' '. $user->getValue('Nachname'));
        if($sysmail->sendSystemMail('SYSMAIL_REGISTRATION_USER', $user) == true)
        {
            $g_message->show($g_l10n->get('ASS_PHR_ASSIGN_LOGIN_EMAIL'));
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_PHR_EMAIL_NOT_SEND', $user->getValue('E-Mail')));
        }
    }
    else
    {
        $g_message->show($g_l10n->get('ASS_PHR_ASSIGN_LOGIN'));
    }
}
elseif($req_mode == 4)
{
    // Registrierung loeschen    
    // im Forum muss er nicht geloescht werden, da der User erst nach der vollstaendigen 
    // Registrierung im Forum angelegt wird.
    $new_user->delete();

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
elseif($req_mode == 6)
{
    // Der User existiert schon und besitzt auch ein Login
    
    // Registrierung loeschen
    // im Forum muss er nicht geloescht werden, da der User erst nach der vollstaendigen 
    // Registrierung im Forum angelegt wird.
    $new_user->delete();

    // Zugangsdaten neu verschicken
    $_SESSION['navigation']->addUrl($g_root_path.'/adm_program/administration/new_user/new_user.php');
    header('Location: '.$g_root_path.'/adm_program/administration/members/members_function.php?mode=4&usr_id='.$req_user_id);
    exit();
}

?>