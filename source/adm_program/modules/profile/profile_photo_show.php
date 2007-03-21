<?php
   /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * usr_id : die ID des Users dessen Bild angezeigt werden soll
 * tmp_photo : 0 (Default) es wird das Foto aus der User-Tabelle angezeigt
 *             1 es wird das temporaere Foto aus der Session-Tabelle angezeigt
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
require("../../system/common.php");
require("../../system/login_valid.php");

// lokale Variablen der Uebergabevariablen initialisieren
$req_usr_id    = 0;
$req_tmp_photo = 0;

// Uebergabevariablen pruefen

if(  (isset($_GET["usr_id"]) 
   && is_numeric($_GET["usr_id"]) == false)
|| isset($_GET["usr_id"]) == false)
{
    $g_message->show("invalid");
}
else
{
    $req_usr_id = $_GET["usr_id"];
}

if(isset($_GET["tmp_photo"]) && $_GET["tmp_photo"] == 1)
{
    $req_tmp_photo = 1;
}

// Foto aus der Datenbank lesen und ausgeben

if($req_tmp_photo == true)
{
    $sql = "SELECT ses_blob photo
              FROM ".TBL_SESSIONS."
             WHERE ses_usr_id = {0}";
}
else
{
    $sql = "SELECT usr_photo photo
              FROM ".TBL_USERS."
             WHERE usr_id = {0}";
}
$sql = prepareSQL($sql, array($req_usr_id));        
$result_photo = mysql_query($sql, $g_adm_con);

header("Content-Type: image/jpeg");
echo mysql_result($result_photo, 0, "photo");

?>
