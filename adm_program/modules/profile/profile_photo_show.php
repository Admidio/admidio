<?php
   /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * usr_id : die ID des Users dessen Bild angezeigt werden soll
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
require("../../system/common.php");
require("../../system/login_valid.php");

// Uebergabevariablen pruefen

if(isset($_GET["usr_id"]) && is_numeric($_GET["usr_id"]) == false)
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_variable&err_text=usr_id";
    header($location);
    exit();
}

// Foto aus der Datenbank lesen und ausgeben

$sql = "SELECT usr_photo
        FROM ".TBL_USERS."
        WHERE usr_id= {0}";
$sql = prepareSQL($sql, array($_GET["usr_id"]));        
$result_photo = mysql_query($sql, $g_adm_con);

header("Content-Type: image/jpeg");
echo @MYSQL_RESULT($result_photo,0,"usr_photo");

?>
