<?php
/******************************************************************************
 * Datenkonvertierung fuer die Version 1.5
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
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


// E-Mail-Flags bei Rolle Webmaster per Default setzen, damit immer eine Rolle in Mail vorhanden ist
$sql = "UPDATE ". TBL_ROLES. " SET rol_mail_login  = 1
                                 , rol_mail_logout = 1
         WHERE rol_name = 'Webmaster' ";
$result = mysql_query($sql, $connection);
if(!$result) showError(mysql_error());


// neue Orgafelder anlegen
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
$result_orga = mysql_query($sql, $connection);
if(!$result_orga) showError(mysql_error());

while($row_orga = mysql_fetch_object($result_orga))
{
    $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
            VALUES ($row_orga->org_id, 'lists_members_per_page', '0')";
    $result = mysql_query($sql, $connection);
    if(!$result) showError(mysql_error());
}

?>