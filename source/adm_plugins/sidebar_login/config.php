<?php
/******************************************************************************
 * Konfigurationsdatei fuer Sidebar-Login
 * ein Admidio-Plugin
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 *****************************************************************************/

// Zeigt einen Link zum Registrieren unter dem Loginbutton an
// 1 = (Default) Link wird angezeigt
// 0 = Link wird nicht angezeigt
$plg_show_register_link = 1;

// Zeigt einen Link um eine E-Mail an den Webmaster zu schreiben, 
// falls es Probleme beim Login gibt
// 1 = (Default) Link wird angezeigt
// 0 = Link wird nicht angezeigt
$plg_show_email_link = 1;

// Name einer CSS-Klasse fuer Links
// Nur noetig, falls die Links ein anderes Aussehen bekommen sollen
$plg_link_class = '';

// Angabe des Ziels (target) in dem die Inhalte der Links geöffnet werden sollen
// Hier koennen die ueblichen targets (_self, _top ...) oder Framenamen angegeben werden
$plg_link_target = '_self';

// eine kleine Spielerei
// hier kann man Raenge eingeben, der Benutzer sieht nach dem Einloggen dann seinen Rang
// in der Seitenleiste und kann sich daran erfreuen :)
// Falls dies nicht gewuenscht ist, einfach alle Zeilen mit den Raengen loeschen
$plg_rank = array(
    "0"   => "Neumitglied",
    "50"  => "Mitglied",
    "100" => "Seniormitglied",
    "200" => "Ehrenmitglied",
    );
?>