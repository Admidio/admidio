<?php
/******************************************************************************
 * Sidebar Online
 *
 * Version 1.0.2
 *
 * Plugin zeigt Besucher und aktive registrierte Mitglieder der Homepage an
 *
 * Kompatible ab Admidio-Versions 1.4.1
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Thomas Thoss
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Pfad des Plugins ermitteln
$plugin_folder_pos = strpos(__FILE__, "adm_plugins") + 11;
$plugin_file_pos   = strpos(__FILE__, "sidebar_online.php");
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/$plugin_folder/config.php");
 
// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(isset($plg_time_online) == false || is_numeric($plg_time_online) == false)
{
    $plg_time_online = 10;
}

if(isset($plg_show_visitors) == false || is_numeric($plg_show_visitors) == false)
{
    $plg_show_visitors = 1;
}

if(isset($plg_show_self) == false || is_numeric($plg_show_self) == false)
{
    $plg_show_self = 1;
}

if(isset($plg_show_users_side_by_side) == false || is_numeric($plg_show_users_side_by_side) == false)
{
    $plg_show_users_side_by_side = 0;
}

if(isset($plg_link_class))
{
    $plg_link_class = strip_tags($plg_link_class);
}
else
{
    $plg_link_class = "";
}

if(isset($plg_link_target))
{
    $plg_link_target = strip_tags($plg_link_target);
}
else
{
    $plg_link_target = "_self";
}

// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
$g_db->select_db($g_adm_db);

// Aktuelle Zeit setzten
$act_date = date("Y.m.d H:i:s", time());
// Referenzzeit setzen
$ref_date = date("Y.m.d H:i:s", time() - 60 * $plg_time_online);

// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
$g_db->select_db($g_adm_db);

// User IDs alles Sessons finden, die in genannter aktueller und referenz Zeit sind
$sql = "SELECT ses_usr_id, usr_login_name
          FROM ". TBL_SESSIONS. " 
          LEFT JOIN ". TBL_USERS. "
            ON ses_usr_id = usr_id
         WHERE ses_timestamp BETWEEN '".$ref_date."' AND '".$act_date."' ";
if($plg_show_visitors == 0)
{
    $sql = $sql. " AND ses_usr_id IS NOT NULL ";
}
if($plg_show_self == 0 && $g_valid_login)
{
    $sql = $sql. " AND ses_usr_id <> ". $g_current_user->getValue("usr_id");
}
$sql = $sql. " ORDER BY ses_usr_id ";
$result = $g_db->query($sql);

if($g_db->num_rows($result) > 0)
{
    echo "Seit ".$plg_time_online." Minuten online:<br>";
    $usr_id_merker  = 0;
    $count_visitors = 0;
    
    while($row = $g_db->fetch_object($result))
    {
        if($row->ses_usr_id > 0)
        {
            if($row->ses_usr_id != $usr_id_merker)
            {
                echo "<b><a class=\"$plg_link_class\"  target=\"$plg_link_target\"
                    href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=$row->ses_usr_id\">$row->usr_login_name</a></b>";

                // User neben-/untereinander anzeigen
                if($plg_show_users_side_by_side)
                {
                    echo ", ";
                }
                else
                {
                    echo "<br>";
                }
                $usr_id_merker = $row->ses_usr_id;
            }
        }
        else
        {
            $count_visitors++;
        }
    }
    
    if($plg_show_visitors && $count_visitors > 0)
    {
        echo $count_visitors. " Besucher";
    }
}
else
{
    echo "Momentan ist kein anderer Benutzer online";
}
?>