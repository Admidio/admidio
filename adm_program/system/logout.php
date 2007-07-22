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

// User aus der Session entfernen
$g_current_session->setValue("ses_usr_id", "");
$g_current_session->save();

// Inhalt der Cookies loeschen
$domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
setcookie("admidio_session_id", "" , time() - 1000, "/", $domain, 0);

unset($_SESSION['g_current_user']);

$message_code = "logout";

// Wenn die Session des Forums aktiv ist, diese ebenfalls loeschen.
if($g_forum_integriert && $g_forum->session_valid)
{
  	$g_forum->userLogoff();
	$message_code = "logoutforum";
}

// Hinweis auf erfolgreiches Ausloggen und weiter zur Startseite
$g_message->setForwardUrl("home", 2000);
$g_message->show($message_code);
?>