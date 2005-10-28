<?php
   /******************************************************************************
 * Photodreher
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
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
   header("Content-Type: image/jpeg");
//Übernahme welches Bild umgerechnet werden soll
   $bild = $_GET["bild"];
	$degr = $_GET["degr"];
	$ordner = $_GET["ordner"];
//Aufrufen des Originalbildes
   $bilddaten = imagecreatefromjpeg("$ordner/$bild.jpg");
//drehen des Bildes
	$rotate = imagerotate($bilddaten, "$degr", 0);
//speichern
   unlink("$ordner/$bild.jpg");
	imagejpeg($rotate, "$ordner/$bild.jpg", 90);
   chmod("$ordner/$bild.jpg",0777);
//Löschen des Bildes aus Arbeitsspeicher
     imagedestroy("$bilddaten");
	  imagedestroy("$rotate");
// zur Ausgangsseite zurueck
$seite=$_GET["seite"];
$location = "location: $g_root_path/adm_program/moduls/photos/thumbnails.php?ordner=$ordner&seite=$seite";
header($location);
exit();
?>