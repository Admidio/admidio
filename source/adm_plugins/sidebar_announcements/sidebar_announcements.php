<?php
/******************************************************************************
 * Sidebar Announcements
 *
 * Version 1.1.0
 *
 * Plugin das die letzten X Ankuendigungen in einer schlanken Oberflaeche auflistet
 * und so ideal in einer Seitenleiste eingesetzt werden kann
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
$plugin_file_pos   = strpos(__FILE__, "sidebar_announcements.php");
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/$plugin_folder/config.php");

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

if(isset($plg_headline))
{
    $plg_headline = strip_tags($plg_headline);
}
else
{
    $plg_headline = "Ank&uuml;ndigungen";
}

$act_date = date("Y.m.d 00:00:00", time());
// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
$g_db->setCurrentDB();

// alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
         WHERE org_org_id_parent = ". $g_current_organization->getValue("org_id");
if($g_current_organization->getValue("org_org_id_parent") > 0)
{
    $sql = $sql. " OR org_id = ". $g_current_organization->getValue("org_org_id_parent");
}
$result = $g_db->query($sql);

$organizations = null;
$i             = 0;

while($row = $g_db->fetch_object($result))
{
    if($i > 0) 
    {
        $organizations = $organizations. ", ";
    }
    $organizations = $organizations. "'$row->org_shortname'";
    $i++;
}

if(strlen($organizations) > 0)
{
    $sql    = "SELECT * FROM ". TBL_ANNOUNCEMENTS. "
                WHERE (  ann_org_shortname = '$g_organization'
                      OR (   ann_global   = 1
                         AND ann_org_shortname IN ($organizations) ))
                ORDER BY ann_timestamp DESC
                LIMIT $plg_announcements_count";
}
else
{
    $sql    = "SELECT * FROM ". TBL_ANNOUNCEMENTS. "
                WHERE ann_org_shortname = '$g_organization'
                ORDER BY ann_timestamp DESC
                LIMIT $plg_announcements_count";
}
$result = $g_db->query($sql);

echo '<div id="plugin_'. $plugin_folder. '">';

if($g_db->num_rows($result) > 0)
{
    while($row = $g_db->fetch_object($result))
    {
        echo '<a class="'. $plg_link_class. '" href="'. $g_root_path. '/adm_program/modules/announcements/announcements.php?id='. $row->ann_id. '&amp;headline='. $plg_headline. '" target="'. $plg_link_target. '">';
        
        if($plg_max_char_per_word > 0)
        {
            $new_headline = "";
            unset($words);
        
            // Woerter unterbrechen, wenn sie zu lang sind
            $words = explode(" ", $row->ann_headline);
            
            for($i = 0; $i < count($words); $i++)
            {
                if(strlen($words[$i]) > $plg_max_char_per_word)
                {
                    $new_headline = "$new_headline ". substr($words[$i], 0, $plg_max_char_per_word). "-<br />". 
                                    substr($words[$i], $plg_max_char_per_word);
                }
                else
                {
                    $new_headline = "$new_headline ". $words[$i];
                }
            }
            echo "$new_headline</a><br />";
        }
        else
        {
            echo "$row->ann_headline</a><br />";
        }
         
        echo "(&nbsp;". mysqldatetime("d.m.y", $row->ann_timestamp). "&nbsp;)<hr />";
    }
    
    echo "<a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/modules/announcements/announcements.php?headline=$plg_headline\" target=\"$plg_link_target\">Alle $plg_headline</a>";
}
else
{
    echo "Es wurden noch keine $plg_headline erfasst.";
}

echo '</div>';

?>