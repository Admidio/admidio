<?php
/******************************************************************************
 * Data conversion for version 2.0.3
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Reihenfolge der Felder noch einmal komplett neu vergeben
$last_cat_id = 0;
$counter     = 0;
$sql = "SELECT * FROM ". TBL_USER_FIELDS. "
         ORDER BY usf_cat_id, usf_sequence ";
$result_fields = $gDb->query($sql);

while($row_fields = $gDb->fetch_array($result_fields))
{
    if($row_fields['usf_cat_id'] != $last_cat_id)
    {
        $counter = 1;
        $last_cat_id = $row_fields['usf_cat_id'];
    }

    $sql = "UPDATE ". TBL_USER_FIELDS. " SET usf_sequence = ". $counter. "
             WHERE usf_id = ". $row_fields['usf_id'];
    $gDb->query($sql);

    $counter++;
}

// Reihenfolge der Kategorien noch einmal komplett neu vergeben
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
$result_orga = $gDb->query($sql);

while($row_orga = $gDb->fetch_array($result_orga))
{
    $last_cat_type = "";
    $counter       = 0;
    $sql = "SELECT * FROM ". TBL_CATEGORIES. "
             WHERE (  cat_org_id = ". $row_orga['org_id']. "
                   OR cat_org_id IS NULL )
             ORDER BY cat_type, cat_org_id, cat_sequence ";
    $result_cat = $gDb->query($sql);

    while($row_cat = $gDb->fetch_array($result_cat))
    {
        if($row_cat['cat_type'] != $last_cat_type)
        {
            $counter = 1;
            $last_cat_type = $row_cat['cat_type'];
        }

        $sql = "UPDATE ". TBL_CATEGORIES. " SET cat_sequence = ". $counter. "
                 WHERE cat_id = ". $row_cat['cat_id'];
        $gDb->query($sql);

        $counter++;
    }
}
