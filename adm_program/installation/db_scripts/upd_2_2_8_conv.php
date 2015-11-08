<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.2.8
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Reihenfolge der Kategorien noch einmal komplett neu vergeben
$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS;
$result_orga = $gDb->query($sql);

while($row_orga = $gDb->fetch_array($result_orga))
{
    $last_cat_type = '';
    $counter       = 0;
    $sql = 'SELECT * FROM '. TBL_CATEGORIES. '
             WHERE (  cat_org_id = '. $row_orga['org_id']. '
                   OR cat_org_id IS NULL )
             ORDER BY cat_type, cat_org_id, cat_sequence ';
    $result_cat = $gDb->query($sql);

    while($row_cat = $gDb->fetch_array($result_cat))
    {
        if($row_cat['cat_type'] != $last_cat_type)
        {
            $counter = 1;
            $last_cat_type = $row_cat['cat_type'];
        }

        $sql = 'UPDATE '. TBL_CATEGORIES. ' SET cat_sequence = '. $counter. '
                 WHERE cat_id = '. $row_cat['cat_id'];
        $gDb->query($sql);

        ++$counter;
    }
}
