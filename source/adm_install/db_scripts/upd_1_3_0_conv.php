<?php
/******************************************************************************
 * Data conversion for version 1.3
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Alle Moderatoren bekommen das Recht fuer Ankuendigungen
$sql = "UPDATE ". TBL_ROLES. " SET rol_announcements = 1
         WHERE rol_moderation = 1 ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
    
// die Uhrzeit bei dat_end wurde nicht immer korrekt gesetzt
$sql = "SELECT * FROM ". TBL_DATES. "
         WHERE (   HOUR(dat_end)   = 0
               AND MINUTE(dat_end) = 0
               AND SECOND(dat_end) = 0 )
           AND (  HOUR(dat_begin)   <> 0
               OR MINUTE(dat_begin) <> 0
               OR SECOND(dat_begin) <> 0 ) ";
$dat_result = mysql_query($sql, $connection);
if(!$dat_result) showError(mysql_error());

while($dat_row = mysql_fetch_object($dat_result))
{
    $startDate = new DateTimeExtended($dat_row->dat_begin, 'Y-m-d H:i:s');
    $endDate = new DateTimeExtended($dat_row->dat_end, 'Y-m-d H:i:s');
    $datetime = $endDate->format('Y-m-d').' '.$startDate->format('H:i:s');
    
    $sql = 'UPDATE '. TBL_DATES. ' SET dat_end = \''.$datetime.'\'
             WHERE dat_id = '.$dat_row->dat_id;
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
}

// Orga-Felder in adm_preferences umwandeln
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
$result_orga = mysql_query($sql, $connection);
if(!$result_orga) showError(mysql_error());

while($row_orga = mysql_fetch_object($result_orga))
{
    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'max_email_attachment_size', $row_orga->org_mail_size)";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'max_file_upload_size', $row_orga->org_upload_size)";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'send_email_extern', $row_orga->org_mail_extern)";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_rss', $row_orga->org_enable_rss)";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());

    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'enable_bbcode', $row_orga->org_bbcode)";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
    
    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'email_administrator', 'webmaster@". $_SERVER['HTTP_HOST']. "')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
    
    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'default_country', 'Deutschland')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
    
    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'photo_save_scale', '640')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
}

// unnoetige Orga-Felder koennen jetzt geloescht werden
$sql = "ALTER TABLE ". TBL_ORGANIZATIONS. " DROP org_mail_size";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

$sql = "ALTER TABLE ". TBL_ORGANIZATIONS. " DROP org_upload_size";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

$sql = "ALTER TABLE ". TBL_ORGANIZATIONS. " DROP org_photo_size";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

$sql = "ALTER TABLE ". TBL_ORGANIZATIONS. " DROP org_mail_extern";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

$sql = "ALTER TABLE ". TBL_ORGANIZATIONS. " DROP org_enable_rss";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

$sql = "ALTER TABLE ". TBL_ORGANIZATIONS. " DROP org_bbcode";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

$sql = "ALTER TABLE ". TBL_ORGANIZATIONS. " DROP org_font";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

?>