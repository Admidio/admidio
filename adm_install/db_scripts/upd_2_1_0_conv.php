<?php
/******************************************************************************
 * Datenkonvertierung fuer die Version 2.1.0
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once("systemmails_texts.php");
 
// Texte fuer Systemmails pflegen
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
$result_orga = $g_db->query($sql);

while($row_orga = $g_db->fetch_array($result_orga))
{
    $sql = "INSERT INTO ". TBL_TEXTS. " (txt_org_id, txt_name, txt_text) 
                 VALUES (".$row_orga['org_id'].", 'SYSMAIL_REGISTRATION_USER', '".$systemmails_texts['SYSMAIL_REGISTRATION_USER']."')
                      , (".$row_orga['org_id'].", 'SYSMAIL_REGISTRATION_WEBMASTER', '".$systemmails_texts['SYSMAIL_REGISTRATION_WEBMASTER']."')
                      , (".$row_orga['org_id'].", 'SYSMAIL_NEW_PASSWORD', '".$systemmails_texts['SYSMAIL_NEW_PASSWORD']."') 
                      , (".$row_orga['org_id'].", 'SYSMAIL_ACTIVATION_LINK', '".$systemmails_texts['SYSMAIL_ACTIVATION_LINK']."') ";
    $g_db->query($sql); 

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'photo_upload_mode', $row_orga->photo_upload_mode)";
    $g_db->query($sql);

}

?>