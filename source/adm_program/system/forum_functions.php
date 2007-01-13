<?php
/******************************************************************************
 * Funktionen und Routinen fuer das Forum
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Thomas Thoss
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

// Globale Variablen fuer das Forum
$g_forum_session_id    = "";			// Die Session fuer das Forum
$g_forum_session_valid = FALSE;		// Session gueltig
$g_forum_user  = "";							// Username im Forum
$g_forum_userid = "";							// UserID im Forum
$g_forum_neuePM = "";							// Nachrichten im Forum
$g_forum_sitename = "";						// Name des Forums
$g_forum_server = "";							// Server des Forums
$g_forum_path = "";								// Pfad zum Forum
$g_forum_cookie_name = "";				// Name des Forum Cookies
$g_forum_cookie_path = "";				// Pfad zum Forum Cookies
$g_forum_cookie_domain = "";			// Domain des Forum Cookies
$g_forum_cookie_secure ="";				// Cookie Secure des Forums


// Forumspezifische Konfigurationsvariablen lesen und zuweisen
// Forums DB waehlen
mysql_select_db($g_forum_db, $g_forum_con);

$sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'sitename' ";
$result = mysql_query($sql, $g_forum_con);
db_error($result);
$row = mysql_fetch_array($result);
$g_forum_sitename = $row[0];

$sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'cookie_name' ";
$result = mysql_query($sql, $g_forum_con);
db_error($result);
$row = mysql_fetch_array($result);
$g_forum_cookie_name = $row[0];

$sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'cookie_path' ";
$result = mysql_query($sql, $g_forum_con);
db_error($result);
$row = mysql_fetch_array($result);
$g_forum_cookie_path = $row[0];

$sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'cookie_domain' ";
$result = mysql_query($sql, $g_forum_con);
db_error($result);
$row = mysql_fetch_array($result);
$g_forum_cookie_domain = $row[0];

$sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'cookie_secure' ";
$result = mysql_query($sql, $g_forum_con);
db_error($result);
$row = mysql_fetch_array($result);
$g_forum_cookie_secure = $row[0];

$sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'server_name' ";
$result = mysql_query($sql, $g_forum_con);
db_error($result);
$row = mysql_fetch_array($result);
$g_forum_server = str_replace('/', '', $row[0]);

$sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'script_path' ";
$result = mysql_query($sql, $g_forum_con);
db_error($result);
$row = mysql_fetch_array($result);
$g_forum_path = str_replace('/', '', $row[0]);

// Admidio DB waehlen
mysql_select_db($g_adm_db, $g_adm_con);


// Cookie des Forums einlesen
if(isset($_COOKIE[$g_forum_cookie_name."_sid"]) AND $g_session_valid)
{
    $g_forum_session_id = $_COOKIE[$g_forum_cookie_name."_sid"];
    $g_forum_session_valid = TRUE;
}
else
{
    $g_forum_session_id = "";
    $g_forum_session_valid = FALSE;
}


// Username, UserID und NeueNachrichten aus der Forums DB lesen
if ($g_forum_session_valid)
{
    $g_forum_user = $g_current_user->login_name;
    
    // Forums DB waehlen
    mysql_select_db($g_forum_db, $g_forum_con);
    
    $sql    = "SELECT user_id, username, user_new_privmsg FROM ". $g_forum_praefix. "_users WHERE username LIKE {0} ";
    $sql    = prepareSQL($sql, array($g_forum_user));
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    
    $row = mysql_fetch_array($result);
    
    $g_forum_userid = $row[0];
    $g_forum_user  = $row[1];
    $g_forum_neuePM = $row[2];
    
    // Wenn neue Nachrichten vorliegen, einen ansprechenden Text generieren
    if ($g_forum_neuePM == 0)
    {
       $g_forum_neuePM_Text = "und haben <b>keine</b> neue Nachrichten.";
    }
    elseif ($g_forum_neuePM == 1)
    {
       $g_forum_neuePM_Text = "und haben <b>1</b> neue Nachricht.";
    }
    else
    {
       $g_forum_neuePM_Text = "und haben <b>".$g_forum_neuePM."</b> neue Nachrichten.";
    }
    
    // Admidio DB waehlen
    mysql_select_db($g_adm_db, $g_adm_con);
}


// Gueltige Session im Forum updaten. Sofern die Admidio Session g�ltig ist, ist auch die Forum Session g�ltig
if($g_session_valid)
{
    // Forums DB w�hlen
    mysql_select_db($g_forum_db, $g_forum_con);
    
    // Erst mal schauen, ob sich die Session noch im Session Table des Forums befindet
    $sql    = "SELECT session_id FROM ". $g_forum_praefix. "_sessions
               WHERE session_id = '".$g_forum_session_id."' ";
    $result = mysql_query($sql, $g_forum_con);
    
    if(mysql_num_rows($result))
    {
        // Session existiert, also updaten
        $sql    = "UPDATE ". $g_forum_praefix. "_sessions 
                  SET session_time = ". time() .",  session_user_id = ". $g_forum_userid .",  session_logged_in = 1
                  WHERE session_id = {0}";
        $sql    = prepareSQL($sql, array($g_forum_session_id));
        $result = mysql_query($sql, $g_forum_con);
        db_error($result);
    }
    else
    {
        // Session existiert nicht, also neu anlegen (Autologin im Forum)
        // Daten f�r das Cookie und den Session Eintrag im Forum aufbereiten
	      $ip_sep = explode('.', getenv('REMOTE_ADDR'));
	      $user_ip = sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
	      $current_time = time();
	      
	      // Session in die Forum DB schreiben
	      $sql = "INSERT INTO " .$g_forum_praefix. "_sessions
	             (session_id, session_user_id, session_start, session_time, session_ip, session_page, session_logged_in, session_admin)
	             VALUES ('$g_session_id', $g_forum_userid, $current_time, $current_time, '$user_ip', 0, 1, 0)";
	      $result = mysql_query($sql, $g_forum_con);
        db_error($result);
    }    
    
    // Admidio DB w�hlen
    mysql_select_db($g_adm_db, $g_adm_con);
}
elseif (isset($_COOKIE[$g_forum_cookie_name."_sid"]))
{
    // Die Admidio Session ist ung�ltig oder abgelaufen, also die Session des Forums ebenfalls l�schen.
    
    // Session ID aus dem Cookie lesen
    $g_forum_session_id = $_COOKIE[$g_forum_cookie_name."_sid"];
       
    // Forums DB w�hlen
    mysql_select_db($g_forum_db, $g_forum_con);
 
    // User-ID aus der Session lesen
    $sql    = "SELECT  session_user_id FROM ". $g_forum_praefix. "_sessions WHERE session_id = '$g_forum_session_id' ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    
    $row = mysql_fetch_array($result);
        
    if(mysql_num_rows($result)!=0)
    {
        // User-Session im Forum l�schen
        $sql    = "DELETE FROM ". $g_forum_praefix. "_sessions WHERE session_user_id = $row[0] ";
        $result = mysql_query($sql, $g_forum_con);
        db_error($result);
    }
 
    // Admidio DB w�hlen
    mysql_select_db($g_adm_db, $g_adm_con);
}


/******************************************************************************
 * FUNKTIONEN 
 *
 * forum_check_admin
 * forum_check_password
 * forum_check_user
 * forum_update_user
 * forum_insert_user
 ******************************************************************************/


