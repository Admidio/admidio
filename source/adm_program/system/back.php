<?php
/******************************************************************************
 * Dieses Script kann als Zurueck-Button verlinkt werden und ruft das letzte
 * sinnvolle Script von Admidio auf. Die Scripte werden intern in der 
 * Navigation-Klasse verwaltet
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

include("common.php");

// die letzte Url aus dem Stack loeschen, da dies die aktuelle Seite ist
$_SESSION['navigation']->deleteLastUrl();

// Jetzt die "neue" letzte Url aufrufen
$next_url = $_SESSION['navigation']->getUrl();

// wurde keine Seite gefunden, dann immer die Startseite anzeigen
if(strlen($next_url) == 0)
{
    $next_url = $g_homepage;
}
header("Location: ".$next_url);
 
?>