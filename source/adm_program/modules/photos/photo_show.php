<?php
 /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id    : Id des Albums, aus dem das Bild kommen soll
 * pho_begin : Datum des Albums
 * pic_nr    : Nummer des Bildes, das angezeigt werden soll
 * scal      : Pixelanzahl auf die die Bildseite scaliert werden soll
 * side      : Seite des Bildes die skaliert werden soll (x oder y)
 *
 *****************************************************************************/
require("../../system/photo_album_class.php");
require("../../system/common.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}
elseif($g_preferences['enable_photo_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require("../../system/login_valid.php");
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
$picpath = SERVER_PATH. "/adm_my_files/photos/".$pho_begin."_".$pho_id."/".$pic_nr.".jpg";

// im Debug-Modus den ermittelten Bildpfad ausgeben
if($g_debug == 1)
{
    error_log($picpath);
}

if(file_exists($picpath) == false)
{
    $picpath = THEME_SERVER_PATH. "/images/nopix.jpg";
}

//Ermittlung der Original Bildgroesse
$bildgroesse = getimagesize($picpath);

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
    if($seitenverhaeltnis>1.6)
    {
        $neubildsize =  array (round($scal*1.6), round($scal*(1.6/$seitenverhaeltnis)));
    }
    else
    {
        $neubildsize =  array (round($scal*$seitenverhaeltnis), $scal);
    }
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
$bilddaten = imagecreatefromjpeg($picpath);

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