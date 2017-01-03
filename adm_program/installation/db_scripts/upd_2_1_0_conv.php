<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.1.0
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
$systemmails_texts = array(
    'SYSMAIL_REGISTRATION_USER' => '#Betreff# Anmeldung bei #organization_long_name#
#Inhalt# Hallo #user_first_name#,

deine Anmeldung auf #organization_homepage# wurde bestätigt.

Nun kannst du dich mit deinem Benutzernamen : #user_login_name#
und dem Passwort auf der Homepage einloggen.

Sollten noch Fragen bestehen, schreib eine E-Mail an #webmaster_email# .

Viele Grüße
Die Webmaster',

    'SYSMAIL_REGISTRATION_WEBMASTER' => '#Betreff# Neue Registrierung bei #organization_long_name#
#Inhalt# Es hat sich ein neuer Benutzer auf #organization_homepage# registriert.

Nachname: #user_last_name#
Vorname:  #user_first_name#
E-Mail:   #user_email#


Diese Nachricht wurde automatisch erzeugt.',

    'SYSMAIL_NEW_PASSWORD' => '#Betreff# Logindaten für #organization_homepage#
#Inhalt# Hallo #user_first_name#,

du erhälst deine Logindaten für #organization_homepage# .
Benutzername: #user_login_name#
Passwort: #variable1#

Das Passwort wurde automatisch generiert.
Du solltest es nach deiner Anmeldung auf #organization_homepage# in deinem Profil ändern.

Viele Grüße
Die Webmaster',

    'SYSMAIL_ACTIVATION_LINK' => '#Betreff# Dein angefordertes Passwort
#Inhalt# Hallo #user_first_name#,

du hast ein neues Passwort angefordert!

Hier sind deine Daten:
Benutzername: #user_login_name#
Passwort: #variable1#

Damit du dein neues Passwort benutzen kannst, musst du es über den folgenden Link freischalten:

#variable2#

Das Passwort wurde automatisch generiert.
Du solltest es nach deiner Anmeldung auf #organization_homepage# in deinem Profil ändern.

Viele Grüße
Die Webmaster'
);

// User-Create fuer alle Anmeldungen fuellen
$sql = 'UPDATE '.TBL_USERS.' SET usr_usr_id_create = usr_id
         WHERE usr_usr_id_create IS NULL
           AND usr_login_name IS NOT NULL';
$gDb->query($sql);

// Texte fuer Systemmails pflegen
$sql = 'SELECT * FROM '.TBL_ORGANIZATIONS.' ORDER BY org_id DESC';
$orgaStatement = $gDb->query($sql);

