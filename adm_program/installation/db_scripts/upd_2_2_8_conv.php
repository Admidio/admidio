<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.2.8
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Reihenfolge der Kategorien noch einmal komplett neu vergeben
$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS;
$orgaStatement = $gDb->query($sql);

while($rowOrga = $orgaStatement->fetch())
{
    $lastCatType = '';
    $counter     = 0;
    $sql = 'SELECT *
              FROM '.TBL_CATEGORIES.'
             WHERE (  cat_org_id = '. $rowOrga['org_id']. '
                   OR cat_org_id IS NULL )
          ORDER BY cat_type, cat_org_id, cat_sequence ';
    $catStatement = $gDb->query($sql);

    while($rowCat = $catStatement->fetch())
    {
        if($rowCat['cat_type'] != $lastCatType)
        {
            $counter = 1;
            $lastCatType = $rowCat['cat_type'];
        }

        $sql = 'UPDATE '.TBL_CATEGORIES.' SET cat_sequence = '. $counter. '
                 WHERE cat_id = '. $rowCat['cat_id'];
        $gDb->query($sql);

        ++$counter;
    }
}
