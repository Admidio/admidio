<?php
/******************************************************************************
 * Konfigurationsdatei von Admidio
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!! W I C H T I G !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * Dies ist eine Demo-Datei, bitte nutzen Sie zur Erstellung Ihrer Config-Datei
 * unser Installationsscript. Dort wird Ihnen eine passende Config-Datei mit
 * Ihren Einstellungen erstellt.
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 *****************************************************************************/

// Tabellenpraefix fuer die Admidio-Tabellen in der Datenbank angeben
// Beispiel: 'adm'
$g_tbl_praefix = 'adm';

// Daten für die MySQL-Datenbank-Verbindung
$g_adm_srv = 'URL_zu_deinem_MySQL-Server'; // Server
$g_adm_usr = 'Benutzername';               // Benutzer
$g_adm_pw  = 'Passwort';                   // Passwort
$g_adm_db  = 'Datenbankname';              // Datenbank

// Root-Pfad für das System auf dem es installiert ist
// Der Pfad muss bis zu dem Verzeichnis, in dem die admidio.html-Datei liegt, angegeben werden !!!
// Beispiel: 'http://www.admidio.org/beispiel'
$g_root_path = 'http://www.deine-homepage.de/admidio';

// Kurzbezeichnung der Gruppierung, des Vereins oder der Organisation auf der Admidio läuft
// Diese muss der Eingabe auf der Installationsseite entsprechen !!!
// Beispiel: 'ADMIDIO'
// Maximal 10 Zeichen !!!
$g_organization = 'Kuerzel'; 

?>