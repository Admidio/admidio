<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
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
	require("../../system/session_check.php");

//Übernahme Variablen
   $pho_id= $_GET['pho_id'];
	$aufgabe=$_GET['aufgabe'];
//Aktueller Timestamp
   $act_datetime= date("Y.m.d G:i:s", time());
//Erfassen der Veranstaltung bei Änderungsaufruf
   $sql = "   SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE (pho_id ='$pho_id')";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   $adm_photo = mysql_fetch_array($result);

//Erfassen der Eltern Veranstaltung
   $pho_parent_id=$adm_photo["pho_pho_id_parent"];
   $sql = "   SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_id ='$pho_parent_id'";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   $adm_photo_parent = mysql_fetch_array($result);
   
//erfassen der Veranstaltungsliste
   $sql = "   SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_org_shortname ='$g_organization'
				ORDER BY pho_begin DESC ";
   $result_list = mysql_query($sql, $g_adm_con);
   db_error($result_list);

//bei Seitenaufruf ohne Moderationsrechte

if(!$g_session_valid || $g_session_valid && (!editPhoto($adm_photo["pho_org_shortname"]) && $aufgabe="change") || !editPhoto())
      {
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=photoverwaltunsrecht";
      header($location);
      exit();
      }
//bei Seitenaufruf mit Moderationsrechten
if($g_session_valid && editPhoto($adm_photo["$g_organization"])){

//Speicherort
   $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];
/********************Änderungen oder Neueintäge speichern***********************************/
   if($_POST["submit"]){
   //Gesendete Variablen übernehmen und kontollieren
      //Veranstaltung
      $veranstaltung = $_POST["veranstaltung"];
      if($veranstaltung==""){
          $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=veranstaltung";
         header($location);
         exit();
      }
      //Parent-Ordner
      $parent_id = $_POST["parent"];
      //Beginn
      $beginn =  $_POST["beginn"];
         if($beginn=="" || !dtCheckDate($beginn)){
            $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=startdatum";
            header($location);
            exit();
         }
         if(dtCheckDate($beginn))$beginn = dtFormatDate($beginn, "Y-m-d");
      //Ende
      $ende =  $_POST["ende"];
         if($ende=="") $ende=$beginn;
         else {
            if(!dtCheckDate($ende)){
               $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=enddatum";
               header($location);
               exit();
            }
            if(dtCheckDate($ende))$ende = dtFormatDate($ende, "Y-m-d");
         }
      //Anfang muss vor oder gleich Ende sein
      if($ende<$beginn){
      	$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=startvorend";
               header($location);
               exit();
      }
      //Photographen
      $photographen =  $_POST["photographen"];
         if($photographen=="")$photographen="leider unbekannt";
         
      //Freigabe
   	$locked=$_POST["locked"];
   //NeuenDatensatz anlegen falls makenew
   if ($aufgabe=="makenew"){
      $sql="INSERT INTO ". TBL_PHOTOS. " (pho_quantity, pho_name, pho_begin,
                         pho_end, pho_photographers, pho_timestamp, pho_last_change, pho_org_shortname,
									pho_usr_id, pho_locked)
               VALUES(0, 'neu', '0000-00-00', '0000-00-00', 'leider unbekannt',
                      '$act_datetime', '$act_datetime', '$g_organization',
								'$g_current_user->id', '$locked')
      ";
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);
      //erfragen der id
      $pho_id=mysql_insert_id($g_adm_con);
   }
   //Verzeichnis erstellen
      if ($aufgabe=="makenew"){
         $ordnerneu = "$beginn"."_"."$pho_id";
         //testen ob Schreibrechte für adm_my_files bestehen
         if (decoct(fileperms("../../../adm_my_files/photos"))!=40777){
            //Wenn keine Schreibrechte Löschen der Daten aus der Datenbank
            $sql ="DELETE
               FROM ". TBL_PHOTOS. "
               WHERE (pho_id ='$pho_id')";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            $load_url = urlencode("$g_root_path/adm_program/modules/photos/photos.php");
            $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=write_access&err_text=adm_my_files/photos&url=$load_url";
            header($location);
            exit();
         }
         //wenn Rechte OK, Ordner erstellen
         $ordnererstellt = mkdir("../../../adm_my_files/photos/$ordnerneu",0777);
         chmod("../../../adm_my_files/photos/$ordnerneu", 0777);
      }//if
   //Bearbeiten Anfangsdatum und Ordner ge&auml;ndert
      if ($aufgabe=="makechange" && $ordner!="../../../adm_my_files/photos/"."$beginn"."_"."$pho_id"){
         $ordnerneu = "$beginn"."_".$adm_photo["pho_id"];
         //testen ob Schreibrechte für adm_my_files bestehen
         if (decoct(fileperms("../../../adm_my_files/photos"))!=40777){
            $load_url = urlencode("$g_root_path/adm_program/modules/photos/photos.php");
            $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=write_access&err_text=adm_my_files/photos&url=$load_url";
            header($location);
            exit();
         }
         //wenn Rechte OK, Ordner erstellen
         $ordnererstellt = mkdir("../../../adm_my_files/photos/$ordnerneu",0777);
         chmod("../../../adm_my_files/photos/$ordnerneu", 0777);
         //Dateien verschieben
         for($x=1; $x<=$adm_photo["pho_quantity"]; $x++){
            chmod("$ordner/$x.jpg", 0777);
            copy("$ordner/$x.jpg", "../../../adm_my_files/photos/$ordnerneu/$x.jpg");
            unlink("$ordner/$x.jpg");
         }
         //alten ordner loeschen
         chmod("$ordner", 0777);
         rmdir("$ordner");
      }//if
