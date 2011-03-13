<?php
/******************************************************************************
 * Datenkonvertierung fuer die Version 2.2.0
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// englische Bezeichnung der Bereiche in Systememails einbauen
$sql = 'SELECT * FROM '. TBL_TEXTS. ' ORDER BY txt_id DESC';
$result_texts = $g_db->query($sql);

while($row_texts = $g_db->fetch_array($result_texts))
{
    $row_texts['txt_text'] = preg_replace ('/#Betreff#/', '#subject#',  $row_texts['txt_text']);
    $row_texts['txt_text'] = preg_replace ('/#Inhalt#/', '#content#',  $row_texts['txt_text']);

    $sql = 'UPDATE '. TBL_TEXTS. ' SET txt_text = "'.addslashes($row_texts['txt_text']). '"
             WHERE txt_id = '.$row_texts['txt_id'];
    $g_db->query($sql);    
}

?>