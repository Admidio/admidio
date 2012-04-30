<?php
/******************************************************************************
 * Data conversion for version 2.4.0
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

 // create new indices
//$sql = 'ALTER TABLE '.TBL_MEMBERS.' DROP INDEX IDX_MEM_ROL_USR_ID';
//$gDb->query($sql, false);
$sql = 'DROP INDEX IDX_MEM_ROL_USR_ID';
$gDb->query($sql, false);

$sql = 'create index IDX_MEM_ROL_USR_ID on '. TBL_MEMBERS. ' (mem_rol_id, mem_usr_id)';
$gDb->query($sql);
 
// write data for every organization
$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS. ' ORDER BY org_id DESC';
$result_orga = $gDb->query($sql);

while($row_orga = $gDb->fetch_array($result_orga))
{
    // select ID of webmaster
    $sql = 'SELECT min(mem_usr_id) as webmaster_id
              FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
             WHERE cat_org_id = '. $row_orga['org_id']. '
               AND rol_cat_id = cat_id
               AND rol_name   = \'Webmaster\'
               AND mem_rol_id = rol_id ';
    $result = $gDb->query($sql);
    $row_webmaster = $gDb->fetch_array($result);

    $sql = 'UPDATE '. TBL_MEMBERS. ' SET mem_usr_id_create = '. $row_webmaster['webmaster_id']. '
                                       , mem_timestamp_create = \''.DATETIME_NOW.'\'';
    $gDb->query($sql);    
}
 
?>