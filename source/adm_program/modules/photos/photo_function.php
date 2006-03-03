<?php
   /******************************************************************************
 * Photofunktionen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
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
   
function right_rotate ($pho_id, $bild){ 
	global $g_adm_con;
	header("Content-Type: image/jpeg");
		//Aufruf der ggf. Übergebenen Veranstaltung
	$sql = "	SELECT *
			FROM ". TBL_PHOTOS. "
			WHERE (pho_id ='$pho_id')";
	$result_event = mysql_query($sql, $g_adm_con);
	db_error($result_event);
	$adm_photo = mysql_fetch_array($result_event);
	//Ordner
	$ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];
	//Ermittlung der Original Bildgröße
   $bildgroesse = getimagesize("$ordner/$bild.jpg"); 
	// Erzeugung neues Bild
   $neubild = imagecreatetruecolor($bildgroesse[1], $bildgroesse[0]);
	//Aufrufen des Originalbildes
   $bilddaten = imagecreatefromjpeg("$ordner/$bild.jpg");
	//kopieren der Daten in neues Bild
   //Rechtsdrehung
    for($y=0; $y<$bildgroesse[1]; $y++){
   	for($x=0; $x<$bildgroesse[0]; $x++){
   		imagecopy($neubild, $bilddaten, $bildgroesse[1]-$y-1, $x, $x, $y, 1,1 );
   	}
	 }
   //ursprungsdatei löschen
	if(file_exists("$ordner/$bild.jpg")){    
   	chmod("$ordner/$bild.jpg", 0777);
   	unlink("$ordner/$bild.jpg");
	}
	//speichern
  imagejpeg($neubild, "$ordner/$bild.jpg", 90);
  chmod("$ordner/$bild.jpg",0777);
	//Löschen des Bildes aus Arbeitsspeicher
  imagedestroy($neubild);
  imagedestroy($bilddaten);
};

function left_rotate ($pho_id, $bild){ 
	global $g_adm_con;
	header("Content-Type: image/jpeg");
		//Aufruf der ggf. Übergebenen Veranstaltung
	$sql = "	SELECT *
			FROM ". TBL_PHOTOS. "
			WHERE (pho_id ='$pho_id')";
	$result_event = mysql_query($sql, $g_adm_con);
	db_error($result_event);
	$adm_photo = mysql_fetch_array($result_event);
	//Ordner
	$ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];
	//Ermittlung der Original Bildgröße
   $bildgroesse = getimagesize("$ordner/$bild.jpg"); 
	// Erzeugung neues Bild
   $neubild = imagecreatetruecolor($bildgroesse[1], $bildgroesse[0]);
	//Aufrufen des Originalbildes
   $bilddaten = imagecreatefromjpeg("$ordner/$bild.jpg");
	//kopieren der Daten in neues Bild
   //Linksdrehung
   for($y=0; $y<$bildgroesse[1]; $y++){
   	for($x=0; $x<$bildgroesse[0]; $x++){
   		imagecopy($neubild, $bilddaten, $y, $bildgroesse[0]-$x-1, $x, $y, 1,1 );
   	}
   }
   //ursprungsdatei löschen
	if(file_exists("$ordner/$bild.jpg")){    
   	chmod("$ordner/$bild.jpg", 0777);
   	unlink("$ordner/$bild.jpg");
	}
	//speichern
  imagejpeg($neubild, "$ordner/$bild.jpg", 90);
  chmod("$ordner/$bild.jpg",0777);
	//Löschen des Bildes aus Arbeitsspeicher
  imagedestroy($neubild);
  imagedestroy($bilddaten);
};
function delete ($pho_id, $bild){
	global $g_current_user;
   global $g_adm_con;
   global $g_organization;
	
	//erfassen der Veranstaltung
	$sql = "	SELECT *
				FROM ". TBL_PHOTOS. "
				WHERE (pho_id ='$pho_id')";
	$result = mysql_query($sql, $g_adm_con);
	db_error($result);
	$adm_photo = mysql_fetch_array($result);	

	//Speicherort
	$ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

	//Bericht mit l&ouml;schen
   $neuebilderzahl = $adm_photo["pho_quantity"]-1;
	//Bilder l&ouml;schen
	if(file_exists("$ordner/$bild.jpg")){    
   	chmod("$ordner/$bild.jpg", 0777);
      unlink("$ordner/$bild.jpg");
	}
   //Umbennenen der Restbilder
   $neuenr=1;
   for($x=1; $x<=$adm_photo["pho_quantity"]; $x++){
   	if(file_exists("$ordner/$x.jpg")){
      	if($x>$neuenr){
         	chmod("$ordner/$x.jpg", 0777);
            rename("$ordner/$x.jpg", "$ordner/$neuenr.jpg");
         }//if
         $neuenr++;
      }//if
   }//for
   //&Auml;ndern der Datenbankeintaege
	$sql = "UPDATE ". TBL_PHOTOS. "
		SET pho_quantity = '$neuebilderzahl',
		pho_last_change ='$act_datetime',
		pho_usr_id_change = $g_current_user->id
		WHERE pho_id = '$pho_id'";
	$result = mysql_query($sql, $g_adm_con);
	db_error($result);
};

if($_GET["job"]=="rotate"){
	//bei Seitenaufruf ohne Moderationsrechte
	if(!$g_session_valid || $g_session_valid && !editPhoto($adm_photo["pho_org_shortname"])){
     	$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=photoverwaltunsrecht";
     	header($location);
     	exit();
   }
	//Aufruf der entsprechenden Funktion
	if($_GET["direction"]=="right")right_rotate($_GET["pho_id"], $_GET["bild"]);
	if($_GET["direction"]=="left")left_rotate($_GET["pho_id"], $_GET["bild"]);
	// zur Ausgangsseite zurueck
	$seite=$_GET["seite"];
	$pho_id=$_GET["pho_id"];
	$location = "location: $g_root_path/adm_program/modules/photos/photos.php?pho_id=$pho_id&seite=$seite";
	header($location);
	exit();
}
if($_GET["job"]=="delete"){
	//bei Seitenaufruf ohne Moderationsrechte
	if(!$g_session_valid || $g_session_valid && !editPhoto($adm_photo["pho_org_shortname"])){
     	$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=photoverwaltunsrecht";
     	header($location);
     	exit();
   }
	//Aufruf der entsprechenden Funktion
	delete($_GET["pho_id"], $_GET["bild"]);
	// zur Ausgangsseite zurueck
	$seite=$_GET["seite"];
	$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=photo_deleted&timer=2000&url=". urlencode("$g_root_path/adm_program/modules/photos/photos.php?pho_id=$pho_id&seite=$seite");
	header($location);
	exit();
}
?>