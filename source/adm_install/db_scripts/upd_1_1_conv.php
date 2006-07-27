<?php
/******************************************************************************
 * Datenkonvertierung fuer die Version 1.1
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

$aim_id   = 0;
$icq_id   = 0;
$msn_id   = 0;
$yahoo_id = 0;

// IDs der einzelnen Messenger auslesen

$sql = "SELECT auf_id FROM adm_user_field WHERE auf_type = 'MESSENGER' AND auf_name = 'AIM' ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$row = mysql_fetch_object($result);
$aim_id = $row->auf_id;

$sql = "SELECT auf_id FROM adm_user_field WHERE auf_type = 'MESSENGER' AND auf_name = 'ICQ' ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$row = mysql_fetch_object($result);
$icq_id = $row->auf_id;

$sql = "SELECT auf_id FROM adm_user_field WHERE auf_type = 'MESSENGER' AND auf_name = 'MSN' ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$row = mysql_fetch_object($result);
$msn_id = $row->auf_id;

$sql = "SELECT auf_id FROM adm_user_field WHERE auf_type = 'MESSENGER' AND auf_name = 'YAHOO' ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());
$row = mysql_fetch_object($result);
$yahoo_id = $row->auf_id;

// alle User selektieren, die einen Messenger zugeordnet haben

$sql = "SELECT au_id, au_messenger, au_messenger_id FROM adm_user WHERE au_messenger > 0 ";
$result_user = mysql_query($sql, $connection);
if(!$result_user) showError(mysql_error());

while($row_usr = mysql_fetch_object($result_user))
{
   $messenger_id = 0;
   switch($row_usr->au_messenger)
   {
      case 1:
         $messenger_id = $icq_id;
         break;
      case 2:
         $messenger_id = $aim_id;
         break;
      case 3:
         $messenger_id = $msn_id;
         break;
      case 4:
         $messenger_id = $yahoo_id;
         break;
   }
   
   // neuen Satz in adm_user_data einfuegen
   $sql = "INSERT INTO adm_user_data (aud_au_id, aud_auf_id, aud_value) VALUES ($row_usr->au_id, $messenger_id, '$row_usr->au_messenger_id') ";
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());
}

// die beiden alten Messengerfelder in adm_user loeschen
$sql = "ALTER TABLE adm_user DROP au_messenger, DROP au_messenger_id ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());

?>