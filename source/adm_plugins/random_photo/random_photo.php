<?php
/******************************************************************************
 * Sidebar Announcements
 *
 * Version 1.0.2
 *
 * Plugin das die letzten X Ankuendigungen in einer schlanken Oberflaeche auflistet
 * und so ideal in einer Seitenleiste eingesetzt werden kann
 *
 * Kompatible ab Admidio-Versions 1.4.1
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
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
    define('PLUGIN_PATH', substr(__FILE__, 0, strpos(__FILE__, "random_photo")-1));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(PLUGIN_PATH. "/random_photo/config.php");

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(isset($plg_max_char_per_word) == false || is_numeric($plg_max_char_per_word) == false)
{
    $plg_max_char_per_word = 0;
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
    $plg_headline = "Fotos";
}

if(!isset($plg_photos_max_width) || !is_numeric($plg_photos_max_width))
{
    $plg_photos_max_width = 150;
}
if(!isset($plg_photos_max_height) || !is_numeric($plg_photos_max_heigth))
{
    $plg_photos_max_height = 200;
}
if(!isset($plg_photos_events) || !is_numeric($plg_photos_events))
{
    $plg_photos_events = 0;
}
if(!isset($plg_photos_picnr) || !is_numeric($plg_photos_picnr))
{
    $plg_photos_picnr = 0;
}
if(!isset($plg_photos_show_link) || !is_numeric($plg_photos_show_link))
{
    $plg_photos_show_link = true;
}

// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
mysql_select_db($g_adm_db, $g_adm_con );

//Versnstaltungen Aufrufen
//Bedingungen: Vreigegeben,Anzahllimit, Bilder enthalten 
$sql="      SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_org_shortname ='$g_organization' 
            AND pho_locked = 0
            ORDER BY pho_begin DESC";

//Limit setzen falls gefordert
if($plg_photos_events != 0)
{
    $sql = $sql."LIMIT 0, $plg_photos_events";
}

$result = mysql_query($sql, $g_adm_con);

//Zeiger per Zufall auf eine Veranstaltung setzen
mysql_data_seek($result, mt_rand(0, mysql_num_rows($result)-1));

//Ausgewähltendatendatz holen
$event =  mysql_fetch_array($result);

//Falls gewuensch Bild per Zufall auswaehlen
if($plg_photos_picnr ==0)
{
    $picnr = mt_rand(1, $event['pho_quantity']);
}
else
{
    $picnr = $plg_photos_picnr;
}

//Bilpfad zusammensetzen
$picpath = PLUGIN_PATH. "/../adm_my_files/photos/".$event['pho_begin']."_".$event['pho_id']."/".$picnr.".jpg";

//Ermittlung der Original Bildgroesse
$bildgroesse = getimagesize($picpath);

//Popupfenstergröße
$popup_height = $g_preferences['photo_show_height']+210;
$popup_width  = $g_preferences['photo_show_width']+70;

if($plg_photos_show_link && $plg_max_char_per_word > 0)
{
    //Linktext umbrechen wenn noetig
    $words = explode(" ", $event['pho_name']);
    
    for($i = 0; $i < count($words); $i++)
    {
        if(strlen($words[$i]) > $plg_max_char_per_word)
        {
            $link_text = "$link_text ". substr($words[$i], 0, $plg_max_char_per_word). "<br />". 
                            substr($words[$i], $plg_max_char_per_word);
        }
        else
        {
            $link_text = "$link_text ". $words[$i];
        }
    }
}
else
{
    $link_text = $event['pho_name'];
}

//Ausgabe
$pho_id = $event['pho_id'];

//Entscheidung ueber scallierung
if($bildgroesse[0]/$plg_photos_max_width > $bildgroesse[1]/$plg_photos_max_height)
{
   echo "<img onclick=\"window.open('$g_root_path/adm_program/modules/photos/photopopup.php?bild=$picnr&pho_id=$pho_id','msg', 'height=".$popup_height.", width=".$popup_width.",left=162,top=5')\" style=\"vertical-align: middle; cursor: pointer;\"
            src=\"$g_root_path/adm_program/modules/photos/photo_show.php?bild=".$picpath."&amp;scal=".$plg_photos_max_width."&amp;side=x\"  border=\"0\" alt=\"Zufallsbild\">";
}
else
{
   echo "<img onclick=\"window.open('../../adm_program/modules/photos/photopopup.php?bild=$picnr&pho_id=$pho_id','msg', 'height=".$popup_height.", width=".$popup_width.",left=162,top=5')\" style=\"vertical-align: middle; cursor: pointer;\"
            src=\"$g_root_path/adm_program/modules/photos/photo_show.php?bild=".$picpath."&amp;scal=".$plg_photos_max_height."&amp;side=y\"  border=\"0\" alt=\"Zufallsbild\">";
}


//Link zur Veranstaltung
if($plg_photos_show_link)
{
    echo"<br><a class=\"$plg_link_class\" href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$pho_id."\" target=\"$plg_link_target\">".$link_text."</a>";
}

?>