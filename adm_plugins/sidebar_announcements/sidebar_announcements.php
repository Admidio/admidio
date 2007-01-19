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
 * Konfigurationsvariablen:
 *
 * plg_announcements_count : Anzahl der Ankuendigungen, die angezeigt werden sollen (Default = 2)
 * plg_max_char_per_word :   Maximale Anzahl von Zeichen in einem Wort, 
 *                           bevor ein Zeilenumbruch kommt (Default = deaktiviert) 
 * plg_headline :    Wahlweise kann hier ein anderer Titel fuer die Ankuendigungen angegeben werden
 * plg_link_class  : Name der Klasse fuer Links
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
define('ADM_PATH', substr(__FILE__, 0, strpos(__FILE__, "adm_plugins")-1));
require_once(ADM_PATH. "/adm_program/system/common.php");

// Konfigurationsvariablen pruefen
// dabei muss beachtet werden, dass diese evtl. per GET uebergeben oder 
// innerhalb des aufrufenden Scripts einfach nur gesetzt werden
if(isset($_GET['plg_announcements_count']) && is_numeric($_GET['plg_announcements_count']))
{
    $plg_announcements_count = $_GET['plg_announcements_count'];
}
if(isset($plg_announcements_count) == false || is_numeric($plg_announcements_count) == false)
{
    $plg_announcements_count = 2;
}

if(isset($_GET['plg_max_char_per_word']) && is_numeric($_GET['plg_max_char_per_word']))
{
    $plg_max_char_per_word = $_GET['plg_max_char_per_word'];
}
if(isset($plg_max_char_per_word) == false || is_numeric($plg_max_char_per_word) == false)
{
    $plg_max_char_per_word = 0;
}

if(isset($_GET['plg_link_class']))
{
    $plg_link_class = strStripTags($_GET['plg_link_class']);
}
if(isset($plg_link_class) == true)
{
    $plg_link_class = strStripTags($plg_link_class);
}
else
{
    $plg_link_class = " ";
}

if(isset($_GET['plg_headline']))
{
    $plg_headline = strStripTags($_GET['plg_headline']);
}
if(isset($plg_headline) == true)
{
    $plg_headline = strStripTags($plg_headline);
}
else
{
    $plg_headline = "Ankündigungen";
}

$act_date = date("Y.m.d 00:00:00", time());
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
                LIMIT $plg_announcements_count ";
}
else
{
    $sql    = "SELECT * FROM ". TBL_ANNOUNCEMENTS. "
                WHERE ann_org_shortname = '$g_organization'
                ORDER BY ann_timestamp DESC
                LIMIT $plg_announcements_count ";
}
$result = mysql_query($sql, $g_adm_con);
db_error($result);

while($row = mysql_fetch_object($result))
{
    echo "<a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/modules/announcements/announcements.php?id=$row->ann_id&amp;headline=$plg_headline\">";
    
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
        echo "$new_headline</a><br />-----<br />";
    }
    else
    {
        echo "$row->ann_headline</a><br />";
    }
     
    echo "(&nbsp;". mysqldatetime("d.m.y", $row->ann_timestamp). "&nbsp;)<br />-----<br />";
}

echo "<a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/modules/announcements/announcements.php?headline=$plg_headline\">mehr</a>";
?>