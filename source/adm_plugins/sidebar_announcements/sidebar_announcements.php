<?php
/******************************************************************************
 * Plugin das die letzten X Ankuendigungen auflistet
 *
 * Compatible to Admidio-Versions 1.2 - 1.4
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

// Include von common 
if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, strpos(__FILE__, "sidebar_announcements")-1));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/sidebar_announcements/config.php");

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(is_numeric($plg_announcements_count) == false)
{
    $plg_announcements_count = 2;
}
if(is_numeric($plg_max_char_per_word) == false)
{
    $plg_max_char_per_word = 0;
}

$plg_link_class = strip_tags($plg_link_class);
$plg_headline = strip_tags($plg_headline);

$act_date = date("Y.m.d 00:00:00", time());
// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
mysql_select_db($g_adm_db, $g_adm_con );

// alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
         WHERE org_org_id_parent = $g_current_organization->id ";
if($g_current_organization->org_id_parent > 0)
{
    $sql = $sql. " OR org_id = $g_current_organization->org_id_parent ";
}
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$organizations = null;
$i             = 0;

while($row = mysql_fetch_object($result))
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
$result = mysql_query($sql, $g_adm_con);
db_error($result);

while($row = mysql_fetch_object($result))
{
    echo "<a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/modules/announcements/announcements.php?id=$row->ann_id&amp;headline=". utf8_decode($plg_headline). "\">";
    
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
     
    echo "(&nbsp;". mysqldatetime("d.m.y", $row->ann_timestamp). "&nbsp;)<br />-----<br />";
}

echo "<a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/modules/announcements/announcements.php?headline=". utf8_decode($plg_headline). "\">mehr</a>";
?>