<?php
/******************************************************************************
 * Datenkonvertierung fuer die Version 1.3
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
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
    $date = mysqldatetime("y-m-d", $dat_row->dat_end);
    $time = mysqldatetime("h:i:s", $dat_row->dat_begin);
    $datetime = "$date $time";
    
    $sql = "UPDATE ". TBL_DATES. " SET dat_end = '$datetime'
             WHERE dat_id = $dat_row->dat_id ";
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