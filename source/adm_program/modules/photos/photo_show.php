<?php
 /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id    : Id des Albums, aus dem das Bild kommen soll
 * pho_begin : Datum des Albums
 * bild      : Nummer des Bildes, das angezeigt werden soll
 * scal      : Pixelanzahl auf die die Bildseite scaliert werden soll
 * side      : Seite des Bildes die skaliert werden soll (x oder y)
 *
 *****************************************************************************/
require("../../system/photo_album_class.php");
require("../../system/common.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] != 1)
{
    // das Modul ist deaktiviert
    error_log("Das Fotomodul ist deaktiviert");
}

//Uebergaben pruefen

$pho_id    = NULL;
$pho_begin = 0;
$pic_nr    = NULL;
$scal      = NULL;
$side      = "";

// Album-ID
if(isset($_GET['pho_id']))
{
    $pho_id = $_GET['pho_id'];
}

//pho_begin
if(isset($_GET['pho_begin']) && strlen($_GET['pho_begin']) == 10)
{
    if(dtCheckDate(mysqldate("d.m.y", $_GET['pho_begin'])))
    {
        $pho_begin = $_GET['pho_begin'];
    }
}

//Bildnr.
if(isset($_GET['pic_nr']))
{
    $pic_nr = $_GET['pic_nr'];
} 

// Bildskalierung
if(isset($_GET['scal']))
{
    $scal = $_GET['scal'];
}

//Seite
if(isset($_GET['side']))
{
    $_GET['side'] = strtolower($_GET['side']);
    if($_GET['side'] ==  "x" || $_GET['side'] == "y")
    {
        $side = $_GET['side'];
    }
}

// Bildpfad zusammensetzten
if(!is_numeric($pho_id) || $pho_begin == 0 || !is_numeric($pic_nr) || !is_numeric($scal))
{
    $bild = THEME_SERVER_PATH. "/images/nopix.jpg";
}
else
{
    $bild = SERVER_PATH. "/adm_my_files/photos/".$pho_begin."_".$pho_id."/".$pic_nr.".jpg";
}
// im Debug-Modus den ermittelten Bildpfad ausgeben
if($g_debug == 1)
{
    error_log($bild);
}

//Ermittlung der Original Bildgroesse
$bildgroesse = getimagesize($bild);

//Errechnung seitenverhaeltniss
$seitenverhaeltnis = $bildgroesse[0]/$bildgroesse[1];

//x-Seite soll scalliert werden
if($side=="x")
{
    $neubildsize = array ($scal, round($scal/$seitenverhaeltnis));
}

//y-Seite soll scalliert werden
if($side=="y")
{
    $neubildsize =  array (round($scal*$seitenverhaeltnis), $scal);
}

//laengere seite soll scallirt werden
if(strlen($side) == 0)
{
    //Errechnug neuen Bildgroesse Querformat
    if($bildgroesse[0]>=$bildgroesse[1])
    {
        $neubildsize = array ($scal, round($scal/$seitenverhaeltnis));
    }
    //Errechnug neuen Bildgroesse Hochformat
    if($bildgroesse[0]<$bildgroesse[1]){
        $neubildsize = array (round($scal*$seitenverhaeltnis), $scal);
    }
}

// Erzeugung neues Bild
$neubild = imagecreatetruecolor($neubildsize[0], $neubildsize[1]);

//Aufrufen des Originalbildes
$bilddaten = imagecreatefromjpeg("$bild");

//kopieren der Daten in neues Bild
imagecopyresampled($neubild, $bilddaten, 0, 0, 0, 0, $neubildsize[0], $neubildsize[1], $bildgroesse[0], $bildgroesse[1]);

//Einfuegen des textes bei bilder die in der Ausgabe groesser als 200px sind
if ($scal>200 && $g_preferences['photo_image_text'] == 1)
{
    $font_c = imagecolorallocate($neubild,255,255,255);
    $font_ttf = THEME_SERVER_PATH."/font.ttf";
    $font_s = $scal/40;
    $font_x = $font_s;
    $font_y = $neubildsize[1]-$font_s;
    $text="&#169;&#32;".$g_current_organization->getValue("org_homepage");
    imagettftext($neubild, $font_s, 0, $font_x, $font_y, $font_c, $font_ttf, $text);
}

//Rueckgabe des Neuen Bildes
header("Content-Type: image/jpeg");
imagejpeg($neubild,"",90);

imagedestroy($neubild);
?>