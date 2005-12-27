<?php
/******************************************************************************
 * Photogalerien
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
 * Foundation, Inc., 79 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

	require("../../system/common.php");
	require("../../system/session_check_login.php");
	
//bei Seitenaufruf ohne Moderationsrechte
if(!$g_session_valid || $g_session_valid && !editPhoto())
      {
        $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=photoverwaltunsrecht";
      header($location);
      exit();
      }
//bei Seitenaufruf mit Moderationsrechten
if($g_session_valid && editPhoto()){
//Übernahme Variablen
	 $pho_id= $_GET['pho_id'];
	 $bild = $_GET["bild"];
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
      $neuebilderzahl = $adm_photo[1]-1;
	//Bilder l&ouml;schen
        	chmod("$ordner/$bild.jpg", 0777);
         unlink("$ordner/$bild.jpg");
   //Umbennenen der Restbilder
         $neuenr=1;
         for($x=1; $x<=$adm_photo["pho_number"]; $x++){
            if(file_exists("$ordner/$x.jpg")){
               if($x>$neuenr){
                  chmod("$ordner/$x.jpg", 0777);
                  rename("$ordner/$x.jpg", "$ordner/$neuenr.jpg");
               }//if
               $neuenr++;
            }//if
         }//for
   //&Auml;ndern der Datenbankeintaege
        $changedatetime= date("Y.m.d G:i:s", time());
		  $sql = "UPDATE ". TBL_PHOTOS. "
		 			SET pho_number = '$neuebilderzahl', pho_last_change = '$changedatetime'
					WHERE pho_id = '$pho_id'";
		 $result = mysql_query($sql, $g_adm_con);
		 db_error($result);

// zur Ausgangsseite zurueck
$seite=$_GET["seite"];
$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=photo_deleted&timer=2000&url=". urlencode("$g_root_path/adm_program/modules/photos/thumbnails.php?pho_id=$pho_id&seite=$seite");
header($location);
exit();
}//if Moderator
?>