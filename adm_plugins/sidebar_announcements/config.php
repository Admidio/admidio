<?php
/**
 ***********************************************************************************************
 * Konfigurationsdatei fuer Admidio-Plugin Sidebar-Announcements
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Anzahl der Termine, die angezeigt werden sollen (Default = 2)
$plg_announcements_count = 2;

// Soll ein Vorschau-Text der Ankündigung gezeigt werden?
// 0 = keine Voranzeige
// >0 = Anzahl Zeichen des Vorschau-Textes
$plg_show_preview = 70;

// If this option is set to true (1) than the full content of the
// description will be shown. Also images and other html content.
// 0 = only show text preview of description
// 1 = show full html content of description
$plgShowFullDescription = 0;

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

// Set a custom headline for the plugin and also for the announcements module.
// The headline could also be a translation string e.g. SYS_HEADLINE
$plg_headline = '';
