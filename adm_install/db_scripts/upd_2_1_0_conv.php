<?php
/******************************************************************************
 * Datenkonvertierung fuer die Version 2.1.0
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once("systemmails_texts.php");
require_once(SERVER_PATH. "/adm_program/system/classes/list_configuration.php");
require_once(SERVER_PATH. "/adm_program/system/classes/user.php");
 
// Texte fuer Systemmails pflegen
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
$result_orga = $g_db->query($sql);

while($row_orga = $g_db->fetch_array($result_orga))
{
    $sql = "INSERT INTO ". TBL_TEXTS. " (txt_org_id, txt_name, txt_text) 
                 VALUES (".$row_orga['org_id'].", 'SYSMAIL_REGISTRATION_USER', '".$systemmails_texts['SYSMAIL_REGISTRATION_USER']."')
                      , (".$row_orga['org_id'].", 'SYSMAIL_REGISTRATION_WEBMASTER', '".$systemmails_texts['SYSMAIL_REGISTRATION_WEBMASTER']."')
                      , (".$row_orga['org_id'].", 'SYSMAIL_NEW_PASSWORD', '".$systemmails_texts['SYSMAIL_NEW_PASSWORD']."') 
                      , (".$row_orga['org_id'].", 'SYSMAIL_ACTIVATION_LINK', '".$systemmails_texts['SYSMAIL_ACTIVATION_LINK']."') ";
    $g_db->query($sql); 

    // Default-Kategorie fuer Datum eintragen
    $sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                      VALUES (". $row_orga['org_id']. ", 'DAT', 'Allgemein', 0, 1)
                                           , (". $row_orga['org_id']. ", 'DAT', 'Kurse', 0, 1)
                                           , (". $row_orga['org_id']. ", 'DAT', 'Training', 0, 1)";
    $g_db->query($sql);
    $category_common = $g_db->insert_id();

    // Alle Termine der neuen Kategorie zuordnen
    $sql = "UPDATE ". TBL_DATES. " SET dat_cat_id = ". $category_common. "
             WHERE dat_cat_id is null
               AND dat_org_shortname LIKE '". $row_orga['org_shortname']. "'";
    $g_db->query($sql);
    
    // bei allen Alben ohne Ersteller, die Erstellungs-ID mit Webmaster befuellen, 
    // damit das Feld auf NOT NULL gesetzt werden kann
    $sql = "SELECT min(mem_usr_id) as webmaster_id
              FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
             WHERE cat_org_id = ". $row_orga['org_id']. "
               AND rol_cat_id = cat_id
               AND rol_name   = 'Webmaster'
               AND mem_rol_id = rol_id ";
    $result = $g_db->query($sql);
    $row_webmaster = $g_db->fetch_array($result);
    
    $sql = "UPDATE ". TBL_PHOTOS. " SET pho_usr_id_create = ". $row_webmaster['webmaster_id']. "
             WHERE pho_usr_id_create IS NULL 
               AND pho_org_shortname = '". $row_orga['org_shortname']."'";
    $g_db->query($sql);
    
    $sql = "SELECT * FROM ". TBL_CATEGORIES. "
             WHERE cat_org_id = ".$row_orga['org_id']. "
               AND cat_type   = 'ROL'";
    $result = $g_db->query($sql);

    $all_cat_ids = array();
    while($row_cat = $g_db->fetch_array($result))
    {
        $all_cat_ids[] = $row_cat['cat_id'];
    }
    $all_cat_str = implode(",", $all_cat_ids);
    
    // neue Rollenfelder fuellen
    $sql = "UPDATE ". TBL_ROLES. " SET rol_timestamp_create = rol_timestamp_change
                                     , rol_usr_id_create    = ". $row_webmaster['webmaster_id']. "
         WHERE rol_timestamp_change IS NOT NULL 
           AND rol_usr_id_change IS NOT NULL
           AND rol_cat_id IN (".$all_cat_str.")";
    $g_db->query($sql);

    $sql = "UPDATE ". TBL_ROLES. " SET rol_timestamp_create = '".date("Y-m-d H:i:s", time())."'
                                     , rol_usr_id_create    = ". $row_webmaster['webmaster_id']. "
         WHERE rol_timestamp_create IS NULL 
           AND rol_cat_id IN (".$all_cat_str.")";
    $g_db->query($sql);
    
    $g_current_user = new User($g_db, $row_webmaster['webmaster_id']);
    $g_current_organization->readData($row_orga['org_id']);
    
    // Default-Listen-Konfigurationen anlegen
    $address_list = new ListConfiguration($g_db);
    $address_list->setValue("lst_name", "Adressliste");
    $address_list->setValue("lst_global", 1);
    $address_list->setValue("lst_default", 1);
    $address_list->addColumn(1, $g_current_user->getProperty("Nachname", "usf_id"), "ASC");
    $address_list->addColumn(2, $g_current_user->getProperty("Vorname", "usf_id"), "ASC");
    $address_list->addColumn(3, $g_current_user->getProperty("Geburtstag", "usf_id"));
    $address_list->addColumn(4, $g_current_user->getProperty("Adresse", "usf_id"));
    $address_list->addColumn(5, $g_current_user->getProperty("PLZ", "usf_id"));
    $address_list->addColumn(6, $g_current_user->getProperty("Ort", "usf_id"));
    $address_list->save();

    $phone_list = new ListConfiguration($g_db);
    $phone_list->setValue("lst_name", "Telefonliste");
    $phone_list->setValue("lst_global", 1);
    $phone_list->addColumn(1, $g_current_user->getProperty("Nachname", "usf_id"), "ASC");
    $phone_list->addColumn(2, $g_current_user->getProperty("Vorname", "usf_id"), "ASC");
    $phone_list->addColumn(3, $g_current_user->getProperty("Telefon", "usf_id"));
    $phone_list->addColumn(4, $g_current_user->getProperty("Handy", "usf_id"));
    $phone_list->addColumn(5, $g_current_user->getProperty("E-Mail", "usf_id"));
    $phone_list->addColumn(6, $g_current_user->getProperty("Fax", "usf_id"));
    $phone_list->save();
    
    $contact_list = new ListConfiguration($g_db);
    $contact_list->setValue("lst_name", "Kontaktdaten");
    $contact_list->setValue("lst_global", 1);
    $contact_list->addColumn(1, $g_current_user->getProperty("Nachname", "usf_id"), "ASC");
    $contact_list->addColumn(2, $g_current_user->getProperty("Vorname", "usf_id"), "ASC");
    $contact_list->addColumn(3, $g_current_user->getProperty("Geburtstag", "usf_id"));
    $contact_list->addColumn(4, $g_current_user->getProperty("Adresse", "usf_id"));
    $contact_list->addColumn(5, $g_current_user->getProperty("PLZ", "usf_id"));
    $contact_list->addColumn(6, $g_current_user->getProperty("Ort", "usf_id"));
    $contact_list->addColumn(7, $g_current_user->getProperty("Telefon", "usf_id"));
    $contact_list->addColumn(8, $g_current_user->getProperty("Handy", "usf_id"));
    $contact_list->addColumn(9, $g_current_user->getProperty("E-Mail", "usf_id"));
    $contact_list->save();
    
    $former_list = new ListConfiguration($g_db);
    $former_list->setValue("lst_name", "Mitgliedschaft");
    $former_list->setValue("lst_global", 1);
    $former_list->addColumn(1, $g_current_user->getProperty("Nachname", "usf_id"));
    $former_list->addColumn(2, $g_current_user->getProperty("Vorname", "usf_id"));
    $former_list->addColumn(3, $g_current_user->getProperty("Geburtstag", "usf_id"));
    $former_list->addColumn(4, "mem_begin");
    $former_list->addColumn(5, "mem_end", "DESC");
    $former_list->save();  
}

$sql = "UPDATE ". TBL_PHOTOS. " SET pho_timestamp_create = '".date("Y-m-d H:i:s", time())."'
         WHERE pho_timestamp_create IS NULL ";
$g_db->query($sql);

// neue Userfelder fuellen
$sql = "UPDATE ". TBL_USERS. " SET usr_timestamp_create = usr_timestamp_change
         WHERE usr_timestamp_change IS NOT NULL ";
$g_db->query($sql);

$sql = "UPDATE ". TBL_USERS. " SET usr_timestamp_create = '".date("Y-m-d H:i:s", time())."'
         WHERE usr_timestamp_create IS NULL ";
$g_db->query($sql);

// Datenstruktur nach Update anpassen
$sql = "ALTER TABLE ". TBL_USERS. " MODIFY COLUMN usr_timestamp_create datetime NOT NULL ";
$g_db->query($sql);

$sql = "ALTER TABLE ". TBL_ROLES. " MODIFY COLUMN rol_timestamp_create datetime NOT NULL ";
$g_db->query($sql);

$sql = "ALTER TABLE ". TBL_PHOTOS. " MODIFY COLUMN pho_timestamp_create datetime NOT NULL ";
$g_db->query($sql);

$sql = "ALTER TABLE ". TBL_DATES. " MODIFY COLUMN dat_cat_id int(11) unsigned NOT NULL ";
$g_db->query($sql);

$sql = "ALTER TABLE ". TBL_DATES. " DROP COLUMN dat_org_shortname ";
$g_db->query($sql);

//Neu Mailrechte installieren
//1. neue Spalten anlegen
	//passiert schon in upd_2_1_0_db.sql

//2.Webmaster mit globalem Mailsenderecht ausstatten
$sql = "UPDATE ". TBL_ROLES. " SET rol_mail_to_all = '1'
        WHERE rol_name = 'Webmaster'";
$g_db->query($sql);

//3. alte Mailrechte Übertragen
//3.1 eingeloggte konnten bisher eine Mail an diese Rolle schreiben
$sql = "UPDATE ". TBL_ROLES. " SET rol_mail_this_role = '2'
        WHERE rol_mail_login = 1";
$g_db->query($sql);

//3.2 ausgeloggte konnten bisher eine Mail an diese Rolle schreiben
$sql = "UPDATE ". TBL_ROLES. " SET rol_mail_this_role = '3'
        WHERE rol_mail_logout = 1";
$g_db->query($sql);

//4. Überflüssige Spalten löschen
$sql = "ALTER TABLE ". TBL_ROLES. " 
		DROP rol_mail_login,
		DROP rol_mail_logout";
$g_db->query($sql);
?>