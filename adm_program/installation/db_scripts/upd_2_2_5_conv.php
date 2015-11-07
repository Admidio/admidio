<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.2.5
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// eine Orga-ID einlesen
$sql = 'SELECT MIN(org_id) as org_id FROM '. TBL_ORGANIZATIONS. ' ORDER BY org_id DESC';
$result_orga = $gDb->query($sql);
$row_orga = $gDb->fetch_array($result_orga);

// Webmaster-ID ermitteln
$sql = 'SELECT usr_id as webmaster_id, usr_timestamp_create as timestamp
          FROM '.TBL_USERS.'
         WHERE usr_id IN (SELECT min(mem_usr_id)
                            FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                           WHERE cat_org_id = '. $row_orga['org_id']. '
                             AND rol_cat_id = cat_id
                             AND rol_name   = \'Webmaster\'
                             AND mem_rol_id = rol_id )';
$result = $gDb->query($sql);
$row_webmaster = $gDb->fetch_array($result);

// Rollenpflichtfelder fuellen, falls dies noch nicht passiert ist
$sql = 'UPDATE '. TBL_ROLES. ' SET rol_timestamp_create = \''. $row_webmaster['timestamp'].'\'
                                 , rol_usr_id_create    = '. $row_webmaster['webmaster_id'].'
     WHERE rol_usr_id_create IS NULL ';
$gDb->query($sql);
