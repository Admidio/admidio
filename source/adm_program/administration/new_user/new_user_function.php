<?php
/******************************************************************************
 * Neuen User zuordnen - Funktionen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
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
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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
if(!hasRole("Webmaster"))
{
   $g_message->show("norights");
}

// pruefen, ob Modul aufgerufen werden darf
if($g_preferences['registration_mode'] == 0)
{
    $g_message->show("module_disabled");
}

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]) && is_numeric($_GET["user_id"]) == false)
{
    $g_message->show("invalid");
}

if(isset($_GET["new_user_id"]) && is_numeric($_GET["new_user_id"]) == false)
{
    $g_message->show("invalid");
}

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 6)
{
    $g_message->show("invalid");
}

$err_code = "";
$err_text = "";

if(isset($_GET['new_user_id']))
{
    $new_user = new User($g_adm_con);
    $new_user->getUser($_GET['new_user_id']);
}

if(isset($_GET['user_id']))
{
    $user = new User($g_adm_con);
    $user->getUser($_GET['user_id']);
}

if($_GET["mode"] == 1 || $_GET["mode"] == 2)
{
    // User-Account einem existierenden Mitglied zuordnen

    // Daten kopieren, aber nur, wenn noch keine Logindaten existieren
    if(strlen($user->login_name) == 0 && strlen($user->password) == 0)
    {
        $user->email      = $new_user->email;
        $user->login_name = $new_user->login_name;
        $user->password   = $new_user->password;
    }

    // zuerst den neuen Usersatz loeschen, dann den alten Updaten,
    // damit kein Duplicate-Key wegen dem Loginnamen entsteht
    $new_user->delete();
    $user->update($g_current_user->id);
}

if($_GET["mode"] == 2)
{
    // User existiert bereits, ist aber bisher noch kein Mitglied der aktuellen Orga,
    // deshalb erst einmal Rollen zuordnen und dann spaeter eine Mail schicken
    $_SESSION['navigation']->addUrl("$g_root_path/adm_program/administration/new_user/new_user_function.php?mode=3&user_id=". $_GET['user_id']. "&new_user_id=". $_GET['new_user_id']);
    header("Location: $g_root_path/adm_program/modules/profile/roles.php?user_id=". $_GET['user_id']);
    exit();
}

if($_GET["mode"] == 1 || $_GET["mode"] == 3)
{
    // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
    if($g_preferences['enable_system_mails'] == 1)
    {
        // Mail an den User schicken, um die Anmeldung bwz. die Zuordnung zur neuen Orga zu bestaetigen
        $email = new Email();
        $email->setSender($g_preferences['email_administrator']);
        $email->addRecipient($user->email, "$user->first_name $user->last_name");
        $email->setSubject("Anmeldung auf $g_current_organization->homepage");
        $email->setText(utf8_decode("Hallo "). $user->first_name. utf8_decode(",\n\ndeine Anmeldung auf ").
            $g_current_organization->homepage."&nbsp". utf8_decode("wurde bestätigt.\n\nNun kannst du dich mit deinem Benutzernamen : ").
            $user->login_name. utf8_decode("\nund dem Passwort auf der Homepage einloggen.\n\n".
            "Sollten noch Fragen bestehen, schreib eine E-Mail an "). $g_preferences['email_administrator'].
            utf8_decode(" .\n\nViele Grüße\nDie Webmaster"));
        if($email->sendEmail() == true)
        {
            $err_code = "send_login_mail";
        }
        else
        {
            $err_code = "mail_not_send";
            $err_text = $user->email;
        }
    }

    $g_message->setForwardUrl("$g_root_path/adm_program/administration/new_user/new_user.php");
    $g_message->show($err_code, $err_text);
}
elseif($_GET["mode"] == 4)
{
   // Registrierung loeschen

   $sql    = "DELETE FROM ". TBL_USERS. " WHERE usr_id = {0}";
   $sql    = prepareSQL($sql, array($_GET['new_user_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $location = "Location: $g_root_path/adm_program/administration/new_user/new_user.php";
   header($location);
   exit();
}
elseif($_GET["mode"] == 5)
{
    // Fragen, ob die Registrierung geloescht werden soll
    $g_message->setForwardYesNo("$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=". $_GET['new_user_id']. "&amp;mode=4");
    $g_message->show("delete_new_user", utf8_encode("$new_user->first_name $new_user->last_name"), "Löschen");
}
elseif($_GET["mode"] == 6)
{
	// Der User existiert schon und besitzt auch ein Login

    // Registrierung loeschen
	$new_user->delete();

    // Zugangsdaten neu verschicken
    $_SESSION['navigation']->addUrl("$g_root_path/adm_program/administration/new_user/new_user.php");
    header("Location: $g_root_path/adm_program/administration/members/members_function.php?mode=4&user_id=". $_GET['user_id']);
    exit();
}

?>