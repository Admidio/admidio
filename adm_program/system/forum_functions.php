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

// globale Klassen mit Datenbankbezug werden in Sessionvariablen gespeichert, 
// damit die Daten nicht bei jedem Script aus der Datenbank ausgelesen werden muessen
if(isset($_SESSION['g_forum']))
{
    $g_forum = $_SESSION['g_forum'];
    $g_forum->forum_db_connection 	= $g_forum_con;
    $g_forum->adm_con				= $g_adm_con;
}
else
{
    $g_forum = new Forum($g_forum_con);
    $g_forum->forum_praefix 		= $g_forum_praefix;
    $g_forum->session_id			= session_id();
    $g_forum->forum_db 				= $g_forum_db;
    $g_forum->adm_con 				= $g_adm_con;
    $g_forum->adm_db 				= $g_adm_db;
    $g_forum->forum_preferences();
   	$_SESSION['g_forum'] 			= $g_forum;
}



// Forum Session auf Gueltigkeit pruefen
// Nur wenn die Admidio Session valid ist, wird auf die Forum Session valid sein
if($g_session_valid)
{
	// Wenn die Forum Session bereits valid ist, wird diese Abfrage uebersprungen
	if($g_forum->session_valid != TRUE)
	{ 
		$g_forum->session_valid = $g_forum->forum_check_user($g_current_user->login_name);
	}
}
else
{
	// Die Admidio Session ist nicht valid, also ist die Forum Session ebenfalls nicht valid
	$g_forum->session_valid = FALSE;
}



// Wenn die Forumssession gueltig ist, Userdaten holen und gueltige Session im Forum updaten. 
if($g_forum->session_valid)
{
	// Userdaten holen und auf neue PMs pruefen
	$g_forum->forum_user($g_current_user->login_name);
	
	// Sofern die Admidio Session gueltig ist, ist auch die Forum Session gueltig
	$g_forum->forum_session("update", $g_forum->forum_userid);
}
elseif(!$g_session_valid AND $g_forum->forum_userid >1)
{
    // Admidio Session ist abgelaufen, ungueltig oder ein logoff, also im Forum logoff
    $g_forum->forum_session("logoff", $g_forum->forum_userid);
}

// Daten in Session-Variablen sichern
$_SESSION['g_forum'] = $g_forum;

?>