// Funktion �berpr�ft ob der Admin Account im Forum ungleich des Admidio Accounts ist.
// Falls ja wird der Admidio Account (Username & Password) ins Forum �bernommen.
function forum_check_admin($username, $password_crypt, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix)
{
    // Forum Datenbank ausw�hlen
    mysql_select_db($g_forum_db, $g_forum_con);
    
    // Administrator nun in Foren-Tabelle suchen und dort das Password, Username & UserID auslesen
    $sql    = "SELECT username, user_password, user_id FROM ". $g_forum_praefix. "_users WHERE user_id = 2";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    
    $row = mysql_fetch_array($result);
    
    if($username == $row[0] AND $password_crypt == $row[1])
    {
        // Admidio DB waehlen
        mysql_select_db($g_adm_db, $g_adm_con);
        
        return FALSE;
    }
    else
    {
        // Password in Foren-Tabelle auf das Password in Admidio setzen
        $sql    = "UPDATE ". $g_forum_praefix. "_users 
                   SET user_password = '". $password_crypt ."', username = '". $username ."'
                   WHERE user_id = 2";
        $result = mysql_query($sql, $g_forum_con);
        db_error($result);
        
        // Admidio DB waehlen
        mysql_select_db($g_adm_db, $g_adm_con);
        
        return TRUE;
    }
}


// Funktion ueberprueft ob das Password im Forum ungleich des Admidio Passwords ist.
// Falls ja wird das Admidio Password ins Forum uebernommen.
function forum_check_password($password_admidio, $password_forum, $forum_userid, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix)
{
    // Passwoerter vergleichen
    if ($password_admidio == $password_forum)
    {
        return TRUE;
    }
    else
    {
        // Forum Datenbank auswaehlen
        mysql_select_db($g_forum_db, $g_forum_con);
        
        // Password in Foren-Tabelle auf das Password in Admidio setzen
        $sql    = "UPDATE ". $g_forum_praefix. "_users 
                   SET user_password = '". $password_admidio ."'
                   WHERE user_id = {0}";
        $sql    = prepareSQL($sql, array($forum_userid));
        $result = mysql_query($sql, $g_forum_con);
        db_error($result);
        
        // Admidio DB waehlen
        mysql_select_db($g_adm_db, $g_adm_con);
        
        return FALSE;
    }
}

