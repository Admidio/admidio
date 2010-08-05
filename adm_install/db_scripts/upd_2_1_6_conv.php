<?php
/******************************************************************************
 * Datenkonvertierung fuer die Version 2.1.6
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Fotoeinstellungen anpassen
$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS;
$result_orga = $g_db->query($sql); 

while($row_orga = $g_db->fetch_array($result_orga))
{
	// erstmal die Fotoskalierung fuer den Upload auslesen
	$sql = 'SELECT prf_value
              FROM '. TBL_PREFERENCES. '
             WHERE prf_org_id = '. $row_orga['org_id']. '
               AND prf_name   = "photo_save_scale" ';
    $result = $g_db->query($sql);
    $row_photo_image_text = $g_db->fetch_array($result);

	// ist die Fotoskalierung kleiner 1030 Pixel, dann die Anzeige darauf anpassen
	if($row_photo_image_text['prf_value'] < 1030)
	{
		$new_photo_size_y = $row_photo_image_text['prf_value'] * 0.75;
		$sql = 'UPDATE '. TBL_PREFERENCES. '
				   SET prf_value = "'.$row_photo_image_text['prf_value'].'"
       			 WHERE prf_org_id = '. $row_orga['org_id']. '
                   AND prf_name   = "photo_show_width" ';
		$g_db->query($sql);

		$sql = 'UPDATE '. TBL_PREFERENCES. '
				   SET prf_value = "'.$new_photo_size_y.'"
       			 WHERE prf_org_id = '. $row_orga['org_id']. '
               	   AND prf_name   = "photo_show_height" ';
		$g_db->query($sql);
	}
}

$sql = 'DELETE FROM '. TBL_PREFERENCES. '
   			WHERE prf_name = "photo_preview_scale" ';
$g_db->query($sql);

$sql = 'UPDATE '. TBL_MEMBERS. ' SET mem_end = "9999-12-31" 
         WHERE mem_end = "" OR mem_end = "0000-00-00" ';
$g_db->query($sql);

// Bei den Links den Counter auf 0 setzen
$sql = "UPDATE ". TBL_LINKS. " SET lnk_counter = 0 WHERE lnk_id > 0";
$g_db->query($sql);

?>