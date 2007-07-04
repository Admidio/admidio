<?php
/******************************************************************************
 * Klasse fuer das Forum phpBB
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Thomas Thoss
 *
 * Diese Klasse dient dazu einen Forumsobjekt zu erstellen.
 * Das Forum kann ueber diese Klasse verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf des Konstruktors und der Uebergabe der
 * aktuellen Datenbankverbindung:
 * $forum = new Forum($server, $database, $db_user, $db_password);
 *
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * connect()              - Stellt die Verbindung zur Datenbank her
 *
 * userClear()            - Userdaten und Session_Valid loeschen
 *
 * preferences()          - Die Preferences des Forums werden in die Allgemeine 
 *                          Forums Umgebungsdaten eingelesen.
 *
 * userCheck($username)   - Es wird geürüft, ob es den User (Username) schon im Forum gibt.
 *                          $username = Der login_name des Users
 *                          RETURNCODE = TRUE  - Den User gibt es
 *                          RETURNCODE = FALSE - Den User gibt es nicht
 *
 * userRegister($username)
 *
 *
 * userLogin($usr_id, $login_name, $password_crypt, $forum_user, $forum_password, $forum_email)
 *                        - Meldet den User (Username) im Forum an.
 *                          $usr_id             = Der aktuelle Admidio user_id des Users
 *                          $login_name         = Der aktuelle Admidio Login reg_login_name des Users
 *                          $password_crypt     = Der aktuelle Admidio Login reg_password Crypt des Users
 *                          $forum_user     = Der aktuelle Admidio login_name des Users
 *                          $forum_password = Das aktuelle Admidio password des Users
 *                          $forum_email        = Der aktuelle Admidio email des Users
 *                          RETURNCODE  = TRUE  - User angemeldet
 *                          RETURNCODE  = FALSE - User nicht angemeldet
 *
 * userLogoff()               - Meldet den aktuellen User im Forum ab.
 *                          RETURNCODE = TRUE  - User abgemeldet
 *                          RETURNCODE = FALSE - User nicht abgemeldet
 *
 * user($username)            - Funktion holt die Userdaten
 *                          $username = Der aktuelle login_name des Users
 *
 * getUserPM($username)   - Funktion prueft auf neue Private Messages (PM) vorliegen und 
 *                          gibt diese als String zurueck
 *
 * checkAdmin($username, $password_crypt)
 *                        - Funktion ueberprueft ob der Admin Account im Forum ungleich des 
 *                          Admidio Accounts ist. Falls ja wird der Admidio Account 
 *                          (Username & Password) ins Forum uebernommen.
 *                          $username       = Login_name des Webmasters
 *                          $password_crypt = Crypt Password des Webmasters
 *
 * checkPassword($password_admidio, $password_forum, $forum_userid)
 *                        - Funktion ueberprueft ob das Password im Forum ungleich des Admidio Passwords ist.
 *                          Falls ja wird das Admidio Password ins Forum uebernommen.
 *                          $password_admidio   = Crypt Admidio Password
 *                          $password_forum     = Crypt Forum Password 
 *                          $forum_userid       = UserID im Forum
 *
 * userInsert($username, $forum_useraktiv, $forum_password, $forum_email)
 *
 * userDelete($username)          - Loescht einen User im Forum
 *                          $username  = Der login_name des Users, der geloescht werden soll
 *                          RETURNCODE = TRUE  - User geloescht
 *                          RETURNCODE = FALSE - User nicht geloescht
 *
 * userUpdate($username, $forum_useraktiv, $forum_password, $forum_email)
 *
 * usernameUpdate($forum_new_username, $forum_old_username, $forum_useraktiv, $forum_password, $forum_email)
 *
 * session($aktion, $id)              - Kuemmert sich um die Sessions und das Cookie des Forums
 *                              $aktion = "logoff"  Die Session wird abgemeldet
 *                          $aktion = "update"  Die Session wird aktualisiert
 *                          $aktion = "insert"  Die Session wird angemeldet
 *
 * sessionBereinigen()          -  Ungenutzter Code Muell (bitte da lassen)
 *
 * Nix()                  - Eine Funktion, die nichts, rein garnichts macht.
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

class Forum
{
    // Allgemeine Variablen
    var $session_valid;
    var $session_id;
    var $praefix;
    var $message;
    var $export;

    // Forum DB Daten
    var $forum_db_connection;
    var $forum_server;
    var $forum_db;
    var $forum_user;
    var $forum_password;

    // Admidio DB Daten
    var $adm_con;
    var $adm_db;

    // Allgemeine Forums Umgebungsdaten
    var $version;                   // Hersteller und Version des Forums
    var $sitename;                  // Name des Forums
    var $server;                    // Server des Forums
    var $path;                      // Pfad zum Forum
    var $cookie_name;               // Name des Forum Cookies
    var $cookie_path;               // Pfad zum Forum Cookies
    var $cookie_domain;             // Domain des Forum Cookies
    var $cookie_secure;             // Cookie Secure des Forums

    // Forum User Daten
    var $userid;                    // UserID im Forum
    var $user;                      // Username im Forum
    var $password;                  // Password im Forum
    var $neuePM;                    // Nachrichten im Forum

    // Konstruktor
    function Forum($server, $database, $db_user, $db_password)
    {
        $this->forum_server   = $server;
        $this->forum_db       = $database;
        $this->forum_user     = $db_user;
        $this->forum_password = $db_password;
        $this->connect();
    }
    
    function connect()
    {
        $this->forum_db_connection = mysql_connect($this->forum_server, $this->forum_user, $this->forum_password);
    }


    // Userdaten und Session_Valid loeschen
    function userClear()
    {
        // Session-Valid und Userdaten loeschen
        $this->session_valid    = FALSE;
        $this->userid           = -1;
        $this->user             = "Gast";
        $this->password         = "";
        $this->neuePM           = 0;
    }


    // Die Preferences des Forums werden in die Allgemeine Forums Umgebungsdaten eingelesen.
    function preferences()
    {
        // Forums DB waehlen
        mysql_select_db($this->forum_db, $this->forum_db_connection);

        $sql    = "SELECT config_value FROM ". $this->praefix. "_config WHERE config_name = 'sitename' ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);
        $row = mysql_fetch_array($result);
        $this->sitename = $row[0];

        $sql    = "SELECT config_value FROM ". $this->praefix. "_config WHERE config_name = 'cookie_name' ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);
        $row = mysql_fetch_array($result);
        $this->cookie_name = $row[0];

        $sql    = "SELECT config_value FROM ". $this->praefix. "_config WHERE config_name = 'cookie_path' ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);
        $row = mysql_fetch_array($result);
        $this->cookie_path = $row[0];

        $sql    = "SELECT config_value FROM ". $this->praefix. "_config WHERE config_name = 'cookie_domain' ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);
        $row = mysql_fetch_array($result);
        $this->cookie_domain = $row[0];

        $sql    = "SELECT config_value FROM ". $this->praefix. "_config WHERE config_name = 'cookie_secure' ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);
        $row = mysql_fetch_array($result);
        $this->cookie_secure = $row[0];

        $sql    = "SELECT config_value FROM ". $this->praefix. "_config WHERE config_name = 'server_name' ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);
        $row = mysql_fetch_array($result);
        $this->server = str_replace('http://', '', $row[0]);
        $this->server = str_replace('HTTP://', '', $row[0]);

        $sql    = "SELECT config_value FROM ". $this->praefix. "_config WHERE config_name = 'script_path' ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);
        $row = mysql_fetch_array($result);
        $this->path = $row[0];
        if(strpos($this->path, "/", 0) == 0)
        {
            $this->path = str_replace('//', '', '/'.$this->path);
        }
        if(strpos($this->path, "/", strlen($this->path)-1) == TRUE)
        {
            $this->path = str_replace('//', '', $this->path.'/');
        }

        // Admidio DB waehlen
        mysql_select_db($this->adm_db, $this->adm_con);
    }


    // Funktion ueberprueft, ob der User schon im Forum existiert
    function userCheck($forum_username)
    {
        // Forums DB waehlen
        mysql_select_db($this->forum_db, $this->forum_db_connection);

        // User im Forum suchen
        $sql    = "SELECT user_id FROM ". $this->praefix. "_users 
                    WHERE username LIKE '$forum_username' ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);      

        // Admidio DB waehlen
        mysql_select_db($this->adm_db, $this->adm_con);

        // Wenn ein Ergebis groesser 0 vorliegt, existiert der User bereits.
        if(mysql_num_rows($result) > 0)
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }


    // Funktion registriert den aktuellen User im Forum an
    function userRegister($forum_user)
    {

    }


    // Funktion meldet den aktuellen User im Forum an
    function userLogin($usr_id, $login_name, $password_crypt, $forum_user, $forum_password, $forum_email)
    {
        /* Ueberpruefen, ob User ID =1 (Administrator) angemeldet ist. 
        Falls ja, wird geprueft, ob im Forum der gleiche Username und Password fuer die UserID 2 
        (Standard ID fuer den Administrator im Forum) besteht.
        Dieser wird im nein Fall (neue Installation des Boards) auf den Username und das Password des 
        Admidio UserID Accounts 1 (Standard fuer Administartor) geaendert und eine Meldung ausgegegen.
        */
        if($usr_id == 1)
        {
            if($this->checkAdmin($login_name, $password_crypt))
            {
                $this->message = "loginforum_admin";
            }
        }

        // Pruefen, ob es den User im Forum gibt, im Nein Fall diesem User ein Forum Account anlegen
        if(!$this->userCheck($login_name))
        {
            if($this->export)
            {
                // Export der Admido Daten ins Forum und einen Forum Account erstellen
                $this->userInsert($forum_user, 1, $forum_password, $forum_email);

                $this->message = "loginforum_new";
                $this->session_valid = TRUE;        
            }
            else
            {
                $this->message = "login";
                $this->session_valid = FALSE;
            }
        }
        else
        {
            $this->session_valid = TRUE;
        }

        if($this->session_valid)
        {
            // Userdaten holen
            $this->user($login_name);

            // Password Admidio und Forum pruefen, ggf. zuruecksetzen
            if(!($this->checkPassword($password_crypt, $this->password, $this->userid)))
            {
                // Password wurde zurueck gesetzt, Meldung vorbereiten
                $this->message = "loginforum_pass";
            }

            // Session anlegen
            $this->session("insert", $this->userid);

            if($this->message == "")
            {
                // Im Forum und in Admidio angemeldet, Meldung vorbereiten
                $this->message = "loginforum";
            }
        }
    }


    // Funktion meldet den aktuellen User im Forum ab
    function userLogoff()
    {
        // Session wird auf logoff gesetzt
        $this->session("logoff", $this->userid);

        // Forums DB waehlen
        mysql_select_db($this->forum_db, $this->forum_db_connection);

        // Last_Visit fuer das Forum aktualisieren
        $sql    = "UPDATE ". $this->praefix. "_users 
                   SET user_lastvisit = ". time() . "
                  WHERE user_id = $this->userid";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);

        // Admidio DB waehlen
        mysql_select_db($this->adm_db, $this->adm_con);

        // Session-Valid und Userdaten loeschen
        $this->userClear();
    }


    // Funktion holt die Userdaten
    function user($forum_user)
    {
        // Forums DB waehlen
        mysql_select_db($this->forum_db, $this->forum_db_connection);

        $sql    = "SELECT user_id, username, user_password FROM ". $this->praefix. "_users WHERE username LIKE {0} ";
        $sql    = prepareSQL($sql, array($forum_user));
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);

        $row = mysql_fetch_array($result);

        $this->userid   = $row[0];
        $this->user     = $row[1];
        $this->password = $row[2];

        // Admidio DB waehlen
        mysql_select_db($this->adm_db, $this->adm_con);
    }


    // Funktion prueft auf neue PM
    function getUserPM($forum_user)
    {
        // Forums DB waehlen
        mysql_select_db($this->forum_db, $this->forum_db_connection);

        $sql    = "SELECT user_new_privmsg FROM ". $this->praefix. "_users WHERE username LIKE {0} ";
        $sql    = prepareSQL($sql, array($forum_user));
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);

        $row = mysql_fetch_array($result);

        $this->neuePM = $row[0];
        $neuePM_Text  = "";

        // Wenn neue Nachrichten vorliegen, einen ansprechenden Text generieren
        if ($this->neuePM == 0)
        {
            $neuePM_Text = "und haben <b>keine</b> neue Nachrichten.";
        }
        elseif ($this->neuePM == 1)
        {
            $neuePM_Text = "und haben <b>1</b> neue Nachricht.";
        }
        else
        {
            $neuePM_Text = "und haben <b>".$this->neuePM."</b> neue Nachrichten.";
        }

        // Admidio DB waehlen
        mysql_select_db($this->adm_db, $this->adm_con);
        
        return $neuePM_Text;
    }


    // Funktion ueberprueft ob der Admin Account im Forum ungleich des Admidio Accounts ist.
    // Falls ja wird der Admidio Account (Username & Password) ins Forum uebernommen.
    function checkAdmin($username, $password_crypt)
    {
        // Forums DB waehlen
        mysql_select_db($this->forum_db, $this->forum_db_connection);

        // Administrator nun in Foren-Tabelle suchen und dort das Password, Username & UserID auslesen
        $sql    = "SELECT username, user_password, user_id FROM ". $this->praefix. "_users WHERE user_id = 2";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);

        $row = mysql_fetch_array($result);

        if($username == $row[0] AND $password_crypt == $row[1])
        {
            // Admidio DB waehlen
            mysql_select_db($this->adm_db, $this->adm_con);

            return FALSE;
        }
        else
        {
            // Password in Foren-Tabelle auf das Password in Admidio setzen
            $sql    = "UPDATE ". $this->praefix. "_users 
                       SET user_password = '". $password_crypt ."', username = '". $username ."'
                       WHERE user_id = 2";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);

            // Admidio DB waehlen
            mysql_select_db($this->adm_db, $this->adm_con);

            return TRUE;
        }
    }


    // Funktion ueberprueft ob das Password im Forum ungleich des Admidio Passwords ist.
    // Falls ja wird das Admidio Password ins Forum uebernommen.
    function checkPassword($password_admidio, $password_forum, $forum_userid)
    {
        // Passwoerter vergleichen
        if ($password_admidio == $password_forum)
        {
            return TRUE;
        }
        else
        {
            // Forums DB waehlen
            mysql_select_db($this->forum_db, $this->forum_db_connection);

            // Password in Foren-Tabelle auf das Password in Admidio setzen
            $sql    = "UPDATE ". $this->praefix. "_users 
                       SET user_password = '". $password_admidio ."'
                       WHERE user_id = {0}";
            $sql    = prepareSQL($sql, array($forum_userid));
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);

            // Admidio DB waehlen
            mysql_select_db($this->adm_db, $this->adm_con);

            return FALSE;
        }
    }


    // Funktion legt einen neuen Benutzer im Forum an
    function userInsert($forum_username, $forum_useraktiv, $forum_password, $forum_email)
    {
        // Forums DB waehlen
        mysql_select_db($this->forum_db, $this->forum_db_connection);

        // jetzt noch den neuen User ins Forum eintragen, ggf. Fehlermeldung als Standard ausgeben.
        $sql    = "SELECT MAX(user_id) as anzahl FROM ". $this->praefix. "_users";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);
        $row    = mysql_fetch_array($result);
        $new_user_id = $row[0] + 1;

        $sql    = "INSERT INTO ". $this->praefix. "_users
                  (user_id, user_active, username, user_password, user_regdate, user_timezone,
                  user_style, user_lang, user_viewemail, user_attachsig, user_allowhtml,
                  user_dateformat, user_email, user_notify, user_notify_pm, user_popup_pm, user_avatar)
                  VALUES 
                  ($new_user_id, $forum_useraktiv, '$forum_username', '$forum_password', ". time(). ", 1.00,
                  2, 'german', 0, 1, 0, 'd.m.Y, H:i', '$forum_email', 0, 1, 1, '') ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);

        // Jetzt noch eine neue private Group anlegen
        $sql    = "SELECT MAX(group_id) as anzahl 
                   FROM ". $this->praefix. "_groups";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);
        $row    = mysql_fetch_array($result);
        $new_group_id = $row[0] + 1;

        $sql    = "INSERT INTO ". $this->praefix. "_groups
                  (group_id, group_type, group_name, group_description, group_moderator, group_single_user)
                  VALUES 
                  ($new_group_id, 1, '', 'Personal User', 0, 1) ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);

        // und den neuen User dieser Gruppe zuordenen
        $sql    = "INSERT INTO ". $this->praefix. "_user_group
                  (group_id, user_id, user_pending)
                  VALUES 
                  ($new_group_id, $new_user_id, 0) ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);

        // Admidio DB waehlen
        mysql_select_db($this->adm_db, $this->adm_con);
    }


    // Funktion loescht einen bestehenden User im Forum
    function userDelete($forum_username)
    {
        if(strlen($forum_username) > 0)
        {
            // Forums DB waehlen
            mysql_select_db($this->forum_db, $this->forum_db_connection);

            // User_ID des Users holen
            $sql    = "SELECT user_id FROM ". $this->praefix. "_users WHERE username LIKE {0} ";
            $sql    = prepareSQL($sql, array($forum_username));
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);

            $row = mysql_fetch_array($result);
            $forum_userid = $row[0];

            // Gruppen ID des Users holen
            $sql    = "SELECT g.group_id 
                        FROM ". $this->praefix. "_user_group ug, ". $this->praefix. "_groups g  
                        WHERE ug.user_id = ". $forum_userid ."
                            AND g.group_id = ug.group_id 
                            AND g.group_single_user = 1";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__); 

            $row = mysql_fetch_array($result);
            $forum_group = $row[0];

            // Alle Post des Users mit Gast Username versehen
            $sql = "UPDATE ". $this->praefix. "_posts
                    SET poster_id = -1, post_username = '" . $forum_username . "' 
                    WHERE poster_id = $forum_userid";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__); 

            // Alle Topics des User auf geloescht setzten
            $sql = "UPDATE ". $this->praefix. "_topics
                        SET topic_poster = -1 
                        WHERE topic_poster = $forum_userid";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);

            // Alle Votes des Users auf geloescht setzten
            $sql = "UPDATE ". $this->praefix. "_vote_voters
                    SET vote_user_id = -1
                    WHERE vote_user_id = $forum_userid";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);

            // GroupID der der Group holen, in denen der User Mod Rechte hat
            $sql = "SELECT group_id
                    FROM ". $this->praefix. "_groups
                    WHERE group_moderator = $forum_userid";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);

            $group_moderator[] = 0;

            while ( $row_group = mysql_fetch_array($result) )
            {
                $group_moderator[] = $row_group['group_id'];
            }

            if ( count($group_moderator) )
            {
                $update_moderator_id = implode(', ', $group_moderator);

                $sql = "UPDATE ". $this->praefix. "_groups
                    SET group_moderator = 2
                    WHERE group_moderator IN ($update_moderator_id)";
                    $result = mysql_query($sql, $this->forum_db_connection);
                    db_error($result,__FILE__,__LINE__);
            }

            // User im Forum loeschen
            $sql = "DELETE FROM ". $this->praefix. "_users 
                    WHERE user_id = $forum_userid ";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);

            // User aus den Gruppen loeschen
            $sql = "DELETE FROM ". $this->praefix. "_user_group 
                    WHERE user_id = $forum_userid ";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);

            // Single User Group loeschen
            $sql = "DELETE FROM ". $this->praefix. "_groups
                    WHERE group_id =  $forum_group ";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);    

            // User aus der Auth Tabelle loeschen
            $sql = "DELETE FROM ". $this->praefix. "_auth_access
                    WHERE group_id = $forum_group ";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);    

            // User aus den zu beobachteten Topics Tabelle loeschen
            $sql = "DELETE FROM ". $this->praefix. "_topics_watch
                    WHERE user_id = $forum_userid ";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);    

            // User aus der Banlist Tabelle loeschen
            $sql = "DELETE FROM ". $this->praefix. "_banlist
                    WHERE ban_userid = $forum_userid ";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);    

            // Session des Users loeschen
            $sql = "DELETE FROM ". $this->praefix. "_sessions
                    WHERE session_user_id = $forum_userid ";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);    

            // Session_Keys des User loeschen
            $sql = "DELETE FROM ". $this->praefix. "_sessions_keys
                    WHERE user_id = $forum_userid ";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);    

            // Admidio DB waehlen
            mysql_select_db($this->adm_db, $this->adm_con);

            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }


    // Funktion updated einen bestehenden User im Forum
    function userUpdate($forum_username, $forum_useraktiv, $forum_password, $forum_email)
    {
        // Erst mal schauen ob der User alle Kriterien erfuellt um im Forum aktiv zu sein
        // Voraussetzung ist ein gueltiger Benutzername, eine Email und ein Password
        if(strlen($forum_username) > 0 AND strlen($forum_password) > 0 AND strlen($forum_email) > 0)
        {
            $forum_useraktiv = 1;
        }
        else
        {
            $forum_useraktiv = 1;
        }

        // Forums DB waehlen
        mysql_select_db($this->forum_db, $this->forum_db_connection);

        // User im Forum updaten
        $sql    = "UPDATE ". $this->praefix. "_users
                   SET user_password = '$forum_password', user_active = $forum_useraktiv, user_email = '$forum_email'
                   WHERE username = '". $forum_username. "' ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);

        // Admidio DB waehlen
        mysql_select_db($this->adm_db, $this->adm_con);
    }


    // Funktion updated einen bestehenden User im Forum
    function usernameUpdate($forum_new_username, $forum_old_username, $forum_useraktiv, $forum_password, $forum_email)
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

        // Forums DB waehlen
        mysql_select_db($this->forum_db, $this->forum_db_connection);

        // User im Forum updaten
        $sql    = "UPDATE ". $this->praefix. "_users
                   SET username = '$forum_new_username', user_password = '$forum_password', user_active = $forum_useraktiv, user_email = '$forum_email'
                   WHERE username = '". $forum_old_username. "' ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);

        // Admidio DB waehlen
        mysql_select_db($this->adm_db, $this->adm_con);
    }

    // diese Funktion bekommt den Admidio-Session-Status (eingeloggt ja/nein) uebergeben und
    // prueft dann, ob die Session im Forum aktualisiert werden muss
    function checkSession($admidio_login_valid)
    {
        // Forum Session auf Gueltigkeit pruefen
        // Nur wenn die Admidio Session valid ist, wird auf die Forum Session valid sein
        if($admidio_login_valid)
        {
            // Wenn die Forum Session bereits valid ist, wird diese Abfrage uebersprungen
            if($this->session_valid != TRUE)
            { 
                $this->session_valid = $this->userCheck($this->user);
            }

            // Wenn die Forumssession gueltig ist, Userdaten holen und gueltige Session im Forum updaten. 
            if($this->session_valid)
            {
                // Sofern die Admidio Session gueltig ist, ist auch die Forum Session gueltig
                $this->session("update", $this->userid);
            }
        }
        else
        {
            // Die Admidio Session ist nicht valid, also ist die Forum Session ebenfalls nicht valid
            if($this->session_valid)
            {
                // Admidio Session ist abgelaufen, ungueltig oder ein logoff, also im Forum logoff
                $this->session("logoff", $this->userid);
            }
            $this->session_valid = FALSE;
        }
    }

    // Funktion kuemmert sich um die Sessions und das Cookie des Forums
    function session($aktion, $forum_userid)
    {
        // Daten fuer das Cookie und den Session Eintrag im Forum aufbereiten
        $ip_sep = explode('.', getenv('REMOTE_ADDR'));
        $user_ip = sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
        $current_time = time();

        // Forums DB waehlen
        mysql_select_db($this->forum_db, $this->forum_db_connection);

        // Nachschauen, ob dieser User mehrere SessionIDs hat, diese dann bis auf die aktuelle loeschen
        $sql    = "SELECT session_id FROM ". $this->praefix. "_sessions
                   WHERE session_user_id = $forum_userid";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);

        if(mysql_num_rows($result) > 1)
        {
            $sql    = "DELETE FROM ". $this->praefix. "_sessions WHERE session_user_id = $forum_userid AND session_id NOT LIKE '".$this->session_id."' ";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);
        }

        // Pruefen, ob sich die aktuelle Session noch im Session Table des Forums befindet
        $sql    = "SELECT session_id, session_start, session_time FROM ". $this->praefix. "_sessions
                   WHERE session_id = '".$this->session_id."' ";
        $result = mysql_query($sql, $this->forum_db_connection);
        db_error($result,__FILE__,__LINE__);

        if(mysql_num_rows($result))
        {
            if($aktion == "logoff")
            {
                $sql    = "UPDATE ". $this->praefix. "_sessions 
                           SET session_time = ". $current_time .", session_ip = '". $user_ip ."', session_user_id = ". $forum_userid .",  session_logged_in = 0
                           WHERE session_id = {0}";
                $sql    = prepareSQL($sql, array($this->session_id));
                $result = mysql_query($sql, $this->forum_db_connection);
                db_error($result,__FILE__,__LINE__);
            }

            if($aktion == "update")
            {
                $sql    = "UPDATE ". $this->praefix. "_sessions
                           SET session_time = ". $current_time .", session_ip = '". $user_ip ."' 
                           WHERE session_id = {0}";
                $sql    = prepareSQL($sql, array($this->session_id));
                $result = mysql_query($sql, $this->forum_db_connection);
                db_error($result,__FILE__,__LINE__);
            }

            if($aktion == "insert")
            {
                $sql    = "UPDATE ". $this->praefix. "_sessions 
                           SET session_time = ". $current_time .", session_start = ". $current_time .", session_ip = '". $user_ip ."', session_user_id = ". $forum_userid .",  session_logged_in = 1
                           WHERE session_id = {0}";
                $sql    = prepareSQL($sql, array($this->session_id));
                $result = mysql_query($sql, $this->forum_db_connection);
                db_error($result,__FILE__,__LINE__);
            }
        }
        else
        {
            // Session auf jeden Fall in die Forum DB schreiben, damit der User angemeldet ist
            $sql    = "INSERT INTO " .$this->praefix. "_sessions
                      (session_id, session_user_id, session_start, session_time, session_ip, session_page, session_logged_in, session_admin)
                      VALUES ('$this->session_id', $forum_userid, $current_time, $current_time, '$user_ip', 0, 1, 0)";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);
        }   

        // Cookie des Forums einlesen
        if(isset($_COOKIE[$this->cookie_name."_sid"]))
        {
            $g_cookie_session_id = $_COOKIE[$this->cookie_name."_sid"];
            if($g_cookie_session_id != $this->session_id)
            {
                // Cookie fuer die Anmeldung im Forum setzen
                setcookie($this->cookie_name."_sid", $this->session_id, 0, $this->cookie_path, $this->cookie_domain, $this->cookie_secure);
            }
        }
        else
        {
            // Cookie fuer die Anmeldung im Forum setzen
            setcookie($this->cookie_name."_sid", $this->session_id, 0, $this->cookie_path, $this->cookie_domain, $this->cookie_secure);
        }

        // Admidio DB waehlen
        mysql_select_db($this->adm_db, $this->adm_con);
    }


    // CODEMUELL fuer spaeter
    function sessionBereinigen()
    {
        // Bereinigungsarbeiten werden nur durchgefuehrt, wenn sich der Admin anmeldet
        if($forum_userid == 2)
        {
            // Bereinigung der Forum Sessions, wenn diese aelter als 60 Tage sind
            $sql    = "DELETE FROM ". $this->praefix. "_sessions WHERE session_start + 518400 < $current_time ";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);
        }

        if($forum_userid > 0)
        {
            // Alte User-Session des Users im Forum loeschen
            $sql    = "DELETE FROM ". $this->praefix. "_sessions WHERE session_user_id = $forum_userid AND session_id NOT LIKE '".$g_forum_session_id."' ";
            $result = mysql_query($sql, $this->forum_db_connection);
            db_error($result,__FILE__,__LINE__);
        }
    }
}
?>