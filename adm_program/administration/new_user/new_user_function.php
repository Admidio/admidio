<?php
/******************************************************************************
 * Neuen User zuordnen - Funktionen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * mode: 1 - Registrierung einem Benutzer zuordnen, der bereits Mitglied der Orga ist
 *       2 - Registrierung einem Benutzer zuordnen, der noch KEIN Mitglied der Orga ist
 *       3 - Benachrichtigung an den User, dass er nun fuer die aktuelle Orga freigeschaltet wurde
 *       4 - User-Account loeschen
 *       5 - Frage, ob User-Account geloescht werden soll
 *       6 - Registrierung muss nicht zugeordnet werden, einfach Logindaten verschicken
 * new_user_id: Id des Logins, das verarbeitet werden soll
 * user_id:     Id des Benutzers, dem das neue Login zugeordnet werden soll
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/email_class.php");

// nur Webmaster duerfen User bestaetigen, ansonsten Seite verlassen
if($g_current_user->approveUsers() == false)
{
   $g_message->show("norights");
}

// pruefen, ob Modul aufgerufen werden darf
if($g_preferences['registration_mode'] == 0)
{
    $g_message->show("module_disabled");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_user_id     = 0;
$req_new_user_id = 0;
$req_mode        = 0;

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]))
{
    if(is_numeric($_GET["user_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $req_user_id = $_GET["user_id"];
}

if(isset($_GET["new_user_id"]))
{
    if(is_numeric($_GET["new_user_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $req_new_user_id = $_GET["new_user_id"];
}

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 6)
{
    $g_message->show("invalid");
}
else
{
    $req_mode = $_GET["mode"];
}

$err_code = "";
$err_text = "";

if($req_new_user_id > 0)
{
    $new_user = new User($g_adm_con, $req_new_user_id);
}

if($req_user_id > 0)
{
    $user = new User($g_adm_con, $req_user_id);
}

if($req_mode == 1 || $req_mode == 2)
{
    // User-Account einem existierenden Mitglied zuordnen

    // Daten kopieren, aber nur, wenn noch keine Logindaten existieren
    if(strlen($user->getValue("usr_login_name")) == 0 && strlen($user->getValue("usr_password")) == 0)
    {
        $user->setValue("E-Mail", $new_user->getValue("E-Mail"));
        $user->setValue("usr_login_name", $new_user->getValue("usr_login_name"));
        $user->setValue("usr_password", $new_user->getValue("usr_password"));
    }

    // zuerst den neuen Usersatz loeschen, dann den alten Updaten,
    // damit kein Duplicate-Key wegen dem Loginnamen entsteht
    $new_user->delete();
    $user->save($g_current_user->getValue("usr_id"));
}

if($req_mode == 2)
{
    // User existiert bereits, ist aber bisher noch kein Mitglied der aktuellen Orga,
    // deshalb erst einmal Rollen zuordnen und dann spaeter eine Mail schicken
    $_SESSION['navigation']->addUrl("$g_root_path/adm_program/administration/new_user/new_user_function.php?mode=3&user_id=$req_user_id&new_user_id=$req_new_user_id");
    header("Location: $g_root_path/adm_program/modules/profile/roles.php?user_id=$req_user_id");
    exit();
}

if($req_mode == 1 || $req_mode == 3)
{
    // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
    if($g_preferences['enable_system_mails'] == 1)
    {
        // Mail an den User schicken, um die Anmeldung bwz. die Zuordnung zur neuen Orga zu bestaetigen
        $email = new Email();
        $email->setSender($g_preferences['email_administrator']);
        $email->addRecipient($user->getValue("E-Mail"), $user->getValue("Vorname"). " ". $user->getValue("Nachname"));
        $email->setSubject("Anmeldung auf ". $g_current_organization->getValue("org_homepage"));
        $email->setText(utf8_decode("Hallo "). $user->getValue("Vorname"). utf8_decode(",\n\ndeine Anmeldung auf ").
            $g_current_organization->getValue("org_homepage")."&nbsp". utf8_decode("wurde bestätigt.\n\nNun kannst du dich mit deinem Benutzernamen : ").
            $user->getValue("usr_login_name"). utf8_decode("\nund dem Passwort auf der Homepage einloggen.\n\n".
            "Sollten noch Fragen bestehen, schreib eine E-Mail an "). $g_preferences['email_administrator'].
            utf8_decode(" .\n\nViele Grüße\nDie Webmaster"));
        if($email->sendEmail() == true)
        {
            $err_code = "assign_login_mail";
        }
        else
        {
            $err_code = "mail_not_send";
            $err_text = $user->getValue("E-Mail");
        }
    }
    else
    {
        $err_code = "assign_login";
    }

    $g_message->setForwardUrl("$g_root_path/adm_program/administration/new_user/new_user.php");
    $g_message->show($err_code, $err_text);
}
elseif($req_mode == 4)
{
    // Registrierung loeschen
    
    // Paralell im Forum loeschen, wenn g_forum gesetzt ist
    if($g_forum_integriert)
    {
        $g_forum->userDelete($new_user->getValue("usr_login_name"));
    }

    // nun aus Admidio-DB loeschen
    $new_user->delete();

    $location = "Location: $g_root_path/adm_program/administration/new_user/new_user.php";
    header($location);
    exit();
}
elseif($req_mode == 5)
{
    // Fragen, ob die Registrierung geloescht werden soll
    $g_message->setForwardYesNo("$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$req_new_user_id&amp;mode=4");
    $g_message->show("delete_new_user", utf8_encode($new_user->getValue("Vorname"). " ". $new_user->getValue("Nachname")), "Löschen");
}
elseif($req_mode == 6)
{
    // Der User existiert schon und besitzt auch ein Login
    
    // Den Username für die Loeschung im Forum zwischenspeichern
    $forum_user = $new_user->getValue("usr_login_name");

    // Registrierung loeschen
    $new_user->delete();
    
    // Paralell im Forum loeschen, wenn g_forum gesetzt ist
    if($g_forum_integriert)
    {
        $g_forum->userDelete($new_user->getValue("usr_login_name"));
    }

    // Zugangsdaten neu verschicken
    $_SESSION['navigation']->addUrl("$g_root_path/adm_program/administration/new_user/new_user.php");
    header("Location: $g_root_path/adm_program/administration/members/members_function.php?mode=4&user_id=$req_user_id");
    exit();
}

?>