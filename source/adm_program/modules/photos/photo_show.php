<?php
 /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id    : Id des Albums, aus dem das Bild kommen soll
 * pho_begin : Datum des Albums
 * pic_nr    : Nummer des Bildes, das angezeigt werden soll
 * max_width : maximale Breite auf die das Bild skaliert werden kann
 * max_height: maximale Hoehe auf die das Bild skaliert werden kann
 * thumb	 : ist thumb == true wird ein Thumnail in der Größe der
 *				Voreinstellung zurückgegeben 
 *
 *****************************************************************************/
require_once('../../system/classes/table_photos.php');
require_once('../../system/common.php');
require_once('../../system/classes/image.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}
elseif($g_preferences['enable_photo_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require('../../system/login_valid.php');
}

//Uebergaben pruefen
$pho_id    = NULL;
$pho_begin = 0;
$pic_nr    = NULL;
$max_width  = 0;
$max_height = 0;
$thumb 	   = false;
$image     = NULL;

// Album-ID
if(isset($_GET['pho_id']))
{
    $pho_id = $_GET['pho_id'];
}

//pho_begin
if(isset($_GET['pho_begin']))
{
    $albumStartDate = new DateTimeExtended($_GET['pho_begin'].' 01:00:00', $g_preferences['system_date'].' h:i:s');
    if($albumStartDate->valid())
    {
        $pho_begin = $_GET['pho_begin'];
    }
}

//Bildnr.
if(isset($_GET['pic_nr']))
{
    $pic_nr = $_GET['pic_nr'];
} 

// max. Breite fuer Skalierung
if(isset($_GET['max_width']) && is_numeric($_GET['max_width']))
{
    $max_width = $_GET['max_width'];
}

// max. Hoehe fuer Skalierung
if(isset($_GET['max_height']) && is_numeric($_GET['max_height']))
{
    $max_height = $_GET['max_height'];
}

//Thumbnail
if(isset($_GET['thumb']))
{
	$thumb = $_GET['thumb'];
}

// Bildpfad zusammensetzten
$ordner = SERVER_PATH. '/adm_my_files/photos/'.$pho_begin.'_'.$pho_id;
$picpath = $ordner.'/'.$pic_nr.'.jpg';

// im Debug-Modus den ermittelten Bildpfad ausgeben
if($g_debug == 1)
{
    error_log($picpath);
}

//Wenn Thumbnail existiert laengere Seite ermitteln
if($thumb)
{
    if($pic_nr > 0)
    {
    	$thumb_length=1;
    	if(file_exists($ordner.'/thumbnails/'.$pic_nr.'.jpg'))
    	{
    	    //Ermittlung der Original Bildgroesse
    	    $bildgroesse = getimagesize($ordner.'/thumbnails/'.$pic_nr.'.jpg');
    	    
    	    $thumb_length = $bildgroesse[1];
    	    if($bildgroesse[0]>$bildgroesse[1])
    	    {
    	        $thumb_length = $bildgroesse[0];
    	    }
    	}
    	
    	//Nachsehen ob Bild als Thumbnail in entsprechender Groesse hinterlegt ist
    	//Wenn nicht anlegen
    	if(!file_exists($ordner.'/thumbnails/'.$pic_nr.'.jpg') || $thumb_length !=$g_preferences['photo_thumbs_scale'])
    	{
            //Nachsehen ob Thumnailordner existiert und wenn nicht SafeMode ggf. anlegen
            if(file_exists($ordner.'/thumbnails') == false)
            {
                require_once('../../system/classes/folder.php');
                $folder = new Folder($ordner);
                $folder->createWriteableFolder('thumbnails');
            }
    
            // nun das Thumbnail anlegen
    	    $image = new Image($picpath);
    	    $image->scaleLargerSide($g_preferences['photo_thumbs_scale']);
    	    $image->copyToFile(null, $ordner.'/thumbnails/'.$pic_nr.'.jpg');
    	}
    	else
    	{
    		readfile($ordner.'/thumbnails/'.$pic_nr.'.jpg');
    	}
    }
    else
    {
        // kein Bild uebergeben, dann NoPix anzeigen
	    $image = new Image(THEME_SERVER_PATH. '/images/nopix.jpg');
	    $image->scaleLargerSide($g_preferences['photo_thumbs_scale']);
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
    $image->scale($max_width, $max_height);
}

if($image != NULL)
{
	// Einfuegen des Textes bei Bildern, die in der Ausgabe groesser als 200px sind
	if (($max_width > 200) && $g_preferences['photo_image_text'] != '')
	{
	    $font_c = imagecolorallocate($image->imageResource,255,255,255);
	    $font_ttf = THEME_SERVER_PATH.'/font.ttf';
	    $font_s = $max_width / 40;
	    $font_x = $font_s;
	    $font_y = $image->imageHeight-$font_s;
	    $text = $g_preferences['photo_image_text'];
	    imagettftext($image->imageResource, $font_s, 0, $font_x, $font_y, $font_c, $font_ttf, $text);
	}
	
	// Rueckgabe des neuen Bildes
	header('Content-Type: '. $image->getMimeType());
	$image->copyToBrowser();
	$image->delete();
}
?>