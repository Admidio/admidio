<?php
/******************************************************************************
 * Sidebar Birthday
 *
 * Version 1.1
 *
 * Plugin listet alle Benutzer auf, die an dem aktuellen Tag Geburtstag haben
 *
 * Kompatible ab Admidio-Versions 1.4.1
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

// Include von common 
if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, strpos(__FILE__, "sidebar_birthday")-1));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/sidebar_birthday/config.php");
 
// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(isset($plg_show_names_extern) == false || is_numeric($plg_show_names_extern) == false)
{
    $plg_show_names_extern = 1;
}

if(isset($plg_show_email_extern) == false || is_numeric($plg_show_email_extern) == false)
{
    $plg_show_email_extern = 0;
}

if(isset($plg_show_names) == false || is_numeric($plg_show_names) == false)
{
    $plg_show_names = 1;
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


$sql    = "SELECT DISTINCT usr_id, usr_last_name, usr_first_name, usr_login_name, usr_birthday, usr_email
             FROM ". TBL_USERS. " 
             JOIN ". TBL_MEMBERS. "
               ON mem_usr_id = usr_id
              AND mem_valid  = 1
             JOIN ". TBL_ROLES. "
               ON mem_rol_id = rol_id
              AND rol_org_shortname = '$g_organization'
              AND rol_valid  = 1
            WHERE Month(usr_birthday)      = Month(SYSDATE())
              AND DayOfMonth(usr_birthday) = DayOfMonth(SYSDATE())
              AND usr_valid = 1
            ORDER BY usr_last_name, usr_first_name ";
$result = mysql_query($sql, $g_adm_con);
db_error($result,__FILE__,__LINE__);

$anz_geb = mysql_num_rows($result);

if($anz_geb > 0)
{
    if($plg_show_names_extern == 1 || $g_session_valid == 1)
    {
        while($row = mysql_fetch_object($result))
        {
            // Alter berechnen
            // Hier muss man aufpassen, da viele PHP-Funkionen nicht mit einem Datum vor 1970 umgehen koennen !!!
            $act_date  = getDate(time());
            $geb_day   = mysqldatetime("d", $row->usr_birthday);
            $geb_month = mysqldatetime("m", $row->usr_birthday);
            $geb_year  = mysqldatetime("y", $row->usr_birthday);
            $birthday = false;

            if($act_date['mon'] >= $geb_month)
            {
                if($act_date['mon'] == $geb_month)
                {
                    if($act_date['mday'] >= $geb_day)
                    {
                        $birthday = true;
                    }
                }
                else
                {
                    $birthday = true;
                }
            }
            $age = $act_date['year'] - $geb_year;
            if($birthday == false)
            {
                $age--;
            }
            
            // Anzeigeart des Namens beruecksichtigen
            if($plg_show_names == 2)        // Nachname, Vorname
            {
                $show_name = "$row->usr_last_name, $row->usr_first_name";
            }
            elseif($plg_show_names == 3)    // Vorname
            {
                $show_name = $row->usr_first_name;
            }
            elseif($plg_show_names == 4)    // Loginname
            {
                $show_name = $row->usr_login_name;
            }
            else                            // Vorname Nachname
            {
                $show_name = "$row->usr_first_name $row->usr_last_name";
            }
            
            // Namen mit Alter und Mail-Link anzeigen
            if(strlen($row->usr_email) > 0
            && ($g_session_valid || $plg_show_email_extern))
            {
                if($g_session_valid)
                {
                    echo "<a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=$row->usr_id\" 
                         target=\"$plg_link_target\">$show_name</a>";
                }
                else
                {
                    echo "<a class=\"$plg_link_class\" href=\"mailto:$row->usr_email\" 
                        target=\"$plg_link_target\">$show_name</a>";
                }
            }
            else
            {
                echo $show_name;
            }
            echo " wird heute $age Jahre alt.<br />-----<br />";
        }
        echo "Herzlichen Gl&uuml;ckwunsch !";
    }
    else
    {
        if($anz_geb == 1)
        {
            echo "Heute hat ein Benutzer Geburtstag !";
        }
        else
        {
            echo "Heute haben $anz_geb Benutzer Geburtstag !";
        }
    }
}
else
{
    echo "Heute hat keiner Geburtstag.";
}
?>