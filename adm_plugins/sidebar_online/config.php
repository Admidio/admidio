<?php
/******************************************************************************
 * Konfigurationsdatei fuer Sidebar-Online
 * ein Admidio-Plugin
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Thomas Thoss
 *
 *****************************************************************************/

// Zeit in der die User noch als Aktiv gelten (Default = 10)
$plg_time_online = 10;

// Sollen Besucher (nicht eingeloggte Benutzer) auch angezeigt werden
// 0 = nur eingeloggte Mitglieder werden angezeigt
// 1 = (Default) Anzahl der Besucher wird aufgelistet
$plg_show_visitors = 1;

// Anzeige der Benutzernamen untereinander bzw. nebeneinander
// 0 = (Default) Benutzernamen untereinander auflisten (1 Name pro Zeile)
// 1 = Benutzernamen nebeneinander auflisten
$plg_show_users_side_by_side = 0;

// Name einer CSS-Klasse fuer Links
// Nur noetig, falls die Links ein anderes Aussehen bekommen sollen
$plg_link_class = '';

// Angabe des Ziels (target) in dem die Inhalte der Links geöffnet werden sollen
// Hier koennen die ueblichen targets (_self, _top ...) oder Framenamen angegeben werden
$plg_link_target = '_self';

?>