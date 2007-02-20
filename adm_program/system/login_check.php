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
$req_login_name     = strStripTags($_POST['loginname']);
$req_password_crypt = md5($_POST["passwort"]);

if(strlen($req_login_name) == 0)
{
    $g_message->show("feld", "Benutzername");
}

// Name und Passwort pruefen
// Rolle muss mind. Mitglied sein

$sql    = "SELECT *
             FROM ". TBL_USERS. ", ". TBL_MEMBERS. ", ". TBL_ROLES. "
            WHERE usr_login_name     LIKE {0}
              AND usr_valid         = 1
              AND mem_usr_id        = usr_id
              AND mem_rol_id        = rol_id
              AND mem_valid         = 1
              AND rol_org_shortname = '$g_organization'
              AND rol_valid         = 1 ";
$sql    = prepareSQL($sql, array($req_login_name));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$user_found = mysql_num_rows($result);
$user_row   = mysql_fetch_object($result);

if ($user_found >= 1)
{
    if($user_row->usr_number_invalid >= 3)
    {
        // wenn innerhalb 15 min. 3 falsche Logins stattfanden -> Konto 15 min. sperren
        if(time() - mysqlmaketimestamp($user_row->usr_date_invalid) < 900)
        {
            $g_message->show("login_failed");
        }
    }

    if($user_row->usr_password == $req_password_crypt)
    {
        // alte Sessions des Users loeschen

        $sql    = "DELETE FROM ". TBL_SESSIONS. "
                    WHERE ses_usr_id        LIKE '$user_row->usr_id'
                      AND ses_org_shortname LIKE '$g_organization'  ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        // Session-ID erzeugen
        //$user_session   = md5(uniqid(rand()));
        $user_session   = session_id();
        $login_datetime = date("Y.m.d H:i:s", time());

        // Session-ID speichern

        $sql = "INSERT INTO ". TBL_SESSIONS. " (ses_usr_id, ses_org_shortname, ses_session, ses_timestamp, ses_ip_address)
                VALUES ('$user_row->usr_id', '$g_organization', '$user_session', '$login_datetime', '". $_SERVER['REMOTE_ADDR']. "') ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        // Cookies fuer die Anmeldung setzen und evtl. Ports entfernen
        $domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
        setcookie("adm_session", "$user_session" , 0, "/", $domain, 0);

        //User Daten in Session speichern
        $g_current_user = new User($g_adm_con);
        $g_current_user->getUser($user_row->usr_id);
        $_SESSION['g_current_user'] = $g_current_user;

        unset($_SESSION['g_current_organizsation']);

        // Last-Login speichern

        $sql = "UPDATE ". TBL_USERS. " SET usr_last_login = usr_actual_login
                 WHERE usr_id = $user_row->usr_id";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        // Logins zaehlen und aktuelles Login-Datum speichern

        $act_date = date("Y-m-d H:i:s", time());
        $sql = "UPDATE ". TBL_USERS. " SET usr_number_login   = usr_number_login + 1
                                         , usr_actual_login   = '$act_date'
                                         , usr_date_invalid   = NULL
                                         , usr_number_invalid = 0
                 WHERE usr_id = $user_row->usr_id";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        // Paralell im Forum einloggen, wenn g_forum gesetzt ist
        if($g_forum)
        {
            $forum_admin_reset = false;
            $forum_export_account = false;
            
            /* Ueberpruefen, ob User ID =1 (Administrator) angemeldet ist. 
            Falls ja, wird geprueft, ob im Forum der gleiche Username und Password fuer die UserID 2 
            (Standard ID fuer den Administrator im Forum) besteht.
            Dieser wird im nein Fall (neue Installation des Boards) auf den Username und das Password des 
            Admidio UserID Accounts 1 (Standard fuer Administartor) geaendert und eine Meldung ausgegegen.
            */
            if($user_row->usr_id == 1)
            {
                $forum_admin_reset = forum_check_admin($req_login_name, $req_password_crypt, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix);
            }


            // Pruefen, ob es den User im Forum gibt, im Nein Fall diesem User ein Forum Account anlegen
            if(!forum_check_user($req_login_name, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix))
            {
                $forum_export_account = TRUE;
                
                // Export der Admido Daten ins Forum und einen Forum Account erstellen
                forum_insert_user($g_current_user->login_name, 1, $g_current_user->password, $g_current_user->email, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix);
            }
            
            // Forums Datenbank auswaehlen
            mysql_select_db($g_forum_db, $g_forum_con);

            // User nun in Foren-Tabelle suchen und dort das Password & UserID auslesen
            $sql    = "SELECT  user_password, user_id FROM ". $g_forum_praefix. "_users WHERE username LIKE {0} ";
            $sql    = prepareSQL($sql, array($req_login_name));
            $result = mysql_query($sql, $g_forum_con);
            db_error($result);
            
            // Admidio DB waehlen
            mysql_select_db($g_adm_db, $g_adm_con);

            // Nat?rlich sollte hier der User auch im Forum existieren 
            // um eine gueltige Anmeldung im Forum zu machen
            if(mysql_num_rows($result))
            {
                $row = mysql_fetch_array($result);
    
                forum_session("insert", $row[1], $g_forum_cookie_name, $g_forum_cookie_path, $g_forum_cookie_domain, $g_forum_cookie_secure, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix);

                // heaerLocation entsprechend der Aktionen setzen, Meldungen ausgeben und weiter zur URL.
                if($forum_admin_reset)
                {
                    // Administrator Account wurde zur?ck gesetzt, Meldung vorbereiten
                    $login_message = "loginforum_admin";
                }
                elseif($forum_export_account)
                {
                    // Admidio Account wurde zum Forum exportiert und ein Account erstellt, Meldung anzeigen
                    $login_message = "loginforum_new";
                }
                elseif(!(forum_check_password($req_password_crypt, $row[0], $row[1], $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix)))
                {
                    // Password wurde zur?ck gesetzt, Meldung vorbereiten
                    $login_message = "loginforum_pass";
                }
                else
                {
                    // Im Forum und in Admidio angemeldet, Meldung vorbereiten
                    $login_message = "loginforum";
                }
            }
            else
            {
                // User gibt es im Forum nicht, also eine reine Admidio anmeldung.
                $login_message = "login";
            }
        }
        else
        {
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
        // ung?ltige Logins werden mitgeloggt
        $sql    = "UPDATE ". TBL_USERS. " SET usr_date_invalid = NOW()
                                            , usr_number_invalid   = usr_number_invalid + 1
                    WHERE usr_id = $user_row->usr_id ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        $g_message->show("password_unknown");
    }
}
else
{
    $g_message->show("login_unknown");
}

?>