<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * Seiten : welche Seite der Thumbnails angezeigt werden soll
 * Ordner : aus welchem Ordner die Thubnails angezeigt werden sollen
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
   require("../../system/string.php");
   require("../../system/tbl_user.php");
   require("../../system/session_check.php");

//&uuml;bernahme der &Uuml;bergebenen Variablen
   $ap_id= $_GET['ap_id'];
   $seite= $_GET['seite'];//aktuelle Seite
	if($seite=="")$seite=1;

//erfassen der Veranstaltung 
	$sql = "	SELECT * 
				FROM adm_photo 
				WHERE (ap_id ='$ap_id')";
	$result = mysql_query($sql, $g_adm_con);
	db_error($result);
	$adm_photo = mysql_fetch_array($result);

//Aanzahl der Bilder
   $bilder = $adm_photo[1];
//Speicherort
	$ordner = "../../../adm_my_files/photos/"."$adm_photo[3]"."_$adm_photo[0]";

//Ausrechnen der Seitenzahl, 25 Thumbnails  pro seiet
   If ($seite=='') $seite=1;
   If (settype($bilder,integer) || settype($seiten,integer))
            $seiten = round($bilder / 25);
   If ($seiten * 25 < $bilder) $seiten++;

//Beginn HTML
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>$g_title - $adm_photo[2]</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">";

      // Javascript deffinition der Links zu den Thumbnails
      echo "<script type=\"text/javascript\"><!-- Begin ";
      for($x=($seite-1)*25+1;$x<=($seite*25);$x++)
      {
         echo "
         function win$x()
         {
            msg = window.open(\"photopopup.php?bild=$x&ap_id=$ap_id\",\"msg\",\"height=600,width=580,left=162,top=5\")
         } ";
      };
      echo "// End --></script>

      <!--[if gte IE 5.5000]>
      <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      require("../../../adm_config/header.php");
   echo "</head>";

//Beginn sichtbarer inhalt
   require("../../../adm_config/body_top.php");
//Rahmung der Galerie
   echo"<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";

   //Ausgabe der &Uuml;berschrift
   echo "<div class=\"formHead\" style=\"width: 90%\">". strspace($adm_photo[2]). "</div>
   <div class=\"formBody\" style=\"width: 90%\">";
      $dt_date_von = mysqldate("d.m.y", $adm_photo[3]);
		$dt_date_bis = mysqldate("d.m.y", $adm_photo[4]);
		echo"<b>Datum: $dt_date_von";
         if($dt_date_von != $dt_date_bis)echo " bis $dt_date_bis";
		echo"
		<br><br>
      Seite $seite / $seiten
		
      <table cellspacing=10 cellpadding=0 border=0>";
         for($zeile=1;$zeile<=5;$zeile++){//durchlaufen der Tabellenzeilen
            echo "<tr>";
            for($spalte=1;$spalte<=5;$spalte++){//durchlaufen der Tabellenzeilen
               $bild = ($seite*25)-25+($zeile*5)-5+$spalte;//Errechnug welches Bild ausgegeben wird
               if ($bild <= $bilder){
                  echo "<td align=\"center\">
                  <a href=\"#\" onclick=\"win$bild()\">
                     <img src=\"resize.php?bild=$ordner/$bild.jpg&amp;scal=100&amp;aufgabe=anzeigen\" border=\"0\" alt=\"$bild\">
                  </a>
						<br>";
						//Buttons für moderatoren
						if ($g_session_valid & editPhoto()){
							echo"
							<a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_photo&err_head=Foto L&ouml;schen&button=2&url=". urlencode("$g_root_path/adm_program/moduls/photos/photodelete.php?ap_id=$ap_id&bild=$bild&seite=$seite"). "\">
								<img src=\"$g_root_path/adm_program/images/photo_delete.png\" border=\"0\" alt=\"Photo löschen\" title=\"Photo löschen\">
							</a>";
						}
						echo"
						</td>";
               }//if
            }//for
            echo "</tr>";//Zeilenende
         }//for
      echo "</table>
		<hr width=\"85%\" />
      <br>";
		//definition der Buttons vor zur&uuml;ck &Uuml;bersich die nur angezeigt werden sollen wenn sie ben&ouml;tigt werden
		//"Letzte Seite"
		$vorseite=$seite-1;
		if($vorseite>=1){
			echo"	<button name=\"back\" type=\"button\" value=\"back\" style=\"width: 140px;\" onclick=\"self.location.href='thumbnails.php?seite=$vorseite&amp;ap_id=$ap_id'\">
         	<img src=\"../../../adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Letzte Seite\">&nbsp;Vorherige Seite
         </button>";
		}
		//Uebersicht
		echo"	<button name=\"up\" type=\"button\" value=\"up\" style=\"width: 135px;\" onclick=\"self.location.href='$g_root_path/adm_program/moduls/photos/photos.php'\">
           		<img src=\"../../../adm_program/images/list.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"&Uuml;bersicht\">  &Uuml;bersicht
           	</button>";
		//naechste Seite
		$nachseite=$seite+1;
		if($nachseite<=$seiten){
		echo"	<button name=\"forward\" type=\"button\" value=\"forward\" style=\"width: 140px;\" onclick=\"self.location.href='thumbnails.php?seite=$nachseite&amp;ap_id=$ap_id'\">N&auml;chste Seite
            	<img src=\"../../../adm_program/images/forward.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"N&auml;chste Seite\">
            </button>";
		}
   echo"
	</div>
   </div>";
   
//Seitenende
   require("../../../adm_config/body_bottom.php");
   echo "</body>
      </html>";
?>