<?php
/******************************************************************************
 * Klasse fuer das Forum phpBB Version 2.0.x
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu einen Forumsobjekt zu erstellen.
 * Das Forum kann ueber diese Klasse verwaltet werden
 *
 * Das Objekt wird erzeugt durch Aufruf der Factory-Klassenmethode mit Angabe 
 * der entsprechenden Forumschnittstelle:
 * $forum = Forum::createForumObject("phpBB2");
 *
 *
 * The following functions are available:
 *
 * connect()              - Stellt die Verbindung zur Datenbank her
 * userClear()            - Userdaten und Session_Valid loeschen
 * initialize()           - Die notwendigen Einstellungen des Forums werden eingelesen und die
 *                          Session im Forum registriert.
 * userExists($username)  - Es wird geprueft, ob es den User (Username) schon im Forum gibt.
 *                          $username = Der login_name des Users
 *                          RETURNCODE = TRUE  - Den User gibt es
 *                          RETURNCODE = FALSE - Den User gibt es nicht
 * userLogin($login_name, $password, $email, $webmaster = 0)
 *                        - Meldet den User (Username) im Forum an.
 *                          $login_name = Der aktuelle Admidio Login reg_login_name des Users
 *                          $password   = Der aktuelle Admidio Login reg_password des Users
 *                          $email      = Der aktuelle Admidio email des Users
 *                          $webmaster  = Kennzeichen, ob User ein Admidio-Webmaster ist true/false
 *                                        dann wird er auch im Forum zum Administrator gemacht
 *                          RETURNCODE  = TRUE  - User angemeldet
 *                          RETURNCODE  = FALSE - User nicht angemeldet
 * userLogoff()               - Meldet den aktuellen User im Forum ab.
 *                          RETURNCODE = TRUE  - User abgemeldet
 *                          RETURNCODE = FALSE - User nicht abgemeldet
 * userDaten($username)   - Funktion holt die Userdaten
 *                          $username = Der aktuelle login_name des Users
 * getUserPM($username)   - Funktion prueft auf neue Private Messages (PM) vorliegen und 
 *                          gibt diese als String zurueck
 * setAdmin($username)  - Funktion ueberprueft ob der uebergebene User Admin im Forum ist und 
 *                          passt dies ggf. an
 *                          $username       = Login_name des Webmasters
 * checkPassword($password_admidio, $password_forum, $forum_userid)
 *                        - Funktion ueberprueft ob das Password im Forum ungleich des Admidio Passwords ist.
 *                          Falls ja wird das Admidio Password ins Forum uebernommen.
 *                          $password_admidio   = Crypt Admidio Password
 *                          $password_forum     = Crypt Forum Password 
 *                          $forum_userid       = UserID im Forum
 * userSave($username, $forum_useraktiv, $forum_password, $forum_email, $forum_old_username = "", $set_admin = 0)
 *                        - Funktion speichert die Daten eines Users
 *                          existiert der User noch nicht, wird er angelegt, ansonsten aktualisiert
 * userInsert($username, $forum_useraktiv, $forum_password, $forum_email)
 * userDelete($username)          - Loescht einen User im Forum
 *                          $username  = Der login_name des Users, der geloescht werden soll
 *                          RETURNCODE = TRUE  - User geloescht
 *                          RETURNCODE = FALSE - User nicht geloescht
 * checkSession($admidio_usr_login_name)
 *                        - diese Funktion bekommt den Admidio-Session-Status (eingeloggt ja/nein) uebergeben
 *                          und prueft dann, ob die Session im Forum aktualisiert werden muss
 * session($aktion, $id)  - Kuemmert sich um die Sessions und das Cookie des Forums
 *                          $aktion = "logoff"  Die Session wird abgemeldet
 *                          $aktion = "update"  Die Session wird aktualisiert
 *                          $aktion = "insert"  Die Session wird angemeldet
 *
 *****************************************************************************/

class PhpBB2
{
    // Allgemeine Variablen
    var $session_valid;
    var $session_id;
    var $praefix;
    var $message;
    var $export;

    // Forum DB Daten
    var $forum_db;
    var $new_db_connection;
    
    // Allgemeine Forums Umgebungsdaten
    var $sitename;                  // Name des Forums
    var $url;                       // URL zum Forum abhaengig von der Admidioeinstellung (iFrame oder neues Fenster)
    var $url_intern;                // URL zum Forum
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
    function PhpBB2()
    {
        $this->forum_db = Database::createDatabaseObject('mysql');
    }
    
