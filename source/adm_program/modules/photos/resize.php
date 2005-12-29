<?php
   /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * Bild: welches Bild soll angezeigt werden
 * scal: Pixelanzahl auf die die längere Bildseite scaliert werden soll  
 * Ziel: wo soll es gspeichert werden
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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
   require("../../../adm_config/config.php");
   require("../../system/common.php");
   
	header("Content-Type: image/jpeg");
	//Übernahme welches Bild umgerechnet werden soll
   $aufgabe = $_GET['aufgabe'];
   $bild = $_GET['bild'];
   $scal = $_GET['scal'];
   $ziel = $_GET['ziel'];
   $nr = $_GET['nr'];
	$side = $_GET["side"];
   if($bild=='') $bild="../../../adm_my_files/photos/temp$nr.jpg";
	//Ermittlung der Original Bildgröße
   $bildgroesse = getimagesize("$bild"); 
	//Errechnung seitenverhältniss
   $seitenverhältnis = $bildgroesse[0]/$bildgroesse[1];
	//x-Seite soll scalliert werden
	if($side=="x")$neubildsize = array ($scal, round($scal/$seitenverhältnis));
	//y-Seite soll scalliert werden
	if($side=="y")$neubildsize =  array (round($scal*$seitenverhältnis), $scal);
	//längere seite soll scallirt werden
	if($side==''){
		//Errechnug neuen Bildgröße Querformat
   	if($bildgroesse[0]>=$bildgroesse[1])$neubildsize = array ($scal, round($scal/$seitenverhältnis));
		//Errechnug neuen Bildgröße Hochformat
   	if($bildgroesse[0]<$bildgroesse[1])$neubildsize = array (round($scal*$seitenverhältnis), $scal);
	}
	// Erzeugung neues Bild
   $neubild = imagecreatetruecolor($neubildsize[0], $neubildsize[1]);
	//Aufrufen des Originalbildes
   $bilddaten = imagecreatefromjpeg("$bild");
	//kopieren der Daten in neues Bild
   imagecopyresampled($neubild, $bilddaten, 0, 0, 0, 0, $neubildsize[0], $neubildsize[1], $bildgroesse[0], $bildgroesse[1]);
   
	//Falls Aufgabe=anzeigen nur rückgabe des Bildes   
   if($aufgabe=="anzeigen"){
      //Einfügen des textes bei bilder die in der Ausgabe größer al 200px sind
		if ($scal>200){
      	$font_c = imagecolorallocate($neubild,255,255,255);
      	$font_ttf=$g_server_path."/adm_program/system/mr_phone1.ttf";
      	$font_s = $scal/40;
      	$font_x = $font_s;
      	$font_y = $neubildsize[1]-$font_s;
      	$text="&#169;&#32;".$g_homepage;
      	imagettftext($neubild, $font_s, 0, $font_x, $font_y, $font_c, $font_ttf, $text);
      }
		//Rückgabe des Neuen Bildes
      imagejpeg($neubild,"",90);
   };
	//Falls Aufgabe=speichern rückgabe und speichern
      if($aufgabe=="speichern"){
         imagejpeg($neubild, "$ziel.jpg", 90);
         imagejpeg($neubild,"",90);
         chmod("$ziel.jpg",0777);
      };//if Aufgabe
	//Löschen des Bildes aus Arbeitsspeicher
   if(file_exists("../../../adm_my_files/photos/temp$nr.jpg"))unlink("../../../adm_my_files/photos/temp$nr.jpg");
     imagedestroy($neubild);
?>