<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.0
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// E-Mail-Flags bei Rolle Webmaster per Default setzen, damit immer eine Rolle in Mail vorhanden ist
// ausserdemm sollte er automatisch mit dem Recht ausgestattet werden alle Listen einzusehen
$sql = 'UPDATE '.TBL_ROLES.' SET rol_mail_login  = 1
                               , rol_mail_logout = 1
                               , rol_all_lists_view = 1
         WHERE rol_name = \'Webmaster\'';
$gDb->query($sql);

// Allgemeine Kategorien anlegen
$sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name, cat_hidden, cat_system, cat_sequence)
                                VALUES (NULL, \'USF\', \'Stammdaten\', 0, 1, 0)';
$gDb->query($sql);
$catIdStammdaten = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name, cat_hidden, cat_system, cat_sequence)
                                VALUES (NULL, \'USF\', \'Messenger\', 0, 0, 1)';
$gDb->query($sql);
$catIdMessenger = $gDb->lastInsertId();

// neue Userfelder anlegen
$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_mandatory, usf_disabled, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'TEXT\', \'Nachname\', 1, 1, 0, 1)';
$gDb->query($sql);
$usfIdLastName = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_mandatory, usf_disabled, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'TEXT\', \'Vorname\', 1, 1, 0, 2)';
$gDb->query($sql);
$usfIdFirstName = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'TEXT\', \'Adresse\', 1, 3)';
$gDb->query($sql);
$usfIdAddress = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'TEXT\', \'PLZ\', 1, 4)';
$gDb->query($sql);
$usfIdZipCode = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'TEXT\', \'Ort\', 1, 5)';
$gDb->query($sql);
$usfIdCity = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'TEXT\', \'Land\', 1, 6)';
$gDb->query($sql);
$usfIdCountry = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'TEXT\', \'Telefon\', 1, 7)';
$gDb->query($sql);
$usfIdPhone = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'TEXT\', \'Handy\', 1, 8)';
$gDb->query($sql);
$usfIdMobile = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'TEXT\', \'Fax\', 1, 9)';
$gDb->query($sql);
$usfIdFax = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'DATE\', \'Geburtstag\', 1, 10)';
$gDb->query($sql);
$usfIdBirthday = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'NUMERIC\', \'Geschlecht\', 1, 11)';
$gDb->query($sql);
$usfIdGender = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_description, usf_system, usf_mandatory, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'EMAIL\', \'E-Mail\', \'Es muss eine gültige E-Mail-Adresse angegeben werden.<br />Ohne diese kann das Programm nicht genutzt werden.\', 1, 1, 12)';
$gDb->query($sql);
$usfIdEmail = $gDb->lastInsertId();

$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                 VALUES ('.$catIdStammdaten.', \'URL\', \'Homepage\', 1, 13)';
$gDb->query($sql);
$usfIdHomepage = $gDb->lastInsertId();

// Termine auf "ganztaegig" konvertieren
$sql = 'UPDATE '.TBL_DATES.' SET dat_all_day = 1
         WHERE date_format(dat_begin, \'%H:%i:%s\') = \'00:00:00\'
           AND date_format(dat_end,   \'%H:%i:%s\') = \'00:00:00\'';
$gDb->query($sql);

$sql = 'UPDATE '.TBL_DATES.' SET dat_end = date_add(dat_end, interval 1 day)
         WHERE dat_all_day = 1';
$gDb->query($sql);

$sql = 'DELETE FROM '.TBL_DATES.'
         WHERE dat_begin = \'0000-00-00 00:00:00\'';
$gDb->query($sql);

// Userdaten in adm_user_fields kopieren
$sql = 'SELECT * FROM '.TBL_USERS;
$usrStatement = $gDb->query($sql);

while($rowUsr = $usrStatement->fetchObject())
{
    $sql = 'INSERT INTO '.TBL_USER_DATA.' (usd_usr_id, usd_usf_id, usd_value)
                                   VALUES ('.$rowUsr->usr_id.', '.$usfIdLastName.',  \''.addslashes($rowUsr->usr_last_name).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdFirstName.', \''.addslashes($rowUsr->usr_first_name).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdAddress.',   \''.addslashes($rowUsr->usr_address).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdZipCode.',   \''.addslashes($rowUsr->usr_zip_code).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdCity.',      \''.addslashes($rowUsr->usr_city).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdCountry.',   \''.addslashes($rowUsr->usr_country).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdPhone.',     \''.addslashes($rowUsr->usr_phone).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdMobile.',    \''.addslashes($rowUsr->usr_mobile).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdFax.',       \''.addslashes($rowUsr->usr_fax).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdBirthday.',  \''.addslashes($rowUsr->usr_birthday).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdGender.',    \''.addslashes($rowUsr->usr_gender).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdEmail.',     \''.addslashes($rowUsr->usr_email).'\')
                                        , ('.$rowUsr->usr_id.', '.$usfIdHomepage.',  \''.addslashes($rowUsr->usr_homepage).'\')';
    $result = $gDb->query($sql);
}

// Daten bereinigen
$sql = 'DELETE FROM '.TBL_USER_DATA.' WHERE LENGTH(usd_value) = 0';
$gDb->query($sql);

$sql = 'UPDATE '.TBL_USER_DATA.' SET usd_value = CONCAT(\'http://\', usd_value)
         WHERE usd_usf_id = '.$usfIdHomepage.'
           AND LOCATE(\'http\', usd_value) = 0';
$gDb->query($sql);

$sql = 'UPDATE '.TBL_ROLES.' SET rol_approve_users  = 1
                               , rol_all_lists_view = 1
         WHERE rol_assign_roles = 1';
$gDb->query($sql);

// Orga-spezifische Kategorie anlegen
$sql = 'SELECT * FROM '.TBL_ORGANIZATIONS;
$orgaStatement = $gDb->query($sql);

while($rowOrga = $orgaStatement->fetchObject())
{
    if($orgaStatement->rowCount() > 1)
    {
        $sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                        VALUES ('.$rowOrga->org_id.', \'USF\', \'Zusätzliche Daten\', 0, 2)';
    }
    else
    {
        $sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                        VALUES (NULL, \'USF\', \'Zusätzliche Daten\', 0, 2)';
    }
    $gDb->query($sql);
    $cat_id_data = $gDb->lastInsertId();

    // Systemeinstellungen anlegen
    $sql = 'UPDATE '.TBL_PREFERENCES.' SET prf_value = \'0\'
             WHERE prf_name = \'lists_roles_per_page\'';
    $gDb->query($sql);

    $sql = 'UPDATE '.TBL_PREFERENCES.' SET prf_value = \'0\'
             WHERE prf_name = \'lists_members_per_page\'';
    $gDb->query($sql);

    $sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_cat_id = '.$cat_id_data.'
             WHERE usf_org_shortname = '.$rowOrga->org_shortname;
    $gDb->query($sql);

    // Datenbank-Versionsnummer schreiben
    $sql = 'INSERT INTO '.TBL_PREFERENCES.' (prf_org_id, prf_name, prf_value)
                                     VALUES ('.$rowOrga->org_id.', \'db_version\', \'2.0.0\')';
    $gDb->query($sql);

    // Fuer das neue Downloadmodul wird der Root-Ordner in die DB eingetragen
    $sql = 'INSERT INTO '.TBL_FOLDERS.' (fol_org_id, fol_type, fol_name, fol_path, fol_locked, fol_public, fol_timestamp)
                                 VALUES ('.$rowOrga->org_id.', \'DOWNLOAD\', \'download\', \'' . FOLDER_DATA . '\',
                                          0, 1, \''.date('Y-m-d h:i:s', time()).'\')';
    $gDb->query($sql);
}

// Messenger-Felder aktualisieren
$sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_cat_id = '.$catIdMessenger.'
                                     , usf_type   = \'TEXT\'
                                     , usf_system = 0
         WHERE usf_type = \'MESSENGER\'';
$gDb->query($sql);

// usf_shortname nun loeschen
$sql = 'ALTER TABLE '.TBL_USER_FIELDS.' DROP COLUMN usf_org_shortname';
$gDb->query($sql);

$sql = 'ALTER TABLE '.TBL_USER_FIELDS.' CHANGE COLUMN `usf_cat_id` `usf_cat_id` int(11) unsigned NOT NULL';
$gDb->query($sql);

// Orga-Felder zur Sortierung durchnummerieren
$sql = 'SELECT * FROM '.TBL_USER_FIELDS.'
         WHERE usf_sequence = 0
      ORDER BY usf_cat_id, usf_name';
$usfStatement = $gDb->query($sql);
$catIdMerker  = 0;
$sequence     = 1;

while($rowUsf = $usfStatement->fetch())
{
    if((int) $rowUsf['usf_cat_id'] !== $catIdMerker)
    {
        $sequence = 1;
        $catIdMerker = (int) $rowUsf['usf_cat_id'];
    }
    $sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_sequence = '.$sequence.'
             WHERE usf_id = '.$rowUsf['usf_id'];
    $gDb->query($sql);

    ++$sequence;
}

// Reihenfolgenummern bei den Kategorien anlegen (USF existiert schon)
$sql = 'SELECT * FROM '.TBL_CATEGORIES.'
         WHERE cat_sequence = 0
           AND cat_type    <> \'USF\'
      ORDER BY cat_type, cat_org_id, cat_name';
$catStatement = $gDb->query($sql);
$typeMerker   = '';
$orgIdMerker  = 0;
$sequence     = 1;

while($rowCat = $catStatement->fetch())
{
    if((int) $rowCat['cat_org_id'] !== $orgIdMerker || $rowCat['cat_type'] != $typeMerker)
    {
        $sequence = 1;
        $orgIdMerker = (int) $rowCat['cat_org_id'];
        $typeMerker  = $rowCat['cat_type'];
    }
    $sql = 'UPDATE '.TBL_CATEGORIES.' SET cat_sequence = '.$sequence.'
             WHERE cat_id = '.$rowCat['cat_id'];
    $gDb->query($sql);

    ++$sequence;
}

// alte User-Felder aus adm_users entfernen
$sql = 'ALTER TABLE '.TBL_USERS.'
        DROP COLUMN `usr_last_name`,
        DROP COLUMN `usr_first_name`,
        DROP COLUMN `usr_address`,
        DROP COLUMN `usr_zip_code`,
        DROP COLUMN `usr_city`,
        DROP COLUMN `usr_country`,
        DROP COLUMN `usr_phone`,
        DROP COLUMN `usr_mobile`,
        DROP COLUMN `usr_fax`,
        DROP COLUMN `usr_birthday`,
        DROP COLUMN `usr_gender`,
        DROP COLUMN `usr_email`,
        DROP COLUMN `usr_homepage`';
$gDb->query($sql);
