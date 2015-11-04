<?php
/******************************************************************************
 * Data conversion for version 2.2.0
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// eine Orga-ID einlesen
$sql = 'SELECT MIN(org_id) as org_id FROM '. TBL_ORGANIZATIONS. ' ORDER BY org_id DESC';
$result_orga = $gDb->query($sql);
$row_orga = $gDb->fetch_array($result_orga);

// die Erstellungs-ID mit Webmaster befuellen, damit das Feld auf NOT NULL gesetzt werden kann
$sql = 'SELECT min(mem_usr_id) as webmaster_id
          FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
         WHERE cat_org_id = '. $row_orga['org_id']. '
           AND rol_cat_id = cat_id
           AND rol_name   = \'Webmaster\'
           AND mem_rol_id = rol_id ';
$result = $gDb->query($sql);
$row_webmaster = $gDb->fetch_array($result);

//Defaultraum fuer Raummodul in der DB anlegen:
$sql = 'INSERT INTO '. TBL_ROOMS. ' (room_name, room_description, room_capacity, room_usr_id_create, room_timestamp_create)
                                VALUES (\'Besprechnungsraum\', \'Hier können Besprechungen stattfinden. Der Raum muss vorher
                                         reserviert werden. Ein Beamer steht zur Verfügung.\', 15, '.
                                         $row_webmaster['webmaster_id'].',\''. DATETIME_NOW.'\')';
$gDb->query($sql);

// interner Name für System-Profilfelder belegen
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'LAST_NAME\' WHERE usf_name = \'Nachname\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'FIRST_NAME\' WHERE usf_name = \'Vorname\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'ADDRESS\' WHERE usf_name = \'Adresse\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'POSTCODE\' WHERE usf_name = \'PLZ\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'CITY\' WHERE usf_name = \'Ort\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'COUNTRY\' WHERE usf_name = \'Land\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'PHONE\', usf_system = 0 WHERE usf_name = \'Telefon\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'MOBILE\', usf_system = 0 WHERE usf_name = \'Handy\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'FAX\', usf_system = 0 WHERE usf_name = \'Fax\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'GENDER\' WHERE usf_name = \'Geschlecht\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'BIRTHDAY\' WHERE usf_name = \'Geburtstag\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'EMAIL\' WHERE usf_name = \'E-Mail\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'WEBSITE\', usf_name = \'Webseite\' WHERE usf_name = \'Homepage\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'AOL_INSTANT_MESSENGER\' WHERE usf_name = \'AIM\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'GOOGLE_TALK\' WHERE usf_name = \'Google Talk\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'ICQ\' WHERE usf_name = \'ICQ\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'MSN_MESSENGER\' WHERE usf_name = \'MSN\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'SKYPE\' WHERE usf_name = \'Skype\' ';
$result_orga = $gDb->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = \'YAHOO_MESSENGER\' WHERE usf_name = \'Yahoo\' ';
$result_orga = $gDb->query($sql);

// interne Name bei allen anderen Feldern fuellen
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = UPPER(usf_name) WHERE usf_name_intern IS NULL ';
$result_orga = $gDb->query($sql);

// E-Mail-Adresse darf jetzt nur noch klein geschrieben werden
$sql = 'UPDATE '. TBL_USER_DATA. ' SET usd_value = LOWER(usd_value)
         WHERE usd_value IS NOT NULL
           AND usd_usf_id IN (SELECT usf_id FROM '. TBL_USER_FIELDS. '
                               WHERE usf_type = \'EMAIL\') ';
$result_orga = $gDb->query($sql);

$sql = 'UPDATE '. TBL_GUESTBOOK. ' SET gbo_email = LOWER(gbo_email)
         WHERE gbo_email IS NOT NULL ';
$result_orga = $gDb->query($sql);

$sql = 'UPDATE '. TBL_GUESTBOOK_COMMENTS. ' SET gbc_email = LOWER(gbc_email)
         WHERE gbc_email IS NOT NULL ';
$result_orga = $gDb->query($sql);

$sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = LOWER(prf_value)
         WHERE prf_name IN (\'email_administrator\', \'mail_sendmail_address\') ';
$result_orga = $gDb->query($sql);

// interner Name für System-Kategorien belegen
$sql = 'UPDATE '. TBL_CATEGORIES. ' SET cat_name_intern = \'MASTER_DATA\' WHERE cat_name = \'Stammdaten\' ';
$result_orga = $gDb->query($sql);

// interne Name bei allen anderen Feldern fuellen
$sql = 'UPDATE '. TBL_CATEGORIES. ' SET cat_name_intern = UPPER(cat_name) WHERE cat_name_intern IS NULL ';
$result_orga = $gDb->query($sql);

// Defaulteintraege fuer alle existierenden Termine bei der Rollenzuordnung
$sql = 'INSERT INTO '. TBL_DATE_ROLE. ' (dtr_dat_id, dtr_rol_id)
        SELECT dat_id, NULL FROM '. TBL_DATES;
$gDb->query($sql);

// Max. Rol-Kategorien-Sequenz einlesen
$sql = 'SELECT MAX(cat_sequence) as sequence FROM '. TBL_CATEGORIES. ' WHERE cat_type = \'ROL\' ';
$result_orga = $gDb->query($sql);
$row_cat = $gDb->fetch_array($result_orga);

// neue Kategorie fuer Terminbestaetigungen
$sql = 'INSERT INTO '. TBL_CATEGORIES. ' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                                  VALUES (NULL, \'ROL\', \'CONFIRMATION_OF_PARTICIPATION\', \''.$gL10n->get('SYS_CONFIRMATION_OF_PARTICIPATION').'\', 1, 1, '.$row_cat['sequence'].', '.$row_webmaster['webmaster_id'].',\''. DATETIME_NOW.'\')';
$gDb->query($sql);

// Daten pro Organisation wegschreiben
$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS. ' ORDER BY org_id DESC';
$result_orga = $gDb->query($sql);

while($row_orga = $gDb->fetch_array($result_orga))
{
    // ID eines Webmasters ermitteln
    $sql = 'SELECT min(mem_usr_id) as webmaster_id
              FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
             WHERE cat_org_id = '. $row_orga['org_id']. '
               AND rol_cat_id = cat_id
               AND rol_name   = \'Webmaster\'
               AND mem_rol_id = rol_id ';
    $result = $gDb->query($sql);
    $row_webmaster = $gDb->fetch_array($result);

    $sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_usr_id_create = '. $row_webmaster['webmaster_id']. '
                                           , usf_timestamp_create = \''.DATETIME_NOW.'\'';
    $gDb->query($sql);

    $sql = 'UPDATE '. TBL_CATEGORIES. ' SET cat_usr_id_create = '. $row_webmaster['webmaster_id']. '
                                          , cat_timestamp_create = \''.DATETIME_NOW.'\'';
    $gDb->query($sql);
}

// Datenstruktur nach Update anpassen
$sql = 'ALTER TABLE '. TBL_USER_FIELDS. ' MODIFY COLUMN usf_name_intern varchar(110) NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '. TBL_USER_FIELDS. ' MODIFY COLUMN usf_timestamp_create datetime NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '. TBL_CATEGORIES. ' MODIFY COLUMN cat_name_intern varchar(110) NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '. TBL_CATEGORIES. ' MODIFY COLUMN cat_timestamp_create datetime NOT NULL ';
$gDb->query($sql);
