<?php
/******************************************************************************
 * Default-Systemmailtexte fuer eine Organisation
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

$systemmails_texts = array(

    'SYSMAIL_REGISTRATION_USER' => '#Betreff# Anmeldung bei %organization_long_name%
#Inhalt# Hallo %user_first_name%,

deine Anmeldung auf %organization_homepage% wurde bestätigt.

Nun kannst du dich mit deinem Benutzernamen : %user_login_name%
und dem Passwort auf der Homepage einloggen.

Sollten noch Fragen bestehen, schreib eine E-Mail an %webmaster_email% .

Viele Grüße
Die Webmaster',

    'SYSMAIL_REGISTRATION_WEBMASTER' => '#Betreff# Neue Registrierung bei %organization_long_name%
#Inhalt# Es hat sich ein neuer Benutzer auf %organization_homepage% registriert.

Nachname: %user_last_name%
Vorname:  %user_first_name%
E-Mail:   %user_email%


Diese Nachricht wurde automatisch erzeugt.',

    'SYSMAIL_NEW_PASSWORD' => '#Betreff# Logindaten für %organization_homepage%
#Inhalt# Hallo %user_first_name%,

du erhälst deine Logindaten für %organization_homepage% .
Benutzername: %user_login_name%
Passwort: %variable1%

Das Passwort wurde automatisch generiert.
Du solltest es nach deiner Anmeldung auf %organization_homepage% in deinem Profil ändern.

Viele Grüße
Die Webmaster',

    'SYSMAIL_ACTIVATION_LINK' => '#Betreff# Dein angefordertes Passwort
#Inhalt# Hallo %user_first_name%,

du hast ein neues Passwort angefordert!

Hier sind deine Daten:
Benutzername: %user_login_name%
Passwort: %variable1%

Damit du dein neues Passwort benutzen kannst, musst du es über den folgenden Link freischalten:

%variable2%

Das Passwort wurde automatisch generiert.
Du solltest es nach deiner Anmeldung auf %organization_homepage% in deinem Profil ändern.

Viele Grüße
Die Webmaster'
 );
?>