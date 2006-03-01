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

//Übername der übergebenen Variablen
//ID einer bestimmten Veranstaltung
$pho_id=$_GET["pho_id"];
if($pho_id=="")$pho_id=NULL;
//aktuelle Seite
$seite= $_GET['seite'];
if($seite=="")$seite=1;
//Ansicht
$view=$_GET["view"];
if($view==NULL)$view="preview";

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
//erfassen ob Unterveranstaltungen existieren
$sql = "	SELECT *
			FROM ". TBL_PHOTOS. "
			WHERE pho_pho_id_parent ='$pho_id'";
$result_children = mysql_query($sql, $g_adm_con);
db_error($result_event);
$children = mysql_num_rows($result_children);

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
/*********************LOCKED************************************/		
//Falls gefordert und Photoeditrechet, ändern der Freigabe
	//erfassen der Veranstaltung
	if($_GET["locked"]=="1" || $_GET["locked"]=="0"){
		//bei Seitenaufruf ohne Moderationsrechte
		if(!$g_session_valid || $g_session_valid && !editPhoto($adm_photo["pho_org_shortname"])){
        	$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=photoverwaltunsrecht";
      	header($location);
      	exit();
      }
		//bei Seitenaufruf mit Moderationsrechten
		if($g_session_valid && editPhoto($adm_photo["pho_org_shortname"])){
			$locked=$_GET["locked"];
					$sql= "UPDATE ". TBL_PHOTOS. "
            	   SET 	pho_locked = '$locked'
						WHERE pho_id = '$pho_id'";
      	//SQL Befehl ausführen
      	$result_approved = mysql_query($sql, $g_adm_con);
      	db_error($result_approved);
      	//Zurück zur Elternveranstaltung
      	$pho_id=$adm_photo_parent["pho_id"];
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
   echo"<h1>";
   if($pho_id==NULL)echo"Fotogalerien";
	if($pho_id!=NULL)echo"<a href=\"$g_root_path/adm_program/modules/photos/photos.php\">Fotogalerien</a>";
	if($pho_id!="" && $adm_photo["pho_pho_id_parent"]!=NULL)echo"&#47<a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo["pho_pho_id_parent"]."\">".$adm_photo_parent["pho_name"]."</a>";
	if($pho_id!="")echo "&#47".$adm_photo["pho_name"];
	echo"</h1>";
   //Ansichtwechsel
   if($view=="preview" && ($children>0 || $pho_id==NULL))echo"<a href=\"$g_root_path/adm_program/modules/photos/photos.php?view=list&amp;pho_id=$pho_id\">Listenansicht</a><br><br>";
   if($view=="list" && ($children>0 || $pho_id==NULL))echo"<a href=\"$g_root_path/adm_program/modules/photos/photos.php?view=preview&amp;pho_id=$pho_id\">Vorschauansicht</a><br><br>";
   //bei Seitenaufruf mit Moderationsrechten
   if($g_session_valid && editPhoto() && $adm_photo["pho_pho_id_parent"]==NULL){
      echo"
      <button name=\"verwaltung\" type=\"button\" value=\"up\" style=\"width: 187px;\"
         onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/event.php?aufgabe=new&amp;pho_id=$pho_id'\">
         <img src=\"$g_root_path/adm_program/images/folder_create.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Veranstaltung anlegen\">  Veranstaltung anlegen
        </button><br><br>";
   }
  //Haupttabelle
  	//Spaltenzahl je nach Photoedit oder nicht
  	$colums=4;
  	if($g_session_valid && editPhoto())$colums=5;
  echo"<table class=\"tableList\" cellpadding=\"4\" cellspacing=\"0\">"; 
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
		<tr><th td colspan=\"$colums\" class=\"tableHeader\" style=\"text-align: center; font-size: 12pt;\">". strspace($adm_photo["pho_name"]). "</th></tr>
		<tr style=\"text-align: center;\"><td colspan=\"$colums\">";
		echo"Datum: ".mysqldate("d.m.y", $adm_photo["pho_begin"]);
         if($adm_photo["pho_end"] != $adm_photo["pho_begin"])echo " bis ".mysqldate("d.m.y", $adm_photo["pho_end"]);
		echo"<br> Seite:&nbsp;";
      //Seiten links
      //"Letzte Seite"
		$vorseite=$seite-1;
		if($vorseite>=1)
			echo"	<a href=\"photos.php?seite=$vorseite&amp;pho_id=$pho_id\">Zur&uuml;ck</a>&nbsp;&nbsp;";
		//Seitenzahlen
      for($s=1; $s<=$seiten; $s++){
      	if($s==$seite)echo $seite."&nbsp;";
      	if($s!=$seite)echo"<a href='photos.php?seite=$s&pho_id=$pho_id'>$s</a>&nbsp;";
      }
      //naechste Seite
		$nachseite=$seite+1;
		if($nachseite<=$seiten)
			echo"	<a href=\"photos.php?seite=$nachseite&amp;pho_id=$pho_id\">Vorw&auml;rts</a>";
		echo"</td></tr>
		<tr style=\"text-align: center;\"><td td colspan=\"$colums\">
      <table cellpadding=\"4\" cellspacing=\"0\" border=\"0\" style=\"width: 100%\">";
         for($zeile=1;$zeile<=5;$zeile++){//durchlaufen der Tabellenzeilen
            echo "<tr>";
            for($spalte=1;$spalte<=5;$spalte++){//durchlaufen der Tabellenzeilen
               $bild = ($seite*25)-25+($zeile*5)-5+$spalte;//Errechnug welches Bild ausgegeben wird
               if ($bild <= $bilder){
                  echo"<td style=\"width: 20%\">
                     <img onclick=\"window.open('photopopup.php?bild=$bild&pho_id=$pho_id','msg', 'height=600,width=580,left=162,top=5')\" style=\"vertical-align: middle; cursor: pointer;\"
								 src=\"resize.php?bild=$ordner/$bild.jpg&amp;scal=100&amp;aufgabe=anzeigen\" border=\"0\" alt=\"$bild\">
						<br>";
						//Buttons für moderatoren
						if ($g_session_valid && editPhoto($adm_photo["pho_org_shortname"])){
							echo"
							<a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_photo&err_head=Foto L&ouml;schen&button=2&url=". urlencode("$g_root_path/adm_program/modules/photos/photodelete.php?pho_id=$pho_id&bild=$bild&seite=$seite"). "\">
								<img src=\"$g_root_path/adm_program/images/photo_delete.png\" border=\"0\" alt=\"Photo löschen\" title=\"Photo löschen\">
							</a>";
						}
					echo"</td>";
					}//if
				}//for
            echo "</tr>";//Zeilenende
         }//for
      	//Anleger und Veraendererinfos
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
	</td></tr>";
  }
/************************Veranstaltungsliste/Preview*************************************/	

	//erfassen der Veranstaltungen die in der Veranstaltungstabelle ausgegeben werden sollen
   $sql = "   SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_org_shortname ='$g_organization'";
            if($pho_id==NULL)$sql=$sql."AND (pho_pho_id_parent IS NULL)";
            if($pho_id!=NULL)$sql=$sql."AND pho_pho_id_parent = '$pho_id'";
				$sql=$sql."ORDER BY pho_begin DESC ";
   $result_list = mysql_query($sql, $g_adm_con);
   db_error($result_list);

/***************nur Listenansicht********************/  
   //anlegen der Veranstaltungstabelle und ausgeb der Kopfzeile wenn Ergbnis vorliegt
   if((mysql_num_rows($result_list)>0 || $pho_id=="") && $view=="list"){
   	echo "
     	<tr>
        	<th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Veranstaltung</th>
        	<th class=\"tableHeader\" style=\"text-align: center;\">Datum</th>
        	<th class=\"tableHeader\" style=\"text-align: center;\">Bilder</th>
        	<th class=\"tableHeader\" style=\"text-align: center;\">Letze &Auml;nderung</th>";
        	if ($g_session_valid && editPhoto())
           	echo"<th class=\"tableHeader\" style=\"text-align: center; width: 90px;\">Bearbeiten</th>";
 			 echo"</tr>";
   }
/***beides******/
	//durchlaufen des Result-Tabelle und Ausgabe in Veranstaltungstabelle
   	$bildersumme=0;//Summe der Bilder in den Unterordnern
   	for($x=0; $adm_photo_list = mysql_fetch_array($result_list); $x++){
        
         //suchen nach Summe der Bilder in Kinderveranstaltungen
         $sql = "   SELECT SUM(pho_quantity)
				FROM ". TBL_PHOTOS. "
            WHERE pho_org_shortname ='$g_organization'
            AND pho_pho_id_parent = '".$adm_photo_list["pho_id"]."'
				AND pho_locked = '0'
				GROUP BY 'pho_pho_id_parent'
				";
   		$result_kibisu = mysql_query($sql, $g_adm_con);
  			db_error($result_kibisu, 1);
  			$kibiesu=mysql_fetch_array($result_kibisu);
  			$veranst_bilder_summe=$kibiesu[0]+$adm_photo_list["pho_quantity"];
        	//Nur hinzurechen wenn Veranstaltung freigegeben ist oder der besucher Photoverwaltungsrechte hat
        	if($adm_photo_list["pho_locked"]=="0" || ($g_session_valid && editPhoto()))
        		$bildersumme=$bildersumme+$veranst_bilder_summe;
     
         //Speicherort der Bilder
         $ordner = "../../../adm_my_files/photos/".$adm_photo_list["pho_begin"]."_".$adm_photo_list["pho_id"];
         
         //Kontrollieren ob der entsprechende Ordner in adm_my_files existiert und freigegeben ist oder Photoeditrechte bestehen
         //wenn ja Zeile ausgeben
         if(file_exists($ordner) && ($adm_photo_list["pho_locked"]==0) || ($g_session_valid && editPhoto($adm_photo_list["pho_org_shortname"]))){
/***************nur Vorschauansicht******************/
			if($view=="preview"){
				//Bild aus Veranstaltung als Vorschau auswählen wenn sie Bilder enthält sonst ggf. Auf unterveranst zurückgreifen
				if($adm_photo_list["pho_quantity"]>0)
					$previewpic=mt_rand(1, $adm_photo_list["pho_quantity"]);
					$previewordner=$ordner;
				if($adm_photo_list["pho_quantity"]==0){
					 $sql = "   SELECT *
								FROM ". TBL_PHOTOS. "
            				WHERE pho_pho_id_parent = '".$adm_photo_list["pho_id"]."'
								AND pho_locked = '0'
								AND pho_quantity > '0'
					";
   				$result_preview = mysql_query($sql, $g_adm_con);
  					db_error($result_preview, 1);
  					$adm_photo_preview=mysql_fetch_array($result_preview);
  					$previewpic=mt_rand(1, $adm_photo_preview["pho_quantity"]);
					$previewordner="../../../adm_my_files/photos/".$adm_photo_preview["pho_begin"]."_".$adm_photo_preview["pho_id"];
				}
				echo"<tr>
					<td><a target=\"_self\" href=\"photos.php?pho_id=".$adm_photo_list["pho_id"]."\">
							<img src=\"resize.php?bild=$previewordner/$previewpic.jpg&amp;scal=150&amp;aufgabe=anzeigen\" border=\"0\" alt=\"$previewpic\"
						  style=\"vertical-align: middle;\"></a></td>
					<td>
						<a target=\"_self\" href=\"photos.php?pho_id=".$adm_photo_list["pho_id"]."\">".$adm_photo_list["pho_name"]."</a><br>
						<div style=\"margin: 8px 4px 4px 4px; font-size: 8pt; text-align: left;\">
							Bilder: ".$veranst_bilder_summe." <br>
							Datum: ".mysqldate("d.m.y", $adm_photo_list["pho_begin"]);
										if($adm_photo["pho_end"] != $adm_photo["pho_begin"])echo " bis ".mysqldate("d.m.y", $adm_photo["pho_end"]);
							echo "<br>Fotos von: ".$adm_photo_list["pho_photographers"]."<br>";
						 	if ($g_session_valid && editPhoto($adm_photo_list["pho_org_shortname"])){
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
								if($adm_photo_list["pho_locked"]==1 && file_exists($ordner))echo"
									<a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo_list["pho_id"]."&locked=0\">
										<img src=\"$g_root_path/adm_program/images/key.png\" border=\"0\" alt=\"Freigeben\" title=\"Freigeben\">
									</a>";
								if($adm_photo_list["pho_locked"]==0 && file_exists($ordner)) echo"
									<a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo_list["pho_id"]."&locked=1\">
										<img src=\"$g_root_path/adm_program/images/key.png\" border=\"0\" alt=\"Sperren\" title=\"Sperren\">
									</a>";
						 	}
					echo"
            
						</div>
					</td>
				</tr>";

				 
			
			
			}//Ende nur Vorschauansicht
/***************nur Listenansicht********************/    
         if($view=="list"){
         echo "
         <tr class=\"listMouseOut\" onMouseOver=\"this.className='listMouseOver'\" onMouseOut=\"this.className='listMouseOut'\">
            <td style=\"text-align: left;\">&nbsp;";
				//Warnung fuer Leute mit Fotorechten: Ordner existiert nicht
				if(!file_exists($ordner) && ($g_session_valid && editPhoto($adm_photo_list["pho_org_shortname"])))
					echo"<img src=\"$g_root_path/adm_program/images/warning16.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Warnhinweis\" title=\"Warnhinweis\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=folder_not_found','Message','width=500, height=260, left=310,top=200,scrollbars=no')\">&nbsp;";
				
				//Hinweis fur Leute mit Photorechten: Veranstaltung ist gesperrt
				if($adm_photo_list["pho_locked"]==1 && file_exists($ordner))
					echo"<img src=\"$g_root_path/adm_program/images/lock.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Veranstaltung ist gesperrt\" title=\"Veranstaltung ist gesperrt\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=not_approved','Message','width=500, height=200, left=310,top=200,scrollbars=no')\">&nbsp;";
				//Veranstaltungsname mit Link zu Thumbnails
				echo"<a target=\"_self\" href=\"photos.php?pho_id=".$adm_photo_list["pho_id"]."\">".$adm_photo_list["pho_name"]."</a></td>";
            //Beginn der Veranst.
            echo"<td style=\"text-align: center;\">".mysqldate("d.m.y", $adm_photo_list["pho_begin"])."</td>";
       		//Summer der Bilder in Veranst
       		echo"<td style=\"text-align: center;\">".$veranst_bilder_summe."</td>";
       		//Datum der letzten Aenderung der Veranst
       		echo"<td style=\"text-align: center;\">".mysqldate("d.m.y", $adm_photo_list["pho_last_change"])."</td>";
           
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
						
						if($adm_photo_list["pho_locked"]==1 && file_exists($ordner))echo"
							<a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo_list["pho_id"]."&locked=0\">
								<img src=\"$g_root_path/adm_program/images/key.png\" border=\"0\" alt=\"Freigeben\" title=\"Freigeben\">
							</a>";
						if($adm_photo_list["pho_locked"]==0 && file_exists($ordner)) echo"
							<a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo_list["pho_id"]."&locked=1\">
								<img src=\"$g_root_path/adm_program/images/key.png\" border=\"0\" alt=\"Sperren\" title=\"Sperren\">
							</a>";
					echo"</td>";
             }
         echo"</tr>";
         }//Ende nur Listenansicht
/***beides***/
         }//Ende Ordner existiert
   	};//for
