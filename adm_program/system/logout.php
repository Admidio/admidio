<?php
/******************************************************************************
 * User aus Admidio ausloggen
 * Cookies loeschen
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

// Session pruefen

$sql    = "SELECT * FROM ". TBL_SESSIONS. " WHERE ses_session = {0} ";
$sql    = prepareSQL($sql, array($g_session_id));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$session_found = mysql_num_rows($result);

// Inhalt der Cookies loeschen
$domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
setcookie("adm_session", "" , time() - 1000, "/", $domain, 0);

unset($_SESSION['g_current_organizsation']);
unset($_SESSION['g_current_user']);

if ($session_found > 0)
{
    // Session loeschen

    $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE ses_session = {0}";
    $sql    = prepareSQL($sql, array($g_session_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
}

// Wenn die Session des Forums aktiv ist, diese ebenfalls loeschen.
if(isset($_SESSION['s_user_valid']) AND $_SESSION['s_user_valid'] == TRUE)
{
    forum_session("update", -1, $g_forum_cookie_name, $g_forum_cookie_path, $g_forum_cookie_domain, $g_forum_cookie_secure, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix);

	// Session Varibale loeschen
	unset($_SESSION['s_user_valid']);

    $message_code = "logoutforum";    
}
else
{
    $message_code = "logout";
}

// Hinweis auf erfolgreiches Ausloggen und weiter zur Startseite
$g_message->setForwardUrl("home", 2000);
$g_message->show($message_code);
?>