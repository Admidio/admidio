<?php
/******************************************************************************
 * Konfigurationsdatei fuer Admidio-Plugin Sidebar-Announcements
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Anzahl der Termine, die angezeigt werden sollen (Default = 2)
$plg_announcements_count = 2;

// Soll ein Vorschau-Text der Ankündigung gezeigt werden?
// 0 = keine Voranzeige
// >0 = Anzahl Zeichen des Vorschau-Textes
$plg_show_preview = 70;

// Name einer CSS-Klasse fuer Links
// Nur noetig, falls die Links ein anderes Aussehen bekommen sollen
$plg_link_class = '';

// Angabe des Ziels (target) in dem die Inhalte der Links geöffnet werden sollen
// Hier koennen die ueblichen targets (_self, _top ...) oder Framenamen angegeben werden
$plg_link_target = '_self';

// Maximale Anzahl von Zeichen in einem Wort,
// bevor ein Zeilenumbruch kommt (Default = 0 (deaktiviert))
$plg_max_char_per_word = 0;

// Soll die Überschrift des Plugins angezeigt werden
// 1 = (Default) Überschrift wird angezeigt
// 0 = Überschrift wird nicht angezeigt
$plg_show_headline = 1;
