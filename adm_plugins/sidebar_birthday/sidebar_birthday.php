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
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Pfad des Plugins ermitteln
$plugin_folder_pos = strpos(__FILE__, "adm_plugins") + 11;
$plugin_file_pos   = strpos(__FILE__, "sidebar_birthday.php");
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/$plugin_folder/config.php");
 
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

// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
$g_db->select_db($g_adm_db);

$sql    = "SELECT DISTINCT usr_id, usr_login_name, 
                           last_name.usd_value as last_name, first_name.usd_value as first_name, 
                           birthday.usd_value as birthday, email.usd_value as email
             FROM ". TBL_USERS. " 
            RIGHT JOIN ". TBL_USER_DATA. " as birthday
               ON birthday.usd_usr_id = usr_id
              AND birthday.usd_usf_id = ". $g_current_user->getProperty("Geburtstag", "usf_id"). "
              AND Month(birthday.usd_value)      = Month(SYSDATE())
              AND DayOfMonth(birthday.usd_value) = DayOfMonth(SYSDATE())
             LEFT JOIN ". TBL_USER_DATA. " as last_name
               ON last_name.usd_usr_id = usr_id
              AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
             LEFT JOIN ". TBL_USER_DATA. " as first_name
               ON first_name.usd_usr_id = usr_id
              AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
             LEFT JOIN ". TBL_USER_DATA. " as email
               ON email.usd_usr_id = usr_id
              AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id"). "
             LEFT JOIN ". TBL_MEMBERS. "
               ON mem_usr_id = usr_id
              AND mem_valid  = 1
             LEFT JOIN ". TBL_ROLES. "
               ON mem_rol_id = rol_id
              AND rol_valid  = 1
             LEFT JOIN ". TBL_CATEGORIES. "
               ON rol_cat_id = cat_id
              AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
            WHERE usr_valid = 1
            ORDER BY last_name, first_name ";
$result = $g_db->query($sql);

$anz_geb = $g_db->num_rows($result);

if($anz_geb > 0)
{
    if($plg_show_names_extern == 1 || $g_valid_login == 1)
    {
        while($row = $g_db->fetch_array($result))
        {
            // Alter berechnen
            // Hier muss man aufpassen, da viele PHP-Funkionen nicht mit einem Datum vor 1970 umgehen koennen !!!
            $act_date  = getDate(time());
            $geb_day   = mysqldatetime("d", $row['birthday']);
            $geb_month = mysqldatetime("m", $row['birthday']);
            $geb_year  = mysqldatetime("y", $row['birthday']);
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
                $show_name = $row['last_name']. ", ". $row['first_name'];
            }
            elseif($plg_show_names == 3)    // Vorname
            {
                $show_name = $row['first_name'];
            }
            elseif($plg_show_names == 4)    // Loginname
            {
                $show_name = $row['usr_login_name'];
            }
            else                            // Vorname Nachname
            {
                $show_name = $row['first_name']. " ". $row['last_name'];
            }
            
            // Namen mit Alter und Mail-Link anzeigen
            if(strlen($row['email']) > 0
            && ($g_valid_login || $plg_show_email_extern))
            {
                if($g_valid_login)
                {
                    echo "<a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=". $row['usr_id']. "\" 
                         target=\"$plg_link_target\">$show_name</a>";
                }
                else
                {
                    echo "<a class=\"$plg_link_class\" href=\"mailto:". $row['email']. "\" 
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