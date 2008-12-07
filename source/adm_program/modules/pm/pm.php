<?php
/******************************************************************************
 * PM
 *
 * Version 1.0.0
 *
 * Ermöglicht das senden und empfangen von Private Messages über die Homepage
 *
 * Kompatible ab Admidio-Versions 2.0.0
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Thomas Thoss
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode: new  		- Neue Nachrichten anzeigen  
 *
 *       old        - Gelesene Nachrichten anzeigen
 *
 *       archiv     - Nachrichten im Archiv anzeigen 
 *
 * id:   (number)	- User ID des Users der angeschrieben werden soll.
 *
 * name: (username) - Username des Users der angeschrieben werden soll.
 *
 *****************************************************************************/
require("../../system/common.php");
require("../../system/classes/ubb_parser.php");

require(THEME_SERVER_PATH. "/overall_header.php");
?>
In Entwicklung!!

<?
	echo "
	<img src=\"". THEME_PATH. "/icons/pm_read.png\">
	<img src=\"". THEME_PATH. "/icons/pm_new.png\">
	<img src=\"". THEME_PATH. "/icons/pm_read.gif\">
	<img src=\"". THEME_PATH. "/icons/pm_new.gif\">
	<img src=\"". THEME_PATH. "/icons/pm_new_ani.gif\">
	";
?>

<?
require(THEME_SERVER_PATH. "/overall_footer.php");
?>
