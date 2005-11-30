<?php
/******************************************************************************
 * Photoupload
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

   require("../../../adm_config/config.php");
   require("../../system/function.php");
   require("../../system/date.php");
   require("../../system/tbl_user.php");
   require("../../system/session_check.php");
//bei Seitenaufruf ohne Moderationsrechte
if(!$g_session_valid || $g_session_valid & !editPhoto())
      {
        $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=photoverwaltunsrecht";
      header($location);
      exit();
      }
//bei Seitenaufruf mit Moderationsrechten
if($g_session_valid & editPhoto()){
//Übernahme Variablen
	 $ap_id= $_GET['ap_id'];
//erfassen der Veranstaltung
	$sql = "	SELECT *
				FROM ". TBL_PHOTOS. "
				WHERE (ap_id ='$ap_id')";
	$result = mysql_query($sql, $g_adm_con);
	db_error($result);
	$". TBL_PHOTOS. " = mysql_fetch_array($result);
//Speicherort
	$ordner = "../../../adm_my_files/photos/"."$". TBL_PHOTOS. "[3]"."_$". TBL_PHOTOS. "[0]";

//kontrollmechanismen bei selbstaufruf
   if($_POST["upload"]){
	 //zählen wieviele Bilder hochgeladen werden sollen
      $counter=0;
      for($x=0; $x<=4; $x++){
         if($_FILES["bilddatei"]["name"]["$x"]!="")$counter++;
      } ;

   //Kontrolle ob Bilder ausgewählt wurden
      if($counter==0){
           $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=photodateiphotoup";
         header($location);
         exit();
      }
   //Kontrolle des Dateityps
      $erlaubt=array(".jpg", ".jpeg", ".JPG", ".JPEG");
		for($x=0; $x<=4; $x=$x+1){
         $endung=strrchr($_FILES["bilddatei"]["name"][$x], ".");
			if ($_FILES["bilddatei"]["name"][$x]!="" && !in_array($endung, $erlaubt)) {
            $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=dateiendungphotoup";
            header($location);
            exit();
         }
      }//for
   }//Kontrollmechanismen

//Beginn HTML
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>". $g_orga_property['ag_shortname']. " - Fotos hochladen</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

      <!--[if gte IE 5.5000]>
      <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";
      require("../../../adm_config/header.php");
   echo "</head>";

   require("../../../adm_config/body_top.php");
   echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";
if($_POST["upload"]){
//bei selbstaufruf der Datei Hinweise zu hochgeladenen Dateien und Kopieren der Datei in Ordner
   //Anlegen des Berichts
      echo"<div style=\"width: 670px\" align=\"center\" class=\"formHead\">Bericht</div>";
      echo"<div style=\"width: 670px\" align=\"center\" class=\"formBody\">Bitte einen Moment Geduld. Die Bilder wurden der Veranstaltung <br> - $". TBL_PHOTOS. "[2] - <br>erfolgreich hinzugefügt, wenn sie hier angezeigt werden.<br>";
   //Verarbeitungsschleife für die einzelnen Bilder
      $bildnr=$". TBL_PHOTOS. "[1];
		for($x=0; $x<=4; $x=$x+1){
         $y=$x+1;
         if($_FILES["bilddatei"]["name"][$x]!="" && $ordner!="") {
         //errechnen der neuen Bilderzahl
               $bildnr++;
         echo "<br>Bild $bildnr:<br>";
         //Größenanpassung Bild und Bericht
               if(copy($_FILES["bilddatei"]["tmp_name"][$x], "../../../adm_my_files/photos/temp$y.jpg"));
               echo"<img src=\"resize.php?scal=640&ziel=$ordner/$bildnr&aufgabe=speichern&nr=$y\"><br><br>";
         unset($y);
         }//if($bilddatei!= "")
      }//for
   //Ende der Bildverarbeitunsschleife
	//Aendern der Datenbankeintaege
      $changedatetime= date("Y.m.d G:i:s", time());
		$sql ="	UPDATE ". TBL_PHOTOS. "
					SET ap_number = '$bildnr',
						 ap_last_change = '$changedatetime'
					WHERE ap_id = '$ap_id'";
		$result = mysql_query($sql, $g_adm_con);
		db_error($result, 1);
	//Ende Bericht
      echo"
		<hr width=\"85%\" />
		<button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php'\">
   		<img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">Zur&uuml;ck
		</button>
		</div><br><br>";
   }//if($upload)

//Formular
   echo"
   <form name=\"photoup\" method=\"post\" action=\"photoupload.php?ap_id=$ap_id\" enctype=\"multipart/form-data\">
      <div style=\"width: 410px\" align=\"center\" class=\"formHead\">Fotoupload</div>
      <div style=\"width: 410px\" align=\"center\" class=\"formBody\">
         Bilder zu dieser Veranstaltung hinzufügen:<br>
         $". TBL_PHOTOS. "[2] <br>";
			$dt_date_von = mysqldate("d.m.y", $". TBL_PHOTOS. "[3]);
			echo"(Beginn: $dt_date_von)
			<hr width=\"85%\" />
         <p>Bild 1:<input type='file' name='bilddatei[]' value='durchsuchen'></p>
         <p>Bild 2:<input type='file' name='bilddatei[]' value='durchsuchen'></p>
         <p>Bild 3:<input type='file' name='bilddatei[]' value='durchsuchen'></p>
         <p>Bild 4:<input type='file' name='bilddatei[]' value='durchsuchen'></p>
         <p>Bild 5:<input type='file' name='bilddatei[]' value='durchsuchen'></p>
         <hr width=\"85%\" />
			Hilfe: <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_up_help','Message','width=500,height=550,left=310,top=200,scrollbars=yes')\">
			<hr width=\"85%\" />
	      <div style=\"margin-top: 6px;\">
   	   <button name=\"upload\" type=\"submit\" value=\"speichern\">
      	   <img src=\"$g_root_path/adm_program/images/save.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">Bilder Speichern
			</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	      <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php'\">
   		   <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">Zur&uuml;ck
			</button>
         	</div></div>
   </form>";
   echo"
   </div>
   ";
   require("../../../adm_config/body_bottom.php");
   echo "</body>
   </html>";
}//if Moderator
?>