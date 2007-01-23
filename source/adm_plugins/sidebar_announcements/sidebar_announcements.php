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
define('PLUGIN_PATH', substr(__FILE__, 0, strpos(__FILE__, "sidebar_announcements.php")-1));
require_once(PLUGIN_PATH. "/../../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/config.php");

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(!defined('PLG_DATES_COUNT'))
{
    define('PLG_DATES_COUNT', 2);
}
if(!defined('PLG_LINK_CLASS'))
{
    define('PLG_LINK_CLASS', '');
}
if(!defined('PLG_MAX_CHAR_PER_WORD'))
{
    define('PLG_MAX_CHAR_PER_WORD', 0);
}
if(!defined('PLG_HEADLINE'))
{
    define('PLG_HEADLINE', 'Ankündigungen');
}

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
                LIMIT ". PLG_ANNOUNCEMENTS_COUNT;
}
else
{
    $sql    = "SELECT * FROM ". TBL_ANNOUNCEMENTS. "
                WHERE ann_org_shortname = '$g_organization'
                ORDER BY ann_timestamp DESC
                LIMIT ". PLG_ANNOUNCEMENTS_COUNT;
}
$result = mysql_query($sql, $g_adm_con);
db_error($result);

while($row = mysql_fetch_object($result))
{
    echo "<a class=\"". PLG_LINK_CLASS. "\" href=\"$g_root_path/adm_program/modules/announcements/announcements.php?id=$row->ann_id&amp;headline=". utf8_decode(PLG_HEADLINE). "\">";
    
    if(PLG_MAX_CHAR_PER_WORD > 0)
    {
        $new_headline = "";
        unset($words);
    
        // Woerter unterbrechen, wenn sie zu lang sind
        $words = explode(" ", $row->ann_headline);
        
        for($i = 0; $i < count($words); $i++)
        {
            if(strlen($words[$i]) > PLG_MAX_CHAR_PER_WORD)
            {
                $new_headline = "$new_headline ". substr($words[$i], 0, PLG_MAX_CHAR_PER_WORD). "-<br />". 
                                substr($words[$i], PLG_MAX_CHAR_PER_WORD);
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

echo "<a class=\"". PLG_LINK_CLASS. "\" href=\"$g_root_path/adm_program/modules/announcements/announcements.php?headline=". utf8_decode(PLG_HEADLINE). "\">mehr</a>";
?>