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

unset($_SESSION['g_current_organizsation']);
unset($_SESSION['g_current_user']);

if($session_found > 0)
{
    // Session loeschen

    $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE ses_session = {0}";
    $sql    = prepareSQL($sql, array($g_session_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
}

// Wenn die Session des Forums aktiv ist, diese ebenfalls loeschen.
if($g_forum->session_valid)
{
    $g_forum->forum_user_logoff();

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