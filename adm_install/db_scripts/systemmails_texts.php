<?php
/******************************************************************************
 * Default-Systemmailtexte fuer eine Organisation
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

$systemmails_texts = array(

    'SYSMAIL_REGISTRATION_USER' => '#Betreff# Anmeldung auf %homepage%
#Inhalt# Hallo %first_name%,

deine Anmeldung auf %homepage% wurde bestätigt.

Nun kannst du dich mit deinem Benutzernamen : %login_name%
und dem Passwort auf der Homepage einloggen.

Sollten noch Fragen bestehen, schreib eine E-Mail an %email_webmaster% .

Viele Grüße
Die Webmaster',

    'SYSMAIL_REGISTRATION_WEBMASTER' => '#Betreff# Neue Registrierung auf %homepage%
#Inhalt# Es hat sich ein neuer Benutzer auf %homepage% registriert.

Nachname: %last_name%
Vorname:  %first_name%
E-Mail:   %email_user%


Diese Nachricht wurde automatisch erzeugt.'
 );
?>