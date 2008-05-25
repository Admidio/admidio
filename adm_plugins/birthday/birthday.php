<?php
/******************************************************************************
 * Birthday
 *
 * Version 2.0.0
 *
 * Das Plugin listet alle Benutzer auf, die an dem aktuellen Tag Geburtstag haben.
 * Auf Wunsch koennen auch Geburtstagskinder vor X Tagen angezeigt werden.
 *
 * Kompatible ab Admidio-Versions 2.0.0
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Pfad des Plugins ermitteln
$plugin_folder_pos = strpos(__FILE__, "adm_plugins") + 11;
$plugin_file_pos   = strpos(__FILE__, "birthday.php");
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

if(isset($plg_link_target))
{
    $plg_link_target = strip_tags($plg_link_target);
}
else
{
    $plg_link_target = "_self";
}

if(isset($plg_show_hinweis_keiner) == false || is_numeric($plg_show_names_extern) == false)
{
    $plg_show_names_extern = 0;
}

if(isset($plg_show_alter_anrede) == false || is_numeric($plg_show_names_extern) == false)
{
    $plg_show_names_extern = 18;
}

if(isset($plg_show_zeitraum) == false || is_numeric($plg_show_names_extern) == false || $plg_show_zeitraum > 28)
{
    $plg_show_zeitraum = 0;
}

// ist der Benutzer ausgeloggt und soll nur die Anzahl der Geb-Kinder angezeigt werden, dann Zeitraum auf 0 Tage setzen
if($plg_show_names_extern == 0 && $g_session_valid == 0)
{
    $plg_show_zeitraum = 0;
}

// Bleiben wir im aktuellen Monat?
if ((date("d",mktime(0, 0, 0, date("m"), date ("d"), date("Y"))) - $plg_show_zeitraum) > 0)
{
    $stichtag_aktueller_monat = date("d",mktime(0, 0, 0, date("m"), date ("d")-$plg_show_zeitraum, date("Y")));
    $sql_prev_month = "";
}
else
{
    // Hier wird sichergestellt, dass alle Geburtstagskinder des aktuellen Monats abgeholt werden.
    $stichtag_aktueller_monat = 1;
    // Hier holen wir die Geburtstagskinder aus dem Vormonat ab, sofern sie in den festgelegten Zeitraum fallen.
    $stichmonat = date("m",mktime(0, 0, 0, date("m"), date ("d")-$plg_show_zeitraum, date("Y")));
    $stichtag_vormonat = date("d",mktime(0, 0, 0, date("m"), date ("d")-$plg_show_zeitraum, date("Y")));
    
    // Sql-Bedingung fuer den Vormonat zusammensetzen
    $sql_prev_month = " OR (   Month(birthday.usd_value)       = '$stichmonat'
                           AND DayOfMonth(birthday.usd_value) >= '$stichtag_vormonat' ) ";
}

// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
$g_db->setCurrentDB();

$sql    = "SELECT DISTINCT usr_id, usr_login_name, 
                           last_name.usd_value as last_name, first_name.usd_value as first_name, 
                           birthday.usd_value as birthday, email.usd_value as email,
                           gender.usd_value as gender
             FROM ". TBL_USERS. " 
            RIGHT JOIN ". TBL_USER_DATA. " as birthday
               ON birthday.usd_usr_id = usr_id
              AND birthday.usd_usf_id = ". $g_current_user->getProperty("Geburtstag", "usf_id"). "
              AND (  (   Month(birthday.usd_value)       = Month(SYSDATE())
                     AND DayOfMonth(birthday.usd_value) <= DayOfMonth(SYSDATE())
                     AND DayOfMonth(birthday.usd_value) >= '$stichtag_aktueller_monat' )
                  $sql_prev_month )
             LEFT JOIN ". TBL_USER_DATA. " as last_name
               ON last_name.usd_usr_id = usr_id
              AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
             LEFT JOIN ". TBL_USER_DATA. " as first_name
               ON first_name.usd_usr_id = usr_id
              AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
             LEFT JOIN ". TBL_USER_DATA. " as email
               ON email.usd_usr_id = usr_id
              AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id"). "
             LEFT JOIN ". TBL_USER_DATA. " as gender
               ON gender.usd_usr_id = usr_id
              AND gender.usd_usf_id = ". $g_current_user->getProperty("Geschlecht", "usf_id"). "
             LEFT JOIN ". TBL_MEMBERS. "
               ON mem_usr_id = usr_id
              AND mem_valid  = 1
            RIGHT JOIN ". TBL_ROLES. "
               ON mem_rol_id = rol_id
              AND rol_valid  = 1
            RIGHT JOIN ". TBL_CATEGORIES. "
               ON rol_cat_id = cat_id
              AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
            WHERE usr_valid = 1
            ORDER BY Month(birthday.usd_value) DESC, DayOfMonth(birthday.usd_value) DESC, last_name, first_name ";
$result = $g_db->query($sql);

$anz_geb = $g_db->num_rows($result);

echo '<div id="plugin_'. $plugin_folder. '">';

if($anz_geb > 0)
{
    if($plg_show_names_extern == 1 || $g_valid_login == 1)
    {
        $later = "";
        // Hier fügen wir Text ein, der an die Auflistung von Geburtstagskindern eines Zeitraumes > 1 Tag angepasst ist.
        if ($plg_show_zeitraum > 0)
        {
            $later = "(nachtr&auml;glich)";
        }
        echo '<p>Zum Geburtstag gratulieren wir '. $later. ':</p>
        
        <ul id="plgBirthdayNameList">';
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
                        $show_name = '<a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='. $row['usr_id']. '" 
                            target="'. $plg_link_target. '" title="Profil aufrufen" alt="Profil aufrufen">'. $show_name. '</a>
                            <a class="iconLink" href="'. $g_root_path. '/adm_program/modules/mail/mail.php?usr_id='. $row['usr_id']. '"><img 
                            src="'. THEME_PATH. '/icons/email.png" alt="E-Mail senden" title="E-Mail senden"></a>';
                    }
                    else
                    {
                        $show_name = $show_name. 
                            '<a class="iconLink" href="mailto:'. $row['email']. '"><img 
                            src="'. THEME_PATH. '/icons/email.png" alt="E-Mail senden" title="E-Mail senden"></a>';
                    }
                }
                else
                {
                    // Soll der Name auch für nicht angemeldete Benutzer angezeigt werden, dann ab festgelegtem Alter statt Vorname die Anrede verwenden.
                    if($g_valid_login == false
                    && $plg_show_alter_anrede <= $age)
                    {
                        if (($row->gender) > 1)
                        {
                            $show_name = "Frau $row->last_name";
                        }
                        else
                        {
                            // Eine kleine Feinheit zur Textanpassung bei den Herren ;-)
                            if ($plg_show_zeitraum > 0)
                            {
                                $show_name = "Herrn $row->last_name";
                            }
                            else
                            {
                                $show_name = "Herr $row->last_name";
                            }
                        }
                    }
                }

                // Soll das Alter auch für nicht angemeldete Benutzer angezeigt werden?
                if($plg_show_names_extern < 2 || $g_valid_login == true)
                {
                    // Geburtstagskinder am aktuellen Tag bekommen anderen Text
                    if($geb_day == date("d", time()))
                    {
                        echo "<li>$show_name wird heute $age Jahre alt.</li>";
                    }
                    else
                    {
                        echo "<li>$show_name zum $age.</li>";
                    }
                }
            }
        echo "</ul>
        <p>Herzlichen Gl&uuml;ckwunsch !</p>";
    }
    else
    {
        if($anz_geb == 1)
        {
            echo "<p>Heute hat ein Benutzer Geburtstag !</p>";
        }
        else
        {
            echo "<p>Heute haben $anz_geb Benutzer Geburtstag !</p>";
        }
    }
}
else
{
    // Bei entsprechend gesetzter Konfiguration wird auch im Fall, dass keiner Geburtstag hat, eine Meldung ausgegeben.
    if($plg_show_hinweis_keiner == 0)
    {
        echo "<p>Heute hat keiner Geburtstag.</p>";
    }
}

echo '</div>';

?>