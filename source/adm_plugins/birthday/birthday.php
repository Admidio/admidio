<?php
/******************************************************************************
 * Birthday
 *
 * Version 1.5.2
 *
 * Das Plugin listet alle Benutzer auf, die an dem aktuellen Tag Geburtstag haben.
 * Auf Wunsch koennen auch Geburtstagskinder vor X Tagen angezeigt werden.
 *
 * Kompatible ab Admidio-Versions 2.1.0
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Pfad des Plugins ermitteln
$plugin_folder_pos = strpos(__FILE__, 'adm_plugins') + 11;
$plugin_file_pos   = strpos(__FILE__, 'birthday.php');
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. '/../adm_program/system/common.php');
require_once(PLUGIN_PATH. '/'.$plugin_folder.'/config.php');
 
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
    $plg_link_target = '_self';
}

if(isset($plg_show_hinweis_keiner) == false || is_numeric($plg_show_names_extern) == false)
{
    $plg_show_names_extern = 0;
}

if(isset($plg_show_alter_anrede) == false || is_numeric($plg_show_names_extern) == false)
{
    $plg_show_names_extern = 18;
}

if(isset($plg_show_zeitraum) == false || is_numeric($plg_show_names_extern) == false)
{
    $plg_show_zeitraum = 5;
}

if(isset($plg_show_future) == false || is_numeric($plg_show_names_extern) == false)
{
    $plg_show_future = 10;
}

// Prüfen, ob die Rollenbedingung gesetzt wurde            //
if(isset($plg_rolle_sql) == false || ($plg_rolle_sql) =="")
{
    $rol_sql = "is not null";
}
else
{
    $rol_sql = "in ".$plg_rolle_sql;
}

// Prüfen, ob die Sotierbedingung gesetzt wurde            //
if(isset($plg_sort_sql) == false || ($plg_sort_sql) =="")
{
    $sort_sql = "desc";
}
else
{
    $sort_sql = $plg_sort_sql;
}

// ist der Benutzer ausgeloggt und soll nur die Anzahl der Geb-Kinder angezeigt werden, dann Zeitraum auf 0 Tage setzen
if($plg_show_names_extern == 0 && $g_valid_login == 0)
{
    $plg_show_zeitraum = 0;
    $plg_show_future = 0;
}

// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
$g_db->setCurrentDB();

$sql    = "SELECT DISTINCT usr_id, usr_login_name, 
                           last_name.usd_value as last_name, first_name.usd_value as first_name, 
                           birthday.bday as birthday, birthday.bdate,
                           DATEDIFF(birthday.bdate, '".DATETIME_NOW."') AS days_to_bdate,
                           YEAR(bdate) - YEAR(bday) AS age,
                           email.usd_value as email, gender.usd_value as gender
             FROM ". TBL_USERS. " users
             JOIN (
            (SELECT 
                usd_usr_id,
                usd_value AS bday,
                CONCAT(year('".DATETIME_NOW."'), '-', month(usd_value),'-', dayofmonth(bd1.usd_value)) AS bdate
                FROM ". TBL_USER_DATA. " bd1
                WHERE DATEDIFF(CONCAT(year('".DATETIME_NOW."'), '-', month(usd_value),'-', dayofmonth(bd1.usd_value)), '".DATETIME_NOW."') BETWEEN -$plg_show_zeitraum AND $plg_show_future
                        AND usd_usf_id = ". $g_current_user->getProperty("Geburtstag", "usf_id"). ")
        UNION
            (SELECT 
                usd_usr_id,
                usd_value AS bday,
                CONCAT(year('".DATETIME_NOW."')-1, '-', month(usd_value),'-', dayofmonth(bd2.usd_value)) AS bdate
                FROM ". TBL_USER_DATA. " bd2
                WHERE DATEDIFF(CONCAT(year('".DATETIME_NOW."')-1, '-', month(usd_value),'-', dayofmonth(bd2.usd_value)), '".DATETIME_NOW."') BETWEEN -$plg_show_zeitraum AND $plg_show_future
                        AND usd_usf_id = ". $g_current_user->getProperty("Geburtstag", "usf_id"). ")
        UNION
            (SELECT 
                usd_usr_id,
                usd_value AS bday,
                CONCAT(year('".DATETIME_NOW."')+1, '-', month(usd_value),'-', dayofmonth(bd3.usd_value)) AS bdate
                FROM ". TBL_USER_DATA. " bd3
                WHERE DATEDIFF(CONCAT(year('".DATETIME_NOW."')+1, '-', month(usd_value),'-', dayofmonth(bd3.usd_value)), '".DATETIME_NOW."') BETWEEN -$plg_show_zeitraum AND $plg_show_future
                        AND usd_usf_id = ". $g_current_user->getProperty("Geburtstag", "usf_id"). ")
         ) AS birthday
               ON birthday.usd_usr_id = usr_id
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
              AND mem_begin <= '".DATE_NOW."'
              AND mem_end    > '".DATE_NOW."'
             JOIN ". TBL_ROLES. "
               ON mem_rol_id = rol_id
              AND rol_valid  = 1
             JOIN ". TBL_CATEGORIES. "
               ON rol_cat_id = cat_id
              AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
            WHERE usr_valid = 1
              AND mem_rol_id ".$rol_sql."
            ORDER BY days_to_bdate ".$sort_sql.", last_name, first_name ";
//echo $sql; exit();
$result = $g_db->query($sql);

$anz_geb = $g_db->num_rows($result);

echo '<div id="plugin_'. $plugin_folder. '">';

if($anz_geb > 0)
{
    if($plg_show_names_extern == 1 || $g_valid_login == 1)
    {
        
	    echo '<ul id="plgBirthdayNameList">';
            while($row = $g_db->fetch_array($result))
            {
                $plg_age = $row['age']; 

                // Anzeigeart des Namens beruecksichtigen
                if($plg_show_names == 2)        // Nachname, Vorname
                {
                    $plg_show_name = $row['last_name']. ", ". $row['first_name'];
                }
                elseif($plg_show_names == 3)    // Vorname
                {
                    $plg_show_name = $row['first_name'];
                }
                elseif($plg_show_names == 4)    // Loginname
                {
                    $plg_show_name = $row['usr_login_name'];
                }
                else                            // Vorname Nachname
                {
                    $plg_show_name = $row['first_name']. " ". $row['last_name'];
                }

                // Namen mit Alter und Mail-Link anzeigen
                if(strlen($row['email']) > 0
                && ($g_valid_login || $plg_show_email_extern == 1))
                {
                    if($g_valid_login)
                    {
                        $plg_show_name = '<a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='. $row['usr_id']. '" 
                            target="'. $plg_link_target. '" title="Profil aufrufen">'. $plg_show_name. '</a>
                            <a class="iconLink" href="'. $g_root_path. '/adm_program/modules/mail/mail.php?usr_id='. $row['usr_id']. '"><img 
                            src="'. THEME_PATH. '/icons/email.png" alt="E-Mail senden" title="E-Mail senden" /></a>';
                    }
                    else
                    {
                        $plg_show_name = $plg_show_name. 
                            '<a class="iconLink" href="mailto:'. $row['email']. '"><img 
                            src="'. THEME_PATH. '/icons/email.png" alt="E-Mail senden" title="E-Mail senden" /></a>';
                    }
                }
                else
                {
                    // Soll der Name auch für nicht angemeldete Benutzer angezeigt werden, dann ab festgelegtem Alter statt Vorname die Anrede verwenden.
                    if($g_valid_login == false
                    && $plg_show_alter_anrede <= $plg_age)
                    {
                        if (($row->gender) > 1)
                        {
                            $plg_show_name = 'Frau '.$row->last_name;
                        }
                        else
                        {
                            // Eine kleine Feinheit zur Textanpassung bei den Herren ;-)
                            if ($plg_show_zeitraum > 0)
                            {
                                $plg_show_name = 'Herrn '.$row->last_name;
                            }
                            else
                            {
                                $plg_show_name = 'Herr '.$row->last_name;
                            }
                        }
                    }
                }

                // Soll das Alter auch für nicht angemeldete Benutzer angezeigt werden?
                if($plg_show_names_extern < 2 || $g_valid_login == true)
                {
                    // Geburtstagskinder am aktuellen Tag bekommen anderen Text
                    if($row['days_to_bdate'] == 0)
                    {
                        // Die Anzeige der Geburtstage folgt nicht mehr als Liste, sondern mittels div-Tag
                        echo '<li><span id="plgBirthdayNameHighlight">'.$plg_show_name.'</span> wird <span id="plgBirthdayDateHighlight"> heute </span>'.$plg_age.'</li>';
                    }
                    else
                    {
                        $plg_date = mysqldatetime('d.m.y', $row['bdate']);
                        $plg_age = $row['age'];
                        $plg_dtb = $row['days_to_bdate'];
                        $plg_tage = '';
                        $plg_alter_text = '';

                        if ($plg_dtb < 0) 
                        {
                            if($plg_dtb == -1)
                            {
                                $plg_alter_text = 'wurde gestern';
                            }
                            else
                            {
                                $plg_alter_text = 'wurde';
                                $plg_tage = 'vor '. -$plg_dtb. ' Tagen';
                            }
                        } 
                        elseif ($plg_dtb > 0) 
                        {
                            if($plg_dtb == 1)
                            {
                                $plg_alter_text = 'wird morgen';
                            }
                            else
                            {
                                $plg_alter_text = 'wird';
                                $plg_tage = 'in '. $plg_dtb. ' Tagen';
                            }
                        }
                        // Die Anzeige der Geburtstage folgt nicht mehr als Liste, sondern mittels div-Tag
                        if($plg_dtb < -0)
                        {
                            // liegt der Geburtstag in der Vergangenheit, dann CSS HighlightAGO verwenden
                            echo '<li><span id="plgBirthdayNameHighlightAgo">'.$plg_show_name.'</span> '.$plg_alter_text.' <span id="plgBirthdayNameHighlightAgo">'.$plg_age.' Jahre</span> '.$plg_tage.', am <span id="plgBirthdayDateHighlightAgo">'.$plg_date.'</span><br/></li>';
                        }
                        if ($plg_dtb > 0)
                        {
                            // liegt der Geburtstag in der Zukunft, dann CSS HighlightFUTURE verwenden
                            echo '<li><span id="plgBirthdayNameHighlightFuture">'.$plg_show_name.'</span> '.$plg_alter_text.' <span id="plgBirthdayNameHighlightFuture">'.$plg_age.' Jahre</span> '.$plg_tage.', am <span id="plgBirthdayDateHighlightFuture">'.$plg_date.'</span><br/></li>';
                        }	
                    }
                }		
            }
        echo '</ul>';
    }
    else
    {
        if($anz_geb == 1)
        {
            echo '<p>Heute hat ein Benutzer Geburtstag !</p>';
        }
        else
        {
            echo '<p>Heute haben '.$anz_geb.' Benutzer Geburtstag !</p>';
        }
    }
}
else
{
    // Bei entsprechend gesetzter Konfiguration wird auch im Fall, dass keiner Geburtstag hat, eine Meldung ausgegeben.
    if($plg_show_hinweis_keiner == 0)
    {
        echo '<p>Heute hat keiner Geburtstag.</p>';
    }
}

echo '</div>';

?>