while($rowOrga = $orgaStatement->fetch())
{
    $sql = 'INSERT INTO '.TBL_TEXTS.' (txt_org_id, txt_name, txt_text)
                 VALUES ('.$rowOrga['org_id'].', \'SYSMAIL_REGISTRATION_USER\',      \''.$systemmails_texts['SYSMAIL_REGISTRATION_USER'].'\')
                      , ('.$rowOrga['org_id'].', \'SYSMAIL_REGISTRATION_WEBMASTER\', \''.$systemmails_texts['SYSMAIL_REGISTRATION_WEBMASTER'].'\')
                      , ('.$rowOrga['org_id'].', \'SYSMAIL_NEW_PASSWORD\',           \''.$systemmails_texts['SYSMAIL_NEW_PASSWORD'].'\')
                      , ('.$rowOrga['org_id'].', \'SYSMAIL_ACTIVATION_LINK\',        \''.$systemmails_texts['SYSMAIL_ACTIVATION_LINK'].'\') ';
    $gDb->query($sql);

    // Default-Kategorie fuer Datum eintragen
    $sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                      VALUES ('. $rowOrga['org_id']. ', \'DAT\', \'Allgemein\', 0, 1)
                                           , ('. $rowOrga['org_id']. ', \'DAT\', \'Kurse\', 0, 1)
                                           , ('. $rowOrga['org_id']. ', \'DAT\', \'Training\', 0, 1)';
    $gDb->query($sql);
    $categoryCommon = $gDb->lastInsertId();

    // Alle Termine der neuen Kategorie zuordnen
    $sql = 'UPDATE '.TBL_DATES.' SET dat_cat_id = '. $categoryCommon. '
             WHERE dat_cat_id IS NULL
               AND dat_org_shortname LIKE \''. $rowOrga['org_shortname']. '\'';
    $gDb->query($sql);

    // bei allen Alben ohne Ersteller, die Erstellungs-ID mit Webmaster befuellen,
    // damit das Feld auf NOT NULL gesetzt werden kann
    $sql = 'SELECT MIN(mem_usr_id) AS webmaster_id
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE rol_name   = \'Webmaster\'
               AND cat_org_id = '.$rowOrga['org_id'];
    $statement = $gDb->query($sql);
    $rowWebmaster = $statement->fetch();

    $sql = 'UPDATE '.TBL_PHOTOS.' SET pho_usr_id_create = '. $rowWebmaster['webmaster_id']. '
             WHERE pho_usr_id_create IS NULL
               AND pho_org_shortname = \''. $rowOrga['org_shortname'].'\'';
    $gDb->query($sql);

    $sql = 'UPDATE '.TBL_USERS.' SET usr_usr_id_create = '. $rowWebmaster['webmaster_id']. '
             WHERE usr_usr_id_create IS NULL ';
    $gDb->query($sql);

    $sql = 'SELECT * FROM '.TBL_CATEGORIES.'
             WHERE cat_org_id = '.$rowOrga['org_id']. '
               AND cat_type   = \'ROL\'';
    $statement = $gDb->query($sql);

    $allCatIds = array();
    while($rowCat = $statement->fetch())
    {
        $allCatIds[] = $rowCat['cat_id'];
    }
    $allCatStr = implode(',', $allCatIds);

    // neue Rollenfelder fuellen
    $sql = 'UPDATE '.TBL_ROLES.' SET rol_timestamp_create = rol_timestamp_change
                                   , rol_usr_id_create    = '. $rowWebmaster['webmaster_id']. '
             WHERE rol_timestamp_change IS NOT NULL
               AND rol_usr_id_change IS NOT NULL
               AND rol_cat_id IN ('.$allCatStr.')';
    $gDb->query($sql);

    $sql = 'UPDATE '.TBL_ROLES.' SET rol_timestamp_create = \''.DATETIME_NOW.'\'
                                   , rol_usr_id_create    = '. $rowWebmaster['webmaster_id']. '
             WHERE rol_timestamp_create IS NULL
               AND rol_cat_id IN ('.$allCatStr.')';
    $gDb->query($sql);

    $sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name = \'Nachname\' ';
    $statement = $gDb->query($sql);
    $rowNachname = $statement->fetch();

    $sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name = \'Vorname\' ';
    $statement = $gDb->query($sql);
    $rowVorname = $statement->fetch();

    $sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name = \'Geburtstag\' ';
    $statement = $gDb->query($sql);
    $rowGeburtstag = $statement->fetch();

    $sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name = \'Adresse\' ';
    $statement = $gDb->query($sql);
    $rowAdresse = $statement->fetch();

    $sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name = \'PLZ\' ';
    $statement = $gDb->query($sql);
    $rowPLZ = $statement->fetch();

    $sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name = \'Ort\' ';
    $statement = $gDb->query($sql);
    $rowOrt = $statement->fetch();

    $sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name = \'Telefon\' ';
    $statement = $gDb->query($sql);
    $rowTelefon = $statement->fetch();

    $sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name = \'Handy\' ';
    $statement = $gDb->query($sql);
    $rowHandy = $statement->fetch();

    $sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name = \'E-Mail\' ';
    $statement = $gDb->query($sql);
    $rowEMail = $statement->fetch();

    $sql = 'SELECT usf_id FROM '.TBL_USER_FIELDS.' WHERE usf_name = \'Fax\' ';
    $statement = $gDb->query($sql);
    $rowFax = $statement->fetch();

    // Default-Listen-Konfigurationen anlegen

    $sql = 'INSERT INTO '.TBL_LISTS.' (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global, lst_default)
                 VALUES ('.$rowOrga['org_id'].', '.$rowWebmaster['webmaster_id'].', \'Adressliste\', \''.DATETIME_NOW.'\', 1, 1)';
    $gDb->query($sql);
    $AdresslisteId = $gDb->lastInsertId();

    $sql = 'INSERT INTO '.TBL_LIST_COLUMNS.' (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort)
                 VALUES ('.$AdresslisteId.', 1, '.$rowNachname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 2, '.$rowVorname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 3, '.$rowGeburtstag[0].', null, null)
                      , ('.$AdresslisteId.', 4, '.$rowAdresse[0].', null, null)
                      , ('.$AdresslisteId.', 5, '.$rowPLZ[0].', null, null)
                      , ('.$AdresslisteId.', 6, '.$rowOrt[0].', null, null)';
    $gDb->query($sql);

    $sql = 'INSERT INTO '.TBL_LISTS.' (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global, lst_default)
                 VALUES ('.$rowOrga['org_id'].', '.$rowWebmaster['webmaster_id'].', \'Telefonliste\', \''.DATETIME_NOW.'\', 1, 0)';
    $gDb->query($sql);
    $AdresslisteId = $gDb->lastInsertId();

    $sql = 'INSERT INTO '.TBL_LIST_COLUMNS.' (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort)
                 VALUES ('.$AdresslisteId.', 1, '.$rowNachname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 2, '.$rowVorname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 3, '.$rowTelefon[0].', null, null)
                      , ('.$AdresslisteId.', 4, '.$rowHandy[0].', null, null)
                      , ('.$AdresslisteId.', 5, '.$rowEMail[0].', null, null)
                      , ('.$AdresslisteId.', 6, '.$rowFax[0].', null, null)';
    $gDb->query($sql);

    $sql = 'INSERT INTO '.TBL_LISTS.' (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global, lst_default)
                 VALUES ('.$rowOrga['org_id'].', '.$rowWebmaster['webmaster_id'].', \'Kontaktdaten\', \''.DATETIME_NOW.'\', 1, 0)';
    $gDb->query($sql);
    $AdresslisteId = $gDb->lastInsertId();

    $sql = 'INSERT INTO '.TBL_LIST_COLUMNS.' (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort)
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

    $sql = 'INSERT INTO '.TBL_LISTS.' (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global, lst_default)
                 VALUES ('.$rowOrga['org_id'].', '.$rowWebmaster['webmaster_id'].', \'Mitgliedschaft\', \''.DATETIME_NOW.'\', 1, 0)';
    $gDb->query($sql);
    $AdresslisteId = $gDb->lastInsertId();

    $sql = 'INSERT INTO '.TBL_LIST_COLUMNS.' (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort)
                 VALUES ('.$AdresslisteId.', 1, '.$rowNachname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 2, '.$rowVorname[0].', null, \'ASC\')
                      , ('.$AdresslisteId.', 3, '.$rowGeburtstag[0].', null, null)
                      , ('.$AdresslisteId.', 4, null, \'mem_begin\', null)
                      , ('.$AdresslisteId.', 5, null, \'mem_end\', null)';
    $gDb->query($sql);

    // Beta-Flag für Datenbank-Versionsnummer schreiben
    $sql = 'INSERT INTO '.TBL_PREFERENCES.' (prf_org_id, prf_name, prf_value)
            VALUES ('.$rowOrga['org_id'].', \'db_version_beta\', \'1\') ';
    $gDb->query($sql);
}

$sql = 'UPDATE '.TBL_PHOTOS.' SET pho_timestamp_create = \''.DATETIME_NOW.'\'
         WHERE pho_timestamp_create IS NULL ';
$gDb->query($sql);

// neue Userfelder fuellen
$sql = 'UPDATE '.TBL_USERS.' SET usr_timestamp_create = usr_timestamp_change
         WHERE usr_timestamp_change IS NOT NULL ';
$gDb->query($sql);

$sql = 'UPDATE '.TBL_USERS.' SET usr_timestamp_create = \''.DATETIME_NOW.'\'
         WHERE usr_timestamp_create IS NULL ';
$gDb->query($sql);

// Datenstruktur nach Update anpassen
$sql = 'ALTER TABLE '.TBL_USERS.' MODIFY COLUMN usr_timestamp_create datetime NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '.TBL_ROLES.' MODIFY COLUMN rol_timestamp_create datetime NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '.TBL_PHOTOS.' MODIFY COLUMN pho_timestamp_create datetime NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '.TBL_DATES.' MODIFY COLUMN dat_cat_id int(11) unsigned NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '.TBL_DATES.' DROP COLUMN dat_org_shortname ';
$gDb->query($sql);

// Neu Mailrechte installieren
// 1. neue Spalten anlegen
    // passiert schon in upd_2_1_0_db.sql

// 2.Webmaster mit globalem Mailsenderecht ausstatten
$sql = 'UPDATE '.TBL_ROLES.' SET rol_mail_to_all = \'1\'
         WHERE rol_name = \'Webmaster\'';
$gDb->query($sql);

// 3. alte Mailrechte Übertragen
// 3.1 eingeloggte konnten bisher eine Mail an diese Rolle schreiben
$sql = 'UPDATE '.TBL_ROLES.' SET rol_mail_this_role = \'2\'
         WHERE rol_mail_login = 1';
$gDb->query($sql);

// 3.2 ausgeloggte konnten bisher eine Mail an diese Rolle schreiben
$sql = 'UPDATE '.TBL_ROLES.' SET rol_mail_this_role = \'3\'
         WHERE rol_mail_logout = 1';
$gDb->query($sql);

// 4. Überflüssige Spalten löschen
$sql = 'ALTER TABLE '.TBL_ROLES.'
        DROP rol_mail_login,
        DROP rol_mail_logout';
$gDb->query($sql);

// Neues Inventarmodulrecht installieren
// 1. neue Spalte anlegen
    // passiert schon in upd_2_1_0_db.sql

// 2.Webmaster mit Inventarverwaltungsrecht ausstatten
$sql = 'UPDATE '.TBL_ROLES.' SET rol_inventory = \'1\'
         WHERE rol_name = \'Webmaster\'';
$gDb->query($sql);

// Fototext updaten
$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS;
$orgaStatement = $gDb->query($sql);
while($rowOrga = $orgaStatement->fetch())
{
    // erstmal gucken ob die Funktion bisher aktiviert war
    $sql = 'SELECT prf_value
              FROM '.TBL_PREFERENCES.'
             WHERE prf_org_id = '. $rowOrga['org_id']. '
               AND prf_name   = \'photo_image_text\' ';
    $statement = $gDb->query($sql);
    $rowPhotoImageText = $statement->fetch();

    // wenn ja
    if($rowPhotoImageText['prf_value'] == 1)
    {
        $sql = 'UPDATE '.TBL_PREFERENCES.'
                   SET prf_value = \'© '.$rowOrga['org_homepage'].'\'
                 WHERE prf_org_id = '. $rowOrga['org_id']. '
                   AND prf_name   = \'photo_image_text\' ';
    }
    // wenn nicht
    else
    {
        $sql = 'UPDATE '.TBL_PREFERENCES.'
                   SET prf_value = \'\'
                 WHERE prf_org_id = '. $rowOrga['org_id']. '
                   AND prf_name   = \'photo_image_text\' ';
    }
    $gDb->query($sql);
}