    function connect($sql_server, $sql_user, $sql_password, $sql_dbname, $admidio_db = NULL)
    {
        // falls die Admidio-DB sich von der Forum-DB unterscheidet, 
        // muss eine neue DB-Verbindung aufgemacht werden
        if(is_null($admidio_db) == false
        && $admidio_db->server   == $this->forum_db->server
        && $admidio_db->user     == $this->forum_db->user
        && $admidio_db->password == $this->forum_db->password
        && $admidio_db->dbName   == $this->forum_db->dbName)
        {
            $new_db_connection = false;
        }
        else
        {
            $new_db_connection = true;
        }
        
        return $this->forum_db->connect($sql_server, $sql_user, $sql_password, $sql_dbname, $new_db_connection);
    }


    // Userdaten und Session_Valid loeschen
    function userClear()
    {
        // Session-Valid und Userdaten loeschen
        $this->session_valid    = false;
        $this->userid           = -1;
        $this->user             = 'Gast';
        $this->password         = '';
        $this->neuePM           = 0;
    }


    // Notwendige Einstellungen des Forums werden eingelesen.
    function initialize($session_id, $table_praefix, $user_export, $link_intern, $admidio_login_name)
    {
        global $g_root_path;

        if($session_id != $this->session_id)
        {
            // pruefen, ob das Praefix richtig gesetzt wurde und die Config-Tabelle gefunden werden kann
            $sql = 'SHOW TABLE STATUS LIKE \''. $table_praefix. '_config\' ';
            $this->forum_db->query($sql);
            
            if($this->forum_db->num_rows() == 1)
            {
                $this->session_id = $session_id;
                $this->praefix    = $table_praefix;
                $this->export     = $user_export;
                $server_name      = '';
                $script_path      = '';
                        
                // wichtige Einstellungen des Forums werden eingelesen
                $sql    = 'SELECT config_name, config_value 
                             FROM '. $this->praefix. '_config 
                            WHERE config_name IN (\'sitename\',\'cookie_name\',\'cookie_path\',\'cookie_domain\',
                                                  \'cookie_secure\',\'server_name\',\'script_path\') ';
                $result = $this->forum_db->query($sql);
                
                while($row = $this->forum_db->fetch_array($result))
                {
                    switch($row['config_name'])
                    {
                        case 'sitename':
                            $this->sitename = $row['config_value'];
                            break;

                        case 'cookie_name':
                            $this->cookie_name = $row['config_value'];
                            break;

                        case 'cookie_path':
                            $this->cookie_path = $row['config_value'];
                            break;

                        case 'cookie_domain':
                            $this->cookie_domain = $row['config_value'];
                            break;

                        case 'cookie_secure':
                            $this->cookie_secure = $row['config_value'];
                            break;

                        case 'server_name':
                            $server_name = $row['config_value'];
                            break;

                        case 'script_path':
                            $script_path = $row['config_value'];
                            break;
                    }
                }
                
                // URLs zum Forum ermitteln
                $this->url_intern = str_replace('http://', '', admStrToLower($server_name));
                if(strlen($script_path) > 1)
                {
                    $this->url_intern .= '/'. $script_path;
                }
                $this->url_intern = trim(str_replace('//', '/', $this->url_intern. '/index.php'));
                $this->url_intern = 'http://'. $this->url_intern;

                if($link_intern == true)
                {
                    $this->url = $g_root_path.'/adm_program/index_forum.php';
                }
                else
                {
                    $this->url = $this->url_intern;
                }
            }
            else
            {
                return false;
            }
        }
        
        // Forumsession pruefen und aktualisieren
        $this->checkSession($admidio_login_name);
        return true;
    }


    // Funktion ueberprueft, ob der User schon im Forum existiert
    function userExists($username)
    {
        // User im Forum suchen
        $sql    = 'SELECT user_id FROM '. $this->praefix. '_users 
                    WHERE username LIKE \''.$username.'\' ';
        $result = $this->forum_db->query($sql);

        // Wenn ein Ergebis groesser 0 vorliegt, existiert der User bereits.
        if($this->forum_db->num_rows($result) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }


    // Funktion meldet den aktuellen User im Forum an
    function userLogin($login_name, $password, $email, $set_admin = 0)
    {
        // Pruefen, ob es den User im Forum gibt, im Nein Fall diesem User ein Forum Account anlegen
        if(!$this->userExists($login_name))
        {
            if($this->export)
            {
                // Export der Admido Daten ins Forum und einen Forum Account erstellen
                $this->userInsert($login_name, 1, md5($password), $email);

                $this->message = 'SYS_FORUM_LOGIN_NEW';
                $this->session_valid = TRUE;        
            }
            else
            {
                $this->message = 'SYS_LOGIN_SUCCESSFUL';
                $this->session_valid = FALSE;
            }
        }
        else
        {
            $this->session_valid = TRUE;
        }

        // Falls ein Admidio-Webmaster angemeldet wird, so sollte dieser automatisch im Forum 
        // auch zum Webmaster gemacht werden
        if($set_admin)
        {
            if($this->setAdmin($login_name))
            {
                $this->message = 'SYS_FORUM_LOGIN_ADMIN';
            }
        }
        
        if($this->session_valid)
        {
            // Userdaten holen
            $this->userDaten($login_name);

            // Password Admidio und Forum pruefen, ggf. zuruecksetzen
            if(!($this->checkPassword(md5($password), $this->password, $this->userid)))
            {
                // Password wurde zurueck gesetzt, Meldung vorbereiten
                $this->message = 'SYS_FORUM_LOGIN_PASSWORD';
            }

            // Session anlegen
            $this->session('insert', $this->userid);

            if($this->message == '')
            {
                // Im Forum und in Admidio angemeldet, Meldung vorbereiten
                $this->message = 'SYS_FORUM_LOGIN_SUCCESSFUL';
            }
        }
    }


    // Funktion meldet den aktuellen User im Forum ab
    function userLogoff()
    {
        if($this->session_valid)
        {
            // Session wird auf logoff gesetzt
            $this->session('logoff', $this->userid);
    
            // Last_Visit fuer das Forum aktualisieren
            $sql    = 'UPDATE '. $this->praefix. '_users 
                       SET user_lastvisit = '. time() . '
                      WHERE user_id = '.$this->userid;
            $result = $this->forum_db->query($sql);
    
            // Session-Valid und Userdaten loeschen
            $this->userClear();
        }
    }


    // Funktion holt die Userdaten
    function userDaten($forum_user)
    {
        $sql    = 'SELECT user_id, username, user_password FROM '. $this->praefix. '_users WHERE username LIKE \''.$forum_user.'\' ';
        $result = $this->forum_db->query($sql);
        $row    = $this->forum_db->fetch_array($result);

        $this->userid   = $row[0];
        $this->user     = $row[1];
        $this->password = $row[2];
    }


    // Funktion prueft auf neue PM
    function getUserPM($forum_user)
    {
        $sql    = 'SELECT user_new_privmsg FROM '. $this->praefix. '_users WHERE username LIKE \''.$forum_user.'\' ';
        $result = $this->forum_db->query($sql);
        $row    = $this->forum_db->fetch_array($result);

        $this->neuePM = $row[0];
        
        return $this->neuePM;
    }


    // Funktion ueberprueft ob der uebergebene User Admin im Forum ist und passt dies ggf. an
    function setAdmin($username, $admin = true)
    {
        // Administrator nun in Foren-Tabelle suchen und dort das Password, Username & UserID auslesen
        $sql    = 'SELECT user_id 
                     FROM '. $this->praefix. '_users 
                    WHERE username   = \''. $username. '\'
                      AND user_level = 1';
        $this->forum_db->query($sql);
        
        if($this->forum_db->num_rows() == 0)
        {
            // Administratorlevel setzen oder zuruecknehmen
            $user_level = 0;
            if($admin)
            {
                $user_level = 1;
            }
            
            $sql    = 'UPDATE '. $this->praefix. '_users 
                          SET user_level = '. $user_level. '
                        WHERE username = \''. $username. '\'';
            $this->forum_db->query($sql);

            return true;
        }
        else
        {
            return false;
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
            // Password in Foren-Tabelle auf das Password in Admidio setzen
            $sql    = 'UPDATE '. $this->praefix. '_users 
                          SET user_password = \''. $password_admidio .'\'
                        WHERE user_id = '.$forum_userid;
            $this->forum_db->query($sql);

            return FALSE;
        }
    }

    // Funktion speichert die Daten eines Users
    // existiert der User noch nicht, wird er angelegt, ansonsten aktualisiert
    function userSave($username, $password, $email, $old_username = "", $usercode = 0, $set_admin = 0)
    {
        // Erst mal schauen ob der User alle Kriterien erfuellt um im Forum aktiv zu sein
        // Voraussetzung ist ein gueltiger Benutzername, eine Email und ein Password
        if(strlen($username) > 0 AND strlen($password) > 0 AND strlen($email) > 0 AND ($usercode == 3 OR strlen($old_username) > 0))
        {
            $user_aktiv = 1;
        }
        else
        {
            $user_aktiv = 0;
        }
        
        if(strlen($old_username) == 0)
        {
            $old_username = $username;
        }

        if($this->userExists($old_username))
        {
            // User im Forum updaten
            $sql    = 'UPDATE '. $this->praefix. '_users
                          SET username      = \''.$username.'\'
                            , user_password = \''.$password.'\'
                            , user_active   = '.$user_aktiv.'
                            , user_email    = \''.$email.'\'
                        WHERE username LIKE \''.$old_username.'\' ';
            $this->forum_db->query($sql);
        }
        else
        {
            // User anlegen
            $this->userInsert($username, $user_aktiv, $password, $email);
        }
        
        // Falls ein Admidio-Webmaster angemeldet wird, so sollte dieser automatisch im Forum 
        // auch zum Webmaster gemacht werden
        if($set_admin)
        {
            $this->setAdmin($username);
        }        
    }

    // Funktion legt einen neuen Benutzer im Forum an
    function userInsert($forum_username, $forum_useraktiv, $forum_password, $forum_email)
    {
        // Erst mal schauen ob der User alle Kriterien erfuellt um im Forum aktiv zu sein
        // Voraussetzung ist ein gueltiger Benutzername, eine Email und ein Password
        if(strlen($forum_username) > 0 AND strlen($forum_password) > 0 AND strlen($forum_email) > 0 AND $forum_useraktiv == 1)
        {
            $forum_useraktiv = 1;
        }
        else
        {
            $forum_useraktiv = 0;
        }
            
        // jetzt noch den neuen User ins Forum eintragen, ggf. Fehlermeldung als Standard ausgeben.
        $sql    = 'SELECT MAX(user_id) as anzahl FROM '. $this->praefix. '_users';
        $result = $this->forum_db->query($sql);
        $row    = $this->forum_db->fetch_array($result);
        $new_user_id = $row[0] + 1;

        $sql    = 'INSERT INTO '. $this->praefix. '_users
                  (user_id, user_active, username, user_password, user_regdate, user_timezone,
                  user_style, user_lang, user_viewemail, user_attachsig, user_allowhtml,
                  user_dateformat, user_email, user_notify, user_notify_pm, user_popup_pm, user_avatar)
                  VALUES 
                  ('.$new_user_id.', '.$forum_useraktiv.', \''.$forum_username.'\', \''.$forum_password.'\', '. time(). ', 1.00,
                  2, \'german\', 0, 1, 0, \'d.m.Y, H:i\', \''.$forum_email.'\', 0, 1, 1, \'\') ';
        $result = $this->forum_db->query($sql);

        // Jetzt noch eine neue private Group anlegen
        $sql    = 'SELECT MAX(group_id) as anzahl 
                   FROM '. $this->praefix. '_groups';
        $result = $this->forum_db->query($sql);
        $row    = $this->forum_db->fetch_array($result);
        $new_group_id = $row[0] + 1;

        $sql    = 'INSERT INTO '. $this->praefix. '_groups
                  (group_id, group_type, group_name, group_description, group_moderator, group_single_user)
                  VALUES 
                  ('.$new_group_id.', 1, \'\', \'Personal User\', 0, 1) ';
        $result = $this->forum_db->query($sql);

        // und den neuen User dieser Gruppe zuordenen
        $sql    = 'INSERT INTO '. $this->praefix. '_user_group
                  (group_id, user_id, user_pending)
                  VALUES 
                  ('.$new_group_id.', '.$new_user_id.', 0) ';
        $result = $this->forum_db->query($sql);
    }


    // Funktion loescht einen bestehenden User im Forum
    function userDelete($forum_username)
    {
        if(strlen($forum_username) > 0)
        {
            // User_ID des Users holen
            $sql    = 'SELECT user_id FROM '. $this->praefix. '_users WHERE username LIKE \''.$forum_username.'\' ';
            $result = $this->forum_db->query($sql);
            $row    = $this->forum_db->fetch_array($result);
            $forum_userid = $row[0];

            if($forum_userid > 0)
            {
                // Gruppen ID des Users holen
                $sql    = 'SELECT g.group_id 
                             FROM '. $this->praefix. '_user_group ug, '. $this->praefix. '_groups g  
                            WHERE ug.user_id = '. $forum_userid .'
                              AND g.group_id = ug.group_id 
                              AND g.group_single_user = 1';
                $result = $this->forum_db->query($sql);
                $row    = $this->forum_db->fetch_array($result);
                $forum_group = $row[0];
    
                // Alle Post des Users mit Gast Username versehen
                $sql = 'UPDATE '. $this->praefix. '_posts
                        SET poster_id = -1, post_username = \''.$forum_username.'\' 
                        WHERE poster_id = '.$forum_userid;
                $result = $this->forum_db->query($sql);
    
                // Alle Topics des User auf geloescht setzten
                $sql = 'UPDATE '. $this->praefix. '_topics
                            SET topic_poster = -1 
                            WHERE topic_poster = '.$forum_userid;
                $result = $this->forum_db->query($sql);
    
                // Alle Votes des Users auf geloescht setzten
                $sql = 'UPDATE '. $this->praefix. '_vote_voters
                        SET vote_user_id = -1
                        WHERE vote_user_id = '.$forum_userid;
                $result = $this->forum_db->query($sql);
    
                // GroupID der der Group holen, in denen der User Mod Rechte hat
                $sql = 'SELECT group_id
                        FROM '. $this->praefix. '_groups
                        WHERE group_moderator = '.$forum_userid;
                $result = $this->forum_db->query($sql);
    
                $group_moderator[] = 0;
    
                while ( $row_group = $this->forum_db->fetch_array($result) )
                {
                    $group_moderator[] = $row_group['group_id'];
                }
    
                if ( count($group_moderator) )
                {
                    $update_moderator_id = implode(', ', $group_moderator);
    
                    $sql = 'UPDATE '. $this->praefix. '_groups
                        SET group_moderator = 2
                        WHERE group_moderator IN ('.$update_moderator_id.')';
                        $result = $this->forum_db->query($sql);
                }
    
                // User im Forum loeschen
                $sql = 'DELETE FROM '. $this->praefix. '_users 
                        WHERE user_id = '.$forum_userid;
                $result = $this->forum_db->query($sql);
    
                // User aus den Gruppen loeschen
                $sql = 'DELETE FROM '. $this->praefix. '_user_group 
                        WHERE user_id = '.$forum_userid;
                $result = $this->forum_db->query($sql);
    
                // Single User Group loeschen
                $sql = 'DELETE FROM '. $this->praefix. '_groups
                        WHERE group_id = '.$forum_group;
                $result = $this->forum_db->query($sql);
    
                // User aus der Auth Tabelle loeschen
                $sql = 'DELETE FROM '. $this->praefix. '_auth_access
                        WHERE group_id = '.$forum_group;
                $result = $this->forum_db->query($sql);
    
                // User aus den zu beobachteten Topics Tabelle loeschen
                $sql = 'DELETE FROM '. $this->praefix. '_topics_watch
                        WHERE user_id = '.$forum_userid;
                $result = $this->forum_db->query($sql);
    
                // User aus der Banlist Tabelle loeschen
                $sql = 'DELETE FROM '. $this->praefix. '_banlist
                        WHERE ban_userid = '.$forum_userid;
                $result = $this->forum_db->query($sql);
    
                // Session des Users loeschen
                $sql = 'DELETE FROM '. $this->praefix. '_sessions
                        WHERE session_user_id = '.$forum_userid;
                $result = $this->forum_db->query($sql);
    
                // Session_Keys des User loeschen
                $sql = 'DELETE FROM '. $this->praefix. '_sessions_keys
                        WHERE user_id = '.$forum_userid;
                $result = $this->forum_db->query($sql);
                
                return true;
            }
        }
        return false;
    }

    // diese Funktion bekommt den Admidio-Session-Status (eingeloggt ja/nein) uebergeben und
    // prueft dann, ob die Session im Forum aktualisiert werden muss
    function checkSession($admidio_usr_login_name)
    {
        // Forum Session auf Gueltigkeit pruefen
        // Nur wenn die Admidio Session valid ist, wird auf die Forum Session valid sein
        if(strlen($admidio_usr_login_name) > 0)
        {
            // Userdaten holen
            $this->userDaten($admidio_usr_login_name);
            $this->getUserPM($admidio_usr_login_name);
        
            // Wenn die Forum Session bereits valid ist, wird diese Abfrage uebersprungen
            if($this->session_valid != true)
            { 
                $this->session_valid = $this->userExists($this->user);
            }

            // Wenn die Forumssession gueltig ist, Userdaten holen und gueltige Session im Forum updaten. 
            if($this->session_valid)
            {
                // Sofern die Admidio Session gueltig ist, ist auch die Forum Session gueltig
                $this->session('update', $this->userid);
            }
        }
        else
        {
            // Die Admidio Session ist nicht valid, also ist die Forum Session ebenfalls nicht valid
            if($this->session_valid)
            {
                // Admidio Session ist abgelaufen, ungueltig oder ein logoff, also im Forum logoff
                $this->session('logoff', $this->userid);
            }
            $this->session_valid = false;
        }
    }

    // Funktion kuemmert sich um die Sessions und das Cookie des Forums
    function session($aktion, $forum_userid)
    {
        // Daten fuer das Cookie und den Session Eintrag im Forum aufbereiten
        $ip_sep = explode('.', getenv('REMOTE_ADDR'));
        $user_ip = sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
        $current_time = time();

        // Nachschauen, ob dieser User mehrere SessionIDs hat, diese dann bis auf die aktuelle loeschen
        $sql    = 'SELECT session_id FROM '. $this->praefix. '_sessions
                   WHERE session_user_id = '.$forum_userid;
        $result = $this->forum_db->query($sql);

        if($this->forum_db->num_rows($result) > 1)
        {
            $sql    = 'DELETE FROM '. $this->praefix. '_sessions WHERE session_user_id = '.$forum_userid.' AND session_id NOT LIKE \''.$this->session_id.'\' ';
            $result = $this->forum_db->query($sql);
        }

        // Pruefen, ob sich die aktuelle Session noch im Session Table des Forums befindet
        $sql    = 'SELECT session_id, session_start, session_time FROM '. $this->praefix. '_sessions
                   WHERE session_id = \''.$this->session_id.'\' ';
        $result = $this->forum_db->query($sql);

        if($this->forum_db->num_rows($result))
        {
            if($aktion == 'logoff')
            {
                $sql    = 'UPDATE '. $this->praefix. '_sessions 
                              SET session_time    = '. $current_time .'
                                , session_ip      = \''. $user_ip .'\'
                                , session_user_id = '. $forum_userid .',  session_logged_in = 0
                            WHERE session_id      = \''. $this->session_id. '\'';
                $this->forum_db->query($sql);
            }
            elseif($aktion == 'update')
            {
                $sql    = 'UPDATE '. $this->praefix. '_sessions
                              SET session_time = '. $current_time .'
                                , session_ip   = \''. $user_ip .'\' 
                            WHERE session_id   = \''. $this->session_id. '\'';
                $this->forum_db->query($sql);
            }
            elseif($aktion == 'insert')
            {
                $sql    = 'UPDATE '. $this->praefix. '_sessions 
                              SET session_time    = '. $current_time .'
                                , session_start   = '. $current_time .'
                                , session_ip      = \''. $user_ip .'\'
                                , session_user_id = '. $forum_userid .',  session_logged_in = 1
                            WHERE session_id      = \''. $this->session_id. '\'';
                $this->forum_db->query($sql);
            }
        }
        else
        {
            // Session auf jeden Fall in die Forum DB schreiben, damit der User angemeldet ist
            $sql    = 'INSERT INTO ' .$this->praefix. '_sessions
                      (session_id, session_user_id, session_start, session_time, session_ip, session_page, session_logged_in, session_admin)
                      VALUES (\''.$this->session_id.'\', '.$forum_userid.', '.$current_time.', '.$current_time.', \''.$user_ip.'\', 0, 1, 0)';
            $this->forum_db->query($sql);
        }   

        // Cookie des Forums einlesen
        if(isset($_COOKIE[$this->cookie_name.'_sid']))
        {
            $g_cookie_session_id = $_COOKIE[$this->cookie_name.'_sid'];
            if($g_cookie_session_id != $this->session_id)
            {
                // Cookie fuer die Anmeldung im Forum setzen
                setcookie($this->cookie_name.'_sid', $this->session_id, 0, $this->cookie_path, $this->cookie_domain, $this->cookie_secure);
            }
        }
        else
        {
            // Cookie fuer die Anmeldung im Forum setzen
            setcookie($this->cookie_name.'_sid', $this->session_id, 0, $this->cookie_path, $this->cookie_domain, $this->cookie_secure);
        }
    }
}
?>