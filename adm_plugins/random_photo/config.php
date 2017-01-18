<?php
/**
 ***********************************************************************************************
 * Konfigurationsdatei fuer Admidio-Plugin Random Photo
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Maximale Anzahl von Zeichen in einem Wort,
// bevor ein Zeilenumbruch kommt (Default = 0 (deaktiviert))
$plg_max_char_per_word = 0;

// Maximale Photobreite
// Angabe in px, (Default = 150)
$plg_photos_max_width = 150;

// Maximale Photohoehe
// Angabe in px, (Default = 200)
$plg_photos_max_height = 200;

// Zahl der Alben aus denen das Foto kommen darf, gezählt wird ab dem Aktuellsten
// Default = 0 (Keine Einschraenkung)
$plg_photos_albums = 0;

// Bildauswahl
// 0 : (Default) Zufallsbild
// 1 : erstes Bild etc.
$plg_photos_picnr = 0;

// Soll der Link zum Album unter dem Bild angezeigt werden?
$plg_photos_show_link = true;

// Name einer CSS-Klasse fuer Links
// Nur noetig, falls die Links ein anderes Aussehen bekommen sollen
$plg_link_class = '';

// Angabe des Ziels (target) in dem die Inhalte der Links geöffnet werden sollen
// Hier koennen die ueblichen targets (_self, _top ...) oder Framenamen angegeben werden
$plg_link_target = '_self';

// Soll die Überschrift des Plugins angezeigt werden
// 1 = (Default) Überschrift wird angezeigt
// 0 = Überschrift wird nicht angezeigt
$plg_show_headline = 1;
