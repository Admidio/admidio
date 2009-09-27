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
$sql = "SELECT min(mem_usr_id) as webmaster_id
          FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
         WHERE cat_org_id = ". $row_orga['org_id']. "
           AND rol_cat_id = cat_id
           AND rol_name   = 'Webmaster'
           AND mem_rol_id = rol_id ";
$result = $g_db->query($sql);
$row_webmaster = $g_db->fetch_array($result);

//Defaultraum fuer Raummodul in der DB anlegen:
$sql = 'INSERT INTO '. TBL_ROOMS. ' (room_name, room_description, room_capacity, room_usr_id_create, room_timestamp_create)
                                VALUES ("Besprechnungsraum", "Hier können Besprechungen stattfinden. Der Raum muss vorher
                                         reserviert werden. Ein Beamer steht zur Verfügung.", 15, '.
                                         $row_webmaster['webmaster_id'].',"'. DATETIME_NOW.'")';
$g_db->query($sql);

?>