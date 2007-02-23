<?php
/******************************************************************************
 * Sidebar Login
 *
 * Version 1.0
 *
 * Plugin zeigt Loginfelder zum Anmelden an und im eingeloggten Zustand ein 
 * paar Daten zum eingeloggten User
 *
 * Kompatible ab Admidio-Versions 1.4.2
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
    define('PLUGIN_PATH', substr(__FILE__, 0, strpos(__FILE__, "sidebar_login")-1));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/sidebar_login/config.php");
 
// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(isset($plg_show_register_link) == false || is_numeric($plg_show_register_link) == false)
{
    $plg_show_register_link = 1;
}

if(isset($plg_show_email_link) == false || is_numeric($plg_show_email_link) == false)
{
    $plg_show_email_link = 1;
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

if(isset($plg_rank) == false)
{
    $plg_rank = array();
}

$sql    = "SELECT rol_id
             FROM ". TBL_ROLES. "
            WHERE rol_org_shortname = '$g_current_organization->shortname'
              AND rol_name          = 'Webmaster' ";
$result = mysql_query($sql, $g_adm_con);
db_error($result);
$webmaster_row = mysql_fetch_object($result);

if($g_session_valid == 1)
{
    echo "<div>
        Benutzer:<br>
        <a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=$g_current_user->id\" 
            target=\"$plg_link_target\">$g_current_user->first_name $g_current_user->last_name</a>
    </div>
    <div style=\"padding-top: 7px;\">
        Eingeloggt seit:<br>
        <b>". mysqldatetime("h:i", $g_current_user->actual_login). " Uhr</b>
    </div>
    <div style=\"padding-top: 7px;\">
        Anzahl Logins:<br><b>$g_current_user->number_login</b>";
    
    // Zeigt einen Rank des Benutzers an, sofern diese in der config.php hinterlegt sind
    if(count($plg_rank) > 0)
    {
        $rank  = "";
        $value = reset($plg_rank);
        
        while($value != false)
        {
            $count_rank = key($plg_rank);
            if($count_rank < $g_current_user->number_login)
            {
                $rank = strip_tags($value);
            }
            $value = next($plg_rank);
        }

        if(strlen($rank) > 0)
        {
            echo "&nbsp;<i>$rank</i>";
        }
    }
    echo "</div>";
}
else
{
    // Login-Formular
    echo "<form style=\"display: inline;\" action=\"$g_root_path/adm_program/system/login_check.php\" method=\"post\">
        <div>
            Login-Name:<br>
            <input type=\"text\" name=\"loginname\" size=\"10\" maxLength=\"25\">
        </div>
        <div style=\"padding-top: 5px;\">
            Passwort:<br>
            <input type=\"password\" name=\"passwort\" size=\"10\" maxLength=\"25\">
        </div>
        <div style=\"padding-top: 5px;\">
            <input type=\"submit\" value=\"Login\">        
        </div>";
        
        if($plg_show_register_link || $plg_show_email_link)
        {
            echo "<div style=\"padding-top: 5px;\">";
                if($plg_show_register_link && $g_preferences['registration_mode'])
                {
                    echo "&nbsp;<a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/system/registration.php\" 
                        target=\"$plg_link_target\">Registrieren</a>";
                }
                if($plg_show_register_link && $plg_show_email_link)
                {
                    echo "<br>";
                }
                if($plg_show_email_link)
                {
                    // E-Mail intern oder extern verschicken
                    if($g_preferences['enable_mail_module'] != 1)
                    {
                        $mail_link = "mailto:". $g_preferences['email_administrator']. "?subject=Loginprobleme";
                    }
                    else
                    {
                        $mail_link = "$g_root_path/adm_program/modules/mail/mail.php?rol_id=$webmaster_row->rol_id&subject=Loginprobleme";
                    }
                    echo "&nbsp;<a class=\"$plg_link_class\" href=\"$mail_link\" target=\"$plg_link_target\">Loginprobleme</a>";
                }
            echo "</div>";
        }
    echo "</form>";
}

?>