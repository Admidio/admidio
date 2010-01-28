<?php
/******************************************************************************
 * Sidebar Dates
 *
 * Version 1.2.3
 *
 * Plugin das die letzten X Termine in einer schlanken Oberflaeche auflistet
 * und so ideal in einer Seitenleiste eingesetzt werden kann
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
$plugin_file_pos   = strpos(__FILE__, 'sidebar_dates.php');
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. '/../adm_program/system/common.php');
require_once(PLUGIN_PATH. '/../adm_program/system/classes/table_date.php');
require_once(PLUGIN_PATH. '/'.$plugin_folder.'/config.php');

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
    $plg_link_class = '';
}

if(isset($plg_link_target))
{
    $plg_link_target = strip_tags($plg_link_target);
}
else
{
    $plg_link_target = '_self';
}

// Prüfen ob the Link-URL gesetzt wurde oder leer ist
// wenn leer, dann Standardpfad zum Admidio-Modul
if(isset($plg_link_url) == false || ($plg_link_url) =="")
{
$plg_link_url = $g_root_path."/adm_program/modules/dates/dates.php";
}

// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
$g_db->setCurrentDB();

// alle Organisationen finden, in denen die Orga entweder Mutter oder Tochter ist
$plg_organizations = '';
$plg_arr_orgas = $g_current_organization->getReferenceOrganizations(true, true);

foreach($plg_arr_orgas as $key => $value)
{
	$plg_organizations = $plg_organizations. $key. ', ';
}
$plg_organizations = $plg_organizations. $g_current_organization->getValue('org_id');

// Wenn User nicht eingeloggt ist, Kalender, die hidden sind, aussortieren
$hidden = '';
if ($g_valid_login == false)
{
	$hidden = ' AND cat_hidden = 0 ';
}

// nun alle relevanten Termine finden
$sql    = 'SELECT * FROM '. TBL_DATES. ', '. TBL_CATEGORIES. '
            WHERE dat_cat_id = cat_id
              AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
                  OR (   dat_global  = 1
                     AND cat_org_id IN ('.$plg_organizations.') ) )
			  AND (  dat_begin >= "'.DATE_NOW.'"
                  OR dat_end   >  "'.DATE_NOW.' 00:00:00" )
                  '.$hidden.'
			ORDER BY dat_begin ASC
			LIMIT '.$plg_dates_count;
$plg_result = $g_db->query($sql);
$plg_date = new TableDate($g_db);

echo '<div id="plugin_'. $plugin_folder. '">';

if($g_db->num_rows($plg_result) > 0)
{
    while($plg_row = $g_db->fetch_object($plg_result))
    {
        $plg_date->clear();
        $plg_date->setArray($plg_row);
        $plg_html_end_date = '';

        echo $plg_date->getValue('dat_begin', $g_preferences['system_date']). '&nbsp;&nbsp;';

        if ($plg_date->getValue('dat_all_day') != 1)
        {
            echo $plg_date->getValue('dat_begin', $g_preferences['system_time']);
        }

        // Bis-Datum und Uhrzeit anzeigen
        if($plg_show_date_end)
        {
            if($plg_date->getValue('dat_begin', $g_preferences['system_date']) != $plg_date->getValue('dat_end', $g_preferences['system_date']))
            {
                $plg_html_end_date .= $plg_date->getValue('dat_end', $g_preferences['system_date']);
            }
            if ($plg_date->getValue('dat_all_day') != 1)
            {
                $plg_html_end_date .= $plg_date->getValue('dat_end', $g_preferences['system_time']);
            }
            if(strlen($plg_html_end_date) > 0)
            {
                $plg_html_end_date = ' - '. $plg_html_end_date;
            }
        }

        // Über $plg_link_url wird die Verbindung zum Date-Modul hergestellt.
        echo $plg_html_end_date. '<br /><a class="'. $plg_link_class. '" href="'. $plg_link_url. '?id='. $plg_date->getValue("dat_id"). '" target="'. $plg_link_target. '">';

        if($plg_max_char_per_word > 0)
        {
            $plg_new_headline = '';
            unset($plg_words);

            // Woerter unterbrechen, wenn sie zu lang sind
            $plg_words = explode(' ', $plg_date->getValue('dat_headline'));

            foreach($plg_words as $plg_key => $plg_value)
            {
                if(strlen($plg_value) > $plg_max_char_per_word)
                {
                    $plg_new_headline = $plg_new_headline.' '. substr($plg_value, 0, $plg_max_char_per_word). '-<br />'.
                                    substr($plg_value, $plg_max_char_per_word);
                }
                else
                {
                    $plg_new_headline = $plg_new_headline.' '. $plg_value;
                }
            }
            echo $plg_new_headline. '</a><hr />';
        }
        else
        {
            echo $plg_date->getValue("dat_headline"). '</a><hr />';
        }
    }

    // WEiterleitung über $plg_link_url ohne weiteren Übergabeparameter
    echo '<a class="'. $plg_link_class. '" href="'. $plg_link_url. '" target="'. $plg_link_target. '">Alle Termine</a>';
}
else
{
    echo 'Es sind keine Termine vorhanden.';
}

echo '</div>';
?>