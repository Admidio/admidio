<?php
/******************************************************************************
 * Funktionen und Routinen fuer das Forum
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Thomas Thoss
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

// Globale Variablen fuer das Forum
$g_forum_user  = "";                    // Username im Forum
$g_forum_userid = "";                   // UserID im Forum
$g_forum_neuePM = "";                   // Nachrichten im Forum
$g_forum_sitename = "";                 // Name des Forums
$g_forum_server = "";                   // Server des Forums
$g_forum_path = "";                     // Pfad zum Forum
$g_forum_cookie_name = "";              // Name des Forum Cookies
$g_forum_cookie_path = "";              // Pfad zum Forum Cookies
$g_forum_cookie_domain = "";            // Domain des Forum Cookies
$g_forum_cookie_secure ="";             // Cookie Secure des Forums


// Session Variablen fuer das Forum auslesen
if(isset($_SESSION['s_forum_sitename'])   
&& isset($_SESSION['s_forum_cookie_name'])
&& isset($_SESSION['s_forum_cookie_path'])
&& isset($_SESSION['s_forum_cookie_domain'])
&& isset($_SESSION['s_forum_cookie_secure'])
&& isset($_SESSION['s_forum_server'])
&& isset($_SESSION['s_forum_path'])) 
{
    $g_forum_sitename = $_SESSION['s_forum_sitename'];
    $g_forum_cookie_name = $_SESSION['s_forum_cookie_name'];
    $g_forum_cookie_path = $_SESSION['s_forum_cookie_path'];
    $g_forum_cookie_domain = $_SESSION['s_forum_cookie_domain'];
    $g_forum_cookie_secure = $_SESSION['s_forum_cookie_secure'];
    $g_forum_server = $_SESSION['s_forum_server'];
    $g_forum_path = $_SESSION['s_forum_path'];
}
else
{
    // Forums DB waehlen
    mysql_select_db($g_forum_db, $g_forum_con);
    
    $sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'sitename' ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    $row = mysql_fetch_array($result);
    $g_forum_sitename = $row[0];
    $_SESSION['s_forum_sitename'] = $g_forum_sitename;

    $sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'cookie_name' ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    $row = mysql_fetch_array($result);
    $g_forum_cookie_name = $row[0];
    $_SESSION['s_forum_cookie_name'] = $g_forum_cookie_name;
    
    $sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'cookie_path' ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    $row = mysql_fetch_array($result);
    $g_forum_cookie_path = $row[0];
    $_SESSION['s_forum_cookie_path'] = $g_forum_cookie_path;
    
    $sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'cookie_domain' ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    $row = mysql_fetch_array($result);
    $g_forum_cookie_domain = $row[0];
    $_SESSION['s_forum_cookie_domain'] = $g_forum_cookie_domain;
    
    $sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'cookie_secure' ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    $row = mysql_fetch_array($result);
    $g_forum_cookie_secure = $row[0];
    $_SESSION['s_forum_cookie_secure'] = $g_forum_cookie_secure;
    
    $sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'server_name' ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    $row = mysql_fetch_array($result);
    $g_forum_server = str_replace('/', '', $row[0]);
    $_SESSION['s_forum_server'] = $g_forum_server;
    
    $sql    = "SELECT config_value FROM ". $g_forum_praefix. "_config WHERE config_name = 'script_path' ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    $row = mysql_fetch_array($result);
    $g_forum_path = str_replace('/', '', $row[0]);
    $_SESSION['s_forum_path'] = $g_forum_path;
    
    // Admidio DB waehlen
    mysql_select_db($g_adm_db, $g_adm_con);
}


// Gueltige Session im Forum updaten und Userdaten holen. Sofern die Admidio 
// Session gueltig ist, ist auch die Forum Session gueltig
if($g_session_valid)
{
    if(!isset($_SESSION['s_user_valid']))
    {
        $_SESSION['s_user_valid'] = forum_check_user($g_current_user->login_name, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix);
    }
    if($_SESSION['s_user_valid'])
    {
        // Username, UserID und NeueNachrichten aus der Forums DB lesen.
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
    
        forum_session("update", $g_forum_userid, $g_forum_cookie_name, $g_forum_cookie_path, $g_forum_cookie_domain, $g_forum_cookie_secure, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix);
    }
    else
    {
        // Gastlogin (Anonymous = -1) im Forum anlegen
        forum_session("insert", -1, $g_forum_cookie_name, $g_forum_cookie_path, $g_forum_cookie_domain, $g_forum_cookie_secure, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix);
    }
}
else
{
    // Session Varibale löschen
    unset($_SESSION['s_user_valid']);
    
    // Die Admidio Session ist ungueltig oder abgelaufen, also die Session des Forums auf Gast umstellen.
    forum_session("insert", -1, $g_forum_cookie_name, $g_forum_cookie_path, $g_forum_cookie_domain, $g_forum_cookie_secure, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix);
}


/******************************************************************************
 * FUNKTIONEN 
 *
 * forum_check_admin
 * forum_check_password
 * forum_check_user
 * forum_update_user
 * forum_insert_user
 * forum_delete_user
 * forum_session
 *******************************************************************************/


