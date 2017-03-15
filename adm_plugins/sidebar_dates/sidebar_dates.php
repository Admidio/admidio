<?php
/**
 ***********************************************************************************************
 * Sidebar Dates
 *
 * Version 1.8.0
 *
 * Plugin das die letzten X Termine in einer schlanken Oberflaeche auflistet
 * und so ideal in einer Seitenleiste eingesetzt werden kann
 *
 * Compatible with Admidio version 3.2
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// create path to plugin
$plugin_folder_pos = strpos(__FILE__, 'adm_plugins') + 11;
$plugin_file_pos   = strpos(__FILE__, 'sidebar_dates.php');
$plugin_folder     = substr(__FILE__, $plugin_folder_pos + 1, $plugin_file_pos - $plugin_folder_pos - 2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. '/../adm_program/system/common.php');
require_once(PLUGIN_PATH. '/'.$plugin_folder.'/config.php');

// integrate language file of plugin to Admidio language object
$gL10n->addLanguagePath(PLUGIN_PATH. '/'.$plugin_folder.'/languages');

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(!isset($plg_dates_count) || !is_numeric($plg_dates_count))
{
    $plg_dates_count = 2;
}

if(!isset($plg_dates_show_preview) || !is_numeric($plg_dates_show_preview))
{
    $plg_dates_show_preview = 0;
}

if(!isset($plgShowFullDescription) || !is_numeric($plgShowFullDescription))
{
    $plgShowFullDescription = 1;
}

if(!isset($plg_show_date_end) || !is_numeric($plg_show_date_end))
{
    $plg_show_date_end = 1;
}

if(!isset($plg_max_char_per_word) || !is_numeric($plg_max_char_per_word))
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

if(!isset($plg_kal_cat))
{
    $plg_kal_cat = array('all');
}

if(!isset($plg_show_headline) || !is_numeric($plg_show_headline))
{
    $plg_show_headline = 1;
}

// Prüfen ob the Link-URL gesetzt wurde oder leer ist
// wenn leer, dann Standardpfad zum Admidio-Modul
if(!isset($plg_link_url) || $plg_link_url === '')
{
    $plg_link_url = ADMIDIO_URL . FOLDER_MODULES . '/dates/dates.php';
}

// create Object
$plgDates = new ModuleDates();

// read events for output
$plgDates->setDateRange();
$plgDatesResult = $plgDates->getDataSet(0, $plg_dates_count);

$plg_date = new TableDate($gDb);

echo '<div id="plugin_'. $plugin_folder. '" class="admidio-plugin-content">';
if($plg_show_headline)
{
    echo '<h3>'.$gL10n->get('PLG_DATES_HEADLINE').'</h3>';
}

if($plgDatesResult['numResults'] > 0)
{
    foreach($plgDatesResult['recordset'] as $plg_row)
    {
        $plg_date->clear();
        $plg_date->setArray($plg_row);
        $plg_html_end_date = '';

        echo '<h4>'.$plg_date->getValue('dat_begin', $gPreferences['system_date']). '&nbsp;&nbsp;';

        if ($plg_date->getValue('dat_all_day') != 1)
        {
            echo $plg_date->getValue('dat_begin', $gPreferences['system_time']);
        }

        // Bis-Datum und Uhrzeit anzeigen
        if($plg_show_date_end)
        {
            if($plg_date->getValue('dat_begin', $gPreferences['system_date']) !== $plg_date->getValue('dat_end', $gPreferences['system_date']))
            {
                $plg_html_end_date .= $plg_date->getValue('dat_end', $gPreferences['system_date']);
            }
            if ($plg_date->getValue('dat_all_day') != 1)
            {
                $plg_html_end_date .= ' '. $plg_date->getValue('dat_end', $gPreferences['system_time']);
            }
            if($plg_html_end_date !== '')
            {
                $plg_html_end_date = ' - '. $plg_html_end_date;
            }
        }

        // ?ber $plg_link_url wird die Verbindung zum Date-Modul hergestellt.
        echo $plg_html_end_date. '<br /><a class="'. $plg_link_class. '" href="'. $plg_link_url. '?view_mode=html&amp;view=detail&amp;id='. $plg_date->getValue('dat_id'). '" target="'. $plg_link_target. '">';

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
                    $plg_new_headline .= ' '. substr($plg_value, 0, $plg_max_char_per_word). '-<br />'.
                                        substr($plg_value, $plg_max_char_per_word);
                }
                else
                {
                    $plg_new_headline .= ' '. $plg_value;
                }
            }
            echo $plg_new_headline. '</a></h4>';
        }
        else
        {
            echo $plg_date->getValue('dat_headline'). '</a></h4>';
        }

        // show preview text
        if($plgShowFullDescription === 1)
        {
            echo '<div>'.$plg_date->getValue('dat_description').'</div>';
        }
        elseif($plg_dates_show_preview > 0)
        {
            // remove all html tags except some format tags
            $textPrev = strip_tags($plg_date->getValue('dat_description'), '<p></p><br><br/><br /><i></i><b></b><strong></strong><em></em>');

            // read first x chars of text and additional 15 chars. Then search for last space and cut the text there
            $textPrev = substr($textPrev, 0, $plg_dates_show_preview + 15);
            $textPrev = substr($textPrev, 0, strrpos($textPrev, ' ')).' ...
                <a class="'. $plg_link_class. '"  target="'. $plg_link_target. '"
                    href="'.$plg_link_url.'?view_mode=html&amp;view=detail&amp;id='. $plg_date->getValue('dat_id'). '"><span
                    class="glyphicon glyphicon-circle-arrow-right" aria-hidden="true"></span> '.$gL10n->get('PLG_SIDEBAR_DATES_MORE').'</a>';
            $textPrev = pluginDatesCloseTags($textPrev);

            echo '<div>'.$textPrev.'</div>';
        }

        echo '<hr>';
    }

    // forward to $plg_link_url without any addional parameters
    echo '<a class="'. $plg_link_class. '" href="'. $plg_link_url. '" target="'. $plg_link_target. '">'.$gL10n->get('PLG_DATES_ALL_EVENTS').'</a>';
}
else
{
    echo $gL10n->get('SYS_NO_ENTRIES');
}

echo '</div>';

/**
 * Function will analyse a html string and close open html tags at the end of the string.
 * @param string $html The html string to parse.
 * @return string Returns the parsed html string with all tags closed.
 */
function pluginDatesCloseTags($html) {
    preg_match_all('#<(?!meta|img|br|hr|input\b)\b([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
    $openedtags = $result[1];
    preg_match_all('#</([a-z]+)>#iU', $html, $result);
    $closedtags = $result[1];
    $len_opened = count($openedtags);
    if (count($closedtags) === $len_opened)
    {
        return $html;
    }
    $openedtags = array_reverse($openedtags);
    for ($i = 0; $i < $len_opened; $i++)
    {
        if (!in_array($openedtags[$i], $closedtags, true))
        {
            $html .= '</'.$openedtags[$i].'>';
        }
        else
        {
            unset($closedtags[array_search($openedtags[$i], $closedtags, true)]);
        }
    }
    return $html;
}
