<?php
/******************************************************************************
 * Plugin das die letzten X Termine auflistet
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
define('PLUGIN_PATH', substr(__FILE__, 0, strpos(__FILE__, "sidebar_dates.php")-1));
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
    $sql    = "SELECT * FROM ". TBL_DATES. "
                WHERE (  dat_org_shortname = '$g_organization'
                      OR (   dat_global   = 1
                         AND dat_org_shortname IN ($organizations) ))
                  AND (  dat_begin >= '$act_date'
                      OR dat_end   >= '$act_date' )
                ORDER BY dat_begin ASC
                LIMIT ". PLG_DATES_COUNT;
}
else
{
    $sql    = "SELECT * FROM ". TBL_DATES. "
                WHERE dat_org_shortname = '$g_organization'
                  AND (  dat_begin >= '$act_date'
                      OR dat_end   >= '$act_date' )
                ORDER BY dat_begin ASC
                LIMIT ". PLG_DATES_COUNT;
}
$result = mysql_query($sql, $g_adm_con);
db_error($result);

while($row = mysql_fetch_object($result))
{
    echo mysqldatetime("d.m.y", $row->dat_begin). "&nbsp;&nbsp;";

    if (mysqldatetime("h:i", $row->dat_begin) != "00:00")
    {
        echo mysqldatetime("h:i", $row->dat_begin);
    }

    echo "<br /><a class=\"". PLG_LINK_CLASS. "\" href=\"$g_root_path/adm_program/modules/dates/dates.php?id=$row->dat_id\">";

    if(PLG_MAX_CHAR_PER_WORD > 0)
    {
        $new_headline = "";
        unset($words);
        
        // Woerter unterbrechen, wenn sie zu lang sind
        $words = explode(" ", $row->dat_headline);
        
        for($i = 0; $i < count($words); $i++)
        {
            if(strlen($words[$i]) > PLG_MAX_CHAR_PER_WORD)
            {
                $new_headline = "$new_headline ". substr($row->dat_headline, 0, PLG_MAX_CHAR_PER_WORD). "-<br />". 
                                substr($row->dat_headline, PLG_MAX_CHAR_PER_WORD);
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
        echo "$row->dat_headline</a><br />-----<br />";
    }
}

echo "<a class=\"". PLG_LINK_CLASS. "\" href=\"$g_root_path/adm_program/modules/dates/dates.php\">Alle Termine</a>";
?>