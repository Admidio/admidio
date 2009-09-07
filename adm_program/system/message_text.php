<?php
/******************************************************************************
 * Texte fuer Hinweistexte oder Fehlermeldungen
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

$message_text = array(
'anmeldung' =>
    'Deine Daten wurden gespeichert.<br /><br />
    Du kannst dich noch nicht einloggen.<br />
    Sobald deine Anmeldung bestätigt wurde, erhältst du eine Benachrichtigung per E-Mail.',

'assign_login' =>
    'Die Logindaten wurden erfolgreich zugeordnet.',

'assign_login_mail' =>
    'Die Logindaten wurden erfolgreich zugeordnet und der
    Benutzer ist darüber per E-Mail benachrichtigt worden.',

'beta_version' =>
    'Dies ist eine Beta-Version von Admidio.<br /><br />
    Sie kann zu Stabilitätsproblemen und Datenverlust führen und
    sollte deshalb nur in einer Testumgebung genutzt werden !',

'category_exist' =>
    'Es existiert bereits eine Kategorie in dieser Organisation mit diesem Namen.',

'database_invalid' =>
    'Die Datenbankversion stimmt nicht mit der Version der Admidio-Scripte überein.<br /><br />
    Wende dich bitte an den <a href="mailto:%VAR1%">Webmaster</a> dieser Webseite.',

'date_invalid' =>
    'Es wurde kein gültiges Datum in das Feld %VAR1% eingegeben.<br /><br />
    Das Format des Datums sollte <i>dd.mm.yyyy</i> sein.',

'delete' =>
    'Die Daten wurden gelöscht!',

'delete_category' =>
    'Soll die Kategorie %VAR1% wirklich gelöscht werden ?<br /><br />
    Es werden alle Daten, die dieser Kategorie zugeordnet sind
    (Felder, Rollen, Links, Termine, Inventargegenstände), gelöscht.',

'delete_role' =>
    'Willst du die Rolle %VAR1% wirklich löschen?<br /><br />
    Es werden damit auch alle Mitgliedschaften entgütig entfernt.',

'delete_field' =>
    'Willst du das Feld %VAR1% wirklich löschen?<br /><br />
    Es werden alle Daten, die Benutzer in diesem Feld gespeichert haben, gelöscht.',

'delete_user' =>
    'Willst du %VAR1% wirklich löschen?<br /><br />
    Der Benutzer wird damit physikalisch in der Datenbank gelöscht und ein Zugriff auf
    seine Daten ist nicht mehr möglich.',

'email_invalid' =>
    'Die E-Mail-Adresse ist nicht gültig.',

'field_numeric' =>
    'Das Feld %VAR1% darf nur Zahlen enthalten.<br />
    Korrigiere bitte deine Eingabe.',

'field_exist' =>
    ' Es existiert bereits ein Feld in dieser Organisation mit diesem Namen.',

'feld' =>
    'Das Feld %VAR1% ist nicht gefüllt.',

'felder' =>
    'Es sind nicht alle Felder gefüllt worden.',

'import' =>
    '%VAR1% Datensätze wurden erfolgreich importiert!',

'installFolderExists' =>
    'Das Installationsverzeichnis <b>adm_install</b> existiert noch auf dem Server.
     Aus Sicherheitsgründen muss dieses gelöscht werden!',

'invalid' =>
    'Ungültiger Seitenaufruf!',

'login_failed' =>
    'Du hast mehrmals innerhalb kurzer Zeit versucht, dich mit einem
    falschen Passwort einzuloggen.<br />Aus Sicherheitsgründen
    ist dein Zugang für <b>15 Minuten</b> gesperrt.',

'login_name' =>
    'Der gewählte Benutzername existiert schon.<br /><br />
    Wähle bitte einen anderen Namen.',

'login_unknown' =>
    'Der angegebene Benutzername existiert nicht.',

'login' =>
    'Du hast dich erfolgreich angemeldet.',

'logout' =>
    'Du wurdest erfolgreich abgemeldet.',

'mylist_condition' =>
    'Bei der Verarbeitung der Bedingungen ist ein Fehler aufgetreten.<br /><br />
    Prüfe bitte, ob die Syntax bei allen Bedingungen korrekt ist.',

'module_disabled' =>
    'Dieses Modul ist nicht freigegeben.',

'missing_orga' =>
    'Die Organisation aus der config.php konnte in der Datenbank nicht gefunden werden.',

'mysql' =>
    'Folgender Fehler trat beim Zugriff auf die Datenbank auf:<br /><br />%VAR1%',

'new_user' =>
    'Bist du sicher, dass der Benutzer noch nicht in der Datenbank existiert?',

'noaccept' =>
    'Deine Anmeldung wurde noch nicht vom Administrator bestätigt.<br /><br />
    Das Einloggen ist noch nicht möglich',

'nodata' =>
    'Es sind keine Daten vorhanden!',

'norights' =>
    'Du hast keine Rechte, diese Aktion auszuführen',

'nomembers' =>
    'Es sind keine Anmeldungen vorhanden.',

'norolle' =>
    'Die Daten können nicht gespeichert werden.<br />
    Dem Benutzer sind keine Rollen zugeordnet.',
    
'no_default_role' =>
    'In den Organisationseinstellungen wurde keine Standardrolle für neue Benutzer hinterlegt.
    Wende dich bitte an einen Webmaster, der diese Einstellung vornehmen kann.',

'no_cookie' =>
    'Der Login kann nicht durchgeführt werden, da dein Browser
    das Setzen von Cookies verbietet!<br /><br />
    Damit du dich erfolgreich anmelden kannst, musst du in deinem Browser
    so einstellen, dass dieser Cookies von %VAR1% akzeptiert.',

'remove_member' =>
    'Willst du die Mitgliedschaft des Benutzers %VAR1% bei %VAR2% beenden?',

'remove_member_ok' =>
    'Die Mitgliedschaft des Benutzers bei %VAR1% wurde erfolgreich beendet!',

'role_active' =>
    'Die Rolle %VAR1% wurde auf <b>aktiv</b> gesetzt.',

'role_inactive' =>
    'Die Rolle %VAR1% wurde auf <b>inaktiv</b> gesetzt.',

'role_exist' =>
    'Es existiert bereits eine Rolle in dieser Kategorie mit demselben Namen.',

'role_select_right' =>
    'Du hast keine Berechtigung, die Rolle %VAR1% auszuwählen.<br />
    Bitte wähle eine andere Rolle aus.',

'role_invisible' =>
    'Die Rolle %VAR1% wurde auf <b>unsichtbar</b> gesetzt.',

'role_visible' =>
    'Die Rolle %VAR1% wurde auf <b>sichtbar</b> gesetzt.',

'save' =>
    'Deine Daten wurden erfolgreich gespeichert.',
    
'saveDate' =>
    'Deine Daten wurden erfolgreich gespeichert und du wurdest zum gewünschten Termin angemeldet.<br /><br />
    Du kannst dich nun einloggen.<br />',

'room_is_reserved' =>
    'Der gewählte <b>Raum</b> ist zu dieser Zeit bereits reserviert.',
    
'uhrzeit' =>
    'Es wurde keine sinnvolle Uhrzeit eingegeben.<br /><br />
    Die Uhrzeit muss zwischen 00:00 und 23:59 liegen!',

'send_new_login' =>
    'Möchtest du %VAR1% eine E-Mail mit dem Benutzernamen
    und einem neuen Passwort zumailen?',

'max_members' =>
    'Speichern nicht möglich, die maximale Mitgliederzahl würde überschritten.',

'max_members_profile' =>
    'Speichern nicht möglich, bei der Rolle &bdquo;%VAR1%&rdquo;
    würde die maximale Mitgliederzahl überschritten werden.',

'max_members_roles_change' =>
    'Speichern nicht möglich, die Rolle hat bereits mehr Mitglieder als die von dir eingegebene Begrenzung.',

'write_access' =>
    'Der Ordner %VAR1% kann nicht mit Schreibrechten angelegt werden. Du musst dich an den
    <a href="mailto:%VAR2%">Webmaster</a> wenden, damit dieser die 
    <acronym title="über FTP die Dateiattribute auf 0777 bzw. drwxrwxrwx setzen.">Schreibrechte</acronym>
    setzen kann.',

'quota_exceeded' =>
    'Es dürfen maximal so viele Teilnehmer insgesamt in allen Rollen (<b>Kontigentierung</b>) sein wie Teilnehmer 
    insgesamt (<b>Teilnehmerbegrenzung</b>)',
    
'quota_with_maximum' =>
    'Eine <b>Kontingentierung</b> kann nur bei einer <b>Teilnehmerbegrenzung</b> stattfinden',

'quota_for_role' =>
    'Es kann nur ein Kontingent für eine Rolle angegeben werden, für die der Termin auch sichtbar ist.',

'quota_and_max_members_must_match' =>
    'Werden alle teilnehmenden Rollen kontingentiert, muss die Anzahl der Summe der Kontingentierung mit
    der maximalen Anzahl übereinstimmen.',

// Meldungen Listen

'no_old_roles' =>
    'Es sind noch keine Rollen aus dem System entfernt worden.<br /><br />
    Erst wenn du in der Rollenverwaltung Rollen löschst, erscheinen diese automatisch bei
    den "Entfernten Rollen".',

'no_enabled_lists' =>
    'Du besitzt keine Rechte Listen der hinterlegten Rollen anzuschauen.',

// Ende Meldungen Listen

//Meldungen Anmeldung im Forum
'login_forum' =>
    'Du hast dich erfolgreich auf Admidio und <br />im Forum %VAR2%
    als User %VAR1% angemeldet.',

'login_forum_pass' =>
    'Dein Password im Forum %VAR2% wurde auf das Admidio-Password zurückgesetz.<br />
    Verwende beim nächsten Login im Forum dein Admidio-Password.<br /><br />
    Du wurdest erfolgreich auf Admidio und <br />im Forum %VAR2%
    als User %VAR1% angemeldet.',

'login_forum_admin' =>
    'Dein Administrator-Account vom Forum %VAR2% wurde auf den
    Admidio-Account zurückgesetz.<br />
    Verwende beim nächsten Login im Forum deinen Admidio-Usernamen und dein Admidio-Password.<br /><br />
    Du wurdest erfolgreich auf Admidio und <br />im Forum %VAR2%
    als User %VAR1% angemeldet.',

'login_forum_new' =>
    'Dein Admidio-Account wurde in das Forum %VAR2% exportiert und angelegt.<br />
    Verwende beim nächsten Login im Forum deinen Admidio-Usernamen und dein Admidio-Password.<br /><br />
    Du wurdest erfolgreich auf Admidio und <br />im Forum %VAR2%
    als User %VAR1% angemeldet.',

'logout_forum' =>
    'Du wurdest erfolgreich auf Admidio und <br />im Forum abgemeldet.',

'login_name_forum' =>
    'Der gewählte Benutzername existiert im Forum schon.<br /><br />
    Wähle bitte einen anderen Namen.',

'delete_forum_user' =>
    'Der gewählte Benutzername wurde im Forum und in Admidio gelöscht.',

//Ende Meldungen Anmeldung im Forum

//Fehlermeldungen Mitgliederzuordnung
'members_changed' =>
    'Die Änderungen wurden erfolgreich gespeichert.',
//Ende Fehlermeldungen Mitgliederzuordnung

//Fehlermeldungen Gästebuchmodul
'flooding_protection' =>
    'Dein letzter Eintrag im Gästebuch <br />
     liegt weniger als %VAR1% Sekunden zurück.',
//Ende Fehlermeldungen Gästebuchmodul

//Fehlermeldungen Profilfoto
'profile_photo_update' =>
    'Das neue Profilfoto wurde erfolgreich gespeichert.',

'profile_photo_update_cancel' =>
    'Der Vorgang wurde abgebrochen.',

'profile_photo_nopic' =>
    'Es wurde keine Bilddatei ausgewählt.',

'profile_photo_deleted' =>
      'Das Profilfoto wurde gelöscht.',

'profile_photo_2big' =>
    'Das hochgeladene Foto übersteigt die vom Server zugelassene
    Dateigröße von %VAR1% B.',

'profile_photo_resolution_2large' =>
    'Die Auflösung des hochgeladenen Bildes übersteigt die vom Server zugelassene Auflösung von %VAR1% Megapixeln.',
 //Ende Fehlermeldungen Profilfoto

// Passwort
'password_length' =>
    'Das Passwort muss aus mindestens 6 Zeichen bestehen.',

'passwords_not_equal' =>
    'Das Passwort stimmt nicht mit der Wiederholung überein.',

'password_unknown' =>
    'Du hast ein falsches Passwort eingegeben und
    konntest deshalb nicht angemeldet werden.<br /><br />
    Überprüf bitte dein Passwort und gib dieses dann erneut ein.',

'password_old_wrong' =>
    'Das alte Passwort ist falsch.',

'password_changed' =>
    'Das Passwort wurde erfolgreich geändert.',

'lost_password_send' =>
    'Das neue Passwort wurde an die Email Addresse %VAR1% geschickt!',

'lost_password_send_error' =>
    'Es ist ein Fehler beim Senden an die Email Addresse %VAR1% aufgetreten!<br /> Bitte versuch es später wieder!',

'lost_password_email_error' =>
    'Es konnte die E-Mail Addresse: %VAR1% im System nicht gefunden werden!',

'lost_password_allready_logged_in' =>
    'Du bist am System angemeldet folglich kennst du ja dein Passwort!',

'password_activation_id_not_valid' =>
    'Es wurde entweder schon das Passwort aktiviert oder der Aktivierungscode ist falsch!',

'password_activation_password_saved'=>
    'Das neue Passwort wurde nun übernommen!',
//Ende Password

// Grußkarte
'ecard_send_error'=>
    'Es ist ein Fehler bei der Verarbeitung der Grußkarte aufgetreten. Bitte probier es zu einem späteren Zeitpunkt noch einmal.',

'ecard_feld_error'=>
    'Es sind einige Eingabefelder nicht bzw. nicht richtig ausgefüllt. Bitte füll diese aus, bzw. korrigier diese.',

//Ende Grußkarte

//Fehlermeldungen Fotomodul
'no_photo_folder'=>
    'Der Ordner adm_my_files/photos wurde nicht gefunden.',

'photodateiphotoup' =>
    'Du hast keine Fotodateien ausgewählt, die hinzugefügt
    werden sollen.<br />',

'photoverwaltunsrecht' =>
    'Nur eingeloggte Benutzer mit Fotoverwaltungsrecht dürfen Fotos verwalten.<br />',

'dateiendungphotoup' =>
    'Es können nur Fotos im JPG und PNG-Format hochgeladen und angezeigt werden.<br />',

'startvorend' =>
    'Das eingegebene Enddatum liegt vor dem Anfangsdatum.<br />',

'delete_photo' =>
    'Soll das ausgewählte Foto wirklich gelöscht werden?',

'photo_deleted' =>
    'Das Foto wurde erfolgreich gelöscht.',

'photo_2big' =>
    'Eine der Dateien oder alle gemeinsam übersteigen die vom Server zugelassenen Uplodgröße von %VAR1% MB.',

'empty_photo_post' =>
    'Die Seite wurde ungültig aufgerufen oder die Datei(en) konnte nicht hochgeladen werden.<br />
    Vermutlich wurde die vom Server vorgegebene, maximale Uploadgröße
    von %VAR1%B. übersteigen!',
//Ende Fehlermeldungen Fotomodul

//Fehlermeldungen Forum

'forum_access_data' =>
    'Es wurden entweder die Felder für die Zugangsdaten des Forums nicht oder nicht richtig ausgefüllt!',

'forum_db_connection_failed' =>
    'Es konnte keine Verbindung zur Forumsdatenbank hergestellt werden! Überprüfe bitte die Zugangsdaten auf Richtigkeit!',

// Ende Fehlermeldungen Forum

//Fehlermeldungen Downloadmodul
'invalid_folder' =>
    'Du hast einen ungültigen Ordner aufgerufen!',

'invalid_file' =>
    'Du hast eine ungültigen Datei aufgerufen!',

'invalid_file_name' =>
    'Der ausgwählte Dateiname enthält ungültige Zeichen!<br /><br />
    Wähle bitte einen anderen Namen für die Datei aus.',

'invalid_folder_name' =>
    'Der ausgwählte Ordnername enthält ungültige Zeichen!<br /><br />
    Wähle bitte einen anderen Namen für den Ordner aus.',

'invalid_file_extension' =>
    'Dateien dieses Dateityps sind auf dem Server nicht erlaubt.',

'file_not_exist' =>
    'Die ausgewählte Datei existiert nicht auf dem Server.',

'folder_not_exist' =>
    'Der aufgerufene Ordner existiert nicht.',

'delete_error' =>
    'Beim Löschen ist ein unbekannter Fehler aufgetreten.',

'upload_file' =>
    'Die Datei %VAR1% wurde hochgeladen.',

'add_file' =>
    'Die Datei %VAR1% wurde der Datenbank hinzugefuegt.',

'add_folder' =>
    'Der Ordner %VAR1% wurde der Datenbank hinzugefuegt.',

'create_folder' =>
    'Der Ordner %VAR1% wurde angelegt.',

'folder_exists' =>
    'Der Ordner %VAR1% existiert bereits!<br /><br />
    Wähle bitte einen anderen Namen für den Ordner aus.',

'file_exists' =>
    'Die Datei %VAR1% existiert bereits!<br /><br />
    Wähle bitte einen anderen Dateinamen aus.',

'rename_folder' =>
    'Der Ordner %VAR1% wurde umbenannt.',

'rename_file' =>
    'Die Datei %VAR1% wurde umbenannt.',

'rename_folder_error' =>
    'Beim Umbenennen des Ordners %VAR1% ist ein Fehler aufgetreten.',

'rename_file_error' =>
    'Beim Umbenennen der Datei %VAR1% ist ein Fehler aufgetreten.',

'file_2big' =>
    'Die hochgeladene Datei übersteigt die zulässige
    Dateigröße von %VAR1% KB.',

'file_2big_server' =>
    'Die hochgeladene Datei übersteigt die vom Server zugelassene
    Dateigröße von %VAR1%.',

'empty_upload_post' =>
    'Die Seite wurde ungültig aufgerufen oder die Datei konnte nicht hochgeladen werden.<br />
    Vermutlich wurde die vom Server vorgegebene maximale Uploadgröße von %VAR1% B. überschritten!',

'file_upload_error' =>
    'Beim Hochladen der Datei %VAR1% ist ein unbekannter Fehler aufgetreten.',
//Ende Fehlermeldungen Downloadmodul


//Fehlermeldungen Mailmodul
'mail_send' =>
    'Die E-Mail wurde erfolgreich an %VAR1% versendet.',

'mail_not_send' =>
    'Die E-Mail konnte leider nicht an %VAR1% gesendet werden.',

'attachment' =>
    'Dein Dateinanhang konnte nicht hochgeladen werden.<br />
    Vermutlich ist das Attachment zu groß!',

'attachment_or_invalid' =>
    'Die Seite wurde ungültig aufgerufen oder dein Dateinanhang konnte nicht hochgeladen werden.<br />
    Vermutlich ist das Attachment zu groß!',

'mail_rolle' =>
    'Bitte wähle eine Rolle als Adressat der E-Mail aus!',

'profile_mail' =>
    'In deinem <a href="%VAR1%">Profil</a>
    ist keine gültige E-Mailadresse hinterlegt!',

'role_empty' =>
    'Die von dir ausgewählte Rolle enthält keine Mitglieder
     mit gültigen E-Mailadressen, an die eine E-Mail versendet werden kann!',

'usrid_not_found' =>
    'Die Userdaten der übergebenen ID konnten nicht gefunden werden!',

'usrmail_not_found' =>
    'Der User hat keine gültige E-Mailadresse in seinem Profil hinterlegt!',
//Ende Fehlermeldungen Mailmodul


//Fehlermeldungen RSSmodul
'rss_disabled' =>
    'Die RSS-Funktion wurde vom Webmaster deaktiviert',
//Ende Fehlermeldungen RSSmodul

//Fehlermeldungen Capcha-Klasse
'captcha_code' =>
    'Der Bestätigungscode wurde falsch eingegeben.',
//Ende Fehlermeldungen Capcha-Klasse


//Fehlermeldungen Servereinstellungen
'no_file_upload_server' =>
    'Die Servereinstellungen lassen leider keine Dateiuploads zu.',
//Fehlermeldungen Servereinstellungen

'default' =>
    'Es ist ein Fehler aufgetreten.<br /><br />
    Der gesuchte Hinweis %VAR1% konnte nicht gefunden werden!'
 )
?>