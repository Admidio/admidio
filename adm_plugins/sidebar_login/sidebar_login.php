<?php
/******************************************************************************
 * Sidebar Login
 *
 * Version 1.0.1
 *
 * Plugin zeigt Loginfelder zum Anmelden an und im eingeloggten Zustand ein 
 * paar Daten zum eingeloggten User
 *
 * Kompatible ab Admidio-Versions 1.4.2
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender 
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
 *
 *****************************************************************************/

// Include von common 
if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, strpos(__FILE__, "sidebar_login")-1));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/../adm_program/system/role_class.php");
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

if(isset($plg_show_logout_link) == false || is_numeric($plg_show_logout_link) == false)
{
    $plg_show_logout_link = 1;
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

// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
$g_db->select_db($g_adm_db);

if($g_valid_login == 1)
{
    echo "
    <script type=\"text/javascript\">
        function loadPageLogout()
        {";
            if(strlen($plg_link_target) > 0 && strpos($plg_link_target, "_") === false)
            {
                echo "
                parent.$plg_link_target.location.href = '$g_root_path/adm_program/system/logout.php';
                self.location.reload(); ";
            }
            else
            {
                echo "self.location.href = '$g_root_path/adm_program/system/logout.php';";
            }
        echo "
        }
    </script>
    
    <div>
        Benutzer:<br>
        <a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=". $g_current_user->getValue("usr_id"). "\" 
            target=\"$plg_link_target\">". $g_current_user->getValue("Vorname"). " ". $g_current_user->getValue("Nachname"). "</a>
    </div>
    <div style=\"padding-top: 7px;\">
        Eingeloggt seit:<br>
        <b>". mysqldatetime("h:i", $g_current_user->getValue("usr_actual_login")). " Uhr</b>
    </div>
    <div style=\"padding-top: 7px;\">
        Anzahl Logins:<br><b>". $g_current_user->getValue("usr_number_login"). "</b>";
    
    // Zeigt einen Rank des Benutzers an, sofern diese in der config.php hinterlegt sind
    if(count($plg_rank) > 0)
    {
        $rank  = "";
        $value = reset($plg_rank);
        
        while($value != false)
        {
            $count_rank = key($plg_rank);
            if($count_rank < $g_current_user->getValue("usr_number_login"))
            {
                $rank = utf8_decode(strip_tags($value));
            }
            $value = next($plg_rank);
        }

        if(strlen($rank) > 0)
        {
            echo "&nbsp;<i>$rank</i>";
        }
    }
    echo "</div>";
    
    // Link zum Ausloggen
    if($plg_show_logout_link)
    {
        echo "<div style=\"padding-top: 5px;\">
            <a class=\"$plg_link_class\" href=\"javascript:loadPageLogout()\">Logout</a>       
        </div>";
    }
}
else
{
    // Login-Formular
    echo "
    <script type=\"text/javascript\">
        function loadPageLogin()
        {
            var loginname = document.login_form_sidebar.usr_login_name.value;
            var password  = document.login_form_sidebar.usr_password.value;
            var link      = '$g_root_path/adm_program/system/login_check.php?usr_login_name=' + loginname + '&usr_password=' + password;";

            if(strlen($plg_link_target) > 0 && strpos($plg_link_target, "_") === false)
            {
                echo "parent.$plg_link_target.location.href = link;
                self.location.reload(); ";
            }
            else
            {
                echo "self.location.href = link;";
            }
        echo "
        }
    </script>
    
    <form style=\"display: inline;\" action=\"javascript:loadPageLogin()\" method=\"get\" name=\"login_form_sidebar\">
        <div>
            Login-Name:<br>
            <input type=\"text\" id=\"usr_login_name\" name=\"usr_login_name\" size=\"10\" maxLength=\"25\">
        </div>
        <div style=\"padding-top: 5px;\">
            Passwort:<br>
            <input type=\"password\" id=\"usr_password\" name=\"usr_password\" size=\"10\" maxLength=\"25\">
        </div>
        <div style=\"padding-top: 5px;\">
            <input type=\"submit\" value=\"Login\">        
        </div>";
        
        // Links zum Registrieren und melden eines Problems anzeigen, falls gewuenscht
        if($plg_show_register_link || $plg_show_email_link)
        {
            echo "<div style=\"padding-top: 5px;\">";
                if($plg_show_register_link && $g_preferences['registration_mode'])
                {
                    echo "<a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/system/registration.php\" 
                        target=\"$plg_link_target\">Registrieren</a>";
                }
                if($plg_show_register_link && $plg_show_email_link)
                {
                    echo "<br>";
                }
                if($plg_show_email_link)
                {
                    // Rollenobjekt fuer 'Webmaster' anlegen
                    $role_webmaster = new Role($g_db, 'Webmaster');
    
                    // E-Mail intern oder extern verschicken
                    if($g_preferences['enable_mail_module'] != 1 
                    || $role_webmaster->getValue("rol_mail_logout") != 1 )
                    {
                        $mail_link = "mailto:". $g_preferences['email_administrator']. "?subject=Loginprobleme";
                    }
                    else
                    {
                        $mail_link = "$g_root_path/adm_program/modules/mail/mail.php?rol_id=". $role_webmaster->getValue("rol_id"). "&subject=Loginprobleme";
                    }
                    echo "<a class=\"$plg_link_class\" href=\"$mail_link\" target=\"$plg_link_target\">Loginprobleme</a>";
                }
            echo "</div>";
        }    
    echo "</form>";   
}

?>