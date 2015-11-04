<?php
/******************************************************************************
 * Data conversion for version 2.1.0
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
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

// User-Create fuer alle Anmeldungen fuellen
$sql = 'UPDATE '. TBL_USERS. ' SET usr_usr_id_create = usr_id
         WHERE usr_usr_id_create IS NULL
           AND usr_login_name IS NOT NULL';
$gDb->query($sql);

// Texte fuer Systemmails pflegen
$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS. ' ORDER BY org_id DESC';
$result_orga = $gDb->query($sql);

while($row_orga = $gDb->fetch_array($result_orga))
{
    $sql = 'INSERT INTO '. TBL_TEXTS. ' (txt_org_id, txt_name, txt_text)
                 VALUES ('.$row_orga['org_id'].', \'SYSMAIL_REGISTRATION_USER\', \''.$systemmails_texts['SYSMAIL_REGISTRATION_USER'].'\')
                      , ('.$row_orga['org_id'].', \'SYSMAIL_REGISTRATION_WEBMASTER\', \''.$systemmails_texts['SYSMAIL_REGISTRATION_WEBMASTER'].'\')
                      , ('.$row_orga['org_id'].', \'SYSMAIL_NEW_PASSWORD\', \''.$systemmails_texts['SYSMAIL_NEW_PASSWORD'].'\')
                      , ('.$row_orga['org_id'].', \'SYSMAIL_ACTIVATION_LINK\', \''.$systemmails_texts['SYSMAIL_ACTIVATION_LINK'].'\') ';
    $gDb->query($sql);

    // Default-Kategorie fuer Datum eintragen
    $sql = 'INSERT INTO '. TBL_CATEGORIES. ' (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                      VALUES ('. $row_orga['org_id']. ', \'DAT\', \'Allgemein\', 0, 1)
                                           , ('. $row_orga['org_id']. ', \'DAT\', \'Kurse\', 0, 1)
                                           , ('. $row_orga['org_id']. ', \'DAT\', \'Training\', 0, 1)';
    $gDb->query($sql);
    $category_common = $gDb->lastInsertId();

    // Alle Termine der neuen Kategorie zuordnen
    $sql = 'UPDATE '. TBL_DATES. ' SET dat_cat_id = '. $category_common. '
             WHERE dat_cat_id is null
               AND dat_org_shortname LIKE \''. $row_orga['org_shortname']. '\'';
    $gDb->query($sql);

    // bei allen Alben ohne Ersteller, die Erstellungs-ID mit Webmaster befuellen,
    // damit das Feld auf NOT NULL gesetzt werden kann
    $sql = 'SELECT min(mem_usr_id) as webmaster_id
              FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
             WHERE cat_org_id = '. $row_orga['org_id']. '
               AND rol_cat_id = cat_id
               AND rol_name   = \'Webmaster\'
               AND mem_rol_id = rol_id ';
    $result = $gDb->query($sql);
    $row_webmaster = $gDb->fetch_array($result);

    $sql = 'UPDATE '. TBL_PHOTOS. ' SET pho_usr_id_create = '. $row_webmaster['webmaster_id']. '
             WHERE pho_usr_id_create IS NULL
               AND pho_org_shortname = \''. $row_orga['org_shortname'].'\'';
    $gDb->query($sql);

    $sql = 'UPDATE '. TBL_USERS. ' SET usr_usr_id_create = '. $row_webmaster['webmaster_id']. '
             WHERE usr_usr_id_create IS NULL ';
    $gDb->query($sql);

    $sql = 'SELECT * FROM '. TBL_CATEGORIES. '
             WHERE cat_org_id = '.$row_orga['org_id']. '
               AND cat_type   = \'ROL\'';
    $result = $gDb->query($sql);

    $all_cat_ids = array();
    while($row_cat = $gDb->fetch_array($result))
    {
        $all_cat_ids[] = $row_cat['cat_id'];
    }
    $all_cat_str = implode(",", $all_cat_ids);

    // neue Rollenfelder fuellen
    $sql = 'UPDATE '. TBL_ROLES. ' SET rol_timestamp_create = rol_timestamp_change
                                     , rol_usr_id_create    = '. $row_webmaster['webmaster_id']. '
         WHERE rol_timestamp_change IS NOT NULL
           AND rol_usr_id_change IS NOT NULL
           AND rol_cat_id IN ('.$all_cat_str.')';
    $gDb->query($sql);

    $sql = 'UPDATE '. TBL_ROLES. ' SET rol_timestamp_create = \''.DATETIME_NOW.'\'
                                     , rol_usr_id_create    = '. $row_webmaster['webmaster_id']. '
         WHERE rol_timestamp_create IS NULL
           AND rol_cat_id IN ('.$all_cat_str.')';
    $gDb->query($sql);

    $sql = 'SELECT usf_id FROM '. TBL_USER_FIELDS. ' WHERE usf_name = \'Nachname\' ';
    $result = $gDb->query($sql);
    $rowNachname = $gDb->fetch_array($result);

    $sql = 'SELECT usf_id FROM '. TBL_USER_FIELDS. ' WHERE usf_name = \'Vorname\' ';
    $result = $gDb->query($sql);
    $rowVorname = $gDb->fetch_array($result);

    $sql = 'SELECT usf_id FROM '. TBL_USER_FIELDS. ' WHERE usf_name = \'Geburtstag\' ';
    $result = $gDb->query($sql);
    $rowGeburtstag = $gDb->fetch_array($result);

    $sql = 'SELECT usf_id FROM '. TBL_USER_FIELDS. ' WHERE usf_name = \'Adresse\' ';
    $result = $gDb->query($sql);
    $rowAdresse = $gDb->fetch_array($result);

    $sql = 'SELECT usf_id FROM '. TBL_USER_FIELDS. ' WHERE usf_name = \'PLZ\' ';
    $result = $gDb->query($sql);
    $rowPLZ = $gDb->fetch_array($result);

    $sql = 'SELECT usf_id FROM '. TBL_USER_FIELDS. ' WHERE usf_name = \'Ort\' ';
    $result = $gDb->query($sql);
    $rowOrt = $gDb->fetch_array($result);

    $sql = 'SELECT usf_id FROM '. TBL_USER_FIELDS. ' WHERE usf_name = \'Telefon\' ';
    $result = $gDb->query($sql);
    $rowTelefon = $gDb->fetch_array($result);

    $sql = 'SELECT usf_id FROM '. TBL_USER_FIELDS. ' WHERE usf_name = \'Handy\' ';
    $result = $gDb->query($sql);
    $rowHandy = $gDb->fetch_array($result);

    $sql = 'SELECT usf_id FROM '. TBL_USER_FIELDS. ' WHERE usf_name = \'E-Mail\' ';
    $result = $gDb->query($sql);
    $rowEMail = $gDb->fetch_array($result);

    $sql = 'SELECT usf_id FROM '. TBL_USER_FIELDS. ' WHERE usf_name = \'Fax\' ';
    $result = $gDb->query($sql);
    $rowFax = $gDb->fetch_array($result);

    // Default-Listen-Konfigurationen anlegen

    $sql = 'INSERT INTO '. TBL_LISTS. ' (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global, lst_default)
                 VALUES ('.$row_orga['org_id'].', '.$row_webmaster['webmaster_id'].', \'Adressliste\', \''.DATETIME_NOW.'\', 1, 1)';
    $gDb->query($sql);
    $AdresslisteId = $gDb->lastInsertId();

    $sql = 'INSERT INTO '. TBL_LIST_COLUMNS. ' (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort)
                 VALUES ('.$AdresslisteId.', 1, '.$rowNachname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 2, '.$rowVorname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 3, '.$rowGeburtstag[0].', null, null)
                      , ('.$AdresslisteId.', 4, '.$rowAdresse[0].', null, null)
                      , ('.$AdresslisteId.', 5, '.$rowPLZ[0].', null, null)
                      , ('.$AdresslisteId.', 6, '.$rowOrt[0].', null, null)';
    $gDb->query($sql);

    $sql = 'INSERT INTO '. TBL_LISTS. ' (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global, lst_default)
                 VALUES ('.$row_orga['org_id'].', '.$row_webmaster['webmaster_id'].', \'Telefonliste\', \''.DATETIME_NOW.'\', 1, 0)';
    $gDb->query($sql);
    $AdresslisteId = $gDb->lastInsertId();

    $sql = 'INSERT INTO '. TBL_LIST_COLUMNS. ' (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort)
                 VALUES ('.$AdresslisteId.', 1, '.$rowNachname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 2, '.$rowVorname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 3, '.$rowTelefon[0].', null, null)
                      , ('.$AdresslisteId.', 4, '.$rowHandy[0].', null, null)
                      , ('.$AdresslisteId.', 5, '.$rowEMail[0].', null, null)
                      , ('.$AdresslisteId.', 6, '.$rowFax[0].', null, null)';
    $gDb->query($sql);

    $sql = 'INSERT INTO '. TBL_LISTS. ' (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global, lst_default)
                 VALUES ('.$row_orga['org_id'].', '.$row_webmaster['webmaster_id'].', \'Kontaktdaten\', \''.DATETIME_NOW.'\', 1, 0)';
    $gDb->query($sql);
    $AdresslisteId = $gDb->lastInsertId();

    $sql = 'INSERT INTO '. TBL_LIST_COLUMNS. ' (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort)
                 VALUES ('.$AdresslisteId.', 1, '.$rowNachname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 2, '.$rowVorname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 3, '.$rowGeburtstag[0].', null, null)
                      , ('.$AdresslisteId.', 4, '.$rowAdresse[0].', null, null)
                      , ('.$AdresslisteId.', 5, '.$rowPLZ[0].', null, null)
                      , ('.$AdresslisteId.', 6, '.$rowOrt[0].', null, null)
                      , ('.$AdresslisteId.', 7, '.$rowTelefon[0].', null, null)
                      , ('.$AdresslisteId.', 8, '.$rowHandy[0].', null, null)
                      , ('.$AdresslisteId.', 9, '.$rowEMail[0].', null, null)';
    $gDb->query($sql);

    $sql = 'INSERT INTO '. TBL_LISTS. ' (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global, lst_default)
                 VALUES ('.$row_orga['org_id'].', '.$row_webmaster['webmaster_id'].', \'Mitgliedschaft\', \''.DATETIME_NOW.'\', 1, 0)';
    $gDb->query($sql);
    $AdresslisteId = $gDb->lastInsertId();

    $sql = 'INSERT INTO '. TBL_LIST_COLUMNS. ' (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort)
                 VALUES ('.$AdresslisteId.', 1, '.$rowNachname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 2, '.$rowVorname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 3, '.$rowGeburtstag[0].', null, null)
                      , ('.$AdresslisteId.', 4, null, \'mem_begin\', null)
                      , ('.$AdresslisteId.', 5, null, \'mem_end\', null)';
    $gDb->query($sql);

    // Beta-Flag für Datenbank-Versionsnummer schreiben
    $sql = 'INSERT INTO '. TBL_PREFERENCES. ' (prf_org_id, prf_name, prf_value)
            VALUES ('.$row_orga['org_id'].', \'db_version_beta\', \'1\') ';
    $gDb->query($sql);
}

$sql = 'UPDATE '. TBL_PHOTOS. ' SET pho_timestamp_create = \''.DATETIME_NOW.'\'
         WHERE pho_timestamp_create IS NULL ';
$gDb->query($sql);

// neue Userfelder fuellen
$sql = 'UPDATE '. TBL_USERS. ' SET usr_timestamp_create = usr_timestamp_change
         WHERE usr_timestamp_change IS NOT NULL ';
$gDb->query($sql);

$sql = 'UPDATE '. TBL_USERS. ' SET usr_timestamp_create = \''.DATETIME_NOW.'\'
         WHERE usr_timestamp_create IS NULL ';
$gDb->query($sql);


// Datenstruktur nach Update anpassen
$sql = 'ALTER TABLE '. TBL_USERS. ' MODIFY COLUMN usr_timestamp_create datetime NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '. TBL_ROLES. ' MODIFY COLUMN rol_timestamp_create datetime NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '. TBL_PHOTOS. ' MODIFY COLUMN pho_timestamp_create datetime NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '. TBL_DATES. ' MODIFY COLUMN dat_cat_id int(11) unsigned NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '. TBL_DATES. ' DROP COLUMN dat_org_shortname ';
$gDb->query($sql);

//Neu Mailrechte installieren
//1. neue Spalten anlegen
    //passiert schon in upd_2_1_0_db.sql

//2.Webmaster mit globalem Mailsenderecht ausstatten
$sql = 'UPDATE '. TBL_ROLES. ' SET rol_mail_to_all = \'1\'
        WHERE rol_name = \'Webmaster\'';
$gDb->query($sql);

//3. alte Mailrechte Übertragen
//3.1 eingeloggte konnten bisher eine Mail an diese Rolle schreiben
$sql = 'UPDATE '. TBL_ROLES. ' SET rol_mail_this_role = \'2\'
        WHERE rol_mail_login = 1';
$gDb->query($sql);

//3.2 ausgeloggte konnten bisher eine Mail an diese Rolle schreiben
$sql = 'UPDATE '. TBL_ROLES. ' SET rol_mail_this_role = \'3\'
        WHERE rol_mail_logout = 1';
$gDb->query($sql);

//4. Überflüssige Spalten löschen
$sql = 'ALTER TABLE '. TBL_ROLES. '
        DROP rol_mail_login,
        DROP rol_mail_logout';
$gDb->query($sql);


//Neues Inventarmodulrecht installieren
//1. neue Spalte anlegen
    //passiert schon in upd_2_1_0_db.sql

//2.Webmaster mit Inventarverwaltungsrecht ausstatten
$sql = 'UPDATE '. TBL_ROLES. ' SET rol_inventory = \'1\'
        WHERE rol_name = \'Webmaster\'';
$gDb->query($sql);



//Fototext updaten
$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS;
$result_orga = $gDb->query($sql);
while($row_orga = $gDb->fetch_array($result_orga))
{
    //erstmal gucken ob die Funktion bisher aktiviert war
    $sql = 'SELECT prf_value
              FROM '. TBL_PREFERENCES. '
             WHERE prf_org_id = '. $row_orga['org_id']. '
               AND prf_name   = \'photo_image_text\' ';
    $result = $gDb->query($sql);
    $row_photo_image_text = $gDb->fetch_array($result);

    //wenn ja
    if($row_photo_image_text['prf_value'] == 1)
    {
        $sql = 'UPDATE '. TBL_PREFERENCES. '
                SET prf_value = \'© '.$row_orga['org_homepage'].'\'
                   WHERE prf_org_id = '. $row_orga['org_id']. '
                   AND prf_name   = \'photo_image_text\' ';
    }
    //wenn nicht
    else
    {
        $sql = 'UPDATE '. TBL_PREFERENCES. '
                SET prf_value = \'\'
                 WHERE prf_org_id = '. $row_orga['org_id']. '
                   AND prf_name   = \'photo_image_text\' ';
    }
    $gDb->query($sql);
}
