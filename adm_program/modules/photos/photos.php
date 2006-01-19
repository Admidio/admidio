<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
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

//Übername der übergebenen Variablen
//ID einer bestimmten Veranstaltung
$pho_id=$_GET["pho_id"];
//aktuelle Seite
$seite= $_GET['seite'];
if($seite=="")$seite=1;

//Aufruf der ggf. Übergebenen Veranstaltung
$sql = "	SELECT *
			FROM ". TBL_PHOTOS. "
			WHERE (pho_id ='$pho_id')";
$result_event = mysql_query($sql, $g_adm_con);
db_error($result_event);
$adm_photo = mysql_fetch_array($result_event);

		
/*********************APPROVED************************************/		
//Falls gefordert und Photoeditrechet, ändern der Freigabe
	//erfassen der Veranstaltung
	if($_GET["approved"]=="1" || $_GET["approved"]=="0"){
		//bei Seitenaufruf ohne Moderationsrechte
		if(!$g_session_valid || $g_session_valid && !editPhoto($adm_photo["pho_org_shortname"])){
        	$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=photoverwaltunsrecht";
      	header($location);
      	exit();
      }
		//bei Seitenaufruf mit Moderationsrechten
		if($g_session_valid && editPhoto($adm_photo["pho_org_shortname"])){
			$approved=$_GET["approved"];
					$sql= "UPDATE ". TBL_PHOTOS. "
            	   SET 	pho_approved = '$approved'
						WHERE pho_id = '$pho_id'";
      	//SQL Befehl ausführen
      	$result_approved = mysql_query($sql, $g_adm_con);
      	db_error($result_approved);
		}
	}
/*********************HTML_TEIL*******************************/	

    //allgemeiner HTML-Teil
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>$g_current_organization->longname - Fotogalerien</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

      <!--[if gte IE 5.5000]>
      <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      require("../../../adm_config/header.php");
   echo "</head>";

   require("../../../adm_config/body_top.php");

   echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";
   echo"<h1>Fotogalerien</h1>";
   //bei Seitenaufruf mit Moderationsrechten
   if($g_session_valid && editPhoto()){
      echo"
      <button name=\"verwaltung\" type=\"button\" value=\"up\" style=\"width: 187px;\"
         onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/event.php?aufgabe=new'\">
         <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Veranstaltung anlegen\">  Veranstaltung anlegen
        </button><br><br>";
   }
