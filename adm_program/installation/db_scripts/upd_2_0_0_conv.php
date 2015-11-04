<?php
/******************************************************************************
 * Data conversion for version 2.0
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/


// E-Mail-Flags bei Rolle Webmaster per Default setzen, damit immer eine Rolle in Mail vorhanden ist
///ausserdemm sollte er automatisch mit dem Recht ausgestattet werden alle Listen einzusehen
$sql = "UPDATE ". TBL_ROLES. " SET rol_mail_login  = 1
                                 , rol_mail_logout = 1
                                 , rol_all_lists_view = 1
         WHERE rol_name = 'Webmaster' ";
$gDb->query($sql);


// Allgemeine Kategorien anlegen
$sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_system, cat_sequence)
                                  VALUES (NULL, 'USF', 'Stammdaten', 0, 1, 0)";
$gDb->query($sql);
$cat_id_stammdaten = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_system, cat_sequence)
                                  VALUES (NULL, 'USF', 'Messenger', 0, 0, 1)";
$gDb->query($sql);
$cat_id_messenger = $gDb->lastInsertId();

// neue Userfelder anlegen
$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_mandatory, usf_disabled, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Nachname', 1, 1, 0, 1) ";
$gDb->query($sql);
$usf_id_last_name = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_mandatory, usf_disabled, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Vorname', 1, 1, 0, 2) ";
$gDb->query($sql);
$usf_id_first_name = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Adresse', 1, 3) ";
$gDb->query($sql);
$usf_id_address = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'PLZ', 1, 4) ";
$gDb->query($sql);
$usf_id_zip_code = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Ort', 1, 5) ";
$gDb->query($sql);
$usf_id_city = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Land', 1, 6) ";
$gDb->query($sql);
$usf_id_country = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Telefon', 1, 7) ";
$gDb->query($sql);
$usf_id_phone = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Handy', 1, 8) ";
$gDb->query($sql);
$usf_id_mobile = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Fax', 1, 9) ";
$gDb->query($sql);
$usf_id_fax = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'DATE', 'Geburtstag', 1, 10) ";
$gDb->query($sql);
$usf_id_birthday = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'NUMERIC', 'Geschlecht', 1, 11) ";
$gDb->query($sql);
$usf_id_gender = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_description, usf_system, usf_mandatory, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'EMAIL',  'E-Mail', 'Es muss eine gültige E-Mail-Adresse angegeben werden.<br />Ohne diese kann das Programm nicht genutzt werden.', 1, 1, 12) ";
$gDb->query($sql);
$usf_id_email = $gDb->lastInsertId();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'URL',     'Homepage', 1, 13) ";
$gDb->query($sql);
$usf_id_homepage = $gDb->lastInsertId();


// Termine auf "ganztaegig" konvertieren
$sql = "UPDATE ". TBL_DATES. " SET dat_all_day = 1
         WHERE date_format(dat_begin, '%H:%i:%s') = '00:00:00'
           AND date_format(dat_end, '%H:%i:%s') = '00:00:00' ";
$gDb->query($sql);

$sql = "UPDATE ". TBL_DATES. " SET dat_end = date_add(dat_end, interval 1 day)
         WHERE dat_all_day = 1 ";
$gDb->query($sql);

$sql = "DELETE FROM ". TBL_DATES. "
         WHERE dat_begin = '0000-00-00 00:00:00' ";
$gDb->query($sql);

// Userdaten in adm_user_fields kopieren
$sql = "SELECT * FROM ". TBL_USERS;
$result_usr = $gDb->query($sql);

while($row_usr = $gDb->fetch_object($result_usr))
{
    $sql = "INSERT INTO ". TBL_USER_DATA. " (usd_usr_id, usd_usf_id, usd_value)
                                     VALUES ($row_usr->usr_id, $usf_id_last_name, '". addslashes($row_usr->usr_last_name). "')
                                          , ($row_usr->usr_id, $usf_id_first_name, '". addslashes($row_usr->usr_first_name). "')
                                          , ($row_usr->usr_id, $usf_id_address, '". addslashes($row_usr->usr_address). "')
                                          , ($row_usr->usr_id, $usf_id_zip_code, '". addslashes($row_usr->usr_zip_code). "')
                                          , ($row_usr->usr_id, $usf_id_city, '". addslashes($row_usr->usr_city). "')
                                          , ($row_usr->usr_id, $usf_id_country, '". addslashes($row_usr->usr_country). "')
                                          , ($row_usr->usr_id, $usf_id_phone, '". addslashes($row_usr->usr_phone). "')
                                          , ($row_usr->usr_id, $usf_id_mobile, '". addslashes($row_usr->usr_mobile). "')
                                          , ($row_usr->usr_id, $usf_id_fax, '". addslashes($row_usr->usr_fax). "')
                                          , ($row_usr->usr_id, $usf_id_birthday, '". addslashes($row_usr->usr_birthday). "')
                                          , ($row_usr->usr_id, $usf_id_gender, '". addslashes($row_usr->usr_gender). "')
                                          , ($row_usr->usr_id, $usf_id_email, '". addslashes($row_usr->usr_email). "')
                                          , ($row_usr->usr_id, $usf_id_homepage, '". addslashes($row_usr->usr_homepage). "') ";
    $result = $gDb->query($sql);
}

// Daten bereinigen
$sql = "DELETE FROM ". TBL_USER_DATA. " WHERE LENGTH(usd_value) = 0 ";
$gDb->query($sql);

$sql = "UPDATE ". TBL_USER_DATA. " SET usd_value = CONCAT('http://', usd_value)
         WHERE usd_usf_id = $usf_id_homepage
           AND LOCATE('http', usd_value) = 0 ";
$gDb->query($sql);

$sql = "UPDATE ". TBL_ROLES. " SET rol_approve_users  = 1
                                 , rol_all_lists_view = 1
         WHERE rol_assign_roles = 1 ";
$gDb->query($sql);

// Orga-spezifische Kategorie anlegen
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
$result_orga = $gDb->query($sql);

while($row_orga = $gDb->fetch_object($result_orga))
{
    if($gDb->num_rows($result_orga) > 1)
    {
        $sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                          VALUES ($row_orga->org_id, 'USF', 'Zusätzliche Daten', 0, 2)";
    }
    else
    {
        $sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                          VALUES (NULL, 'USF', 'Zusätzliche Daten', 0, 2)";
    }
    $gDb->query($sql);
    $cat_id_data = $gDb->lastInsertId();

    // Systemeinstellungen anlegen
    $sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = '0'
             WHERE prf_name = 'lists_roles_per_page' ";
    $gDb->query($sql);

    $sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = '0'
             WHERE prf_name = 'lists_members_per_page' ";
    $gDb->query($sql);

    $sql = "UPDATE ". TBL_USER_FIELDS. " SET usf_cat_id = $cat_id_data
             WHERE usf_org_shortname = '$row_orga->org_shortname' ";
    $gDb->query($sql);

    // Datenbank-Versionsnummer schreiben
    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                                       VALUES ($row_orga->org_id, 'db_version', '2.0.0') ";
    $gDb->query($sql);

    //Fuer das neue Downloadmodul wird der Root-Ordner in die DB eingetragen
    $sql = "INSERT INTO ". TBL_FOLDERS. " (fol_org_id, fol_type, fol_name, fol_path,
                                           fol_locked, fol_public, fol_timestamp)
                                   VALUES ($row_orga->org_id, 'DOWNLOAD', 'download', '/adm_my_files',
                                            0,1,'".date("Y-m-d h:i:s", time())."')";
    $gDb->query($sql);
}

// Messenger-Felder aktualisieren
$sql = "UPDATE ". TBL_USER_FIELDS. " SET usf_cat_id = $cat_id_messenger
                                       , usf_type   = 'TEXT'
                                       , usf_system = 0
         WHERE usf_type = 'MESSENGER' ";
$gDb->query($sql);

// usf_shortname nun loeschen
$sql = "ALTER TABLE ". TBL_USER_FIELDS. " DROP COLUMN usf_org_shortname ";
$gDb->query($sql);

$sql = "ALTER TABLE ". TBL_USER_FIELDS. " CHANGE COLUMN `usf_cat_id` `usf_cat_id` int(11) unsigned not null ";
$gDb->query($sql);

// Orga-Felder zur Sortierung durchnummerieren
$sql = "SELECT * FROM ". TBL_USER_FIELDS. "
         WHERE usf_sequence = 0
         ORDER BY usf_cat_id, usf_name ";
$result_usf = $gDb->query($sql);
$cat_id_merker = 0;
$sequence      = 1;

while($row_usf = $gDb->fetch_array($result_usf))
{
    if($row_usf['usf_cat_id'] != $cat_id_merker)
    {
        $sequence = 1;
        $cat_id_merker = $row_usf['usf_cat_id'];
    }
    $sql = "UPDATE ". TBL_USER_FIELDS. " SET usf_sequence = $sequence
             WHERE usf_id = ". $row_usf['usf_id'];
    $gDb->query($sql);

    $sequence++;
}

// Reihenfolgenummern bei den Kategorien anlegen (USF existiert schon)
$sql = "SELECT * FROM ". TBL_CATEGORIES. "
         WHERE cat_sequence = 0
           AND cat_type    <> 'USF'
         ORDER BY cat_type, cat_org_id, cat_name ";
$result_cat = $gDb->query($sql);
$type_merker   = "";
$org_id_merker = 0;
$sequence      = 1;

while($row_cat = $gDb->fetch_array($result_cat))
{
    if($row_cat['cat_org_id'] != $org_id_merker
    || $row_cat['cat_type']   != $type_merker)
    {
        $sequence = 1;
        $org_id_merker = $row_cat['cat_org_id'];
        $type_merker = $row_cat['cat_type'];
    }
    $sql = "UPDATE ". TBL_CATEGORIES. " SET cat_sequence = $sequence
             WHERE cat_id = ". $row_cat['cat_id'];
    $gDb->query($sql);

    $sequence++;
}

// alte User-Felder aus adm_users entfernen
$sql = "ALTER TABLE ". TBL_USERS. " DROP COLUMN `usr_last_name`,
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
         DROP COLUMN `usr_homepage` ";
$gDb->query($sql);
