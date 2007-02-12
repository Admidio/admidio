<?php
/******************************************************************************
 * Sidebar Wer ist Online
 *
 * Version 1.0
 *
 * Plugin das die aktiven Besucher der Homepage anzeigt
 *
 * Compatible to Admidio-Versions 1.4
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Thomas Thoss
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

// Include von common 
if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, strpos(__FILE__, "sidebar_online")-1));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/sidebar_online/config.php");
 
// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(is_numeric($onlinezeit) == false)
{
    $onlinezeit = 10;
}

// Aktuelle Zeit setzten
$act_date = date("Y.m.d H:i:s", time());
// Referenzzeit setzen
$ref_date = date("Y.m.d H:i:s", time() - 60 * $onlinezeit);

// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
mysql_select_db($g_adm_db, $g_adm_con );

// User IDs alles Sessons finden, die in genannter aktueller und referenz Zeit sind
$sql = "SELECT ses_usr_id FROM ". TBL_SESSIONS. " WHERE ses_timestamp BETWEEN '".$ref_date."' AND '".$act_date."'";
$result = mysql_query($sql, $g_adm_con);
db_error($result);

echo "Seit ".$onlinezeit." Minuten online:<br>";

while($row = mysql_fetch_object($result))
{
    // User_login_name finden und ausgeben
    $sql = "SELECT usr_login_name FROM ". TBL_USERS. " WHERE usr_id LIKE '".$row->ses_usr_id."'";

    $on_result = mysql_query($sql, $g_adm_con);
    db_error($on_result);
    
    $useronline = mysql_fetch_array($on_result);
    echo "<b><a href=\"/adm_program/modules/profile/profile.php?user_id=$row->ses_usr_id\">".$useronline['usr_login_name']."</a></b><br>";
}

?>