/*************************THUMBNAILS**********************************/	  
 //Nur wenn Veranstaltung übergeben wurde
 if($pho_id!=""){
  	//Aanzahl der Bilder
   $bilder = $adm_photo["pho_quantity"];
	//Speicherort
	$ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

	//Ausrechnen der Seitenzahl, 25 Thumbnails  pro seiet
   If ($seite=='') $seite=1;
   If (settype($bilder,integer) || settype($seiten,integer))
            $seiten = round($bilder / 25);
   If ($seiten * 25 < $bilder) $seiten++; 

	//Rahmung der Galerie
   echo"<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";
   //Ausgabe der &Uuml;berschrift
   echo "<div class=\"formHead\" style=\"width: 90%\">". strspace($adm_photo["pho_name"]). "</div>
   <div class=\"formBody\" style=\"width: 90%\">";
		
		echo"Datum: ".mysqldate("d.m.y", $adm_photo["pho_begin"]);
         if($adm_photo["pho_end"] != $adm_photo["pho_begin"])echo " bis ".mysqldate("d.m.y", $adm_photo["pho_end"]);
		echo"<br><br> Seite:&nbsp;";
      //Seiten links
      //"Letzte Seite"
		$vorseite=$seite-1;
		if($vorseite>=1)
			echo"	<a href=\"thumbnails.php?seite=$vorseite&amp;pho_id=$pho_id\">Letzte</a>&nbsp;&nbsp;";
		//Seitenzahlen
      for($s=1; $s<=$seiten; $s++){
      	if($s==$seite)echo $seite."&nbsp;";
      	if($s!=$seite)echo"<a href='photos.php?seite=$s&pho_id=$pho_id'>$s</a>&nbsp;";
      }
      //naechste Seite
		$nachseite=$seite+1;
		if($nachseite<=$seiten)
			echo"	<a href=\"photos.php?seite=$nachseite&amp;pho_id=$pho_id\">N&auml;chste</a>";
		echo"
      <table cellspacing=10 cellpadding=0 border=0>";
         for($zeile=1;$zeile<=5;$zeile++){//durchlaufen der Tabellenzeilen
            echo "<tr>";
            for($spalte=1;$spalte<=5;$spalte++){//durchlaufen der Tabellenzeilen
               $bild = ($seite*25)-25+($zeile*5)-5+$spalte;//Errechnug welches Bild ausgegeben wird
               if ($bild <= $bilder){
                  echo "<td align=\"center\">
                     <img onclick=\"window.open('photopopup.php?bild=$bild&pho_id=$pho_id','msg', 'height=600,width=580,left=162,top=5')\" style=\"vertical-align: middle; cursor: pointer;\" src=\"resize.php?bild=$ordner/$bild.jpg&amp;scal=100&amp;aufgabe=anzeigen\" border=\"0\" alt=\"$bild\">
						<br>";
						//Buttons für moderatoren
						if ($g_session_valid && editPhoto($adm_photo["pho_org_shortname"])){
							echo"
							<a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_photo&err_head=Foto L&ouml;schen&button=2&url=". urlencode("$g_root_path/adm_program/modules/photos/photodelete.php?pho_id=$pho_id&bild=$bild&seite=$seite"). "\">
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
		//Uebersicht
		echo"	<button name=\"up\" type=\"button\" value=\"up\" style=\"width: 135px;\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php'\">
           		<img src=\"../../../adm_program/images/list.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"&Uuml;bersicht\">  &Uuml;bersicht
           	</button>";
		
   echo"
	</div>
   </div>";
  }
/************************TABELLE*************************************/	

	//erfassen der Veranstaltungen die in der Veranstaltungstabelle ausgegeben werden sollen
   $sql = "   SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_org_shortname ='$g_organization'";
            if($pho_id=="")$sql=$sql."AND (pho_pho_id_parent IS NULL)";
            if($pho_id!="")$sql=$sql."AND pho_pho_id_parent = '$pho_id'";
				$sql=$sql."ORDER BY pho_begin DESC ";
   $result_list = mysql_query($sql, $g_adm_con);
   db_error($result_list);
   
   //anlegen der Veranstaltungstabelle und ausgeb der Kopfzeile wenn Ergbnis vorliegt
   if(mysql_num_rows($result_list)>0 || $pho_id==""){
   	echo "
   	<table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
      	<tr>
         	<th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Veranstaltung</th>
         	<th class=\"tableHeader\" style=\"text-align: left;\">Datum</th>
         	<th class=\"tableHeader\" style=\"text-align: center;\">Bilder</th>
         	<th class=\"tableHeader\" style=\"text-align: center;\">Letze &Auml;nderung</th>";
         	if ($g_session_valid && editPhoto())
            	echo"<th class=\"tableHeader\" style=\"text-align: center; width: 90px;\">Bearbeiten</th>";
     			 echo"</tr>";

//durchlaufen des Result-Tabelle und Ausgabe in Veranstaltungstabelle
   	$bildersumme=0;//Summe der Bilder in den Unterordnern
   	for($x=0; $adm_photo_list = mysql_fetch_array($result_list); $x++){
         $bildersumme=$bildersumme+$adm_photo_list["pho_quantity"];//erhöhen der Bildersumme
         $ordner = "../../../adm_my_files/photos/".$adm_photo_list["pho_begin"]."_".$adm_photo_list["pho_id"];
         //Kontrollieren ob der entsprechende Ordner in adm_my_files existiert und freigegeben ist oder Photoeditrechte bestehen
         //wenn ja Zeile ausgeben
         if(file_exists($ordner) && ($adm_photo_list["pho_approved"]==1) || ($g_session_valid && editPhoto($adm_photo_list["pho_org_shortname"]))){
         echo "
         <tr class=\"listMouseOut\" onMouseOver=\"this.className='listMouseOver'\" onMouseOut=\"this.className='listMouseOut'\">
            <td style=\"text-align: left;\">&nbsp;";
				//Warnung für Für Leute mit Fotorechten
				if(!file_exists($ordner) && ($g_session_valid && editPhoto($adm_photo_list["pho_org_shortname"])))
					echo"<img src=\"$g_root_path/adm_program/images/warning16.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Warnhinweis\" title=\"Warnhinweis\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=folder_not_found','Message','width=500, height=260, left=310,top=200,scrollbars=no')\">&nbsp;";
				echo"<a target=\"_self\" href=\"photos.php?pho_id=".$adm_photo_list["pho_id"]."\">".$adm_photo_list["pho_name"]."</a></td>
            <td style=\"text-align: center;\">".mysqldate("d.m.y", $adm_photo_list["pho_begin"])."</td>";//Anzeige beginn datum im deutschen Format
       		echo"<td style=\"text-align: center;\">".$adm_photo_list["pho_quantity"]."</td>
            <td style=\"text-align: center;\">".mysqldate("d.m.y", $adm_photo_list["pho_last_change"])."</td>";//Anzeige online seitdatum im deutschen Format
            //Bearbeitungsbuttons für Leute mit Photoeditrechten
            if ($g_session_valid && editPhoto($adm_photo_list["pho_org_shortname"])){
               echo"<td style=\"text-align: center;\">";
                  if(file_exists($ordner)){
                  echo"
						<a href=\"$g_root_path/adm_program/modules/photos/photoupload.php?pho_id=".$adm_photo_list["pho_id"]."\">
                     <img src=\"$g_root_path/adm_program/images/photo.png\" border=\"0\" alt=\"Photoupload\" title=\"Photoupload\"></a>&nbsp;
                  <a href=\"$g_root_path/adm_program/modules/photos/event.php?pho_id=".$adm_photo_list["pho_id"]."&aufgabe=change\">
                     <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"></a>&nbsp;";
            		}
                  $err_text= $adm_photo_list["pho_name"]."(Beginn: ".mysqldate("d.m.y", $adm_photo_list["pho_begin"]).")";
                  echo"
                  <a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_veranst&err_text=$err_text&err_head=Veranstaltung L&ouml;schen&button=2&url=". urlencode("$g_root_path/adm_program/modules/photos/event.php?aufgabe=delete&pho_id=".$adm_photo_list["pho_id"].""). "\">
                     <img src=\"$g_root_path/adm_program/images/delete.png\" border=\"0\" alt=\"Veranstaltung löschen\" title=\"Veranstaltung löschen\"></a>";
						
						if($adm_photo_list["pho_approved"]==0 && file_exists($ordner)) echo "<a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo_list["pho_id"]."&approved=1\">
                     <img src=\"$g_root_path/adm_program/images/ok.png\" border=\"0\" alt=\"Freigeben\" title=\"Freigeben\"></a>&nbsp;";
						if($adm_photo_list["pho_approved"]==1 && file_exists($ordner)) echo "<a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo_list["pho_id"]."&approved=0\">
                     <img src=\"$g_root_path/adm_program/images/no.png\" border=\"0\" alt=\"Sperren\" title=\"Sperren\"></a>&nbsp;";
					echo"</td>";
             }
         echo"</tr>
         ";
         }//Ende Ordner existiert
   	};//for
   	// wenn keine Bilder vorhanden sind, dann eine Meldung ausgeben
   	if(mysql_num_rows($result_list)==0)
      	echo "<tr><td>&nbsp;Es sind keine Veranstaltungen vorhanden.</td></tr>";
//tabbelen Ende mit Ausgabe der Gesammtbilderzahl
   		echo"<tr>
         	<th class=\"tableHeader\" style=\"text-align: right;\" colspan=\"2\">Bilder Gesamt:</th>
         	<th class=\"tableHeader\" style=\"text-align: center;\">$bildersumme</th>
         	<th class=\"tableHeader\">&nbsp;</th>";
         	if ($g_session_valid && editPhoto($g_organization))echo"<th class=\"tableHeader\" colspan=\"2\">&nbsp;</th>";
         echo"</tr>
   	</table>";
   }//if mehr als ein Ergebnis
  



//Seitenende
echo"</div>";
require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>