//&Auml;ndern  der Daten in der Datenbank
         $sql= "UPDATE ". TBL_PHOTOS. "
                SET  pho_name = '$veranstaltung',";
                if($parent_id!="0")$sql=$sql."pho_pho_id_parent = '$parent_id',";
                if($parent_id=="0")$sql=$sql."pho_pho_id_parent = NULL,";
                $sql=$sql." 
							pho_begin ='$beginn',
                     pho_end ='$ende',
                     pho_photographers ='$photographen',
							pho_last_change ='$act_datetime',
							pho_usr_id_change = $g_current_user->id,
							pho_locked = '$locked'
					WHERE pho_id = '$pho_id'";
      //SQL Befehl ausführen
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);
   //daten aus Datenbank neu laden
   //erfassen der Veranstaltung
   $sql = "   SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_id ='$pho_id'";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   $neudaten = mysql_fetch_array($result);
	//Speicherort
   $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];
	//Erfassen der Eltern Veranstaltung
   $pho_parent_id=$neudaten["pho_pho_id_parent"];
   $sql = "   SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_id ='$pho_parent_id'";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   $neudaten_parent = mysql_fetch_array($result);
   
   //Erfassen des Anlegers der Ubergebenen Veranstaltung
	if($pho_id!=NULL){
		$sql     = "SELECT * FROM ". TBL_USERS. " WHERE usr_id =".$neudaten["pho_usr_id"];
         $result_u1 = mysql_query($sql, $g_adm_con);
         db_error($result_u1);
         $user1 = mysql_fetch_object($result_u1);
	}
	//Erfassen des Veraenderers der Ubergebenen Veranstaltung
	if($pho_id!=NULL){
		$sql     = "SELECT * FROM ". TBL_USERS. " WHERE usr_id =".$neudaten["pho_usr_id_change"];
         $result_u2 = mysql_query($sql, $g_adm_con);
         db_error($result_u2);
         $user2 = mysql_fetch_object($result_u2);
	}
   }// If submit

/******************************HTML-Teil******************************************/
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>$g_current_organization->longname - Veranstaltungsverwaltung</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

      <!--[if gte IE 5.5000]>
      <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      require("../../../adm_config/header.php");
   echo "</head>";

   require("../../../adm_config/body_top.php");
   echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";
/*******************************Bericht*********************************************/
   if($_POST["submit"]){
      echo"<div style=\"width: 430px\" align=\"center\" class=\"formHead\">Bericht</div>";
      echo"
      <div style=\"width: 430px\" align=\"center\" class=\"formBody\">
          <table cellspacing=3 cellpadding=0 border=\"0\">
            <tr><td colspan=\"2\" align=\"center\">Die Veranstaltung Wurde erfolgreich angelegt/ge&auml;ndert:</td></tr>
				<tr><td align=\"right\">Veranstaltung:</td><td align=\"left\">".$neudaten["pho_name"]."</td></tr>
				<tr><td align=\"right\" width=\"50%\">in Ordner:</td><td align=\"left\">";
            	if($pho_parent_id!=NULL)echo $neudaten_parent["pho_name"];
					if($pho_parent_id==NULL)echo "Fotogalerien(Hauptordner)";
				echo"</td></tr>
				<tr><td align=\"right\">Anfangsdatum:</td><td align=\"left\">".mysqldate("d.m.y", $neudaten["pho_begin"])."</td></tr>
            <tr><td align=\"right\">Enddatum:</td><td align=\"left\">".mysqldate("d.m.y", $neudaten["pho_end"])."</td></tr>
            <tr><td align=\"right\">Fotografen:</td><td align=\"left\">".$neudaten["pho_photographers"]."</td></tr>
            <tr><td align=\"right\">Gesperrt:</td><td align=\"left\">";
            	if($neudaten["pho_locked"]==1) echo"Ja";
            	if($neudaten["pho_locked"]==0) echo"Nein";
            echo"</td></tr>
            <tr><td align=\"right\" width=\"50%\">Aktuelle Bilderzahl:</td><td align=\"left\">".$neudaten["pho_quantity"]."</td></tr>				
				<tr><td align=\"right\">angelegt von:</td><td align=\"left\">". strSpecialChars2Html($user1->usr_first_name). " ". strSpecialChars2Html($user1->usr_last_name)."</td></tr>
				<tr><td align=\"right\">angelegt am:</td><td align=\"left\">".mysqldatetime("d.m.y h:i", $neudaten["pho_timestamp"])."</td></tr>
				<tr><td align=\"right\">letztes Update durch:</td><td align=\"left\">". strSpecialChars2Html($user2->usr_first_name). " ". strSpecialChars2Html($user2->usr_last_name)."</td></tr>
				<tr><td align=\"right\">letztes Update am:</td><td align=\"left\">".mysqldatetime("d.m.y h:i", $neudaten["pho_timestamp"])."</td></tr>
         </table>
         <hr width=\"85%\" />
         <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php'\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">Zur&uuml;ck
         </button>
      </div>
      <br><br>
      ";
   }//submit
