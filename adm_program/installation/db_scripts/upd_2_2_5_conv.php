<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.2.5
 *
 * @copyright 2004-2016 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// eine Orga-ID einlesen
$sql = 'SELECT MIN(org_id) AS org_id FROM '.TBL_ORGANIZATIONS.' ORDER BY org_id DESC';
$orgaStatement = $gDb->query($sql);
$row_orga = $orgaStatement->fetch();

// Webmaster-ID ermitteln
$sql = 'SELECT usr_id AS webmaster_id, usr_timestamp_create AS timestamp
          FROM '.TBL_USERS.'
         WHERE usr_id IN (SELECT MIN(mem_usr_id)
                            FROM '.TBL_MEMBERS.'
                      INNER JOIN '.TBL_ROLES.'
                              ON rol_id = mem_rol_id
                      INNER JOIN '.TBL_CATEGORIES.'
                              ON cat_id = rol_cat_id
                           WHERE rol_name   = \'Webmaster\'
                             AND cat_org_id = '.$row_orga['org_id'].')';
$statement = $gDb->query($sql);
$row_webmaster = $statement->fetch();

// Rollenpflichtfelder fuellen, falls dies noch nicht passiert ist
$sql = 'UPDATE '.TBL_ROLES.' SET rol_timestamp_create = \''. $row_webmaster['timestamp'].'\'
                               , rol_usr_id_create    = '. $row_webmaster['webmaster_id'].'
         WHERE rol_usr_id_create IS NULL';
$gDb->query($sql);
