<?php
/******************************************************************************
 * Meldet den User bei Admidio an, wenn sich dieser einloggen darf
 * Cookies setzen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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

require("common.php");

$_POST['loginname'] = strStripTags($_POST['loginname']);

if(strlen($_POST['loginname']) == 0)
{
    $g_message->show("feld", "Benutzername");
}

$user_found = 0;
$password_crypt = md5($_POST["passwort"]);

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
$sql    = prepareSQL($sql, array($_POST["loginname"]));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$user_found = mysql_num_rows($result);
$user_row   = mysql_fetch_object($result);

if ($user_found >= 1)
{
    if($user_row->usr_number_invalid >= 3)
    {
        // wenn innerhalb 15 min. 3 falsche Logins stattfanden -> Konto 15 min. sperren
        if(mktime() - mysqlmaketimestamp($user_row->usr_date_invalid) < 900)
        {
            $g_message->show("login_failed");
        }
    }

    if($user_row->usr_password == $password_crypt)
    {
        // alte Sessions des Users loeschen

        $sql    = "DELETE FROM ". TBL_SESSIONS. " 
                    WHERE ses_usr_id        LIKE '$user_row->usr_id' 
                      AND ses_org_shortname LIKE '$g_organization'  ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        // Session-ID erzeugen
        $user_session   = md5(uniqid(rand()));      
        $login_datetime = date("Y.m.d H:i:s", time());

        // darf der User laenger eingeloggt sein

        if(array_key_exists("long_login", $_POST))
        {
            $long_login = 1;
        }
        else
        {
            $long_login = 0;
        }

        // Session-ID speichern

        $sql = "INSERT INTO ". TBL_SESSIONS. " (ses_usr_id, ses_org_shortname, ses_session, ses_timestamp, ses_ip_address, ses_longer_session)
                VALUES ('$user_row->usr_id', '$g_organization', '$user_session', '$login_datetime', '". $_SERVER['REMOTE_ADDR']. "', $long_login) ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        // Cookies fuer die Anmeldung setzen
        if($_SERVER['HTTP_HOST'] == 'localhost')
        {
            // beim localhost darf keine Domaine uebergeben werden
            setcookie("adm_session", "$user_session", 0, "/");
        }
        else
        {
            setcookie("adm_session", "$user_session" , 0, "/", ".". $g_domain);
        }

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

        // falls noch keine Forward-Url gesetzt wurde, dann nach dem Login auf 
        // die Startseite verweisen
        if(isset($_SESSION['login_forward_url']) == false)
        {
        	$_SESSION['login_forward_url'] = home;
        }
        
        // bevor zur entsprechenden Seite weitergeleitet wird, muss noch geprueft werden,
        // ob der Browser Cookies setzen darf -> sonst kein Login moeglich
	    $location = "Location: $g_root_path/adm_program/system/cookie_check.php";
	    header($location);
	    exit();
    }
    else
    {
        // ungültige Logins werden mitgeloggt
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