/****************************Formular***********************************************/
if($_GET["aufgabe"]=="change" || $_GET["aufgabe"]=="new"){
   //Kopfzeile
   echo"<div class=\"formHead\" style=\"width: 460px\">";
      if($_GET["aufgabe"]=="new") echo "Neue Veranstaltung anlegen";
      if($_GET["aufgabe"]=="change") echo "Veranstaltung bearbeiten";
   echo"</div>";
   //Body
   echo"
      <div style=\"width: 460px\" align=\"center\" class=\"formBody\">
         <form method=\"POST\" action=\"event.php?pho_id=$pho_id";
            if($_GET["aufgabe"]=="new")echo "&aufgabe=makenew\">";
            if($_GET["aufgabe"]=="change")echo "&aufgabe=makechange\">";
            //Veranstaltung
            echo"
              <div>
            <div style=\"text-align: right; width: 170px; float: left;\">Veranstaltung:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"veranstaltung\" size=\"30\" maxlength=\"40\" tabindex=\"1\">";
               if($_GET["aufgabe"]=="change")echo "<input type=\"text\" name=\"veranstaltung\" size=\"30\" maxlength=\"40\" tabindex=\"1\" value=\"".$adm_photo["pho_name"]."\">";
            echo"</div></div>";
            //Parent
            echo"
            <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 170px; float: left;\">in Ordner:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";
               echo"<select size=\"1\" name=\"parent\">";
               if($_GET["aufgabe"]=="new" && $pho_id!="")echo"<option value=\"".$adm_photo["pho_id"]."\">".$adm_photo["pho_name"]."&nbsp(".mysqldate("y", $adm_photo["pho_begin"]).")</option>";
					if($_GET["aufgabe"]=="change" && $adm_photo["pho_pho_id_parent"]!=NULL)echo"<option value=\"".$adm_photo_parent["pho_pho_id_parent"]."\">".$adm_photo_parent["pho_name"]."&nbsp(".mysqldate("y", $adm_photo_parent["pho_begin"]).")</option>";
               echo"<option value=\"0\">Fotogalerien(Hauptordner)</option>";
	            //Erfassen ob die Veranstaltung Kinder hat
	            $sql = "   SELECT *
            					FROM ". TBL_PHOTOS. "
            					WHERE (pho_pho_id_parent ='$pho_id')";
   				$result_child = mysql_query($sql, $g_adm_con);
   				db_error($result_child, 1);
	         	//Auswahlmöglichkeit nur wenn Veranst. nicht selbst Kinder hat
	           	for($x=0; $adm_photo_list = mysql_fetch_array($result_list); $x++){
	           		if(mysql_num_rows($result_child)==0 && $adm_photo_list["pho_id"]!=$pho_id_parent && $adm_photo_list["pho_id"]!=$pho_id && $adm_photo_list["pho_pho_id_parent"]==NULL)
	           			echo"<option value=\"".$adm_photo_list["pho_id"]."\">".$adm_photo_list["pho_name"]."&nbsp(".mysqldate("y", $adm_photo_list["pho_begin"]).")</option>";
	            }//for
	            echo"</select>";
            echo"</div></div>";
            //Beginn
            echo"
            <div style=\"margin-top: 6px;\">
              <div style=\"text-align: right; width: 170px; float: left;\">Beginn:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"beginn\" size=\"10\" tabindex=\"1\" maxlength=\"10\" >";
               if($_GET["aufgabe"]=="change")
                  echo "<input type=\"text\" name=\"beginn\" size=\"10\" tabindex=\"1\" maxlength=\"10\" value=\"".mysqldate("d.m.y", $adm_photo["pho_begin"])."\">";
            echo"</div></div>";
            //Ende
            echo"
            <div style=\"margin-top: 6px;\">
              <div style=\"text-align: right; width: 170px; float: left;\">Ende:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"ende\" size=\"10\" tabindex=\"1\" maxlength=\"10\">";
               if($_GET["aufgabe"]=="change")
                  echo "<input type=\"text\" name=\"ende\" size=\"10\" tabindex=\"1\" maxlength=\"10\" value=\"".mysqldate("d.m.y", $adm_photo["pho_end"])."\">";
            echo"</div></div>";
            //Photographen
            echo"
            <div style=\"margin-top: 6px;\">
              <div style=\"text-align: right; width: 170px; float: left;\">Fotografen:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"photographen\" size=\"30\" tabindex=\"1\">";
               if($_GET["aufgabe"]=="change")echo "<input type=\"text\" name=\"photographen\" size=\"30\" tabindex=\"1\" value=\"".$adm_photo["pho_photographers"]."\">";
            echo"</div></div>";
            //Freigabe
            echo"
            <div style=\"margin-top: 6px;\">
              <div style=\"text-align: right; width: 170px; float: left;\">Sperren:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";
               if($_GET["aufgabe"]=="new")echo "<input type=\"checkbox\" name=\"locked\" id=\"locked\" value=\"1\">";
               if($_GET["aufgabe"]=="change"){
                 	if($adm_photo["pho_locked"]==1) echo "<input type=\"checkbox\" name=\"locked\" id=\"locked\" checked value=\"1\">";
						if($adm_photo["pho_locked"]==0) echo "<input type=\"checkbox\" name=\"locked\" id=\"locked\" value=\"1\">";
               }           
            echo"</div></div>";
            //Submit
            echo"
            <div style=\"margin-top: 6px;\">
            <hr width=\"85%\" />
               Hilfe: <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=veranst_help','Message','width=500, height=350, left=310,top=200,scrollbars=no')\">
            <hr width=\"85%\" />
            <div style=\"margin-top: 6px;\">
               <button name=\"submit\" type=\"submit\" value=\"speichern\">
                  <img src=\"$g_root_path/adm_program/images/save.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">Speichern
               </button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
               <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
                  <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">Zur&uuml;ck
               </button>
            </div></div>
         </form>
      </div>
   ";
}//Ende Formular
/***********************Veranstaltung Löschen*******************************************/