// Funktion ueberprueft ob der Admin Account im Forum ungleich des Admidio Accounts ist.
// Falls ja wird der Admidio Account (Username & Password) ins Forum uebernommen.
function forum_check_admin($username, $password_crypt, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix)
{
    // Forum Datenbank auswaehlen
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
    // Voraussetzung ist ein gueltiger Benutzername, eine Email und ein Password
    if(strlen($forum_username) > 0 AND strlen($forum_password) > 0 AND strlen($forum_email) > 0)
    {
        $forum_useraktiv = 1;
    }
    else
    {
        $forum_useraktiv = 1;
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
    
    // Jetzt noch eine neue private Group anlegen
    $sql    = "SELECT MAX(group_id) as anzahl 
                 FROM ". $g_forum_praefix. "_groups";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    $row    = mysql_fetch_array($result);
    $new_group_id = $row[0] + 1;
    
    $sql    = "INSERT INTO ". $g_forum_praefix. "_groups
              (group_id, group_type, group_name, group_description, group_moderator, group_single_user)
              VALUES 
              ($new_group_id, 1, '', 'Personal User', 0, 1) ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    
    // und den neuen User dieser Gruppe zuordenen
    $sql    = "INSERT INTO ". $g_forum_praefix. "_user_group
              (group_id, user_id, user_pending)
              VALUES 
              ($new_group_id, $new_user_id, 0) ";
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
        $forum_useraktiv = 1;
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


// Funktion loescht einen bestehenden User im Forum
function forum_delete_user($forum_username, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix)
{
    // Forum Datenbank auswaehlen
    mysql_select_db($g_forum_db, $g_forum_con);
    
    // User_ID des Users holen
    $sql    = "SELECT user_id FROM ". $g_forum_praefix. "_users WHERE username LIKE {0} ";
    $sql    = prepareSQL($sql, array($forum_username));
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    
    $row = mysql_fetch_array($result);
    $forum_userid = $row[0];
    
    // Gruppen ID des Users holen
    $sql    = "SELECT g.group_id 
                FROM ". $g_forum_praefix. "_user_group ug, ". $g_forum_praefix. "_groups g  
                WHERE ug.user_id = ". $forum_userid ."
                    AND g.group_id = ug.group_id 
                    AND g.group_single_user = 1";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result); 
    
    $row = mysql_fetch_array($result);
    $forum_group = $row[0];
    
    // Alle Post des Users mit Gast Username versehen
    $sql = "UPDATE ". $g_forum_praefix. "_posts
            SET poster_id = -1, post_username = '" . $forum_username . "' 
            WHERE poster_id = $forum_userid";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result); 

    // Alle Topics des User auf geloescht setzten
    $sql = "UPDATE ". $g_forum_praefix. "_topics
                SET topic_poster = -1 
                WHERE topic_poster = $forum_userid";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    
    // Alle Votes des Users auf geloescht setzten
    $sql = "UPDATE ". $g_forum_praefix. "_vote_voters
            SET vote_user_id = -1
            WHERE vote_user_id = $forum_userid";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    
    // GroupID der der Group holen, in denen der User Mod Rechte hat
    $sql = "SELECT group_id
            FROM ". $g_forum_praefix. "_groups
            WHERE group_moderator = $forum_userid";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);
    
    $group_moderator[] = 0;

    while ( $row_group = mysql_fetch_array($result) )
    {
        $group_moderator[] = $row_group['group_id'];
    }
    
    if ( count($group_moderator) )
    {
        $update_moderator_id = implode(', ', $group_moderator);
        
        $sql = "UPDATE ". $g_forum_praefix. "_groups
            SET group_moderator = 2
            WHERE group_moderator IN ($update_moderator_id)";
            $result = mysql_query($sql, $g_forum_con);
            db_error($result);
    }

    // User im Forum loeschen
    $sql = "DELETE FROM ". $g_forum_praefix. "_users 
            WHERE user_id = $forum_userid ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);

    // User aus den Gruppen loeschen
    $sql = "DELETE FROM ". $g_forum_praefix. "_user_group 
            WHERE user_id = $forum_userid ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);

    // Single User Group loeschen
    $sql = "DELETE FROM ". $g_forum_praefix. "_groups
            WHERE group_id =  $forum_group ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);    
    
    // User aus der Auth Tabelle loeschen
    $sql = "DELETE FROM ". $g_forum_praefix. "_auth_access
            WHERE group_id = $forum_group ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);    

    // User aus den zu beobachteten Topics Tabelle loeschen
    $sql = "DELETE FROM ". $g_forum_praefix. "_topics_watch
            WHERE user_id = $forum_userid ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);    
    
    // User aus der Banlist Tabelle loeschen
    $sql = "DELETE FROM ". $g_forum_praefix. "_banlist
            WHERE ban_userid = $forum_userid ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);    

    // Session des Users loeschen
    $sql = "DELETE FROM ". $g_forum_praefix. "_sessions
            WHERE session_user_id = $forum_userid ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);    
    
    // Session_Keys des User loeschen
    $sql = "DELETE FROM ". $g_forum_praefix. "_sessions_keys
            WHERE user_id = $forum_userid ";
    $result = mysql_query($sql, $g_forum_con);
    db_error($result);    
    
    // Admidio DB waehlen
    mysql_select_db($g_adm_db, $g_adm_con);
}


