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
if($pho_id=="")$pho_id=NULL;
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

//Erfassen der Eltern Veranstaltung
if($adm_photo["pho_pho_id_parent"]!=NULL){
   $pho_parent_id=$adm_photo["pho_pho_id_parent"];
   $sql = "   SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_id ='$pho_parent_id'";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   $adm_photo_parent = mysql_fetch_array($result);
}
	
//Erfassen des Anlegers der Ubergebenen Veranstaltung
if($pho_id!=NULL && $adm_photo["pho_usr_id"]!=NULL){
	$sql     = "SELECT * FROM ". TBL_USERS. " WHERE usr_id =".$adm_photo["pho_usr_id"];
         $result_u1 = mysql_query($sql, $g_adm_con);
         db_error($result_u1);
         $user1 = mysql_fetch_object($result_u1);
}
//Erfassen des Veraenderers der Ubergebenen Veranstaltung
if($pho_id!=NULL && $adm_photo["pho_usr_id_change"]!=NULL){
	$sql     = "SELECT * FROM ". TBL_USERS. " WHERE usr_id =".$adm_photo["pho_usr_id_change"];
         $result_u2 = mysql_query($sql, $g_adm_con);
         db_error($result_u2);
         $user2 = mysql_fetch_object($result_u2);
}
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
      	//Zurück zur Elternveranstaltung
      	$pho_id=NULL;
      	$sql = "	SELECT *
						FROM ". TBL_PHOTOS. "
						WHERE (pho_id ='$pho_id')";
			$result_event = mysql_query($sql, $g_adm_con);
			db_error($result_event);
			$adm_photo = mysql_fetch_array($result_event);
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
   echo"<h1>Fotogalerien";
	if($pho_id!="" && $adm_photo["pho_pho_id_parent"]!=NULL)echo"&#47".$adm_photo_parent["pho_name"];
	if($pho_id!="")echo "&#47".$adm_photo["pho_name"];
	echo"</h1>";
   //bei Seitenaufruf mit Moderationsrechten
   if($g_session_valid && editPhoto() && $adm_photo["pho_pho_id_parent"]==NULL){
      echo"
      <button name=\"verwaltung\" type=\"button\" value=\"up\" style=\"width: 187px;\"
         onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/event.php?aufgabe=new&amp;pho_id=$pho_id'\">
         <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Veranstaltung anlegen\">  Veranstaltung anlegen
        </button><br><br>";
   }
   
/*************************THUMBNAILS**********************************/	  
 //Nur wenn Veranstaltung übergeben wurde
 if($adm_photo["pho_quantity"]>0){
  	//Aanzahl der Bilder
   $bilder = $adm_photo["pho_quantity"];
	//Speicherort
	$ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

	//Ausrechnen der Seitenzahl, 25 Thumbnails  pro seiet
   if ($seite=='') $seite=1;
   if (settype($bilder,integer) || settype($seiten,integer))
            $seiten = round($bilder / 25);
   if ($seiten * 25 < $bilder) $seiten++; 

	//Rahmung der Galerie
   echo"
	<table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\" style=\"width: 580px\">
		<tr><th class=\"tableHeader\" style=\"text-align: center; font-size: 12pt;\">". strspace($adm_photo["pho_name"]). "</th></tr>
		<tr style=\"text-align: center;\"><td>";
		echo"Datum: ".mysqldate("d.m.y", $adm_photo["pho_begin"]);
         if($adm_photo["pho_end"] != $adm_photo["pho_begin"])echo " bis ".mysqldate("d.m.y", $adm_photo["pho_end"]);
		echo"<br> Seite:&nbsp;";
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
      	echo"<tr><td colspan=\"5\">
			<div style=\"margin: 8px 4px 4px 4px; font-size: 8pt; text-align: center;\">";
				if($adm_photo["pho_usr_id"]!=NULL)
					echo"Angelegt von ". strSpecialChars2Html($user1->usr_first_name). " ". strSpecialChars2Html($user1->usr_last_name).
            	" am ". mysqldatetime("d.m.y h:i", $adm_photo["pho_timestamp"]);
         	if($adm_photo["pho_usr_id_change"]!=NULL)
					echo"<br>	
					Letztes Update durch ". strSpecialChars2Html($user2->usr_first_name). " ". strSpecialChars2Html($user2->usr_last_name).
            	" am ". mysqldatetime("d.m.y h:i", $adm_photo["pho_last_change"]);
			echo"</div>
			</td></tr>";
      echo "</table>";
   echo"
	</td></tr></table>";
  }
