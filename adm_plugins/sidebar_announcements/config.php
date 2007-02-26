<?php
/******************************************************************************
 * Konfigurationsdatei fuer Sidebar-Announcements
 * ein Admidio-Plugin
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 *****************************************************************************/

// Angabe des Ziels (target) in dem die Inhalte der Links geöffnet werden sollen
// Hier koennen die ueblichen targets (_self, _top ...) oder Framenamen angegeben werden
$plg_link_target = '_self';

// Maximale Anzahl von Zeichen in einem Wort, 
// bevor ein Zeilenumbruch kommt (Default = 0 (deaktiviert)) 
$plg_max_char_per_word = 0;

// Wahlweise kann hier ein anderer Titel fuer die Ankuendigungen angegeben werden
$plg_headline = 'Fotos';

//Maximale Photobreite
//Angabe in px, (Default = 150)
$plg_photos_max_width = 150;

//Maximale Photohoehe
//Angabe in px, (Default = 200)
$plg_photos_max_height = 150;

//Zahl der Veranstaltungen aus denen das Foto kommen darf, gezählt wird ab der aktuellsten
//Default = 0 (Keine Einschraenkung)
$plg_photos_events = 0;

//Bildauswahl
// =1 (erstes Bild) etc. =0 (Zufall,(Default)) 
$plg_photos_picnr = 0;

//Soll der Link zur Veranstaltung unter dem Bild angezeigt werden?
$plg_photos_show_link = false;
?>