// Funktion ueberprueft, ob der User schon im Forum existiert
function forum_check_user($forum_username, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix)
{
    // Forum Datenbank auswaehlen
    mysql_select_db($g_forum_db, $g_forum_con);
    
    // User im Forum suchen
    $sql    = "SELECT user_id FROM ". $g_forum_praefix. "_users 
                WHERE username LIKE '$forum_username' ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);      
    
    // Admidio DB waehlen
    mysql_select_db($g_adm_db, $g_adm_con);
    
    // Wenn ein Ergebis groesser 0 vorliegt, existiert der User bereits.
    if(mysql_num_rows($result) !=0)
    {
        return TRUE;
    }
    else
    {
        return FALSE;
    }
}

// Funktion updated einen bestehenden User im Forum
function forum_update_user($forum_username, $forum_useraktiv, $forum_password, $forum_email, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix)
{
    // Erst mal schauen ob der User alle Kriterien erfaellt um im Forum aktiv zu sein
    // Voraussetzung ist ein gaeltiger Benutzername, eine Email und ein Password
    if($forum_username AND $forum_password AND $forum_email)
    {
        $forum_useraktiv = 1;
    }
    else
    {
        $forum_useraktiv = 0;
    }
    
    // Forum Datenbank auswaehlen
    mysql_select_db($g_forum_db, $g_forum_con);
    
    // User im Forum updaten
    $sql    = "UPDATE ". $g_forum_praefix. "_users
               SET user_password = '$forum_password', user_active = $forum_useraktiv, user_email = '$forum_email'
               WHERE username = '". $forum_username. "' ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    
    // Admidio DB waehlen
    mysql_select_db($g_adm_db, $g_adm_con);
}


// Funktion legt einen neuen Benutzer im Forum an
function forum_insert_user($forum_username, $forum_useraktiv, $forum_password, $forum_email, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix)
{
    // Forum Datenbank auswaehlen
    mysql_select_db($g_forum_db, $g_forum_con);
    
    // jetzt noch den neuen User ins Forum eintragen, ggf. Fehlermeldung als Standard ausgeben.
    $sql    = "SELECT MAX(user_id) as anzahl 
                 FROM ". $g_forum_praefix. "_users";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    $row    = mysql_fetch_array($result);
    $new_user_id = $row[0] + 1;
    
    $sql    = "INSERT INTO ". $g_forum_praefix. "_users
                           (user_id, user_active, username, user_password, user_regdate, user_timezone,
                            user_style, user_lang, user_viewemail, user_attachsig, user_allowhtml,
                            user_dateformat, user_email, user_notify, user_notify_pm, user_popup_pm,
                            user_avatar)
                    VALUES ($new_user_id, $forum_useraktiv, '$forum_username', '$forum_password', ". time(). ", 1.00,
                            2, 'german', 0, 1, 0,
                            'd.m.Y, H:i', '$forum_email', 0, 1, 1,
                            '') ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    
    // Admidio DB waehlen
    mysql_select_db($g_adm_db, $g_adm_con);
}


// Funktion updated einen bestehenden User im Forum
function forum_update_username($forum_new_username, $forum_old_username, $forum_useraktiv, $forum_password, $forum_email, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix)
{
    // Erst mal schauen ob der User alle Kriterien erfuellt um im Forum aktiv zu sein
    // Voraussetzung ist ein gueltiger Benutzername, eine Email und ein Password
    if(strlen($forum_new_username) > 0 AND strlen($forum_password) > 0 AND strlen($forum_email) > 0)
    {
        $forum_useraktiv = 1;
    }
    else
    {
        $forum_useraktiv = 0;
    }
    
    // Forum Datenbank auswaehlen
    mysql_select_db($g_forum_db, $g_forum_con);
    
    // User im Forum updaten
    $sql    = "UPDATE ". $g_forum_praefix. "_users
               SET username = '$forum_new_username', user_password = '$forum_password', user_active = $forum_useraktiv, user_email = '$forum_email'
               WHERE username = '". $forum_old_username. "' ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    
    // Admidio DB waehlen
    mysql_select_db($g_adm_db, $g_adm_con);
}
?>