/************************Veranstaltungsliste*************************************/	

	//erfassen der Veranstaltungen die in der Veranstaltungstabelle ausgegeben werden sollen
   $sql = "   SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_org_shortname ='$g_organization'";
            if($pho_id==NULL)$sql=$sql."AND (pho_pho_id_parent IS NULL)";
            if($pho_id!=NULL)$sql=$sql."AND pho_pho_id_parent = '$pho_id'";
				$sql=$sql."ORDER BY pho_begin DESC ";
   $result_list = mysql_query($sql, $g_adm_con);
   db_error($result_list);
   
   //anlegen der Veranstaltungstabelle und ausgeb der Kopfzeile wenn Ergbnis vorliegt
   if(mysql_num_rows($result_list)>0 || $pho_id==""){
   	echo "
   	<table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\" style=\"width: 580px\">
      	<tr>
         	<th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Veranstaltung</th>
         	<th class=\"tableHeader\" style=\"text-align: center;\">Datum</th>
         	<th class=\"tableHeader\" style=\"text-align: center;\">Bilder</th>
         	<th class=\"tableHeader\" style=\"text-align: center;\">Letze &Auml;nderung</th>";
         	if ($g_session_valid && editPhoto())
            	echo"<th class=\"tableHeader\" style=\"text-align: center; width: 90px;\">Bearbeiten</th>";
     			 echo"</tr>";

//durchlaufen des Result-Tabelle und Ausgabe in Veranstaltungstabelle
   	$bildersumme=0;//Summe der Bilder in den Unterordnern
   	for($x=0; $adm_photo_list = mysql_fetch_array($result_list); $x++){
         //suchen nach Summe der Bilder in Kinderveranstaltungen
         $sql = "   SELECT SUM(pho_quantity)
				FROM ". TBL_PHOTOS. "
            WHERE pho_org_shortname ='$g_organization'
            AND pho_pho_id_parent = '".$adm_photo_list["pho_id"]."'
				AND pho_approved = '1'
				GROUP BY 'pho_pho_id_parent'
				";
   		$result_kibisu = mysql_query($sql, $g_adm_con);
  			db_error($result_kibisu, 1);
  			$kibiesu=mysql_fetch_array($result_kibisu);
  			$veranst_bilder_summe=$kibiesu[0]+$adm_photo_list["pho_quantity"];
        	$bildersumme=$bildersumme+$veranst_bilder_summe;//erhöhen der Bildersumme
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
       		echo"<td style=\"text-align: center;\">".$veranst_bilder_summe."</td>
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
						
						if($adm_photo_list["pho_approved"]==0 && file_exists($ordner))echo"
							<a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo_list["pho_id"]."&approved=1\">
								<img src=\"$g_root_path/adm_program/images/ok.png\" border=\"0\" alt=\"Freigeben\" title=\"Freigeben\">
							</a>";
						if($adm_photo_list["pho_approved"]==1 && file_exists($ordner)) echo"
							<a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo_list["pho_id"]."&approved=0\">
								<img src=\"$g_root_path/adm_program/images/no.png\" border=\"0\" alt=\"Sperren\" title=\"Sperren\">
							</a>";
					echo"</td>";
             }
         echo"</tr>";
         }//Ende Ordner existiert
   	};//for
//tabbelen Ende mit Ausgabe der Gesammtbilderzahl
   		echo"<tr>
         	<th class=\"tableHeader\" style=\"text-align: right;\" colspan=\"2\">Bilder Gesamt:</th>
         	<th class=\"tableHeader\" style=\"text-align: center;\">$bildersumme</th>
         	<th class=\"tableHeader\">&nbsp;</th>";
         	if ($g_session_valid && editPhoto($g_organization))echo"<th class=\"tableHeader\" colspan=\"2\">&nbsp;</th>";
         echo"</tr>
   	</table>";
   }//if mehr als ein Ergebnis
/****************************Leere Veranstaltung****************/
//Falls die Veranstaltung weder Bilder noch Unterordner enthält
   if($adm_photo["pho_quantity"]=="0" && mysql_num_rows($result_list)==0)
   echo"Diese Veranstaltung enth&auml;lt leider noch keine Bilder.<br><br>";
/************************Buttons********************************/
	//Uebersicht
	if($adm_photo["pho_id"]!=NULL)
	echo"<br>
	<button name=\"up\" type=\"button\" value=\"up\" style=\"width: 135px;\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php'\">
   	<img src=\"../../../adm_program/images/list.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Haupt&uuml;bersicht\">  Haupt&uuml;bersicht
    </button>";
   if($adm_photo["pho_pho_id_parent"]!=NULL)
   echo"&nbsp;&nbsp;
	<button name=\"up\" type=\"button\" value=\"up\" style=\"width: 135px;\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo["pho_pho_id_parent"]."'\">
   	<img src=\"../../../adm_program/images/list.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"&Uuml;bersicht\">  &Uuml;bersicht
    </button>";

/***************************Seitenende***************************/
echo"</div>";
require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>