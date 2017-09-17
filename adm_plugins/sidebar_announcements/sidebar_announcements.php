<?php
/**
 ***********************************************************************************************
 * Sidebar Announcements
 *
 * Version 2.0.0
 *
 * Plugin das die letzten X Ankuendigungen in einer schlanken Oberflaeche auflistet
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
$plugin_file_pos   = strpos(__FILE__, 'sidebar_announcements.php');
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
if(!isset($plg_announcements_count) || !is_numeric($plg_announcements_count))
{
    $plg_announcements_count = 2;
}
if(!isset($plg_max_char_per_word) || !is_numeric($plg_max_char_per_word))
{
    $plg_max_char_per_word = 0;
}

if(!isset($plgShowFullDescription) || !is_numeric($plgShowFullDescription))
{
    $plgShowFullDescription = 0;
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

if(!isset($plg_show_preview) || !is_numeric($plg_show_preview))
{
    $plg_show_preview = 0;
}

if(!isset($plg_show_headline) || !is_numeric($plg_show_headline))
{
    $plg_show_headline = 1;
}

if(!isset($plg_headline) || $plg_headline === '')
{
    $plg_headline = $gL10n->get('PLG_SIDEBAR_ANNOUNCEMENTS_HEADLINE');
}
elseif(strpos($plg_headline, '_') === 3)
{
    // if text is a translation-id then translate it
    $plg_headline = $gL10n->get($plg_headline);
}

// create announcements object
$plg_announcements = new ModuleAnnouncements();

echo '<div id="plugin_'. $plugin_folder. '" class="admidio-plugin-content">';

if($plg_show_headline === 1)
{
    echo '<h3>'.$plg_headline.'</h3>';
}

if($plg_announcements->getDataSetCount() === 0)
{
    echo $gL10n->get('SYS_NO_ENTRIES');
}
else
{
    // get announcements data
    $plg_getAnnouncements = $plg_announcements->getDataSet(0, $plg_announcements_count);
    $plg_announcement = new TableAnnouncement($gDb);

    foreach($plg_getAnnouncements['recordset'] as $plg_row)
    {
        $plg_announcement->clear();
        $plg_announcement->setArray($plg_row);

        echo '<h4><a class="'. $plg_link_class. '" href="'. ADMIDIO_URL. FOLDER_MODULES. '/announcements/announcements.php?id='. $plg_announcement->getValue('ann_id'). '&amp;headline='. $plg_headline. '" target="'. $plg_link_target. '">';

        if($plg_max_char_per_word > 0)
        {
            $plg_new_headline = '';
            unset($plg_words);

            // Woerter unterbrechen, wenn sie zu lang sind
            $plg_words = explode(' ', $plg_announcement->getValue('ann_headline'));

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
            echo $plg_new_headline.'</a></h4>';
        }
        else
        {
            echo $plg_announcement->getValue('ann_headline').'</a></h4>';
        }

        // show preview text
        if($plgShowFullDescription === 1)
        {
            echo '<div>'.$plg_announcement->getValue('ann_description').'</div>';
        }
        elseif($plg_show_preview > 0)
        {
            // remove all html tags except some format tags
            $textPrev = strip_tags($plg_announcement->getValue('ann_description'), '<p></p><br><br/><br /><i></i><b></b><strong></strong><em></em>');

            // read first x chars of text and additional 15 chars. Then search for last space and cut the text there
            $textPrev = substr($textPrev, 0, $plg_show_preview + 15);
            $textPrev = substr($textPrev, 0, strrpos($textPrev, ' ')).' ...
                <a class="'. $plg_link_class. '"  target="'. $plg_link_target. '"
                    href="'. ADMIDIO_URL. FOLDER_MODULES. '/announcements/announcements.php?id='. $plg_announcement->getValue('ann_id'). '&amp;headline='. $plg_headline. '"><span
                    class="glyphicon glyphicon-circle-arrow-right" aria-hidden="true"></span> '.$gL10n->get('PLG_SIDEBAR_ANNOUNCEMENTS_MORE').'</a>';
            $textPrev = pluginAnnouncementsCloseTags($textPrev);

            echo '<div>'.$textPrev.'</div>';
        }

        echo '<div><em>('. $plg_announcement->getValue('ann_timestamp_create', $gPreferences['system_date']). ')</em></div>';

        echo '<hr />';

    }

    echo '<a class="'.$plg_link_class.'" href="'.ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements.php?headline='.$plg_headline.'" target="'.$plg_link_target.'">'.$gL10n->get('PLG_SIDEBAR_ANNOUNCEMENTS_ALL_ENTRIES').'</a>';
}
echo '</div>';

/**
 * Function will analyse a html string and close open html tags at the end of the string.
 * @param string $html The html string to parse.
 * @return string Returns the parsed html string with all tags closed.
 */
function pluginAnnouncementsCloseTags($html) {
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
