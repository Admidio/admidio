<?php
/**
 ***********************************************************************************************
 * Konfigurationsdatei fuer Admidio-Plugin Birthday
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Die Namen der Geburtstagskinder koennen nur fuer registrierte User angezeigt werden
// 0 = Name und Alter der Geb-Kinder wird nur fuer registrierte Benutzer angezeigt
//     Besucher erhalten nur einen Hinweis das X Leute Geburtstag haben
// 1 = Name und Alter werden auch fuer Besucher angezeigt
// 2 = (Default) Name ohne Alter wird für Besucher angezeigt
$plg_show_names_extern = 2;

// Wie soll der Name des Geburtstagskindes angezeigt werden ?
// 1 = (Default) Vorname Nachname  (Hans Mustermann)
// 2 = Nachname, Vorname (Mustermann, Hans)
// 3 = Vorname (Hans)
// 4 = Loginname (Hansi)
$plg_show_names = 1;

// Ab welchem Alter soll bei Geburtstagskindern für Besucher der Vorname durch
// die Anrede ersetzt werden?
// Falls nicht festgelegt, wird im PlugIn 18 als Default gesetzt.
// Falls Funktion nicht gewünscht, Alter einfach z.B. auf 99 setzen.
$plg_show_alter_anrede = 18;

// Soll der Hinweis darauf, dass es keine Geburtstagskinder gibt, entfallen?
// 0 = (Default) Hinweis wird angezeigt
// 1 = Hinweis wird nicht angezeigt
$plg_show_hinweis_keiner = 0;

// zeige alle geburtstage in den letzten x tagen
$plg_show_zeitraum = 5;
// zeige zukünftige geburtstage
$plg_show_future = 10;

// Soll die E-Mail-Adresse fuer Besucher verlinkt sein ?
// Bei registrierten Benutzern wird immer ein Link auf das Mailmodul gesetzt
// 0 = (Default) Es wird nur der Name ohne Link mit E-Mail-Adresse angezeigt
// 1 = E-Mail-Adresse ist fuer Besucher verlinkt
// 2 = keine Verlinkung fuer Besucher und registrierte Benutzer
$plg_show_email_extern = 0;

// Angabe des Ziels (target) in dem die Inhalte der Links geöffnet werden sollen
// Hier koennen die ueblichen targets (_self, _top ...) oder Framenamen angegeben werden
$plg_link_target = '_self';

// Angabe welche Rollen selektiert werden sollen
// Aufzählung mit Komma vornehmen "(4,5)"
$plg_rolle_sql = '';

// Angabe in welcher Sortierfolge die Geburtstage dargestellt werden sollen
// Aufsteigend bzw. absteigend "asc bzw. desc"
$plg_sort_sql = 'desc';

// Soll die Überschrift des Plugins angezeigt werden
// 1 = (Default) Überschrift wird angezeigt
// 0 = Überschrift wird nicht angezeigt
$plg_show_headline = 1;
