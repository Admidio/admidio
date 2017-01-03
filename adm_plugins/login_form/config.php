<?php
/**
 ***********************************************************************************************
 * Konfigurationsdatei fuer das Admidio-Plugin Login Form
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Zeigt einen Link zum Registrieren unter dem Loginbutton an
// 1 = (Default) Link wird angezeigt
// 0 = Link wird nicht angezeigt
$plg_show_register_link = 1;

// Zeigt einen Link um eine E-Mail an den Webmaster zu schreiben,
// falls es Probleme beim Login gibt
// 1 = (Default) Link wird angezeigt
// 0 = Link wird nicht angezeigt
$plg_show_email_link = 1;

// Zeigt nach dem Einloggen einen Link zum Ausloggen an
// 1 = (Default) Link wird angezeigt
// 0 = Link wird nicht angezeigt
$plg_show_logout_link = 1;

// Zeigt vor den Links noch zusaetzlich kleine Icons an
// 1 = (Default) Icons werden angezeigt
// 0 = Icons werden nicht angezeigt
$plg_show_icons = 1;

// Angabe des Ziels (target) in dem die Inhalte der Links geöffnet werden sollen
// Hier koennen die ueblichen targets (_self, _top ...) oder Framenamen angegeben werden
$plg_link_target = '_self';

// eine kleine Spielerei
// hier kann man Raenge eingeben, der Benutzer sieht nach dem Einloggen dann seinen Rang
// in der Seitenleiste und kann sich daran erfreuen :)
// Falls dies nicht gewuenscht ist, einfach alle Zeilen mit den Raengen loeschen
$plg_rank = array(
    '0'   => $gL10n->get('PLG_LOGIN_NEW_ONLINE_MEMBER'),
    '50'  => $gL10n->get('PLG_LOGIN_ONLINE_MEMBER'),
    '100' => $gL10n->get('PLG_LOGIN_SENIOR_ONLINE_MEMBER'),
    '200' => $gL10n->get('PLG_LOGIN_HONORARY_MEMBER')
    );
