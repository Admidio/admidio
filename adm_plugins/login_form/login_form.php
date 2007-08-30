<?php
/******************************************************************************
 * Login Form
 *
 * Version 2.0.0
 *
 * Login Form stellt das Loginformular mit den entsprechenden Feldern dar,
 * damit sich ein Benutzer anmelden kann. Ist der Benutzer angemeldet, so
 * werden an der Stelle der Felder nun nützliche Informationen des Benutzers
 * angezeigt.
 *
 * Kompatible ab Admidio-Versions 2.0.0
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Pfad des Plugins ermitteln
$plugin_folder_pos = strpos(__FILE__, "adm_plugins") + 11;
$plugin_file_pos   = strpos(__FILE__, "login_form.php");
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/../adm_program/system/role_class.php");
require_once(PLUGIN_PATH. "/$plugin_folder/config.php");
 
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

echo '<link rel="stylesheet" type="text/css" href="'. $g_root_path. '/adm_plugins/'. $plugin_folder. '/login_form.css">';

if($g_valid_login == 1)
{
    echo '    
    <script type="text/javascript">
        function loadPageLogout()
        {';
            if(strlen($plg_link_target) > 0 && strpos($plg_link_target, "_") === false)
            {
                echo '
                parent.'. $plg_link_target. '.location.href = \''. $g_root_path. '/adm_program/system/logout.php\';
                self.location.reload(); ';
            }
            else
            {
                echo 'self.location.href = \''. $g_root_path. '/adm_program/system/logout.php\';';
            }
        echo '
        }
    </script>
    
    <ul id="plgLoginFormFieldList">
        <li>
            <dl>
                <dt>Benutzer:</dt>
                <dd>
                    <a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='. $g_current_user->getValue("usr_id"). '" 
                    target="'. $plg_link_target. '">'. $g_current_user->getValue("Vorname"). ' '. $g_current_user->getValue("Nachname"). '</a>
                </dd>
            </dl>
        </li>
        <li>
            <dl>
                <dt>Eingeloggt seit:</dt>
                <dd>'. mysqldatetime("h:i", $g_current_user->getValue("usr_actual_login")). ' Uhr</dd>
            </dl>
        </li>
        <li>
            <dl>
                <dt>Anzahl Logins:</dt>
                <dd>'. $g_current_user->getValue("usr_number_login");
    
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
                            echo "&nbsp;$rank";
                        }
                    }
                echo "</dd>
            </dl>
        </li>";
    
        // Link zum Ausloggen
        if($plg_show_logout_link)
        {
            echo "<li>
                <dl>
                    <dt><a href=\"javascript:loadPageLogout()\">Logout</a></dt>
                </dl>
            </li>";
        }
}
else
{
    // Login-Formular
    echo '
    <script type="text/javascript">
        function loadPageLogin()
        {
            var loginname = document.plg_login_form.usr_login_name.value;
            var password  = document.plg_login_form.usr_password.value;
            var link      = \''. $g_root_path. '/adm_program/system/login_check.php?usr_login_name=\' + loginname + \'&usr_password=\' + password;';

            if(strlen($plg_link_target) > 0 && strpos($plg_link_target, "_") === false)
            {
                echo 'parent.'. $plg_link_target. '.location.href = link;
                self.location.reload(); ';
            }
            else
            {
                echo 'self.location.href = link;';
            }
        echo '
        }
    </script>
    
    <form style="display: inline;" action="javascript:loadPageLogin()" method="get" name="plg_login_form">
        <ul id="plgLoginFormFieldList">
            <li>
                <dl>
                    <dt><label for="usr_login_name">Login-Name:</label></dt>
                    <dd><input type="text" id="usr_login_name" name="usr_login_name" size="10" maxlength="35"></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="usr_password">Passwort:</label></dt>
                    <dd><input type="password" id="usr_password" name="usr_password" size="10" maxlength="25"></dd>
                </dl>
            </li>';
            
            if($g_preferences['enable_auto_login'] == 1)
            {
                echo '
                <li>
                    <dl>
                        <dt style="clear: left;"><input type="checkbox" id="auto_login" name="auto_login" value="1"> 
                            <label for="auto_login">angemeldet bleiben</label></dt>
                    </dl>
                </li>';
            }            
            
            echo '
            <li>
                <dl>
                    <dt style="clear: left;"><input type="submit" value="Login"></dt>
                </dl>
            </li>';
        
            // Links zum Registrieren und melden eines Problems anzeigen, falls gewuenscht
            if($plg_show_register_link || $plg_show_email_link)
            {
                echo '<li>
                    <dl>';
                        if($plg_show_register_link && $g_preferences['registration_mode'])
                        {
                            echo '<dt style="clear: left;"><a href="'. $g_root_path. '/adm_program/system/registration.php" 
                                target="'. $plg_link_target. '">Registrieren</a></dt>';
                        }
                        if($plg_show_register_link && $plg_show_email_link)
                        {
                            echo '</dl></li><li><dl>';
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
                            echo '<dt style="clear: left;"><a href="'. $mail_link. '" target="'. $plg_link_target. '">Loginprobleme</a></dt>';
                        }
                    echo '</dl>
                </li>';
            }    
        echo "</ul>
    </form>";   
}

?>