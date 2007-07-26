<?php
/******************************************************************************
 * Texte fuer Hinweistexten oder Fehlermeldungen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
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
        Sobald deine Anmeldung bestätigt wurde, erhältst du eine Benachrichtigung per E-Mail.</p>",

    "assign_login" =>
        "Die Logindaten wurden erfolgreich zugeordnet.",

    "assign_login_mail" =>
        "Die Logindaten wurden erfolgreich zugeordnet und der
        Benutzer ist darüber per E-Mail benachrichtigt worden.",

    "category_exist" =>
        "Es existiert bereits eine Kategorie in dieser Organisation mit diesem Namen.",

    "date_invalid" =>
        "<p>Es wurde kein gültiges Datum in das Feld <b>%VAR1%</b> eingegeben.</p>
        <p>Das Format des Datums sollte <i>dd.mm.yyyy</i> sein.</p>",

    "delete" =>
        "Die Daten wurden gelöscht!",

    "delete_announcement" =>
        "Willst du die Ankündigung<br />
        <b>%VAR1%</b><br />wirklich löschen?",

    "delete_category" =>
        "<p>Soll die Kategorie <b>%VAR1%</b> wirklich gelöscht werden ?</p>
        <p>Es werden alle Daten, die dieser Kategorie zugeordnet sind 
        (Felder, Rollen, Links), mit gelöscht.</p>",

    "delete_date" =>
        "Willst du den Termin<br />
        <b>%VAR1%</b><br />wirklich löschen?",

    "delete_role" =>
        "Willst du die Rolle <b>%VAR1%</b> wirklich löschen?<br><br>
        Es werden damit auch alle Mitgliedschaften entgütig entfernt.",

    "delete_field" =>
        "<p>Willst du das Feld <b>%VAR1%</b> wirklich löschen?</p>
        <p>Es werden alle Daten, die Benutzer in diesem Feld gespeichert haben, gelöscht.</p>",

    "delete_user" =>
        "<p>Willst du <b>%VAR1%</b> wirklich löschen?</p>
        <p>Der Benutzer wird damit physikalisch in der Datenbank gelöscht und ein Zugriff auf
        seine Daten ist nicht mehr möglich.</p>",

    "delete_new_user" =>
        "<p>Willst du die Web-Registrierung von
        <b>%VAR1%</b> wirklich löschen?</p>",

    "email_invalid" =>
        "Die E-Mail-Adresse ist nicht gültig.",

    "field_numeric" =>
        "Das Feld <b>%VAR1%</b> darf nur Zahlen enthalten.<br>
        Korrigiere bitte deine Eingabe.",

    "field_exist" =>
        " Es existiert bereits ein Feld in dieser Organisation mit diesem Namen.",

    "feld" =>
        "Das Feld <b>%VAR1%</b> ist nicht gefüllt.",

    "felder" =>
        "Es sind nicht alle Felder gefüllt worden.",

    "import" =>
        "%VAR1% Datensätze wurden erfolgreich importiert!",

    "installFolderExists" =>
        "Das Installationsverzeichnis <b>adm_install</b> existiert noch auf dem Server.
         Aus Sicherheitsgründen muss dieses gelöscht werden!",

    "invalid" =>
        "Ungültiger Seitenaufruf!",

    "login_failed" =>
        "Du hast mehrmals innerhalb kurzer Zeit versucht, dich mit einem
        falschen Passwort einzuloggen.<br />Aus Sicherheitsgründen
        ist dein Zugang für <b>15 Minuten</b> gesperrt.",

    "login_name" =>
        "Der gewählte Benutzername existiert schon.<br /><br />
        Wähle bitte einen anderen Namen.",

    "login_unknown" =>
        "Der angegebene Benutzername existiert nicht.",

    "login" =>
        "Du hast dich erfolgreich angemeldet.",

    "logout" =>
        "Du wurdest erfolgreich abgemeldet.",

    "module_disabled" =>
        "Dieses Modul ist nicht freigegeben.",

    "missing_orga" =>
        "Die Organisation aus der config.php konnte in der Datenbank nicht gefunden werden.",

    "mysql" =>
        "Folgender Fehler trat beim Zugriff auf die Datenbank auf:<br /><br />%VAR1%",

    "new_user" =>
        "Bist du sicher, dass der Benutzer noch nicht in der Datenbank existiert?",

    "noaccept" =>
        "Deine Anmeldung wurde noch nicht vom Administrator bestätigt.<br /><br />
        Das Einloggen ist noch nicht möglich",

    "nodata" =>
        "Es sind keine Daten vorhanden!",

    "no_category_roles" =>
        "Es sind noch keine Rollen für diese Kategorie erstellt worden.<br /><br />
        Rollen können <a href=\"%VAR1%\">hier</a>
        erstellt und gepflegt werden.",

    "no_old_roles" =>
        "Es sind noch keine Rollen aus dem System entfernt worden.<br /><br />
        Erst wenn du in der Rollenverwaltung Rollen löschst, erscheinen diese automatisch bei
        den \"Entfernten Rollen\".",

    "norights" =>
        "Du hast keine Rechte, diese Aktion auszuführen",

    "nomembers" =>
        "Es sind keine Anmeldungen vorhanden.",

    "norolle" =>
        "Die Daten können nicht gespeichert werden.<br />
        Dem Benutzer sind keine Rollen zugeordnet.",

    "no_cookie" =>
        "Der Login kann nicht durchgeführt werden, da dein Browser
        das Setzen von Cookies verbietet!<br><br>
        Damit du dich erfolgreich anmelden kannst, musst du in deinem Browser
        so einstellen, dass dieser Cookies von %VAR1% akzeptiert.",

    "password_length" =>
        "Das Passwort muss aus mindestens 6 Zeichen bestehen.",

    "passwords_not_equal" =>
        "Das Passwort stimmt nicht mit der Wiederholung überein.",

    "password_unknown" =>
        "Du hast ein falsches Passwort eingegeben und
        konntest deshalb nicht angemeldet werden.<br /><br />
        Überprüf bitte dein Passwort und gib dieses dann erneut ein.",

    "remove_member" =>
        "Willst du die Mitgliedschaft des Benutzers %VAR1% bei %VAR2% beenden?",

    "remove_member_ok" =>
        "Die Mitgliedschaft des Benutzers bei %VAR1% wurde erfolgreich beendet!",

    "role_active" =>
        "Die Rolle <b>%VAR1%</b> wurde auf <b>aktiv</b> gesetzt.",

    "role_inactive" =>
        "Die Rolle <b>%VAR1%</b> wurde auf <b>inaktiv</b> gesetzt.",

    "role_exist" =>
        " Es existiert bereits eine Rolle in dieser Kategorie mit demselben Namen.",

    "save" =>
        "Deine Daten wurden erfolgreich gespeichert.",

    "uhrzeit" =>
        "Es wurde keine sinnvolle Uhrzeit eingegeben.<br /><br />
        Die Uhrzeit muss zwischen 00:00 und 23:59 liegen!",

    "send_new_login" =>
        "Möchtest du <b>%VAR1%</b> eine E-Mail mit dem Benutzernamen
        und einem neuen Passwort zumailen?",

    "max_members" =>
        "Speichern nicht möglich, die maximale Mitgliederzahl würde überschritten.",

    "max_members_profile" =>
        "Speichern nicht möglich, bei der Rolle &bdquo;%VAR1%&rdquo;
        würde die maximale Mitgliederzahl überschritten werden.",

    "max_members_roles_change" =>
        "Speichern nicht möglich, die Rolle hat bereits mehr Mitglieder als die von dir eingegebene Begrenzung.",

    "write_access" =>
        "Der Ordner <b>%VAR1%</b> konnte nicht angelegt werden. Du musst dich an
        den <a href=\"mailto:%VAR2%\">Webmaster</a>
        wenden, damit dieser <acronym title=\"über FTP die Dateiattribute auf 0777 bzw. drwxrwxrwx setzen.\">
        Schreibrechte</acronym> für den Ordner setzen kann.",


    //Meldungen Anmeldung im Forum
    "login_forum" =>
        "Du hast dich erfolgreich auf Admidio und <br />im Forum <b>%VAR2%</b>
        als User <b>%VAR1%</b> angemeldet.",

    "login_forum_pass" =>
        "Dein Password im Forum %VAR2% wurde auf das Admidio-Password zurückgesetz.<br>
        Verwende beim nächsten Login im Forum dein Admidio-Password.<br><br>
        Du wurdest erfolgreich auf Admidio und <br />im Forum <b>%VAR2%</b>
        als User <b>%VAR1%</b> angemeldet.",

    "login_forum_admin" =>
        "Dein Administrator-Account vom Forum %VAR2% wurde auf den
        Admidio-Account zurückgesetz.<br>
        Verwende beim nächsten Login im Forum deinen Admidio-Usernamen und dein Admidio-Password.<br><br>
        Du wurdest erfolgreich auf Admidio und <br />im Forum <b>%VAR2%</b>
        als User <b>%VAR1%</b> angemeldet.",

    "login_forum_new" =>
        "Dein Admidio-Account wurde in das Forum %VAR2% exportiert und angelegt.<br>
        Verwende beim nächsten Login im Forum deinen Admidio-Usernamen und dein Admidio-Password.<br><br>
        Du wurdest erfolgreich auf Admidio und <br />im Forum <b>%VAR2%</b>
        als User <b>%VAR1%</b> angemeldet.",

    "logout_forum" =>
        "Du wurdest erfolgreich auf Admidio und <br />im Forum abgemeldet.",

    "login_name_forum" =>
        "Der gewählte Benutzername existiert im Forum schon.<br /><br />
        Wähle bitte einen anderen Namen.",

    "delete_forum_user" =>
        "Der gewählte Benutzername wurde im Forum und in Admidio gelöscht.",

    //Ende Meldungen Anmeldung im Forum

    //Fehlermeldungen Mitgliederzuordnung
    "members_changed" =>
        "Die Änderungen wurden erfolgreich gespeichert.",

    //Fehlermeldungen Linkmodul
    "delete_link" =>
        "Willst du den Link<br />
        <b>%VAR1%</b><br />wirklich löschen?",


    //Fehlermeldungen Gästebuchmodul
    "delete_gbook_entry" =>
        "Willst du den Gästebucheintrag von<br />
        <b>%VAR1%</b><br />wirklich löschen?",

    "delete_gbook_comment" =>
        "Willst du den Kommentar von<br />
        <b>%VAR1%</b><br />wirklich löschen?",

    "flooding_protection" =>
        "Dein letzter Eintrag im Gästebuch <br />
         liegt weniger als %VAR1% Sekunden zurück.",
    //Ende Fehlermeldungen Gästebuchmodul

        //Fehlermeldungen Profilfoto
    "profile_photo_update" =>
        "Das neue Profilfoto wurde erfolgreich gespeichert.",

    "profile_photo_update_cancel" =>
        "Der Vorgang wurde abgebrochen.",

    "profile_photo_nopic" =>
        "Es wurde keine Bilddatei ausgewählt.",

    "profile_photo_deleted" =>
          "Das Profilfoto wurde gelöscht.",

    "profile_photo_2big" =>
        "Das hochgeladene Foto übersteigt die vom Server zugelassene
        Dateigröße von %VAR1% B.",


    //Fehlermeldungen Fotomodul
    "no_photo_folder"=>
        "Der Ordner adm_my_files/photos wurde nicht gefunden.",

    "photodateiphotoup" =>
        "Du hast keine Bilddateien ausgewählt, die hinzugefügt
        werden sollen.<br />",

    "photoverwaltunsrecht" =>
        "Nur eingeloggte Benutzer mit Fotoverwaltungsrecht dürfen Fotos verwalten.<br />",

    "dateiendungphotoup" =>
        "Die ausgewählte Datei ist nicht im JPG-Format gespeichert.<br />",

    "startvorend" =>
        "Das eingegebene Enddatum liegt vor dem Anfangsdatum.<br />",

    "delete_veranst" =>
        "Willst du die Veranstaltung:<br />
        <b>%VAR1%</b><br />wirklich löschen?<br>
        Alle enthaltenen Unterveranstaltungen und Bilder gehen verloren.",
        
    "event_deleted" =>
        "Die Veranstaltung wurde erfolgreich gelöscht.",
        
    "event_deleted_error" =>
        "Beim Löschen der Veranstaltung sind Probleme aufgetreten.<br>
        Es konnten nicht alle Dateien bzw. Datensätze der Veranstaltung gelöscht werden.",

    "delete_photo" =>
        "Soll das ausgewählte Foto wirklich gelöscht werden?",

    "photo_deleted" =>
        "Das Foto wurde erfolgreich gelöscht.",

    "photo_2big" =>
        "Mindestens eins der hochgeladenen Fotos übersteigt die vom Server zugelassene
        Dateigröße von %VAR1% B.",

    "empty_photo_post" =>
        "Die Seite wurde ungültig aufgerufen oder die Datei(en) konnte nicht hochgeladen werden.<br />
        Vermutlich wurde die vom Server vorgegebene, maximale Uploadgröße
        von %VAR1% B. übersteigen!",
    //Ende Fehlermeldungen Fotomodul


    //Fehlermeldungen Downloadmodul
    "invalid_folder" =>
        "Du hast einen ungültigen Ordner aufgerufen!",

    "invalid_folder" =>
        "Du hast eine ungültigen Datei aufgerufen!",

    "invalid_file_name" =>
        "Der ausgwählte Dateiname enthält ungültige Zeichen!<br><br>
        Wähle bitte einen anderen Namen für die Datei aus.",

    "invalid_file_extension" =>
        "Du kannst keine PHP-, HTML- oder Perl-Dateien hochladen.",

    "file_not_exist" =>
        "Die ausgewählte Datei existiert nicht.",

    "folder_not_exist" =>
        "Der aufgerufene Ordner existiert nicht.",

    "delete_file_folder" =>
        "Willst du die Datei / den Ordner <b>%VAR1%</b> wirklich löschen?",

    "delete_file" =>
        "Die Datei <b>%VAR1%</b> wurde gelöscht.",

    "delete_folder" =>
        "Der Ordner <b>%VAR1%</b> wurde gelöscht.",

    "delete_error" =>
        "Beim Löschen ist ein unbekannter Fehler aufgetreten.",

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
        Dateigröße von %VAR1% KB.",

    "file_2big_server" =>
        "Die hochgeladene Datei übersteigt die vom Server zugelassene
        Dateigröße von %VAR1% B.",

    "empty_upload_post" =>
        "Die Seite wurde ungültig aufgerufen oder die Datei konnte nicht hochgeladen werden.<br>
        Vermutlich wurde die vom Server vorgegebene maximale Uploadgröße von %VAR1% B. überschritten!",

    "file_upload_error" =>
        "Beim Hochladen der Datei <b>%VAR1%</b> ist ein unbekannter Fehler aufgetreten.",
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
        "Die Seite wurde ungültig aufgerufen oder dein Dateinanhang konnte nicht hochgeladen werden.<br />
        Vermutlich ist das Attachment zu groß!",

    "mail_rolle" =>
        "Bitte wähle eine Rolle als Adressat der E-Mail aus!",

    "profile_mail" =>
        "In Ihrem <a href=\"%VAR1%\">Profil</a>
        ist keine gültige E-Mailadresse hinterlegt!",

    "role_empty" =>
        "Die von dir ausgewählte Rolle enthält keine Mitglieder
         mit gültigen E-Mailadressen, an die eine E-Mail versendet werden kann!",

    "usrid_not_found" =>
        "Die Userdaten der übergebenen ID konnten nicht gefunden werden!",

    "usrmail_not_found" =>
        "Der User hat keine gültige E-Mailadresse in seinem Profil hinterlegt!",
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
        "Die Servereinstellungen lassen leider keine Dateiuploads zu.",
    //Fehlermeldungen Servereinstellungen

    "default" =>
        "Es ist ein Fehler aufgetreten.<br><br>
        Der gesuchte Hinweis <b>%VAR1%</b> konnte nicht gefunden werden!"
 )
?>