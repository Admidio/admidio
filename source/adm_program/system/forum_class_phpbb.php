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
 * $forum = new Forum($g_forum_com);
 *
 *
 * Folgende Funktionen stehen nun zur Verfuegung:
 *
 * forum_user_clear()			  - Userdaten und Session_Valid loeschen
 *
 * forum_preferences()    	  	  - Die Preferences des Forums werden in die Allgemeine 
 *									Forums Umgebungsdaten eingelesen.
 *
 * forum_check_user($username)	  -	Es wird geürüft, ob es den User (Username) schon im Forum gibt.
 *									$username = Der login_name des Users
 *									RETURNCODE = TRUE  - Den User gibt es
 *									RETURNCODE = FALSE - Den User gibt es nicht
 *
 * forum_user_register($username)
 *
 *
 * forum_user_login($usr_id, $login_name, $password_crypt, $g_forum_user, $g_forum_password, $g_forum_email)
 *								  - Meldet den User (Username) im Forum an.
 *									$usr_id 			= Der aktuelle Admidio user_id des Users
 *									$login_name			= Der aktuelle Admidio Login reg_login_name des Users
 *									$password_crypt		= Der aktuelle Admidio Login reg_password Crypt des Users
 *									$g_forum_user		= Der aktuelle Admidio login_name des Users
 *									$g_forum_password	= Das aktuelle Admidio password des Users
 *									$g_forum_email		= Der aktuelle Admidio email des Users
 *									RETURNCODE 	= TRUE  - User angemeldet
 *									RETURNCODE 	= FALSE - User nicht angemeldet
 *
 * forum_user_logoff()	  		  - Meldet den aktuellen User im Forum ab.
 *									RETURNCODE = TRUE  - User abgemeldet
 *									RETURNCODE = FALSE - User nicht abgemeldet
 *
 * forum_user($username)	  	  - Funktion holt die Userdaten und prueft auf neue PM
 *									$username = Der aktuelle login_name des Users
 *
 * forum_check_admin($username, $password_crypt)
 *								  - Funktion ueberprueft ob der Admin Account im Forum ungleich des 
 *									Admidio Accounts ist. Falls ja wird der Admidio Account 
 *									(Username & Password) ins Forum uebernommen.
 *									$username 		= Login_name des Webmasters
 *									$password_crypt = Crypt Password des Webmasters
 *
 * forum_check_password($password_admidio, $password_forum, $forum_userid)
 *								  - Funktion ueberprueft ob das Password im Forum ungleich des Admidio Passwords ist.
 *									Falls ja wird das Admidio Password ins Forum uebernommen.
 *									$password_admidio 	= Crypt Admidio Password
 *									$password_forum 	= Crypt Forum Password 
 *									$forum_userid		= UserID im Forum
 *
 * forum_insert_user($username, $forum_useraktiv, $forum_password, $forum_email)
 *
 * forum_delete_user($username)	  -	Loescht einen User im Forum
 *									$username  = Der login_name des Users, der geloescht werden soll
 *									RETURNCODE = TRUE  - User geloescht
 *									RETURNCODE = FALSE - User nicht geloescht
 *
 * forum_update_user($username, $forum_useraktiv, $forum_password, $forum_email)
 *
 * forum_update_username($forum_new_username, $forum_old_username, $forum_useraktiv, $forum_password, $forum_email)
 *
 * forum_session($aktion, $id)	  - Kuemmert sich um die Sessions und das Cookie des Forums
 *  								$aktion = "logoff" 	Die Session wird abgemeldet
 *				 					$aktion = "update" 	Die Session wird aktualisiert
 * 									$aktion = "insert" 	Die Session wird angemeldet
 *
 * forum_session_bereinigung()	  - Ungenutzter Code Muell (bitte da lassen)
 *
 * forum_nix() 					  -	Eine Funktion, die nichts, rein garnichts macht.
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
	var $forum_praefix;
	var $session_valid;
	var $session_id;
	var $message;
	
	// Forum DB Daten
	var $forum_db_connection;
	var $forum_db;

	// Admidio DB Daten
	var $adm_con;
	var $adm_db;
    
    // Allgemeine Forums Umgebungsdaten
	var $forum_sitename;						// Name des Forums
	var $forum_server;							// Server des Forums
	var $forum_path;							// Pfad zum Forum
	var $forum_cookie_name;						// Name des Forum Cookies
	var $forum_cookie_path;						// Pfad zum Forum Cookies
	var $forum_cookie_domain;					// Domain des Forum Cookies
	var $forum_cookie_secure;					// Cookie Secure des Forums

	// Forum User Daten
	var $forum_user;                    		// Username im Forum
	var $forum_userid;                   		// UserID im Forum
	var $forum_neuePM;                   		// Nachrichten im Forum
	var $forum_neuePM_Text;						// Formatierter Ausgabetext für private Nachrichten


    // Konstruktor
    function Forum($connection)
    {
        $this->forum_db_connection = $connection;
    }

    
    // Userdaten und Session_Valid loeschen
    function forum_user_clear()
    {
       	// Session-Valid und Userdaten loeschen
    	$this->session_valid 	= FALSE;
    	$this->forum_user		= "Gast";
		$this->forum_userid		= -1;
		$this->forum_neuePM	 	= 0;
		
		$_SESSION['g_forum'] = $g_forum;
    }


	// Die Preferences des Forums werden in die Allgemeine Forums Umgebungsdaten eingelesen.
    function forum_preferences()
    {
    	// Forums DB waehlen
    	mysql_select_db($this->forum_db, $this->forum_db_connection);
    	
    	$sql    = "SELECT config_value FROM ". $this->forum_praefix. "_config WHERE config_name = 'sitename' ";
    	$result = mysql_query($sql, $this->forum_db_connection);
    	db_error($result);
    	$row = mysql_fetch_array($result);
    	$this->forum_sitename	= $row[0];
    	
    	$sql    = "SELECT config_value FROM ". $this->forum_praefix. "_config WHERE config_name = 'cookie_name' ";
    	$result = mysql_query($sql, $this->forum_db_connection);
    	db_error($result);
    	$row = mysql_fetch_array($result);
    	$this->forum_cookie_name = $row[0];
    	
    	$sql    = "SELECT config_value FROM ". $this->forum_praefix. "_config WHERE config_name = 'cookie_path' ";
    	$result = mysql_query($sql, $this->forum_db_connection);
    	db_error($result);
    	$row = mysql_fetch_array($result);
    	$this->forum_cookie_path = $row[0];
    	
    	$sql    = "SELECT config_value FROM ". $this->forum_praefix. "_config WHERE config_name = 'cookie_domain' ";
    	$result = mysql_query($sql, $this->forum_db_connection);
    	db_error($result);
    	$row = mysql_fetch_array($result);
    	$this->forum_cookie_domain = $row[0];
    	
    	$sql    = "SELECT config_value FROM ". $this->forum_praefix. "_config WHERE config_name = 'cookie_secure' ";
    	$result = mysql_query($sql, $this->forum_db_connection);
    	db_error($result);
    	$row = mysql_fetch_array($result);
    	$this->forum_cookie_secure = $row[0];
    	
    	$sql    = "SELECT config_value FROM ". $this->forum_praefix. "_config WHERE config_name = 'server_name' ";
    	$result = mysql_query($sql, $this->forum_db_connection);
    	db_error($result);
    	$row = mysql_fetch_array($result);
    	$this->forum_server = str_replace('/', '', $row[0]);
    	
    	$sql    = "SELECT config_value FROM ". $this->forum_praefix. "_config WHERE config_name = 'script_path' ";
    	$result = mysql_query($sql, $this->forum_db_connection);
    	db_error($result);
    	$row = mysql_fetch_array($result);
    	$this->forum_path = str_replace('/', '', $row[0]);
    	
    	// Admidio DB waehlen
    	mysql_select_db($this->adm_db, $this->adm_con);
    }


    // Funktion ueberprueft, ob der User schon im Forum existiert
	function forum_check_user($forum_username)
	{
    	// Forums DB waehlen
    	mysql_select_db($this->forum_db, $this->forum_db_connection);
	    
	    // User im Forum suchen
	    $sql    = "SELECT user_id FROM ". $this->forum_praefix. "_users 
	               WHERE username LIKE '$forum_username' ";
    	$result = mysql_query($sql, $this->forum_db_connection);
	    db_error($result);      
	    
    	// Admidio DB waehlen
    	mysql_select_db($this->adm_db, $this->adm_con);
	    
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


	// Funktion registriert den aktuellen User im Forum an
	function forum_user_register($g_forum_user)
	{
	
	}


	// Funktion meldet den aktuellen User im Forum an
	function forum_user_login($usr_id, $login_name, $password_crypt, $g_forum_user, $g_forum_password, $g_forum_email)
	{
		/* Ueberpruefen, ob User ID =1 (Administrator) angemeldet ist. 
		Falls ja, wird geprueft, ob im Forum der gleiche Username und Password fuer die UserID 2 
		(Standard ID fuer den Administrator im Forum) besteht.
		Dieser wird im nein Fall (neue Installation des Boards) auf den Username und das Password des 
		Admidio UserID Accounts 1 (Standard fuer Administartor) geaendert und eine Meldung ausgegegen.
		*/
		if($usr_id == 1)
		{
		    if($this->forum_check_admin($login_name, $password_crypt))
		    {
		    	$this->message = "loginforum_admin";
		    }
		}
		
		// Pruefen, ob es den User im Forum gibt, im Nein Fall diesem User ein Forum Account anlegen
		if(!$this->forum_check_user($login_name))
		{
		    // Export der Admido Daten ins Forum und einen Forum Account erstellen
		    $this->forum_insert_user($g_forum_user, 1, $g_forum_password, $g_forum_email);
		    
		    $this->message = "loginforum_new";
		}
		
    	// Forums DB waehlen
    	mysql_select_db($this->forum_db, $this->forum_db_connection);
		
		// User nun in Foren-Tabelle suchen und dort das Password & UserID auslesen
		$sql    = "SELECT  user_password, user_id FROM ". $this->forum_praefix. "_users WHERE username LIKE {0} ";
		$sql    = prepareSQL($sql, array($login_name));
    	$result = mysql_query($sql, $this->forum_db_connection);
		db_error($result);
		
    	// Admidio DB waehlen
    	mysql_select_db($this->adm_db, $this->adm_con);
		
		// Natuerlich sollte hier der User auch im Forum existieren 
		// um eine gueltige Anmeldung im Forum zu machen
		if(mysql_num_rows($result))
		{
		    $row = mysql_fetch_array($result);
		
		    $this->forum_session("insert", $row[1]);
		
		    if(!($this->forum_check_password($password_crypt, $row[0], $row[1])))
		    {
		        // Password wurde zurueck gesetzt, Meldung vorbereiten
		        $this->message = "loginforum_pass";
		    }
		    elseif($this->message == "")
		    {
		        // Im Forum und in Admidio angemeldet, Meldung vorbereiten
		        $this->message = "loginforum";
		    }
			// In jedem obigen Fall ist die Forum Session valid
        	$this->session_valid = TRUE;
		}
		else
		{
			$this->message = "login";
		}
	}


	// Funktion meldet den aktuellen User im Forum ab
	function forum_user_logoff()
	{
		// Session wird auf logoff gesetzt
		$this->forum_session("logoff", $this->forum_userid);

    	// Forums DB waehlen
    	mysql_select_db($this->forum_db, $this->forum_db_connection);

    	// Last_Visit fuer das Forum aktualisieren
    	$sql    = "UPDATE ". $this->forum_praefix. "_users 
    	   	      SET user_lastvisit = ". time() . "
    	       	  WHERE user_id = $this->forum_userid";
    	$result = mysql_query($sql, $this->forum_db_connection);
    	db_error($result);

    	// Admidio DB waehlen
    	mysql_select_db($this->adm_db, $this->adm_con);
    	
    	// Session-Valid und Userdaten loeschen
    	$this->forum_user_clear();
	}


	// Funktion holt die Userdaten und prueft auf neue PM
	function forum_user($g_forum_user)
	{
    	// Forums DB waehlen
    	mysql_select_db($this->forum_db, $this->forum_db_connection);
        
        $sql    = "SELECT user_id, username, user_new_privmsg FROM ". $this->forum_praefix. "_users WHERE username LIKE {0} ";
        $sql    = prepareSQL($sql, array($g_forum_user));
    	$result = mysql_query($sql, $this->forum_db_connection);
        db_error($result);
        
        $row = mysql_fetch_array($result);
        
        $this->forum_userid = $row[0];
        $this->forum_user  	= $row[1];
        $this->forum_neuePM = $row[2];
        
        // Wenn neue Nachrichten vorliegen, einen ansprechenden Text generieren
    	if ($this->forum_neuePM == 0)
    	{
    	    $this->forum_neuePM_Text = "und haben <b>keine</b> neue Nachrichten.";
    	}
    	elseif ($this->forum_neuePM == 1)
    	{
    	    $this->forum_neuePM_Text = "und haben <b>1</b> neue Nachricht.";
    	}
    	else
    	{
    	    $this->forum_neuePM_Text = "und haben <b>".$this->forum_neuePM."</b> neue Nachrichten.";
    	}
        
    	// Admidio DB waehlen
    	mysql_select_db($this->adm_db, $this->adm_con);
	}


	// Funktion ueberprueft ob der Admin Account im Forum ungleich des Admidio Accounts ist.
	// Falls ja wird der Admidio Account (Username & Password) ins Forum uebernommen.
	function forum_check_admin($username, $password_crypt)
	{
    	// Forums DB waehlen
    	mysql_select_db($this->forum_db, $this->forum_db_connection);
	    
	    // Administrator nun in Foren-Tabelle suchen und dort das Password, Username & UserID auslesen
	    $sql    = "SELECT username, user_password, user_id FROM ". $this->forum_praefix. "_users WHERE user_id = 2";
    	$result = mysql_query($sql, $this->forum_db_connection);
	    db_error($result);
	    
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
	        $sql    = "UPDATE ". $this->forum_praefix. "_users 
	                   SET user_password = '". $password_crypt ."', username = '". $username ."'
	                   WHERE user_id = 2";
	        $result = mysql_query($sql, $this->forum_db_connection);
	        db_error($result);
	        
    		// Admidio DB waehlen
    		mysql_select_db($this->adm_db, $this->adm_con);
	        
	        return TRUE;
	    }
	}


	// Funktion ueberprueft ob das Password im Forum ungleich des Admidio Passwords ist.
	// Falls ja wird das Admidio Password ins Forum uebernommen.
	function forum_check_password($password_admidio, $password_forum, $forum_userid)
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
	        $sql    = "UPDATE ". $this->forum_praefix. "_users 
	                   SET user_password = '". $password_admidio ."'
	                   WHERE user_id = {0}";
	        $sql    = prepareSQL($sql, array($forum_userid));
	        $result = mysql_query($sql, $this->forum_db_connection);
	        db_error($result);
	        
    		// Admidio DB waehlen
    		mysql_select_db($this->adm_db, $this->adm_con);
	        
	        return FALSE;
	    }
	}


	// Funktion legt einen neuen Benutzer im Forum an
	function forum_insert_user($forum_username, $forum_useraktiv, $forum_password, $forum_email)
	{
    	// Forums DB waehlen
    	mysql_select_db($this->forum_db, $this->forum_db_connection);
	    
	    // jetzt noch den neuen User ins Forum eintragen, ggf. Fehlermeldung als Standard ausgeben.
	    $sql    = "SELECT MAX(user_id) as anzahl 
	                 FROM ". $this->forum_praefix. "_users";
	    $result = mysql_query($sql, $this->forum_db_connection);
	    db_error($result);
	    $row    = mysql_fetch_array($result);
	    $new_user_id = $row[0] + 1;
	    
	    $sql    = "INSERT INTO ". $this->forum_praefix. "_users
	              (user_id, user_active, username, user_password, user_regdate, user_timezone,
	              user_style, user_lang, user_viewemail, user_attachsig, user_allowhtml,
	              user_dateformat, user_email, user_notify, user_notify_pm, user_popup_pm,
	              user_avatar)
	              VALUES ($new_user_id, $forum_useraktiv, '$forum_username', '$forum_password', ". time(). ", 1.00,
	              2, 'german', 0, 1, 0, 'd.m.Y, H:i', '$forum_email', 0, 1, 1, '') ";
	    $result = mysql_query($sql, $this->forum_db_connection);
	    db_error($result);
	    
	    // Jetzt noch eine neue private Group anlegen
	    $sql    = "SELECT MAX(group_id) as anzahl 
	                 FROM ". $this->forum_praefix. "_groups";
	    $result = mysql_query($sql, $this->forum_db_connection);
	    db_error($result);
	    $row    = mysql_fetch_array($result);
	    $new_group_id = $row[0] + 1;
	    
	    $sql    = "INSERT INTO ". $this->forum_praefix. "_groups
	              (group_id, group_type, group_name, group_description, group_moderator, group_single_user)
	              VALUES 
	              ($new_group_id, 1, '', 'Personal User', 0, 1) ";
	    $result = mysql_query($sql, $this->forum_db_connection);
	    db_error($result);
	    
	    // und den neuen User dieser Gruppe zuordenen
	    $sql    = "INSERT INTO ". $this->forum_praefix. "_user_group
	              (group_id, user_id, user_pending)
	              VALUES 
	              ($new_group_id, $new_user_id, 0) ";
	    $result = mysql_query($sql, $this->forum_db_connection);
	    db_error($result);
	    
    	// Admidio DB waehlen
    	mysql_select_db($this->adm_db, $this->adm_con);
	}


	// Funktion loescht einen bestehenden User im Forum
	function forum_delete_user($forum_username)
	{
    	if(strlen($forum_username) > 0)
    	{
    		// Forums DB waehlen
    		mysql_select_db($this->forum_db, $this->forum_db_connection);
	    	
	    	// User_ID des Users holen
	    	$sql    = "SELECT user_id FROM ". $this->forum_praefix. "_users WHERE username LIKE {0} ";
	    	$sql    = prepareSQL($sql, array($forum_username));
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);
	    	
	    	$row = mysql_fetch_array($result);
	    	$forum_userid = $row[0];
	    	
	    	// Gruppen ID des Users holen
	    	$sql    = "SELECT g.group_id 
	    	            FROM ". $this->forum_praefix. "_user_group ug, ". $this->forum_praefix. "_groups g  
	    	            WHERE ug.user_id = ". $forum_userid ."
	    	                AND g.group_id = ug.group_id 
	    	                AND g.group_single_user = 1";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result); 
	    	
	    	$row = mysql_fetch_array($result);
	    	$forum_group = $row[0];
	    	
	    	// Alle Post des Users mit Gast Username versehen
	    	$sql = "UPDATE ". $this->forum_praefix. "_posts
	    	        SET poster_id = -1, post_username = '" . $forum_username . "' 
	    	        WHERE poster_id = $forum_userid";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result); 
	    	
	    	// Alle Topics des User auf geloescht setzten
	    	$sql = "UPDATE ". $this->forum_praefix. "_topics
	    	            SET topic_poster = -1 
	    	            WHERE topic_poster = $forum_userid";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);
	    	
	    	// Alle Votes des Users auf geloescht setzten
	    	$sql = "UPDATE ". $this->forum_praefix. "_vote_voters
	    	        SET vote_user_id = -1
	    	        WHERE vote_user_id = $forum_userid";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);
	    	
	    	// GroupID der der Group holen, in denen der User Mod Rechte hat
	    	$sql = "SELECT group_id
	    	        FROM ". $this->forum_praefix. "_groups
	    	        WHERE group_moderator = $forum_userid";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);
	    	
	    	$group_moderator[] = 0;
	    	
	    	while ( $row_group = mysql_fetch_array($result) )
	    	{
	    	    $group_moderator[] = $row_group['group_id'];
	    	}
	    	
	    	if ( count($group_moderator) )
	    	{
	    	    $update_moderator_id = implode(', ', $group_moderator);
	    	    
	    	    $sql = "UPDATE ". $this->forum_praefix. "_groups
	    	        SET group_moderator = 2
	    	        WHERE group_moderator IN ($update_moderator_id)";
	    	        $result = mysql_query($sql, $this->forum_db_connection);
	    	        db_error($result);
	    	}
	    	
	    	// User im Forum loeschen
	    	$sql = "DELETE FROM ". $this->forum_praefix. "_users 
	    	        WHERE user_id = $forum_userid ";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);
	    	
	    	// User aus den Gruppen loeschen
	    	$sql = "DELETE FROM ". $this->forum_praefix. "_user_group 
	    	        WHERE user_id = $forum_userid ";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);
	    	
	    	// Single User Group loeschen
	    	$sql = "DELETE FROM ". $this->forum_praefix. "_groups
	    	        WHERE group_id =  $forum_group ";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);    
	    	
	    	// User aus der Auth Tabelle loeschen
	    	$sql = "DELETE FROM ". $this->forum_praefix. "_auth_access
	    	        WHERE group_id = $forum_group ";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);    
	    	
	    	// User aus den zu beobachteten Topics Tabelle loeschen
	    	$sql = "DELETE FROM ". $this->forum_praefix. "_topics_watch
	    	        WHERE user_id = $forum_userid ";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);    
	    	
	    	// User aus der Banlist Tabelle loeschen
	    	$sql = "DELETE FROM ". $this->forum_praefix. "_banlist
	    	        WHERE ban_userid = $forum_userid ";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);    
	    	
	    	// Session des Users loeschen
	    	$sql = "DELETE FROM ". $this->forum_praefix. "_sessions
	    	        WHERE session_user_id = $forum_userid ";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);    
	    	
	    	// Session_Keys des User loeschen
	    	$sql = "DELETE FROM ". $this->forum_praefix. "_sessions_keys
	    	        WHERE user_id = $forum_userid ";
	    	$result = mysql_query($sql, $this->forum_db_connection);
	    	db_error($result);    
	    	
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
	function forum_update_user($forum_username, $forum_useraktiv, $forum_password, $forum_email)
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
	    
    	// Forums DB waehlen
    	mysql_select_db($this->forum_db, $this->forum_db_connection);
	    
	    // User im Forum updaten
	    $sql    = "UPDATE ". $this->forum_praefix. "_users
	               SET user_password = '$forum_password', user_active = $forum_useraktiv, user_email = '$forum_email'
	               WHERE username = '". $forum_username. "' ";
	    $result = mysql_query($sql, $this->forum_db_connection);
	    db_error($result);
	    
    	// Admidio DB waehlen
    	mysql_select_db($this->adm_db, $this->adm_con);
	}
	
	
	// Funktion updated einen bestehenden User im Forum
	function forum_update_username($forum_new_username, $forum_old_username, $forum_useraktiv, $forum_password, $forum_email)
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
	    $sql    = "UPDATE ". $this->forum_praefix. "_users
	               SET username = '$forum_new_username', user_password = '$forum_password', user_active = $forum_useraktiv, user_email = '$forum_email'
	               WHERE username = '". $forum_old_username. "' ";
	    $result = mysql_query($sql, $this->forum_db_connection);
	    db_error($result);
	    
    	// Admidio DB waehlen
    	mysql_select_db($this->adm_db, $this->adm_con);
	}


	// Funktion kuemmert sich um die Sessions und das Cookie des Forums
	function forum_session($aktion, $g_forum_userid)
	{
	    // Daten fuer das Cookie und den Session Eintrag im Forum aufbereiten
	    $ip_sep = explode('.', getenv('REMOTE_ADDR'));
	    $user_ip = sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
	    $current_time = time();

    	// Forums DB waehlen
    	mysql_select_db($this->forum_db, $this->forum_db_connection);

		// Nachschauen, ob dieser User mehrere SessionIDs hat, diese dann bis auf die aktuelle loeschen
		$sql    = "SELECT session_id FROM ". $this->forum_praefix. "_sessions
	               WHERE session_user_id = $g_forum_userid";
    	$result = mysql_query($sql, $this->forum_db_connection);
    	
    	if(mysql_num_rows($result) > 1)
    	{
    		$sql    = "DELETE FROM ". $this->forum_praefix. "_sessions WHERE session_user_id = $g_forum_userid AND session_id NOT LIKE '".$this->session_id."' ";
    		$result = mysql_query($sql, $this->forum_db_connection);
	        db_error($result);
    	}


	    // Pruefen, ob sich die aktuelle Session noch im Session Table des Forums befindet
	    $sql    = "SELECT session_id, session_start, session_time FROM ". $this->forum_praefix. "_sessions
	               WHERE session_id = '".$this->session_id."' ";
    	$result = mysql_query($sql, $this->forum_db_connection);
	    
	    if(mysql_num_rows($result))
	    {
	    	if($aktion == "logoff")
	    	{
	    		$sql    = "UPDATE ". $this->forum_praefix. "_sessions 
	                       SET session_time = ". $current_time .", session_ip = '". $user_ip ."', session_user_id = ". $g_forum_userid .",  session_logged_in = 0
	                       WHERE session_id = {0}";
	          	$sql    = prepareSQL($sql, array($this->session_id));
	        	$result = mysql_query($sql, $this->forum_db_connection);
	        	db_error($result);
	    	}
	    	
	    	if($aktion == "update")
	    	{
	    		$sql    = "UPDATE ". $this->forum_praefix. "_sessions
	                       SET session_time = ". $current_time .", session_ip = '". $user_ip ."' 
	                       WHERE session_id = {0}";
	          	$sql    = prepareSQL($sql, array($this->session_id));
	        	$result = mysql_query($sql, $this->forum_db_connection);
	        	db_error($result);
	    	}
	    	
	    	if($aktion == "insert")
	    	{
	    		$sql    = "UPDATE ". $this->forum_praefix. "_sessions 
	                       SET session_time = ". $current_time .", session_start = ". $current_time .", session_ip = '". $user_ip ."', session_user_id = ". $g_forum_userid .",  session_logged_in = 1
	                       WHERE session_id = {0}";
	          	$sql    = prepareSQL($sql, array($this->session_id));
	        	$result = mysql_query($sql, $this->forum_db_connection);
	        	db_error($result);
	    	}
	    	
	    }
	    else
	    {
	    	// Session auf jeden Fall in die Forum DB schreiben, damit der User angemeldet ist
	        $sql    = "INSERT INTO " .$this->forum_praefix. "_sessions
	                  (session_id, session_user_id, session_start, session_time, session_ip, session_page, session_logged_in, session_admin)
	                  VALUES ('$this->session_id', $g_forum_userid, $current_time, $current_time, '$user_ip', 0, 1, 0)";
	        $result = mysql_query($sql, $this->forum_db_connection);
	        db_error($result);
	    }	
	    
	    
	    // Cookie des Forums einlesen
	    if(isset($_COOKIE[$this->forum_cookie_name."_sid"]))
	    {
	        $g_cookie_session_id = $_COOKIE[$this->forum_cookie_name."_sid"];
	        if($g_cookie_session_id != $this->session_id)
	        {
	            // Cookie fuer die Anmeldung im Forum setzen
	            setcookie($this->forum_cookie_name."_sid", $this->session_id, 0, $this->forum_cookie_path, $this->forum_cookie_domain, $this->forum_cookie_secure);
	        }
	    }
	    else
	    {
	        // Cookie fuer die Anmeldung im Forum setzen
            setcookie($this->forum_cookie_name."_sid", $this->session_id, 0, $this->forum_cookie_path, $this->forum_cookie_domain, $this->forum_cookie_secure);
	    }
	    
    	// Admidio DB waehlen
    	mysql_select_db($this->adm_db, $this->adm_con);
	}


	// CODEMUELL fuer spaeter
	function forum_session_bereinigung()
	{
	    // Bereinigungsarbeiten werden nur durchgefuehrt, wenn sich der Admin anmeldet
	    if($g_forum_userid == 2)
	    {   
	        // Bereinigung der Forum Sessions, wenn diese aelter als 60 Tage sind
	        $sql    = "DELETE FROM ". $this->forum_praefix. "_sessions WHERE session_start + 518400 < $current_time ";
	        $result = mysql_query($sql, $this->forum_db_connection);
	        db_error($result);
	    }
	    
	    if($g_forum_userid > 0)
	    {
	        // Alte User-Session des Users im Forum loeschen
	        $sql    = "DELETE FROM ". $this->forum_praefix. "_sessions WHERE session_user_id = $g_forum_userid AND session_id NOT LIKE '".$g_forum_session_id."' ";
	        $result = mysql_query($sql, $this->forum_db_connection);
	        db_error($result);
	    }
	    
	    /*
	    // Erst mal schauen, ob sich die Session noch im Session Table des Forums befindet
	    $sql    = "SELECT session_id, session_start, session_time FROM ". $this->forum_praefix. "_sessions
	               WHERE session_id = '".$g_forum_session_id."' ";
	    $result = mysql_query($sql, $this->forum_db_connection);
	    
	    if(mysql_num_rows($result))
	    {
	        // Session existiert, also updaten
	        
	        // Sessionstart alle 5 Minuten aktualisieren
	        $row = mysql_fetch_array($result);
	        if($row[1] + 300 < $row[2])
	        {
	            $sql    = "UPDATE ". $this->forum_praefix. "_sessions 
	                      SET session_time = ". $current_time .", session_start = ". $current_time .", session_user_id = ". $g_forum_userid .",  session_logged_in = $session_logged_in
	                      WHERE session_id = {0}";
	        }
	        else
	        {
	            $sql    = "UPDATE ". $this->forum_praefix. "_sessions 
	                      SET session_time = ". $current_time .", session_user_id = ". $g_forum_userid .",  session_logged_in = $session_logged_in
	                      WHERE session_id = {0}";
	        }
	        $sql    = prepareSQL($sql, array($g_forum_session_id));
	        $result = mysql_query($sql, $this->forum_db_connection);
	        db_error($result);
	    }
	    else
	    {
	        // Session in die Forum DB schreiben
	        $sql    = "INSERT INTO " .$this->forum_praefix. "_sessions
	                  (session_id, session_user_id, session_start, session_time, session_ip, session_page, session_logged_in, session_admin)
	                  VALUES ('$g_forum_session_id', $g_forum_userid, $current_time, $current_time, '$user_ip', 0, $session_logged_in, 0)";
	        $result = mysql_query($sql, $this->forum_db_connection);
	        db_error($result);
	    }
	    */
	}


	// Eine Funktion, die nichts, rein garnichts macht. 
	// Wenn der Returncode gleich TRUE, wurde auch in der Tat das nichts machen bestaetigt
	function forum_nix()
	{
		return TRUE;
	}

}
?>