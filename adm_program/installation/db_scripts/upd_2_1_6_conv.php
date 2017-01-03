<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.1.6
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// Fotoeinstellungen anpassen
$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS;
$orgaStatement = $gDb->query($sql);

while($rowOrga = $orgaStatement->fetch())
{
    // erstmal die Fotoskalierung fuer den Upload auslesen
    $sql = 'SELECT prf_value
              FROM '.TBL_PREFERENCES.'
             WHERE prf_org_id = '. $rowOrga['org_id']. '
               AND prf_name   = \'photo_save_scale\' ';
    $statement = $gDb->query($sql);
    $rowPhotoImageText = $statement->fetch();

    // ist die Fotoskalierung kleiner 1030 Pixel, dann die Anzeige darauf anpassen
    if($rowPhotoImageText['prf_value'] < 1030)
    {
        $newPhotoSizeY = $rowPhotoImageText['prf_value'] * 0.75;
        $sql = 'UPDATE '.TBL_PREFERENCES.'
                   SET prf_value = \''.$rowPhotoImageText['prf_value'].'\'
                 WHERE prf_org_id = '. $rowOrga['org_id']. '
                   AND prf_name   = \'photo_show_width\' ';
        $gDb->query($sql);

        $sql = 'UPDATE '.TBL_PREFERENCES.'
                   SET prf_value = \''.$newPhotoSizeY.'\'
                 WHERE prf_org_id = '. $rowOrga['org_id']. '
                   AND prf_name   = \'photo_show_height\' ';
        $gDb->query($sql);
    }
}

$sql = 'DELETE FROM '.TBL_PREFERENCES.'
              WHERE prf_name = \'photo_preview_scale\'';
$gDb->query($sql);

$sql = 'UPDATE '.TBL_MEMBERS.' SET mem_end = '.DATE_MAX.'
         WHERE mem_end = \'\'
            OR mem_end = \'0000-00-00\' ';
$gDb->query($sql);
