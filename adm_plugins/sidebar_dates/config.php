<?php
/******************************************************************************
 * Konfigurationsdatei fuer Admidio-Plugin Sidebar-Dates
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Anzahl der Termine, die angezeigt werden sollen (Default = 2)
$plg_dates_count = 2;

// Bis-Uhrzeit/Datum anzeigen
// 0 = Bis-Uhrzeit und Datum nicht anzeigen
// 1 = (Default) Bis-Uhrzeit und Datum anzeigen
$plg_show_date_end = 1;

// Name einer CSS-Klasse fuer Links
// Nur noetig, falls die Links ein anderes Aussehen bekommen sollen
$plg_link_class = '';

// Angabe des Ziels (target) in dem die Inhalte der Links geöffnet werden sollen
// Hier koennen die ueblichen targets (_self, _top ...) oder Framenamen angegeben werden
$plg_link_target = '_self';

// Maximale Anzahl von Zeichen in einem Wort, 
// bevor ein Zeilenumbruch kommt (Default = 0 (deaktiviert)) 
$plg_max_char_per_word = 0;

// Angabe der Prefix-Url für den Aufruf in Joomla
// wenn keine Angabe erfolgt dann wird die Standard-URL von Admidio verwendet
$plg_link_url = $g_root_path. '/adm_program/modules/dates/dates.php';

// Welche Kalender sollen ausgegeben werden: Alle (all), Kalender xyz (xyz)
// Mehrere Einträge: $plg_kal_cat = array('abc','cdf')
$plg_kal_cat =  array('all');

?>