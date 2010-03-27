<?php
/******************************************************************************
 * Datenkonvertierung fuer die Version 2.2.0
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// eine Orga-ID einlesen
$sql = 'SELECT MIN(org_id) as org_id FROM '. TBL_ORGANIZATIONS. ' ORDER BY org_id DESC';
$result_orga = $g_db->query($sql);
$row_orga = $g_db->fetch_array($result_orga);

// die Erstellungs-ID mit Webmaster befuellen, damit das Feld auf NOT NULL gesetzt werden kann
$sql = 'SELECT min(mem_usr_id) as webmaster_id
          FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
         WHERE cat_org_id = '. $row_orga['org_id']. '
           AND rol_cat_id = cat_id
           AND rol_name   = "Webmaster"
           AND mem_rol_id = rol_id ';
$result = $g_db->query($sql);
$row_webmaster = $g_db->fetch_array($result);

//Defaultraum fuer Raummodul in der DB anlegen:
$sql = 'INSERT INTO '. TBL_ROOMS. ' (room_name, room_description, room_capacity, room_usr_id_create, room_timestamp_create)
                                VALUES ("Besprechnungsraum", "Hier können Besprechungen stattfinden. Der Raum muss vorher
                                         reserviert werden. Ein Beamer steht zur Verfügung.", 15, '.
                                         $row_webmaster['webmaster_id'].',"'. DATETIME_NOW.'")';
$g_db->query($sql);

// interner Name für System-Profilfelder belegen
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "LAST_NAME" WHERE usf_name = "Nachname" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "FIRST_NAME" WHERE usf_name = "Vorname" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "ADDRESS" WHERE usf_name = "Adresse" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "POSTCODE" WHERE usf_name = "PLZ" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "CITY" WHERE usf_name = "Ort" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "COUNTRY" WHERE usf_name = "Land" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "PHONE", usf_system = 0 WHERE usf_name = "Telefon" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "MOBILE", usf_system = 0 WHERE usf_name = "Handy" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "FAX", usf_system = 0 WHERE usf_name = "Fax" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "GENDER" WHERE usf_name = "Geschlecht" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "BIRTHDAY" WHERE usf_name = "Geburtstag" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "EMAIL" WHERE usf_name = "E-Mail" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "WEBSITE", usf_name = "Webseite" WHERE usf_name = "Homepage" ';
$result_orga = $g_db->query($sql);

$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "AOL_INSTANT_MESSENGER" WHERE usf_name = "AIM" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "GOOGLE_TALK" WHERE usf_name = "Google Talk" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "ICQ" WHERE usf_name = "ICQ" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "MSN_MESSENGER" WHERE usf_name = "MSN" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "SKYPE" WHERE usf_name = "Skype" ';
$result_orga = $g_db->query($sql);
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = "YAHOO_MESSENGER" WHERE usf_name = "Yahoo" ';
$result_orga = $g_db->query($sql);

// interne Name bei allen anderen Feldern fuellen
$sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_name_intern = UPPER(usf_name) WHERE usf_name_intern IS NULL ';
$result_orga = $g_db->query($sql);

// Defaulteintraege fuer alle existierenden Termine bei der Rollenzuordnung
$sql = 'INSERT INTO '. TBL_DATE_ROLE. ' (dtr_dat_id, dtr_rol_id)
        SELECT dat_id, NULL FROM '. TBL_DATES;
$g_db->query($sql);

// Daten pro Organisation wegschreiben
$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS. ' ORDER BY org_id DESC';
$result_orga = $g_db->query($sql);

while($row_orga = $g_db->fetch_array($result_orga))
{
    // ID eines Webmasters ermitteln
    $sql = 'SELECT min(mem_usr_id) as webmaster_id
              FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
             WHERE cat_org_id = '. $row_orga['org_id']. '
               AND rol_cat_id = cat_id
               AND rol_name   = "Webmaster"
               AND mem_rol_id = rol_id ';
    $result = $g_db->query($sql);
    $row_webmaster = $g_db->fetch_array($result);

    $sql = 'UPDATE '. TBL_USER_FIELDS. ' SET usf_usr_id_create = '. $row_webmaster['webmaster_id']. '
                                           , usf_timestamp_create = "'.DATETIME_NOW.'"';
    $g_db->query($sql);
}

// Datenstruktur nach Update anpassen
$sql = "ALTER TABLE ". TBL_USER_FIELDS. " MODIFY COLUMN usf_name_intern varchar(110) NOT NULL ";
$g_db->query($sql);

$sql = "ALTER TABLE ". TBL_USER_FIELDS. " MODIFY COLUMN usf_timestamp_create datetime NOT NULL ";
$g_db->query($sql);

?>