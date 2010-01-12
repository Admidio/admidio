<?php
/******************************************************************************
 * Konfigurationsdatei von Admidio
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Tabellenpraefix fuer die Admidio-Tabellen in der Datenbank angeben
// Beispiel: 'adm'
$g_tbl_praefix = '%PREFIX%';

// Daten für die MySQL-Datenbank-Verbindung
$g_adm_srv = '%SERVER%';      // Server
$g_adm_usr = '%USER%';        // Benutzer
$g_adm_pw  = '%PASSWORD%';    // Passwort
$g_adm_db  = '%DATABASE%';    // Datenbank

// Root-Pfad für das System auf dem es installiert ist
// Der Pfad muss bis zu dem Verzeichnis, in dem die admidio.html-Datei liegt, angegeben werden !!!
// Beispiel: 'http://www.admidio.org/beispiel'
$g_root_path = '%ROOT_PATH%';

// Kurzbezeichnung der Gruppierung, des Vereins oder der Organisation auf der Admidio läuft
// Diese muss der Eingabe auf der Installationsseite entsprechen !!!
// Beispiel: 'ADMIDIO'
// Maximal 10 Zeichen !!!
$g_organization = '%ORGANIZATION%'; 

?>