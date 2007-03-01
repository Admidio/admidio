<?php
/******************************************************************************
 * Konfigurationsdatei fuer Sidebar-Birthday
 * ein Admidio-Plugin
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 *****************************************************************************/

// Die Namen der Geburtstagskinder koennen nur fuer registrierte User angezeigt werden
// 1 = (Default) Name und Alter werden auch fuer Besucher angezeigt
// 0 = Name und Alter der Geb-Kinder wird nur fuer registrierte Benutzer angezeigt
//     Besucher erhalten nur einen Hinweis das X Leute Geburtstag haben
$plg_show_names_extern = 1;

// Soll die E-Mail-Adresse fuer Besucher verlinkt sein ?
// Bei registrierten Benutzern gibt es weiterhin einen Link auf das Kontaktformular
// 1 = E-Mail-Adresse ist fuer Besucher verlinkt
// 0 = (Default) Es wird nur der Name ohne Link mit E-Mail-Adresse angezeigt
$plg_show_email_extern = 0;

// Wie soll der Name des Geburtstagskindes angezeigt werden ?
// 1 = (Default) Vorname Nachname  (Hans Mustermann)
// 2 = Nachname, Vorname (Mustermann, Hans)
// 3 = Vorname (Hans)
// 4 = Loginname (Hansi)
$plg_show_names = 1;

// Name einer CSS-Klasse fuer Links
// Nur noetig, falls die Links ein anderes Aussehen bekommen sollen
$plg_link_class = '';

// Angabe des Ziels (target) in dem die Inhalte der Links geöffnet werden sollen
// Hier koennen die ueblichen targets (_self, _top ...) oder Framenamen angegeben werden
$plg_link_target = '_self';

?>