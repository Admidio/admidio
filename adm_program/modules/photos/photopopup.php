<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * Bild: welches Bild soll angezeigt werden
 * Ordner : aus welchem Ordner stammt das Bild welches angezeigt werden soll
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
 
	require("../../system/common.php");
	require("../../system/session_check.php");
	
//&Uuml;bernahme der &Uuml;bergebenen variablen
   $pho_id= $_GET['pho_id'];
   $bild= $_GET['bild'];
//erfassen der Veranstaltung
	$sql = "	SELECT *
				FROM ". TBL_PHOTOS. "
				WHERE (pho_id ='$pho_id')";
	$result = mysql_query($sql, $g_adm_con);
	db_error($result);
	$adm_photo = mysql_fetch_array($result);
//Aanzahl der Bilder
   $bilder = $adm_photo["pho_quantity"];
//Nächstes und Letztes Bild
	$last=$bild-1;
	$next=$bild+1;
//Speicherort
	$ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

   //Anfang HTML
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>$g_current_organization->longname - Fotogalerien</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">";

 	echo"
      <!--[if gte IE 5.5000]>
      <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      require("../../../adm_config/header.php");
   echo "</head>";

   //Ausgabe der Eine Tabelle Kopfzelle mit &Uuml;berschrift, Photographen und Datum
   //untere Zelle mit Buttons Bild und Fenster Schlie&szlig;en Button
   echo "<body>
   <div style=\"margin-top: 5px; margin-bottom: 5px;\" align=\"center\">
   <div class=\"formHead\" style=\"width: 95%\">".$adm_photo["pho_name"]."</div>
   <div class=\"formBody\" style=\"width: 95%; height: 520px;\">";
      echo"Datum: ".mysqldate("d.m.y", $adm_photo["pho_begin"]);
         if($adm_photo["pho_end"] != $adm_photo["pho_begin"])echo " bis ".mysqldate("d.m.y", $adm_photo["pho_end"]);
		echo "<br>Fotos von: ".$adm_photo["pho_photographers"]."<br><br>";
     //Vor und zurück buttons
	   if($last>0){
			echo"<button name=\"back\" type=\"button\" value=\"back\" style=\"width: 130px;\" onclick=\"self.location.href='photopopup.php?bild=$last&pho_id=$pho_id'\">
              <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Letztes Bild\">  Letztes Bild
           </button>  ";
		}
		if($next<=$bilder){
		echo"<button name=\"forward\" type=\"button\" value=\"forward\" style=\"width: 130px;\" onclick=\"self.location.href='photopopup.php?bild=$next&pho_id=$pho_id'\"> N&auml;chstes Bild
		 			<img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"N&auml;chstes Bild\">
           </button>";
		}
		echo"<br><br>";
		//Ermittlung der Original Bildgröße
   	$bildgroesse = getimagesize("$ordner/$bild.jpg");
		//Entscheidung über scallierung
		//Hochformat Bilder
		if ($bildgroesse[0]<=$bildgroesse[1]){
			$side=y;
			if ($bildgroesse[1]>380) $scal=380;
			else $scal=$bildgroesse[1];
		}
		//Querformat Bilder
		if ($bildgroesse[0]>$bildgroesse[1]){
			$side=x;
			if ($bildgroesse[0]>500) $scal=500;
			else $scal=$bildgroesse[0];
		}
		//Ausgabe Bild
		echo"
      <div style=\"align: center\">
         <img src=\"resize.php?bild=$ordner/$bild.jpg&amp;scal=$scal&amp;aufgabe=anzeigen&amp;side=$side\"  border=\"0\" alt=\"$ordner $bild\">
      </div>
      <div style=\"align: center; margin-top: 10px;\">
		<button name=\"close\" type=\"button\" value=\"close\" style=\"width: 150px;\" onClick=\"parent.window.close()\">
         	<img src=\"$g_root_path/adm_program/images/error.png\" style=\" vertical-align: bottom;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Fenster schlie&szlig;en\">
         	Fenster schlie&szlig;en
      </button>
		</div>
   </div>
   </div>
   ";
   //Seitenende
   echo "</body>
      </html>";
?>