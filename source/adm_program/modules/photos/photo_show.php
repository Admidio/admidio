<?php
 /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * bild: welches Bild soll angezeigt werden
 * scal: Pixelanzahl auf die die laengere Bildseite scaliert werden soll
 * nr: Nummer des hochgeladenen bildes
 * side: Seite des Bildes die scaliert werden soll
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
require("../../system/common.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}


header("Content-Type: image/jpeg");

//Scale
$scal=NULL;
if(isset($_GET['scal']))
{
    $scal = $_GET['scal'];
}
if(!is_numeric($scal))
{
    $g_message->show("invalid");
}

//Seite
$side=NULL;
if(isset($_GET['side']))
{
    $side = $_GET['side'];
}
if($side != "y" && $side != "x" && $side!=NULL)
{
    $g_message->show("invalid");
}

//Bild
$bild;
if(isset($_GET['bild']))
{
    $bild = strStripTags($_GET['bild']);
}


//Ermittlung der Original Bildgroesse
$bildgroesse = getimagesize("$bild");

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
if($side=='')
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
    $font_ttf=SERVER_PATH."/adm_program/system/mr_phone1.ttf";
    $font_s = $scal/40;
    $font_x = $font_s;
    $font_y = $neubildsize[1]-$font_s;
    $text="&#169;&#32;".$g_current_organization->homepage;
    imagettftext($neubild, $font_s, 0, $font_x, $font_y, $font_c, $font_ttf, $text);
}

//Rueckgabe des Neuen Bildes
imagejpeg($neubild,"",90);

imagedestroy($neubild);
?>