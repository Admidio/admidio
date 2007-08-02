<?php
/******************************************************************************
 * Datenkonvertierung fuer die Version 1.5
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


// E-Mail-Flags bei Rolle Webmaster per Default setzen, damit immer eine Rolle in Mail vorhanden ist
$sql = "UPDATE ". TBL_ROLES. " SET rol_mail_login  = 1
                                 , rol_mail_logout = 1
         WHERE rol_name = 'Webmaster' ";
$db->query($sql);

// Allgemeine Kategorien anlegen
$sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_system, cat_sequence)
                                  VALUES (NULL, 'USF', 'Stammdaten', 0, 1, 0)";
$db->query($sql);
$cat_id_stammdaten = $db->insert_id();

$sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_system, cat_sequence)
                                  VALUES (NULL, 'USF', 'Messenger', 0, 1, 1)";
$db->query($sql);
$cat_id_messenger = $db->insert_id();

// neue Userfelder anlegen
$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_mandatory, usf_disabled, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Nachname', 1, 1, 0, 1) ";
$db->query($sql);
$usf_id_last_name = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_mandatory, usf_disabled, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Vorname', 1, 1, 0, 2) ";
$db->query($sql);
$usf_id_first_name = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Adresse', 1, 3) ";
$db->query($sql);
$usf_id_address = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'PLZ', 1, 4) ";
$db->query($sql);
$usf_id_zip_code = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Ort', 1, 5) ";
$db->query($sql);
$usf_id_city = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Land', 1, 6) ";
$db->query($sql);
$usf_id_country = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Telefon', 1, 7) ";
$db->query($sql);
$usf_id_phone = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Handy', 1, 8) ";
$db->query($sql);
$usf_id_mobile = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'TEXT', 'Fax', 1, 9) ";
$db->query($sql);
$usf_id_fax = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'DATE', 'Geburtstag', 1, 10) ";
$db->query($sql);
$usf_id_birthday = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'NUMERIC', 'Geschlecht', 1, 11) ";
$db->query($sql);
$usf_id_gender = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_description, usf_system, usf_mandatory, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'EMAIL',  'E-Mail', 'Es muss eine g&uuml;ltige E-Mail-Adresse angegeben werden.<br />' + 
                                                               'Ohne diese kann das Programm nicht genutzt werden.', 1, 1, 12) ";
$db->query($sql);
$usf_id_email = $db->insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_cat_id, usf_type, usf_name, usf_system, usf_sequence)
                                   VALUES ($cat_id_stammdaten, 'URL',     'Homepage', 1, 13) ";
$db->query($sql);
$usf_id_homepage = $db->insert_id();

// Userdaten in adm_user_fields kopieren
$sql = "SELECT * FROM ". TBL_USERS;
$result_usr = $db->query($sql);

while($row_usr = $db->fetch_object($result_usr))
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
    $result = $db->query($sql);
}

// Daten bereinigen
$sql = "DELETE FROM ". TBL_USER_DATA. " WHERE LENGTH(usd_value) = 0 ";
$db->query($sql);

$sql = "UPDATE ". TBL_USER_DATA. " SET usd_value = CONCAT('http://', usd_value)
         WHERE usd_usf_id = $usf_id_homepage
           AND LOCATE('http', usd_value) = 0 ";
$db->query($sql);

$sql = "UPDATE ". TBL_ROLES. " SET rol_approve_users = 1
         WHERE rol_assign_roles = 1 ";
$db->query($sql);

// Orga-spezifische Kategorie anlegen
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
$result_orga = $db->query($sql);

while($row_orga = $db->fetch_object($result_orga))
{
    
    $sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                      VALUES ($row_orga->org_id, 'USF', '". utf8_decode('Zusätzliche Daten'). "', 0, 2)";
    $db->query($sql);
    $cat_id_data = $db->insert_id();

    // Systemeinstellungen anlegen
    $sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = '0'
             WHERE prf_name = 'lists_roles_per_page' ";
    $db->query($sql);

    $sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = '0'
             WHERE prf_name = 'lists_members_per_page' ";
    $db->query($sql);

    $sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = 'main.css'
             WHERE prf_name = 'user_css' ";
    $db->query($sql);

    $sql = "UPDATE ". TBL_USER_FIELDS. " SET usf_cat_id = $cat_id_data
             WHERE usf_org_shortname = '$row_orga->org_shortname' ";
    $db->query($sql);
}

// Messenger-Felder aktualisieren
$sql = "UPDATE ". TBL_USER_FIELDS. " SET usf_cat_id = $cat_id_messenger
                                       , usf_type   = 'TEXT'
                                       , usf_system = 1
         WHERE usf_type = 'MESSENGER' ";
$db->query($sql);

// usf_shortname nun loeschen
$sql = "ALTER TABLE ". TBL_USER_FIELDS. " DROP COLUMN usf_org_shortname ";
$db->query($sql);

$sql = "ALTER TABLE ". TBL_USER_FIELDS. " CHANGE COLUMN `usf_cat_id` `usf_cat_id` int(11) unsigned not null ";
$db->query($sql);

// Orga-Felder zur Sortierung durchnummerieren
$sql = "SELECT * FROM ". TBL_USER_FIELDS. " 
         WHERE usf_sequence = 0 
         ORDER BY usf_cat_id, usf_name ";
$result_usf = $db->query($sql);
$cat_id_merker = 0;
$sequence      = 1;

while($row_usf = $db->fetch_array($result_usf))
{
    if($row_usf['usf_cat_id'] != $cat_id_merker)
    {
        $sequence = 1;
        $cat_id_merker = $row_usf['usf_cat_id'];
    }
    $sql = "UPDATE ". TBL_USER_FIELDS. " SET usf_sequence = $sequence 
             WHERE usf_id = ". $row_usf['usf_id'];
    $db->query($sql);
    
    $sequence++;
}

// Reihenfolgenummern bei den Kategorien anlegen (USF existiert schon)
$sql = "SELECT * FROM ". TBL_CATEGORIES. " 
         WHERE cat_sequence = 0 
           AND cat_type    <> 'USF'
         ORDER BY cat_type, cat_org_id, cat_name ";
$result_cat = $db->query($sql);
$type_merker   = "";
$org_id_merker = 0;
$sequence      = 1;

while($row_cat = $db->fetch_array($result_cat))
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
    $db->query($sql);
    
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
$db->query($sql);

?>