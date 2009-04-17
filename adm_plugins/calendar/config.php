<?php
/******************************************************************************
 * Konfigurationsdatei fuer Sidebar-Kalender
 *
 * Version 1.4.1
 *
 * Plugin das den aktuellen Monatskalender auflistet und die Termine und Geburtstage
 * des Monats markiert und so ideal in einer Seitenleiste eingesetzt werden kann
 *
 * Kompatible ab Admidio-Versions 2.1.0
 *
 * Copyright    : (c) 2007-2009 Matthias Roberg
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Einblenden per Ajaxbox (1) oder als normaler Link-Title (0)
$plg_ajaxbox = 1;

// Angabe des Zielframes
$plg_link_target = "_self";

// Anzeige der Termine aktiviert (1) oder deaktiviert (0)
$plg_ter_aktiv = 1;

// Anzeige der Termine nur für Mitglieder (eingeloggt) (1) oder alle (0)
$plg_ter_login = 0;

// Anzeige der Geburtstage aktiviert (1) oder deaktiviert (0)
$plg_geb_aktiv = 1;

// Anzeige der Geburtstage nur für Mitglieder (eingeloggt) (1) oder alle (0)
$plg_geb_login = 0;

// Anzeige der Geburtstage mit Icon (1) oder ohne Icon (0)
$plg_geb_icon = 1;

// Welche Kalender sollen ausgegeben werden: Alle (all), Kalender xyz (xyz)
// Meherer Einträge: $plg_kal_cat = array("abc","cdf")
$plg_kal_cat =  array("all");

?>