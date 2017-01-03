<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.2.0
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// eine Orga-ID einlesen
$sql = 'SELECT MIN(org_id) AS org_id FROM '.TBL_ORGANIZATIONS.' ORDER BY org_id DESC';
$orgaStatement = $gDb->query($sql);
$rowOrga = $orgaStatement->fetch();

// die Erstellungs-ID mit Webmaster befuellen, damit das Feld auf NOT NULL gesetzt werden kann
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

// Defaultraum fuer Raummodul in der DB anlegen:
$sql = 'INSERT INTO '.TBL_ROOMS.' (room_name, room_description, room_capacity, room_usr_id_create, room_timestamp_create)
                                VALUES (\'Besprechnungsraum\', \'Hier können Besprechungen stattfinden. Der Raum muss vorher
                                         reserviert werden. Ein Beamer steht zur Verfügung.\', 15, '.
                                         $rowWebmaster['webmaster_id'].',\''. DATETIME_NOW.'\')';
$gDb->query($sql);

// interner Name für System-Profilfelder belegen
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'LAST_NAME\' WHERE usf_name = \'Nachname\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'FIRST_NAME\' WHERE usf_name = \'Vorname\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'ADDRESS\' WHERE usf_name = \'Adresse\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'POSTCODE\' WHERE usf_name = \'PLZ\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'CITY\' WHERE usf_name = \'Ort\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'COUNTRY\' WHERE usf_name = \'Land\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'PHONE\', usf_system = 0 WHERE usf_name = \'Telefon\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'MOBILE\', usf_system = 0 WHERE usf_name = \'Handy\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'FAX\', usf_system = 0 WHERE usf_name = \'Fax\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'GENDER\' WHERE usf_name = \'Geschlecht\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'BIRTHDAY\' WHERE usf_name = \'Geburtstag\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'EMAIL\' WHERE usf_name = \'E-Mail\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'WEBSITE\', usf_name = \'Webseite\' WHERE usf_name = \'Homepage\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'AOL_INSTANT_MESSENGER\' WHERE usf_name = \'AIM\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'GOOGLE_TALK\' WHERE usf_name = \'Google Talk\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'ICQ\' WHERE usf_name = \'ICQ\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'MSN_MESSENGER\' WHERE usf_name = \'MSN\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'SKYPE\' WHERE usf_name = \'Skype\' ';
$gDb->query($sql);
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \'YAHOO_MESSENGER\' WHERE usf_name = \'Yahoo\' ';
$gDb->query($sql);

// interne Name bei allen anderen Feldern fuellen
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = UPPER(usf_name) WHERE usf_name_intern IS NULL ';
$gDb->query($sql);

// E-Mail-Adresse darf jetzt nur noch klein geschrieben werden
$sql = 'UPDATE '.TBL_USER_DATA.' SET usd_value = LOWER(usd_value)
         WHERE usd_value IS NOT NULL
           AND usd_usf_id IN (SELECT usf_id FROM '.TBL_USER_FIELDS.'
                               WHERE usf_type = \'EMAIL\') ';
$gDb->query($sql);

$sql = 'UPDATE '.TBL_GUESTBOOK.' SET gbo_email = LOWER(gbo_email)
         WHERE gbo_email IS NOT NULL ';
$gDb->query($sql);

$sql = 'UPDATE '.TBL_GUESTBOOK_COMMENTS.' SET gbc_email = LOWER(gbc_email)
         WHERE gbc_email IS NOT NULL ';
$gDb->query($sql);

$sql = 'UPDATE '.TBL_PREFERENCES.' SET prf_value = LOWER(prf_value)
         WHERE prf_name IN (\'email_administrator\', \'mail_sendmail_address\') ';
$gDb->query($sql);

// interner Name für System-Kategorien belegen
$sql = 'UPDATE '.TBL_CATEGORIES.' SET cat_name_intern = \'MASTER_DATA\' WHERE cat_name = \'Stammdaten\' ';
$gDb->query($sql);

// interne Name bei allen anderen Feldern fuellen
$sql = 'UPDATE '.TBL_CATEGORIES.' SET cat_name_intern = UPPER(cat_name) WHERE cat_name_intern IS NULL ';
$gDb->query($sql);

// Defaulteintraege fuer alle existierenden Termine bei der Rollenzuordnung
$sql = 'INSERT INTO '.TBL_DATE_ROLE.' (dtr_dat_id, dtr_rol_id)
        SELECT dat_id, NULL FROM '.TBL_DATES;
$gDb->query($sql);

// Max. Rol-Kategorien-Sequenz einlesen
$sql = 'SELECT MAX(cat_sequence) AS sequence FROM '.TBL_CATEGORIES.' WHERE cat_type = \'ROL\' ';
$orgaStatement = $gDb->query($sql);
$rowCat = $orgaStatement->fetch();

// neue Kategorie fuer Terminbestaetigungen
$sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                                VALUES (NULL, \'ROL\', \'CONFIRMATION_OF_PARTICIPATION\', \''.$gL10n->get('SYS_CONFIRMATION_OF_PARTICIPATION').'\', 1, 1, '.$rowCat['sequence'].', '.$rowWebmaster['webmaster_id'].',\''. DATETIME_NOW.'\')';
$gDb->query($sql);

// Daten pro Organisation wegschreiben
$sql = 'SELECT * FROM '.TBL_ORGANIZATIONS.' ORDER BY org_id DESC';
$orgaStatement = $gDb->query($sql);

while($rowOrga = $orgaStatement->fetch())
{
    // ID eines Webmasters ermitteln
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

    $sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_usr_id_create = '. $rowWebmaster['webmaster_id']. '
                                         , usf_timestamp_create = \''.DATETIME_NOW.'\'';
    $gDb->query($sql);

    $sql = 'UPDATE '.TBL_CATEGORIES.' SET cat_usr_id_create = '. $rowWebmaster['webmaster_id']. '
                                        , cat_timestamp_create = \''.DATETIME_NOW.'\'';
    $gDb->query($sql);
}

// Datenstruktur nach Update anpassen
$sql = 'ALTER TABLE '.TBL_USER_FIELDS.' MODIFY COLUMN usf_name_intern varchar(110) NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '.TBL_USER_FIELDS.' MODIFY COLUMN usf_timestamp_create datetime NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '.TBL_CATEGORIES.' MODIFY COLUMN cat_name_intern varchar(110) NOT NULL ';
$gDb->query($sql);

$sql = 'ALTER TABLE '.TBL_CATEGORIES.' MODIFY COLUMN cat_timestamp_create datetime NOT NULL ';
$gDb->query($sql);