/***************nur Listenansicht********************/	
	//tabbelen Ende mit Ausgabe der Gesammtbilderzahl
   	if($view=="list"){
   		echo"<tr>
         	<th class=\"tableHeader\" style=\"text-align: right;\" colspan=\"2\">Bilder Gesamt:</th>
         	<th class=\"tableHeader\" style=\"text-align: center;\">$bildersumme</th>
         	<th class=\"tableHeader\">&nbsp;</th>";
         	if ($g_session_valid && editPhoto($g_organization))echo"<th class=\"tableHeader\" colspan=\"2\">&nbsp;</th>";
         echo"</tr>";
   	}
/****************************Leere Veranstaltung****************/
//Falls die Veranstaltung weder Bilder noch Unterordner enthält
   if($adm_photo["pho_quantity"]=="0" && mysql_num_rows($result_list)==0)
   echo"<tr style=\"text-align: center;\"><td td colspan=\"$colums\">
				Diese Veranstaltung enth&auml;lt leider noch keine Bilder.
			</td></tr>";
/************************Ende Haupttabelle**********************/
echo"</table>";
/************************Buttons********************************/
	//Uebersicht
	if($adm_photo["pho_id"]!=NULL)
	echo"<br>
	<button name=\"up\" type=\"button\" value=\"up\" style=\"width: 135px;\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php'\">
   	<img src=\"../../../adm_program/images/list.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Haupt&uuml;bersicht\">  &Uuml;bersicht
    </button>";

/***************************Seitenende***************************/
echo"</div>";
require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>