if($_GET["aufgabe"]=="delete"){
   //Erfasse der zu löschenden VEranstaltung bzw. Unterveranstaltungen
   //Erfassen der Veranstaltung bei Änderungsaufruf
   $sql = "   SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE (pho_id ='$pho_id') OR (pho_pho_id_parent ='$pho_id')";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result, 1);
   
   echo"<div style=\"width: 500px\" align=\"center\" class=\"formHead\">Bericht</div>";
   echo"<div style=\"width: 500px\" align=\"center\" class=\"formBody\">";
   for($x=0; $adm_photo = mysql_fetch_array($result); $x++){ 
      $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];
      if(file_exists($ordner)){
      	chmod("$ordner", 0777);
      	//Löschen der Bilder
      	for($x=1; $x<=$adm_photo["pho_quantity"]; $x++){
        		if(file_exists("$ordner/$x.jpg")){
        			chmod("$ordner/$x.jpg", 0777);
         		if(unlink("$ordner/$x.jpg"))echo"Datei &bdquo;".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"]."/$x.jpg&rdquo; wurde erfolgreich GEL&Ouml;SCHT.<br>";
        		}
      	}	
      }
		//Löschen der Daten aus der Datenbank
      $sql ="DELETE
            FROM ". TBL_PHOTOS. "
            WHERE (pho_id ='".$adm_photo["pho_id"]."')";
      $result_delet = mysql_query($sql, $g_adm_con);
      db_error($result_delet);
      if($result_delet)echo"Der Datensatz zu &bdquo;".$adm_photo["pho_name"]."&rdquo; wurde aus der Datenbank GEL&Ouml;SCHT.";

      //Löschen der Ordners
       if(file_exists($ordner)){
       	if(rmdir("$ordner"))echo"<br>Die Veranstaltung Wurde erfolgreich GEL&Ouml;SCHT.<br>";
       }
	}//for
      
       echo"
       <hr width=\"85%\" />
         <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php'\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">Zur&uuml;ck
         </button>
      </div>";
}
//Ende Veranstaltung loeschen
/***********************************Ende********************************************/
   echo"</div>";
   require("../../../adm_config/body_bottom.php");
   echo "</body>
      </html>";
};//Moderation
?>