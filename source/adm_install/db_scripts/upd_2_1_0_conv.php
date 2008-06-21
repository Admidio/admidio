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
                 VALUES (".$row_orga['org_id'].", 'SYSMAIL_REGISTRATION_USER', '".$systemmails_texts['SYSMAIL_REGISTRATION_USER']."') ";
    $g_db->query($sql);
    
    $sql = "INSERT INTO ". TBL_TEXTS. " (txt_org_id, txt_name, txt_text) 
                 VALUES (".$row_orga['org_id'].", 'SYSMAIL_REGISTRATION_WEBMASTER', '".$systemmails_texts['SYSMAIL_REGISTRATION_WEBMASTER']."') ";
    $g_db->query($sql);    
}

?>