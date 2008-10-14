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
                                           VALUES (". $row_orga['org_id']. ", 'DAT', 'Allgemein', 0, 1)";
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

?>