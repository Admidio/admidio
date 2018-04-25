<?php
/**
 ***********************************************************************************************
 * Random Photo
 *
 * Plugin zeigt ein zufaellig ausgewaehltes Foto aus dem Fotomodul an und
 * und verlinkt neben dem Bild das dazugehörige Album
 *
 * Compatible with Admidio version 3.3
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');
require_once(__DIR__ . '/config.php');

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

echo '<div id="plugin_'. $pluginFolder. '" class="admidio-plugin-content">';
if($plg_show_headline)
{
    echo '<h3>'.$gL10n->get('PHO_PHOTOS').'</h3>';
}

// Fotoalben Aufrufen
// Bedingungen: freigegeben,Anzahllimit, Bilder enthalten
$sql = 'SELECT *
          FROM '.TBL_PHOTOS.'
         WHERE pho_org_id   = ? -- $gCurrentOrganization->getValue(\'org_id\')
           AND pho_locked   = 0
           AND pho_quantity > 0
      ORDER BY pho_begin DESC';

// Limit setzen falls gefordert
if($plg_photos_albums > 0)
{
    $sql .= ' LIMIT '.$plg_photos_albums;
}

$albumStatement = $gDb->queryPrepared($sql, array($gCurrentOrganization->getValue('org_id')));
$albumList      = $albumStatement->fetchAll();

// Variablen initialisieren
$i        = 0;
$picNr    = 0;
$picPath  = '';
$linkText = '';
$album = new TablePhotos($gDb);

// Schleife, falls nicht direkt ein Bild gefunden wird, aber auf 20 Durchlaeufe begrenzen
while(!is_file($picPath) && $i < 20 && $albumStatement->rowCount() > 0)
{
    // Ausgewähltendatendatz holen
    $album->setArray($albumList[mt_rand(0, $albumStatement->rowCount()-1)]);

    // Falls gewuensch Bild per Zufall auswaehlen
    if($plg_photos_picnr === 0)
    {
        $picNr = mt_rand(1, $album->getValue('pho_quantity'));
    }
    else
    {
        $picNr = $plg_photos_picnr;
    }

    // Bilpfad zusammensetzen
    $picPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $album->getValue('pho_begin', 'Y-m-d') . '_' . $album->getValue('pho_id') . '/' . $picNr . '.jpg';
    ++$i;
}

if(!is_file($picPath))
{
    $picPath = THEME_PATH . '/images/no_photo_found.png';
}

if($plg_photos_show_link && $plg_max_char_per_word > 0)
{
    // Linktext umbrechen wenn noetig
    $words = explode(' ', $album->getValue('pho_name'));

    foreach ($words as $word)
    {
        if(strlen($word) > $plg_max_char_per_word)
        {
            $linkText .= substr($word, 0, $plg_max_char_per_word).'-<br />'.
                substr($word, $plg_max_char_per_word).' ';
        }
        else
        {
            $linkText .= $word.' ';
        }
    }
}
else
{
    $linkText = $album->getValue('pho_name');
}

// Ausgabe
$phoId = (int) $album->getValue('pho_id');
echo '<a class="'.$plg_link_class.'" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php', array('pho_id' => $phoId, 'photo_nr' => $picNr)).'" target="'. $plg_link_target. '"><img
    class="thumbnail" alt="'.$linkText.'" title="'.$linkText.'"
    src="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('pho_id' => $phoId, 'photo_nr' => $picNr, 'pho_begin' => $album->getValue('pho_begin', 'Y-m-d'), 'max_width' => $plg_photos_max_width, 'max_height' => $plg_photos_max_height)).'" /></a>';

// Link zum Album
if($plg_photos_show_link)
{
    echo '<a class="'.$plg_link_class.'" href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php', array('pho_id' => $phoId)).'" target="'.$plg_link_target.'">'.$linkText.'</a>';
}

echo '</div>';
