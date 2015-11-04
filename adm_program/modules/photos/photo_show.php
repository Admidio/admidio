<?php
 /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * pho_id    : Id des Albums, aus dem das Bild kommen soll
 * photo_nr  : Nummer des Bildes, das angezeigt werden soll
 * max_width : maximale Breite auf die das Bild skaliert werden kann
 * max_height: maximale Hoehe auf die das Bild skaliert werden kann
 * thumb     : ist thumb == 1 wird ein Thumnail in der Größe der
 *             Voreinstellung zurückgegeben
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getPhotoId    = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', array('requireValue' => true));
$getPhotoNr    = admFuncVariableIsValid($_GET, 'photo_nr', 'numeric');
$getMaxWidth   = admFuncVariableIsValid($_GET, 'max_width', 'numeric');
$getMaxHeight  = admFuncVariableIsValid($_GET, 'max_height', 'numeric');
$getThumbnail  = admFuncVariableIsValid($_GET, 'thumb', 'boolean');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif ($gPreferences['enable_photo_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require('../../system/login_valid.php');
}

// lokale Variablen initialisieren
$image = NULL;

// read album data out of session or database
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $getPhotoId)
{
    $photoAlbum =& $_SESSION['photo_album'];
    $photoAlbum->setDatabase($gDb);
}
else
{
    $photoAlbum = new TablePhotos($gDb, $getPhotoId);
    $_SESSION['photo_album'] = $photoAlbum;
}

// Bildpfad zusammensetzten
$ordner  = SERVER_PATH. '/adm_my_files/photos/'.$photoAlbum->getValue('pho_begin', 'Y-m-d').'_'.$getPhotoId;
$picpath = $ordner.'/'.$getPhotoNr.'.jpg';

// im Debug-Modus den ermittelten Bildpfad ausgeben
if($gDebug == 1)
{
    error_log($picpath);
}

//Wenn Thumbnail existiert laengere Seite ermitteln
if($getThumbnail)
{
    if($getPhotoNr > 0)
    {
        $thumb_length=1;
        if(file_exists($ordner.'/thumbnails/'.$getPhotoNr.'.jpg'))
        {
            //Ermittlung der Original Bildgroesse
            $bildgroesse = getimagesize($ordner.'/thumbnails/'.$getPhotoNr.'.jpg');

            $thumb_length = $bildgroesse[1];
            if($bildgroesse[0]>$bildgroesse[1])
            {
                $thumb_length = $bildgroesse[0];
            }
        }

        //Nachsehen ob Bild als Thumbnail in entsprechender Groesse hinterlegt ist
        //Wenn nicht anlegen
        if(!file_exists($ordner.'/thumbnails/'.$getPhotoNr.'.jpg') || $thumb_length !=$gPreferences['photo_thumbs_scale'])
        {
            //Nachsehen ob Thumnailordner existiert und wenn nicht SafeMode ggf. anlegen
            if(file_exists($ordner.'/thumbnails') == false)
            {
                $folder = new Folder($ordner);
                $folder->createFolder('thumbnails', true);
            }

            // nun das Thumbnail anlegen
            $image = new Image($picpath);
            $image->scaleLargerSide($gPreferences['photo_thumbs_scale']);
            $image->copyToFile(null, $ordner.'/thumbnails/'.$getPhotoNr.'.jpg');
        }
        else
        {
            header('content-type: image/jpg');
            echo readfile($ordner.'/thumbnails/'.$getPhotoNr.'.jpg');
        }
    }
    else
    {
        // kein Bild uebergeben, dann NoPix anzeigen
        $image = new Image(THEME_SERVER_PATH. '/images/nopix.jpg');
        $image->scaleLargerSide($gPreferences['photo_thumbs_scale']);
    }
}
else
{
    if(file_exists($picpath) == false)
    {
        $picpath = THEME_SERVER_PATH. '/images/nopix.jpg';
    }
    // Bild einlesen und scalieren
    $image = new Image($picpath);
    $image->scale($getMaxWidth, $getMaxHeight);
}

if($image != NULL)
{
    // Einfuegen des Textes bei Bildern, die in der Ausgabe groesser als 200px sind
    if (($getMaxWidth > 200) && $gPreferences['photo_image_text'] != '')
    {
        $font_c = imagecolorallocate($image->imageResource, 255, 255, 255);
        $font_ttf = THEME_SERVER_PATH.'/font.ttf';
        $font_s = $getMaxWidth / 40;
        $font_x = $font_s;
        $font_y = $image->imageHeight-$font_s;
        $text = $gPreferences['photo_image_text'];
        imagettftext($image->imageResource, $font_s, 0, $font_x, $font_y, $font_c, $font_ttf, $text);
    }

    // Rueckgabe des neuen Bildes
    header('Content-Type: '. $image->getMimeType());
    $image->copyToBrowser();
    $image->delete();
}
