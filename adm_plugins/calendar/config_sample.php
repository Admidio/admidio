<?php
/**
 ***********************************************************************************************
 * Configuration file for Admidio plugin Calendar
 *
 * Version 2.0.3
 *
 * Plugin das den aktuellen Monatskalender auflistet und die Termine und Geburtstage
 * des Monats markiert und so ideal in einer Seitenleiste eingesetzt werden kann
 *
 * Compatible with Admidio version 3.0
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Einblenden per Ajaxbox (1) oder als normaler Link-Title (0)
$plg_ajaxbox = 1;

// Monatswechsel per Ajax aktiviert (1) oder deaktiviert (0)
$plg_ajax_change = 1;

// Angabe des Zielframes für Termine
$plg_link_target_termin = '_self';

// Angabe des Zielframes für Geburtstage
$plg_link_target_geb = '_self';

// Anzeige der Termine aktiviert (1) oder deaktiviert (0)
$plg_ter_aktiv = 1;

// Anzeige der Termine nur für Mitglieder (eingeloggt) (1) oder alle (0)
$plg_ter_login = 0;

// Anzeige der Geburtstage aktiviert (1) oder deaktiviert (0)
$plg_geb_aktiv = 1;

// Anzeige der Geburtstage nur für Mitglieder (eingeloggt) (1) oder alle (0)
$plg_geb_login = 1;

// Anzeige der Geburtstage mit Icon (1) oder ohne Icon (0)
$plg_geb_icon = 1;

// Anzeige des "Nachname, Vorname" (0) oder "Vorname" (1) oder "Nachname" (2)
$plg_geb_displayNames = 1;

// Welche Kalender sollen ausgegeben werden: Alle (all), Kalender xyz (xyz)
// Mehrere Einträge: $plg_kal_cat = array('abc','cdf')
// Achtung: Seit Admidio 2.2 und dermit verbundenen Mehrsprachigkeit haben die Standardkalender
// folgende interne Bezeichnungen: "Allgemein" = "SYS_COMMON", "Training" = "INS_TRAINING", "Kurse" = "INS_COURSES"
$plg_kal_cat =  array('all');

// Soll die Kategorie des Kalenders mit ausgegeben werden (1) Ja oder (0) Nein
$plg_kal_cat_show = 0;

// Angabe welche Rollen (ID) selektiert werden sollen
// Alle Rollen (Standard)  = 'all';
// Nur Rollenids 2 und 5   = '(2,5)';
$plg_rolle_sql = 'all';

// Angabe der Prefix-Url für den Aufruf in Joomla
// wenn keine Angabe erfolgt dann wird die Standard-URL von Admidio verwendet
$plg_link_url = '';

// Welche CSS-Linkklasse soll für die Anzeige der Geburtstage verwendet werden
$plg_link_class_geb = 'geb';

// Welche CSS-Linkklasse soll für die Anzeige der Termine verwendet werden
$plg_link_class_date = 'date';

// Welche CSS-Linkklasse soll bei Daten mit Geburtstagen und Terminen verwendet werden
$plg_link_class_merge = 'merge';
