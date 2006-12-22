<?php
/******************************************************************************
 * Texte fuer Hinweistexten oder Fehlermeldungen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

$message_text = array(
    "anmeldung" =>
        "<p>Deine Daten wurden gespeichert.</p>
        <p>Du kannst dich noch nicht einloggen.<br />
        Sobald deine Anmeldung vom Administrator bestätigt wurde, erhälst du eine E-Mail.</p>",

    "category_exist" =>
        " Es existiert bereits eine Kategorie in dieser Organisation mit dem Namen.",

    "datum" =>
        "Es wurde kein gültiges Datum in das Feld <b>%VAR1%</b> eingegeben.",

    "delete" =>
        "Die Daten wurden gelöscht !",

    "delete_announcement" =>
        "Willst du die Ankündigung<br />
        <b>%VAR1%</b><br />wirklich löschen ?",

    "delete_category" =>
        "<p>Willst du die Kategorie <b>%VAR1%</b> wirklich löschen ?</p>",

    "delete_date" =>
        "Willst du den Termin<br />
        <b>%VAR1%</b><br />wirklich löschen ?",

    "delete_role" =>
        "Willst du die Rolle <b>%VAR1%</b> wirklich löschen ?<br><br>
        Es werden damit auch alle Mitgliedschaften entgütig entfernt.",

    "delete_field" =>
        "<p>Willst du das Feld <b>%VAR1%</b> wirklich löschen ?</p>
        <p>Es werden alle Daten, die Benutzer in diesem Feld gespeichert haben, gelöscht.</p>",

    "delete_user" =>
        "<p>Willst du <b>%VAR1%</b> wirklich löschen ?</p>
        <p>Der Benutzer wird damit physikalisch in der Datenbank gelöscht und ein Zugriff auf
        seine Daten nicht mehr möglich.</p>",

    "delete_new_user" =>
        "<p>Willst du die Web-Registrierung von
        <b>%VAR1%</b> wirklich löschen ?</p>",

    "email_invalid" =>
        "Die E-Mail-Adresse ist nicht gültig.",

    "field_numeric" =>
        "Das Feld <b>%VAR1%</b> darf nur Zahlen enthalten.<br>
        Korrigier bitte deine Eingabe.",

    "field_exist" =>
        " Es existiert bereits ein Feld in dieser Organisation mit dem Namen.",

    "feld" =>
        "Das Feld <b>%VAR1%</b> ist nicht gefüllt.",

    "felder" =>
        "Es sind nicht alle Felder aufgefüllt worden.",

    "import" =>
        "%VAR1% Datensätze wurden erfolgreich importiert !",

    "invalid" =>
        "Ungültiger Seitenaufruf !",

    "login_failed" =>
        "Du hast dich innerhalb kurzer Zeit mehrmals mit einem
        falschen Passwort versucht einzuloggen.<br />Aus Sicherheitsgründen
        ist dein Zugang für <b>15 Minuten</b> gesperrt.",

    "login_name" =>
        "Der gewählte Benutzername existiert schon.<br /><br />
        Wähle bitte einen neuen Namen.",

    "login_unknown" =>
        "Der angegebene Benutzername existiert nicht.",

    "login" =>
        "Du hast dich erfolgreich angemeldet.",

    "logout" =>
        "Du wurdest erfolgreich abgemeldet.",

    "module_disabled" =>
        "Dieses Modul wurde nicht freigegeben.",

    "mysql" =>
        "Folgender Fehler trat beim Zugriff auf die Datenbank auf:<br /><br />
        <b>%VAR1%</b>",

    "new_user" =>
        "Bist du sicher, dass der Benutzer noch nicht in der Datenbank existiert ?",

    "noaccept" =>
        "Deine Anmeldung wurde noch nicht vom Administrator bestätigt.<br /><br />
        Einloggen ist nicht möglich",

    "nodata" =>
        "Es sind keine Daten vorhanden !",

    "no_category_roles" =>
        "Es sind noch keine Rollen für diese Kategorie erstellt worden.<br /><br />
        Rollen können <a href=\"%VAR1%\">hier</a>
        erstellt und gepflegt werden.",

    "no_old_roles" =>
        "Es sind noch keine Rollen aus dem System entfernt worden.<br /><br />
        Erst wenn du in der Rollenverwaltung Rollen löschst, erscheinen diese automatisch bei
        den \"Entfernten Rollen\".",

    "norights" =>
        "Du hast keine Rechte diese Aktion auszuführen",

    "nomembers" =>
        "Es sind keine Anmeldungen vorhanden.",

    "norolle" =>
        "Die Daten können nicht gespeichert werden.<br />
        Dem Benutzer sind keine Rollen zugeordnet.",

    "no_cookie" =>
        "Der Login kann nicht durchgeführt werden, da dein Browser
        das Setzen von Cookies verbietet !<br><br>
        Damit du dich erfolgreich anmelden kannst, musst du in deinem Browser
        einstellen, dass dieser Cookies von %VAR1% akzeptiert.",

    "passwort" =>
        "Das Passwort stimmt nicht mit der Wiederholung überein.",

    "password_unknown" =>
        "Du hast ein falsches Passwort eingegeben und
        konntest deshalb nicht angemeldet werden.<br /><br />
        Überprüf bitte dein Passwort und gib dieses dann erneut ein.",

    "remove_member" =>
        "Wollen Sie die Mitgliedschaft des Benutzers %VAR1% bei %VAR2% beenden ?",

    "remove_member_ok" =>
        "Die Mitgliedschaft des Benutzers bei %VAR1% wurde erfolgreich beendet !",

    "role_active" =>
        "Die Rolle <b>%VAR1%</b> wurde wieder auf <b>aktiv</b> gesetzt.",

    "role_inactive" =>
        "Die Rolle <b>%VAR1%</b> wurde auf <b>inaktiv</b> gesetzt.",

    "role_exist" =>
        " Es existiert bereits eine Rolle in dieser Kategorie mit demselben Namen.",

    "save" =>
        "Deine Daten wurden erfolgreich gespeichert.",

    "uhrzeit" =>
        "Es wurde keine sinnvolle Uhrzeit eingegeben.<br /><br />
        Die Uhrzeit muss zwischen 00:00 und 23:59 liegen !",

    "zuordnen" =>
        "Wollen Sie die aktuelle Webanmeldung wirklich<br />
        <b>%VAR1%</b> zuordnen ?",

    "send_login_mail" =>
        "Die Logindaten wurden erfolgreich zugeordnet und der
        Benutzer wurde darüber benachrichtigt.",

    "send_new_login" =>
        "Möchtest du <b>%VAR1%</b> eine E-Mail mit dem Benutzernamen
        und einem neuen Passwort zumailen ?",

    "max_members" =>
        "Speichern nicht möglich, die maximale Mitgliederzahl würde überschritten.",

    "max_members_profile" =>
        "Speichern nicht möglich, bei der Rolle &bdquo;%VAR1%&rdquo;
        würde die maximale Mitgliederzahl überschritten.",

    "max_members_roles_change" =>
        "Speichern nicht möglich, die Rolle hat bereits mehr Mitglieder als die von Dir eingegeben Begrenzung.",

    "write_access" =>
        "Der Ordner <b>%VAR1%</b> konnte nicht angelegt werden. Du musst dich an
        den <a href=\"mailto:%VAR2%\">Webmaster</a>
        wenden, damit dieser <acronym title=\"über FTP die Dateiattribute auf 0777 bzw. drwxrwxrwx setzen.\">
        Schreibrechte</acronym> für den Ordner setzen kann.",

    //Fehlermeldungen Linkmodul
    "delete_link" =>
        "Willst Du den Link<br />
        <b>%VAR1%</b><br />wirklich löschen ?",


    //Fehlermeldungen Gaestebuchmodul
    "delete_gbook_entry" =>
        "Willst Du den Gästebucheintrag von<br />
        <b>%VAR1%</b><br />wirklich löschen ?",

    "delete_gbook_comment" =>
        "Willst Du den Kommentar von<br />
        <b>%VAR1%</b><br />wirklich löschen ?",

    "flooding_protection" =>
        "Dein letzter Eintrag im Gästebuch <br />
         liegt weniger als %VAR1% Sekunden zurück.",
    //Ende Fehlermeldungen Gaestebuchmodul

        //Fehlermeldungen Profilfoto
    "profile_photo_update" =>
        "Das neue Profilfoto wurde erfolgreich gespeichert.",

    "profile_photo_update_cancel" =>
        "Der Vorgang wurde abgebrochen.",

    "profile_photo_nopic" =>
        "Es wurde keine Bilddatei ausgewählt.",

    "profile_photo_deleted" =>
          "Das Profilfoto wurde gel&ouml;scht.",

    "profile_photo_2big" =>
        "Das hochgeladene Foto übersteigt die vom Server zugelassene
        Dateigröße von %VAR1%B.",


    //Fehlermeldungen Fotomodul
    "no_photo_folder"=>
        "Der Ordner adm_my_files/photos wurde nicht gefunden.",

    "photodateiphotoup" =>
        "Du hast keine Bilddatei ausgewählt, die hinzugefügt
        werden sollen.<br />",

    "photoverwaltunsrecht" =>
        "Nur eingeloggte Benutzer mit Fotoverwaltungsrecht dürfen Fotos verwalten.<br />",

    "dateiendungphotoup" =>
        "Die ausgewählte Datei ist nicht im JPG-Format gespeichert.<br />",

    "startdatum" =>
        "Es muss ein gültiges  Startdatum für die Veranstalltung eingegeben werden.<br />",

    "enddatum" =>
        "Das eingegebene Enddatum ist ungültig.<br />",

    "startvorend" =>
        "Das eingegebene Enddatum liegt vor dem Anfangsdatum.<br />",

    "veranstaltung" =>
        "Es muss ein Name für die Veranstaltung eingegeben weden.<br />",

    "delete_veranst" =>
        "Willst du die Veranstaltung:<br />
        <b>%VAR1%</b><br />wirklich löschen ?<br>
        Alle enthaltenen Unterveranstaltungen und Bilder gehen verloren.",

    "delete_photo" =>
        "Soll das ausgewählte Foto wirklich gelöscht werden?",

    "photo_deleted" =>
        "Das Foto wurde erfolgreich gelöscht.",

    "photo_2big" =>
        "Mindestens eins der hochgeladenen Fotos übersteigt die vom Server zugelassene
        Dateigröße von %VAR1%B.",

    "empty_photo_post" =>
        "Die Seite wurde ungültig aufgerufen oder die Datei(en) konnte nicht hochgeladen werden.<br />
        Vermutlich wurde die vom Server vorgegebene, maximale Uploadgröße,
        von %VAR1%B übersteigen!",
    //Ende Fehlermeldungen Fotomodul


    //Fehlermeldungen Downloadmodul
    "invalid_folder" =>
        "Sie haben einen ungültigen Ordner aufgerufen !",

    "invalid_folder" =>
        "Sie haben eine ungültigen Datei aufgerufen !",

    "invalid_file_name" =>
        "Du kannst die Datei <b>%VAR1%</b> nicht hochladen,
        da der Dateiname ungültige Zeichen enthält.",

    "invalid_file_extension" =>
        "Du kannst keine PHP, HTML oder Perl Dateien hochladen.",

    "file_not_exist" =>
        "Die ausgewählte Datei existiert nicht.",

    "folder_not_exist" =>
        "Der aufgerufene Ordner existiert nicht.",

    "delete_file_folder" =>
        "Willst du die Datei / den Ordner <b>%VAR1%</b> wirklich löschen ?",

    "delete_file" =>
        "Die Datei <b>%VAR1%</b> wurde gelöscht.",

    "delete_folder" =>
        "Der Ordner <b>%VAR1%</b> wurde gelöscht.",

    "upload_file" =>
        "Die Datei <b>%VAR1%</b> wurde hochgeladen.",

    "create_folder" =>
        "Der Ordner <b>%VAR1%</b> wurde angelegt.",

    "folder_exists" =>
        "Der Ordner <b>%VAR1%</b> existiert bereits!<br><br>
        Wähle bitte einen anderen Namen für den neuen Ordner aus.",

    "file_exists" =>
        "Die Datei <b>%VAR1%</b> existiert bereits!<br><br>
        Wähle bitte einen anderen Dateinamen aus.",

    "rename_folder" =>
        "Der Ordner <b>%VAR1%</b> wurde umbenannt.",

    "rename_file" =>
        "Die Datei <b>%VAR1%</b> wurde umbenannt.",

    "file_2big" =>
        "Die hochgeladene Datei übersteigt die zulässige
        Dateigröße von %VAR1%KB.",

    "file_2big_server" =>
        "Die hochgeladene Datei übersteigt die vom Server zugelassene
        Dateigröße von %VAR1%B.",

    "empty_upload_post" =>
        "Die Seite wurde ungültig aufgerufen oder die Datei konnte nicht hochgeladen werden.<br>
        Vermutlich wurde die vom Server vorgegebene, maximale Uploadgröße, von %VAR1%B übersteigen!",
    //Ende Fehlermeldungen Downloadmodul


    //Fehlermeldungen Mailmodul
    "mail_send" =>
        "Die E-Mail wurde erfolgreich an <b>%VAR1%</b> versendet.",

    "mail_not_send" =>
        "Die E-Mail konnte leider nicht an <b>%VAR1%</b> gesendet werden.",

    "attachment" =>
        "Dein Dateinanhang konnte nicht hochgeladen werden.<br />
        Vermutlich ist das Attachment zu groß!",

    "attachment_or_invalid" =>
        "Die Seite wurde ungültig aufgerufen oder Dein Dateinanhang konnte nicht hochgeladen werden.<br />
        Vermutlich ist das Attachment zu groß!",

    "mail_rolle" =>
        "Bitte wähle eine Rolle als Adressat der Mail aus!",

    "profile_mail" =>
        "In Ihrem <a href=\"%VAR1%\">Profil</a>
        ist keine gültige Emailadresse hinterlegt!",

    "role_empty" =>
        "Die von Ihnen ausgewählte Rolle enthält keine Mitglieder
         mit gültigen Mailadressen, an die eine Mail versendet werden kann!",

    "usrid_not_found" =>
        "Die Userdaten der übergebenen ID konnten nicht gefunden werden!",

    "usrmail_not_found" =>
        "Der User hat keine gültige Mailadresse in seinem Profil hinterlegt!",
    //Ende Fehlermeldungen Mailmodul


    //Fehlermeldungen RSSmodul
    "rss_disabled" =>
        "Die RSS-Funktion wurde vom Webmaster deaktiviert",
    //Ende Fehlermeldungen RSSmodul

    //Fehlermeldungen Capcha-Klasse
    "captcha_code" =>
        "Der Bestätigungscode wurde falsch eingegeben.",
    //Ende Fehlermeldungen Capcha-Klasse


    //Fehlermeldungen Servereinstellungen
    "no_file_upload_server" =>
        "Die Servereinstellungen lassen keine Dateiuploads zu.",
    //Fehlermeldungen Servereinstellungen

    "default" =>
        "Es ist ein Fehler aufgetreten.<br><br>
        Der gesuchte Hinweis <b>%VAR1%</b> konnte nicht gefunden werden !"
 )
?>
