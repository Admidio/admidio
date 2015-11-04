<?php
/******************************************************************************
 * Sidebar Announcements
 *
 * Version 1.7.1
 *
 * Plugin das die letzten X Ankuendigungen in einer schlanken Oberflaeche auflistet
 * und so ideal in einer Seitenleiste eingesetzt werden kann
 *
 * Compatible with Admidio version 3.0
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// create path to plugin
$plugin_folder_pos = strpos(__FILE__, 'adm_plugins') + 11;
$plugin_file_pos   = strpos(__FILE__, 'sidebar_announcements.php');
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. '/../adm_program/system/common.php');
require_once(PLUGIN_PATH. '/'.$plugin_folder.'/config.php');

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(isset($plg_announcements_count) == false || is_numeric($plg_announcements_count) == false)
{
    $plg_announcements_count = 2;
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

if(isset($plg_show_preview) == false || is_numeric($plg_show_preview) == false)
{
    $plg_show_preview = 0;
}

// Sprachdatei des Plugins einbinden
$gL10n->addLanguagePath(PLUGIN_PATH. '/'.$plugin_folder.'/languages');

//Objekt anlegen
$plg_announcements = new ModuleAnnouncements();

echo '<div id="plugin_'. $plugin_folder. '" class="admidio-plugin-content">';
if($plg_show_headline==1)
{
    echo '<h3>'.$gL10n->get('PLG_SIDEBAR_ANNOUNCEMENTS_HEADLINE').'</h3>';
}

if($plg_announcements->getDataSetCount() == 0)
{
    echo $gL10n->get('SYS_NO_ENTRIES');
}
else
{
    //Daten holen
    $plg_getAnnouncements = $plg_announcements->getDataSet(0, $plg_announcements_count);
    $plg_announcement = new TableAnnouncement($gDb);

    foreach($plg_getAnnouncements['recordset'] as $plg_row)
    {
        $plg_announcement->clear();
        $plg_announcement->setArray($plg_row);

        echo '<div><a class="'. $plg_link_class. '" href="'. $g_root_path. '/adm_program/modules/announcements/announcements.php?id='. $plg_announcement->getValue("ann_id"). '&amp;headline='. $gL10n->get('PLG_ANNOUNCEMENTS_HEADLINE'). '" target="'. $plg_link_target. '">';

        if($plg_max_char_per_word > 0)
        {
            $plg_new_headline = "";
            unset($plg_words);

            // Woerter unterbrechen, wenn sie zu lang sind
            $plg_words = explode(" ", $plg_announcement->getValue('ann_headline'));

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
            echo $plg_new_headline.'</a></div>';
        }
        else
        {
            echo $plg_announcement->getValue('ann_headline').'</a></div>';
        }

        // Vorschau-Text anzeigen
        if($plg_show_preview > 0)
        {
            // Anfang des Ankündigungs-Textes auslesen. Plus 15 Zeichen, um am Ende eines Wortes abzubrechen
            $textPrev = substr($plg_announcement->getValue('ann_description'), 0, $plg_show_preview + 15);
            $textPrev = substr($textPrev, 0, strrpos($textPrev, ' ')).' ...';

            echo '<div>'.$textPrev.'
            <a class="'. $plg_link_class. '"  target="'. $plg_link_target. '"
                href="'. $g_root_path. '/adm_program/modules/announcements/announcements.php?id='. $plg_announcement->getValue("ann_id"). '&amp;headline='. $gL10n->get('PLG_ANNOUNCEMENTS_HEADLINE'). '"><span
                class="glyphicon glyphicon-circle-arrow-right" aria-hidden="true"></span> '.$gL10n->get('PLG_SIDEBAR_ANNOUNCEMENTS_MORE').'</a></div>';
        }

        echo '<div><i>('. $plg_announcement->getValue('ann_timestamp_create', $gPreferences['system_date']). ')</i></div>';

        echo '<hr />';

    }

    echo '<a class="'.$plg_link_class.'" href="'.$g_root_path.'/adm_program/modules/announcements/announcements.php" target="'.$plg_link_target.'">'.$gL10n->get('PLG_SIDEBAR_ANNOUNCEMENTS_ALL_ANNOUNCEMENTS').'</a>';
}
echo '</div>';
