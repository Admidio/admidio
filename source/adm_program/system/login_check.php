<?php
/******************************************************************************
 * Meldet den User bei Admidio an, wenn sich dieser einloggen darf
 * Cookies setzen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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

require("common.php");

// Variablen initialisieren
$user_found  = 0;

// Uebergabevariablen filtern
$req_password_crypt = md5($_POST["passwort"]);

if(strlen($_POST['loginname']) == 0)
{
    $g_message->show("feld", "Benutzername");
}

// Name und Passwort pruefen
// Rolle muss mind. Mitglied sein

$sql    = "SELECT usr_id
             FROM ". TBL_USERS. ", ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
            WHERE usr_login_name LIKE ". $_POST['loginname']. "
              AND usr_valid      = 1
              AND mem_usr_id     = usr_id
              AND mem_rol_id     = rol_id
              AND mem_valid      = 1
              AND rol_valid      = 1 
              AND rol_cat_id     = cat_id
              AND cat_org_id     = ". $g_current_organization->getValue("org_id");
$result = $g_db->query($sql);

$user_found = $g_db->num_rows($result);
$user_row   = $g_db->fetch_array($result);

if ($user_found >= 1)
{
    $act_date = date("Y-m-d H:i:s", time());
    
    // Userobjekt anlegen
    $g_current_user = new User($g_db, $user_row['usr_id']);
    
    if($g_current_user->getValue("usr_number_invalid") >= 3)
    {
        // wenn innerhalb 15 min. 3 falsche Logins stattfanden -> Konto 15 min. sperren
        if(time() - mysqlmaketimestamp($g_current_user->getValue("usr_date_invalid")) < 900)
        {
            $g_message->show("login_failed");
        }
    }

    if($g_current_user->getValue("usr_password") == $req_password_crypt)
    {
        $g_current_session->setValue("ses_usr_id", $g_current_user->getValue("usr_id"));
        $g_current_session->save();

        // Cookies fuer die Anmeldung setzen und evtl. Ports entfernen
        $domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
        setcookie("admidio_session_id", "$g_session_id" , 0, "/", $domain, 0);

        // Logins zaehlen und aktuelles Login-Datum aktualisieren
        $g_current_user->setValue("usr_last_login",   $g_current_user->getValue("usr_actual_login"));
        $g_current_user->setValue("usr_number_login", $g_current_user->getValue("usr_number_login") + 1);
        $g_current_user->setValue("usr_actual_login", $act_date);
        $g_current_user->setValue("usr_date_invalid", NULL);
        $g_current_user->setValue("usr_number_invalid", 0);
        $g_current_user->save($user_row['usr_id'], false);

        // Paralell im Forum einloggen, wenn g_forum gesetzt ist
        if($g_forum_integriert)
        {
            $g_forum->userLogin($g_current_user->getValue("usr_id"), $_POST['loginname'], $req_password_crypt, 
                                $g_current_user->getValue("usr_login_name"), $g_current_user->getValue("usr_password"), 
                                $g_current_user->getValue("E-Mail"));

            $login_message = $g_forum->message;
        }
        else
        {
            // User gibt es im Forum nicht, also eine reine Admidio anmeldung.
            $login_message = "login";
        }

        // falls noch keine Forward-Url gesetzt wurde, dann nach dem Login auf
        // die Startseite verweisen
        if(isset($_SESSION['login_forward_url']) == false)
        {
            $_SESSION['login_forward_url'] = "home";
        }

        // bevor zur entsprechenden Seite weitergeleitet wird, muss noch geprueft werden,
        // ob der Browser Cookies setzen darf -> sonst kein Login moeglich
        $location = "Location: $g_root_path/adm_program/system/cookie_check.php?message_code=$login_message";
        header($location);
        exit();
    }
    else
    {
        // ungueltige Logins werden mitgeloggt
        
        if($g_current_user->getValue("usr_number_invalid") >= 3)
        {
            $g_current_user->setValue("usr_number_invalid", 1);
        }
        else
        {
            $g_current_user->setValue("usr_number_invalid", $g_current_user->getValue("usr_number_invalid") + 1);
        }
        $g_current_user->setValue("usr_date_invalid", $act_date);
        $g_current_user->save(false);

        if($g_current_user->getValue("usr_number_invalid") >= 3)
        {
            $g_message->show("login_failed");
        }
        else
        {
            $g_message->show("password_unknown");
        }
    }
}
else
{
    $g_message->show("login_unknown");
}

?>