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
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// Allgemeine Kategorien anlegen
$sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden)
                                  VALUES (NULL, 'USR', 'Stammdaten', 0)";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$cat_id_stammdaten = mysql_insert_id();

$sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden)
                                  VALUES (NULL, 'USR', 'Messenger', 0)";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$cat_id_messenger = mysql_insert_id();

// neue Userfelder anlegen
$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_cat_id, usf_type, usf_name, usf_system)
                                   VALUES (NULL, $cat_id_stammdaten, 'TEXT', 'Adresse', 1) ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$usf_id_address = mysql_insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_cat_id, usf_type, usf_name, usf_system)
                                   VALUES (NULL, $cat_id_stammdaten, 'TEXT', 'PLZ', 1) ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$usf_id_zip_code = mysql_insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_cat_id, usf_type, usf_name, usf_system)
                                   VALUES (NULL, $cat_id_stammdaten, 'TEXT', 'Ort', 1) ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$usf_id_city = mysql_insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_cat_id, usf_type, usf_name, usf_system)
                                   VALUES (NULL, $cat_id_stammdaten, 'TEXT', 'Land', 1) ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$usf_id_country = mysql_insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_cat_id, usf_type, usf_name, usf_system)
                                   VALUES (NULL, $cat_id_stammdaten, 'TEXT', 'Telefon', 1) ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$usf_id_phone = mysql_insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_cat_id, usf_type, usf_name, usf_system)
                                   VALUES (NULL, $cat_id_stammdaten, 'TEXT', 'Handy', 1) ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$usf_id_mobile = mysql_insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_cat_id, usf_type, usf_name, usf_system)
                                   VALUES (NULL, $cat_id_stammdaten, 'TEXT', 'Fax', 1) ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$usf_id_fax = mysql_insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_cat_id, usf_type, usf_name, usf_system)
                                   VALUES (NULL, $cat_id_stammdaten, 'DATE', 'Geburtstag', 1) ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$usf_id_birthday = mysql_insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_cat_id, usf_type, usf_name, usf_system)
                                   VALUES (NULL, $cat_id_stammdaten, 'NUMERIC', 'Geschlecht', 1) ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$usf_id_gender = mysql_insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_cat_id, usf_type, usf_name, usf_system)
                                   VALUES (NULL, $cat_id_stammdaten, 'E-Mail',  'E-Mail', 1) ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$usf_id_email = mysql_insert_id();

$sql = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_cat_id, usf_type, usf_name, usf_system)
                                   VALUES (NULL, $cat_id_stammdaten, 'URL',     'Homepage', 1) ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$usf_id_homepage = mysql_insert_id();

// Userdaten in adm_user_fields kopieren
$sql = "SELECT * FROM ". TBL_USERS;
$result_usr = mysql_query($sql, $connection);
if(!$result_usr) showError(mysql_error());

while($row_usr = mysql_fetch_object($result_usr))
{
    $sql = "INSERT INTO ". TBL_USER_DATA. " (usd_usr_id, usd_usf_id, usd_value)
                                     VALUES ($row_usr->usr_id, $usf_id_address, '$row_usr->usr_address')
                                          , ($row_usr->usr_id, $usf_id_zip_code, '$row_usr->usr_zip_code')
                                          , ($row_usr->usr_id, $usf_id_city, '$row_usr->usr_city')
                                          , ($row_usr->usr_id, $usf_id_country, '$row_usr->usr_country')
                                          , ($row_usr->usr_id, $usf_id_phone, '$row_usr->usr_phone')
                                          , ($row_usr->usr_id, $usf_id_mobile, '$row_usr->usr_mobile')
                                          , ($row_usr->usr_id, $usf_id_fax, '$row_usr->usr_fax')
                                          , ($row_usr->usr_id, $usf_id_birthday, '$row_usr->usr_birthday')
                                          , ($row_usr->usr_id, $usf_id_gender, '$row_usr->usr_gender')
                                          , ($row_usr->usr_id, $usf_id_email, '$row_usr->usr_email')
                                          , ($row_usr->usr_id, $usf_id_homepage, '$row_usr->usr_homepage') ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());    
}

// neue Systemeinstellungen anlegen
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
$result_orga = mysql_query($sql, $connection);
if(!$result_orga) showError(mysql_error());

while($row_orga = mysql_fetch_object($result_orga))
{
    // Orga-spezifische Kategorie anlegen
    $sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden)
                                      VALUES ($row_orga->org_id, 'USR', '". utf8_decode('Zusätzliche Daten'). "', 0)";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
    $cat_id_data = mysql_insert_id();

    // Systemeinstellungen anlegen
    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'lists_members_per_page', '0')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'user_css', 'main.css')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'system_align', 'center')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "UPDATE ". TBL_USER_FIELDS. " SET usf_org_id = $row_orga->org_id
                                           , usf_cat_id = $cat_id_data
             WHERE usf_org_shortname = '$row_orga->org_shortname' ";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
}

// Messenger-Felder aktualisieren
$sql = "UPDATE ". TBL_USER_FIELDS. " SET usf_cat_id = $cat_id_messenger
                                       , usf_type   = 'TEXT'
                                       , usf_system = 1
         WHERE usf_type = 'MESSENGER' ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

// usf_shortname nun loeschen
$sql = "ALTER TABLE ". TBL_USER_FIELDS. " DROP COLUMN usf_org_shortname ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

$sql = "ALTER TABLE ". TBL_USER_FIELDS. " CHANGE COLUMN `usf_cat_id` `usf_cat_id` int(11) unsigned not null ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

?>