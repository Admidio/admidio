<?php
/******************************************************************************
 * Sidebar Dates
 *
 * Version 1.1.0
 *
 * Plugin das die letzten X Termine in einer schlanken Oberflaeche auflistet
 * und so ideal in einer Seitenleiste eingesetzt werden kann
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
$plugin_file_pos   = strpos(__FILE__, "sidebar_dates.php");
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/../adm_program/system/date_class.php");
require_once(PLUGIN_PATH. "/$plugin_folder/config.php");
 
// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(isset($plg_dates_count) == false || is_numeric($plg_dates_count) == false)
{
    $plg_dates_count = 2;
}

if(isset($plg_show_date_end) == false || is_numeric($plg_show_date_end) == false)
{
    $plg_show_date_end = 1;
}

if(isset($plg_max_char_per_word) == false || is_numeric($plg_max_char_per_word) == false)
{
    $plg_max_char_per_word = 0;
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

$act_date = date("Y.m.d H:i:s", time());
// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
$g_db->setCurrentDB();

// alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
         WHERE org_org_id_parent = ". $g_current_organization->getValue("org_id");
if($g_current_organization->getValue("org_org_id_parent") > 0)
{
    $sql = $sql. " OR org_id = ". $g_current_organization->getValue("org_org_id_parent");
}
$plg_result = $g_db->query($sql);

$plg_organizations = null;
$i             = 0;

while($plg_row = $g_db->fetch_object($plg_result))
{
    if($i > 0) 
    {
        $plg_organizations = $plg_organizations. ", ";
    }
    $plg_organizations = $plg_organizations. "'$plg_row->org_shortname'";
    $i++;
}

if(strlen($plg_organizations) > 0)
{
    $sql    = "SELECT * FROM ". TBL_DATES. "
                WHERE (  dat_org_shortname = '$g_organization'
                      OR (   dat_global   = 1
                         AND dat_org_shortname IN ($plg_organizations) ))
                  AND dat_end   >= '$act_date'
                ORDER BY dat_begin ASC
                LIMIT $plg_dates_count";
}
else
{
    $sql    = "SELECT * FROM ". TBL_DATES. "
                WHERE dat_org_shortname = '$g_organization'
                  AND dat_end   >= '$act_date'
                ORDER BY dat_begin ASC
                LIMIT $plg_dates_count";
}
$plg_result = $g_db->query($sql);
$plg_date = new Date($g_db);

echo '<div id="plugin_'. $plugin_folder. '">';

if($g_db->num_rows($plg_result) > 0)
{
    while($plg_row = $g_db->fetch_object($plg_result))
    {
        $plg_date->clear();
        $plg_date->setArray($plg_row);
        $plg_html_end_date = "";
        
        echo mysqldatetime("d.m.y", $plg_date->getValue("dat_begin")). '&nbsp;&nbsp;';
    
        if ($plg_date->getValue("dat_all_day") != 1)
        {
            echo mysqldatetime("h:i", $plg_date->getValue("dat_begin"));
        }
        
        // Bis-Datum und Uhrzeit anzeigen
        if($plg_show_date_end)
        {
            if(mysqldatetime("d.m.y", $plg_date->getValue("dat_begin")) != mysqldatetime("d.m.y", $plg_date->getValue("dat_end")))
            {
                $plg_html_end_date .= mysqldatetime("d.m.y", $plg_date->getValue("dat_end"));
            }
            if ($plg_date->getValue("dat_all_day") != 1)
            {
                $plg_html_end_date .= mysqldatetime("h:i", $plg_date->getValue("dat_end"));
            }
            if(strlen($plg_html_end_date) > 0)
            {
                $plg_html_end_date = ' - '. $plg_html_end_date;
            }
        }
    
        echo $plg_html_end_date. '<br /><a class="'. $plg_link_class. '" href="'. $g_root_path. '/adm_program/modules/dates/dates.php?id='. $plg_date->getValue("dat_id"). '" target="'. $plg_link_target. '">';
    
        if($plg_max_char_per_word > 0)
        {
            $plg_new_headline = "";
            unset($plg_words);
            
            // Woerter unterbrechen, wenn sie zu lang sind
            $plg_words = explode(" ", $plg_date->getValue("dat_headline"));
            
            for($i = 0; $i < count($plg_words); $i++)
            {
                if(strlen($plg_words[$i]) > $plg_max_char_per_word)
                {
                    $plg_new_headline = "$plg_new_headline ". substr($plg_date->getValue("dat_headline"), 0, $plg_max_char_per_word). "-<br />". 
                                    substr($plg_date->getValue("dat_headline"), $plg_max_char_per_word);
                }
                else
                {
                    $plg_new_headline = "$plg_new_headline ". $plg_words[$i];
                }
            }
            echo $plg_new_headline. '</a><hr />';
        }
        else
        {
            echo $plg_date->getValue("dat_headline"). '</a><hr />';
        }
    }
    
    echo '<a class="'. $plg_link_class. '" href="'. $g_root_path. '/adm_program/modules/dates/dates.php" target="'. $plg_link_target. '">Alle Termine</a>';
}
else
{
    echo 'Es sind keine Termine vorhanden.';
}

echo '</div>';
?>