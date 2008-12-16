<?php
/******************************************************************************
 * Messages
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
 *       read       - Gelesene Nachrichten anzeigen
 *
 *       archiv     - Nachrichten im Archiv anzeigen 
 *
 *       send       - Nachricht schreiben
 *
 * id:   (number)	- User ID des Users der angeschrieben werden soll.
 *
 * name: (username) - Username des Users der angeschrieben werden soll.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/ubb_parser.php");

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

if($g_valid_login == true)
{
    // wenn User eingeloggtist alles OK
}
else
{
    // wenn User ausgeloggt, dann Login-Bildschirm anzeigen
    require("../../system/login_valid.php");
}

// zusaetzliche Daten fuer den Html-Kopf setzen
$g_layout['title']  = "Nachrichten";

require(THEME_SERVER_PATH. "/overall_header.php");

if(strlen($_GET['mode']) == 0)
{
    // Mode ist leer, Übersicht anzeigen
    echo "<h1 class=\"moduleHeadline\">Nachrichten &Uuml;bersicht</h1>";
}
elseif($_GET['mode'] == "new") 
{
    // Mode ist new, neue Nachrichten anzeigen
    echo "<h1 class=\"moduleHeadline\">Posteingang anzeigen</h1>";
}
elseif($_GET['mode'] == "read") 
{
    // Mode ist read, gesendete Nachrichten anzeigen
    echo "<h1 class=\"moduleHeadline\">Postausgang anzeigen</h1>";
}
elseif($_GET['mode'] == "archiv") 
{
    // Mode ist archiv archivierte Nachrichten anzeigen
    echo "<h1 class=\"moduleHeadline\">Nachrichtenarchiv anzeigen</h1>";
}
elseif($_GET['mode'] == "send") 
{
    echo "<h1 class=\"moduleHeadline\">Nachricht schreiben</h1>";
    // Prüfen ob schon ein Empfänger übergeben wurde
    if(strlen($_GET['id']) > 0)
	{
    	// ID ist gesetzt, Nachricht an UserID erfassen
    	echo "Nachricht an UserID ".$_GET['id']." schreiben";
	}
	elseif(strlen($_GET['name']) > 0)
	{
    	// Name ist gesetzt, Nachricht an UserName erfassen
    	echo "Nachricht an UserName ".$_GET['name']." schreiben";
	}
	else
	{
    	// Keine ID und keine Name, Standard Nachricht erfassen anzeigen
    	echo "Nachricht schreiben";
    }
}

?>
<br /><br />
In Entwicklung!!

<?
require(THEME_SERVER_PATH. "/overall_footer.php");
?>