// Funktion kuemmert sich um die Sessions und das Cookie des Forums
function forum_session($aktion, $g_forum_userid, $g_forum_cookie_name, $g_forum_cookie_path, $g_forum_cookie_domain, $g_forum_cookie_secure, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix)
{
    // Daten fuer das Cookie und den Session Eintrag im Forum aufbereiten
    $ip_sep = explode('.', getenv('REMOTE_ADDR'));
    $user_ip = sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
    $current_time = time();
    $g_forum_session_id = session_id();
    
    if($g_forum_userid == -1)
    {
        $session_logged_in = 0;
    }
    else
    {
        $session_logged_in = 1;
    }
    
    // Forum Datenbank auswaehlen
    mysql_select_db($g_forum_db, $g_forum_con);
    
    // Bereinigungsarbeiten werden nur durchgefuehrt, wenn sich der Admin anmeldet
    if($g_forum_userid == 2)
    {   
        // Bereinigung der Forum Sessions, wenn diese aelter als 60 Tage sind
        $sql    = "DELETE FROM ". $g_forum_praefix. "_sessions WHERE session_start + 518400 < $current_time ";
        $result = mysql_query($sql, $g_forum_con);
        db_error($result);
    }
    
    if($g_forum_userid > 0)
    {
        // Alte User-Session des Users im Forum loeschen
        $sql    = "DELETE FROM ". $g_forum_praefix. "_sessions WHERE session_user_id = $g_forum_userid AND session_id NOT LIKE '".$g_forum_session_id."' ";
        $result = mysql_query($sql, $g_forum_con);
        db_error($result);
    }

    // Erst mal schauen, ob sich die Session noch im Session Table des Forums befindet
    $sql    = "SELECT session_id, session_start, session_time FROM ". $g_forum_praefix. "_sessions
               WHERE session_id = '".$g_forum_session_id."' ";
    $result = mysql_query($sql, $g_forum_con);
    
    if(mysql_num_rows($result))
    {
        // Session existiert, also updaten
        
        // Sessionstart alle 5 Minuten aktualisieren
        $row = mysql_fetch_array($result);
        if($row[1] + 300 < $row[2])
        {
            $sql    = "UPDATE ". $g_forum_praefix. "_sessions 
                      SET session_time = ". $current_time .", session_start = ". $current_time .", session_user_id = ". $g_forum_userid .",  session_logged_in = $session_logged_in
                      WHERE session_id = {0}";
        }
        else
        {
            $sql    = "UPDATE ". $g_forum_praefix. "_sessions 
                      SET session_time = ". $current_time .", session_user_id = ". $g_forum_userid .",  session_logged_in = $session_logged_in
                      WHERE session_id = {0}";
        }
        $sql    = prepareSQL($sql, array($g_forum_session_id));
        $result = mysql_query($sql, $g_forum_con);
        db_error($result);
    }
    else
    {
        // Session in die Forum DB schreiben
        $sql    = "INSERT INTO " .$g_forum_praefix. "_sessions
                  (session_id, session_user_id, session_start, session_time, session_ip, session_page, session_logged_in, session_admin)
                  VALUES ('$g_forum_session_id', $g_forum_userid, $current_time, $current_time, '$user_ip', 0, $session_logged_in, 0)";
        $result = mysql_query($sql, $g_forum_con);
        db_error($result);
    }
    
    // Cookie des Forums einlesen
    if(isset($_COOKIE[$g_forum_cookie_name."_sid"]))
    {
        $g_cookie_session_id = $_COOKIE[$g_forum_cookie_name."_sid"];
        if($g_cookie_session_id != $g_forum_session_id)
        {
            // Cookie fuer die Anmeldung im Forum setzen
            setcookie($g_forum_cookie_name."_sid", $g_forum_session_id, time() + 60*60*24*30, $g_forum_cookie_path, $g_forum_cookie_domain, $g_forum_cookie_secure);
        }
    }
    else
    {
        // Cookie fuer die Anmeldung im Forum setzen
        setcookie($g_forum_cookie_name."_sid", $g_forum_session_id, time() + 60*60*24*30, $g_forum_cookie_path, $g_forum_cookie_domain, $g_forum_cookie_secure);
    }
    
    // Admidio DB waehlen
    mysql_select_db($g_adm_db, $g_adm_con);
}

?>