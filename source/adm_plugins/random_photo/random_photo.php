<?php
/******************************************************************************
 * Random Photo
 *
 * Version 1.5.0
 *
 * Plugin zeigt ein zufaellig ausgewaehltes Foto aus dem Fotomodul an und 
 * und verlinkt neben dem Bild das dazugehörige Album
 *
 * Compatible with Admidio version 2.3.0
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// create path to plugin
$plugin_folder_pos = strpos(__FILE__, 'adm_plugins') + 11;
$plugin_file_pos   = strpos(__FILE__, 'random_photo.php');
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. '/../adm_program/system/common.php');
require_once(PLUGIN_PATH. '/../adm_program/system/classes/table_photos.php');
require_once(PLUGIN_PATH. '/'.$plugin_folder.'/config.php');

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
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

if(!isset($plg_photos_max_width) || !is_numeric($plg_photos_max_width))
{
    $plg_photos_max_width = 150;
}

if(!isset($plg_photos_max_height) || !is_numeric($plg_photos_max_height))
{
    $plg_photos_max_height = 200;
}
if(!isset($plg_photos_albums) || !is_numeric($plg_photos_albums))
{
    $plg_photos_albums = 0;
}
if(!isset($plg_photos_picnr) || !is_numeric($plg_photos_picnr))
{
    $plg_photos_picnr = 0;
}
if(!isset($plg_photos_show_link))
{
    $plg_photos_show_link = true;
}

// Sprachdatei des Plugins einbinden
$gL10n->addLanguagePath(PLUGIN_PATH. '/'.$plugin_folder.'/languages');

// set database to admidio, sometimes the user has other database connections at the same time
$gDb->setCurrentDB();

echo '<div id="plugin_'. $plugin_folder. '" class="admPluginContent">';
if($plg_show_headline==1)
{
    echo '<div class="admPluginHeader"><h3>'.$gL10n->get('PLG_RANDOM_PHOTOS_HEADLINE').'</h3></div>';
}
echo '<div class="admPluginBody">';

// Fotoalben Aufrufen
// Bedingungen: freigegeben,Anzahllimit, Bilder enthalten 
$sql='      SELECT *
            FROM '. TBL_PHOTOS. '
            WHERE pho_org_shortname = \''.$gCurrentOrganization->getValue('org_shortname').'\' 
            AND pho_locked = 0
            AND pho_quantity > 0
            ORDER BY pho_begin DESC';

//Limit setzen falls gefordert
if($plg_photos_albums != 0)
{
    $sql = $sql.' LIMIT '.$plg_photos_albums;
}

$result = $gDb->query($sql);

// Variablen initialisieren
$i         = 0;
$picnr     = 0;
$picpath   = '';
$link_text = '';
$album = new TablePhotos($gDb);

// Schleife, falls nicht direkt ein Bild gefunden wird, aber auf 20 Durchlaeufe begrenzen
while(!file_exists($picpath) && $i < 20 && $gDb->num_rows($result) > 0)
{
    //Zeiger per Zufall auf ein Album setzen
    $gDb->data_seek($result, mt_rand(0, $gDb->num_rows($result)-1));
    
    //Ausgewähltendatendatz holen
    $album->setArray($gDb->fetch_array($result));
    
    //Falls gewuensch Bild per Zufall auswaehlen
    if($plg_photos_picnr ==0)
    {
        $picnr = mt_rand(1, $album->getValue('pho_quantity'));
    }
    else
    {
        $picnr = $plg_photos_picnr;
    }
    
    //Bilpfad zusammensetzen
    $picpath = PLUGIN_PATH. '/../adm_my_files/photos/'.$album->getValue('pho_begin', 'Y-m-d').'_'.$album->getValue('pho_id').'/'.$picnr.'.jpg';
    $i++;
}

if(!file_exists($picpath))
{
    $picpath = THEME_SERVER_PATH. '/images/nopix.jpg';
}

//Ermittlung der Original Bildgroesse
$bildgroesse = getimagesize($picpath);

//Popupfenstergröße
$popup_height = $gPreferences['photo_show_height']+210;
$popup_width  = $gPreferences['photo_show_width']+70;

if($plg_photos_show_link && $plg_max_char_per_word > 0)
{
    //Linktext umbrechen wenn noetig
    $words = explode(' ', $album->getValue('pho_name'));
    
    for($i = 0; $i < count($words); $i++)
    {
        if(strlen($words[$i]) > $plg_max_char_per_word)
        {
            $link_text = $link_text.substr($words[$i], 0, $plg_max_char_per_word). '-<br />'. 
                            substr($words[$i], $plg_max_char_per_word);
        }
        else
        {
            $link_text = $link_text. $words[$i];
        }
    }
}
else
{
    $link_text = $album->getValue('pho_name');
}

//Ausgabe
$pho_id = $album->getValue('pho_id');
echo '<a class="'.$plg_link_class.'" href="'. $g_root_path. '/adm_program/modules/photos/photos.php?pho_id='.$pho_id.'&amp;photo_nr='.$picnr.'" target="'. $plg_link_target. '"><img 
    style="vertical-align: middle; cursor: pointer;" alt="Photo"
    src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$pho_id.'&amp;photo_nr='.$picnr.'&amp;pho_begin='.$album->getValue('pho_begin', 'Y-m-d').'&amp;max_width='.$plg_photos_max_width.'&amp;max_height='.$plg_photos_max_height.'" /></a>';

//Link zum Album
if($plg_photos_show_link)
{
    echo'<br /><a class="'.$plg_link_class.'" href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$pho_id.'" target="'.$plg_link_target.'">'.$link_text.'</a>';
}

echo '</div></div>';

?>