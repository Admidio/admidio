<?php
/******************************************************************************
 * Konfigurationsdatei von Admidio
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

// Tabellenpraefix fuer die Admidio-Tabellen in der Datenbank angeben
// Beispiel: "adm"
$g_tbl_praefix = "%PRAEFIX%";

// Daten für die MySQL-Datenbank-Verbindung
$g_adm_srv = "%SERVER%";      // Server
$g_adm_usr = "%USER%";        // Benutzer
$g_adm_pw  = "%PASSWORD%";    // Passwort
$g_adm_db  = "%DATABASE%";    // Datenbank

// Root-Pfad für das System auf dem es installiert ist
// Der Pfad muss bis zu dem Verzeichnis, in dem die admidio.html-Datei liegt, angegeben werden !!!
// Beispiel: "http://www.admidio.org/beispiel"
$g_root_path = "%ROOT_PATH%";

// Startseite deiner Homepage
// Auf diese Seite geht Admidio z.B. nach dem Login
// Relativer Pfad von $g_root_path aus gesehen
$g_main_page = "admidio.html";

// Kurzbezeichnung der Gruppierung, des Vereins oder der Organisation auf der Admidio läuft
// Diese muss der Eingabe auf der Installationsseite entsprechen !!!
// Beispiel: "ADMIDIO"
// Maximal 10 Zeichen !!!
$g_organization = "%ORGANIZATION%";


// Forumspezifisch
// phpBB-Forum integriert
// 1 = ja
// 0 = nein
$g_forum = 0;

// Praefix der Tabellen des phpBB-Forums
$g_forum_praefix = "";

// Zugangsdaten zur Datenbank des Forums
$g_forum_srv = "";
$g_forum_usr = "";
$g_forum_pw  = "";
$g_forum_db  = "";  

?>