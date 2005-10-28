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
//Aktueller Timestamp
   $act_datetime= date("Y.m.d G:i:s", time());
//erfassen der Veranstaltung bei Änderungsaufruf
   $sql = "   SELECT * 
            FROM adm_photo 
            WHERE (ap_id ='$ap_id')";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   $adm_photo = mysql_fetch_array($result);
//Speicherort
   $ordner = "../../../adm_my_files/photos/"."$adm_photo[3]"."_"."$adm_photo[0]";
//*************************************************************************************
//Änderungen oder Neueintäge speichern
   if($_POST["submit"]){
   //Gesendete Variablen übernehmen und kontollieren
      //Veranstaltung
      $veranstaltung = $_POST["veranstaltung"];
      if($veranstaltung==""){
          $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=veranstaltung";
         header($location);
         exit();
      }
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
      //Photographen
      $photographen =  $_POST["photographen"];
         if($photographen=="")$photographen="leider unbekannt";
   //NeuenDatensatz anlegen falls makenew
   if ($aufgabe=="makenew"){
      $sql="INSERT INTO adm_photo (ap_number, ap_name, ap_begin,
                         ap_end, ap_photographers, ap_online_since, ap_last_change, ap_ag_shortname)
               VALUES(0, 'neu', '0000-00-00', '0000-00-00', 'leider unbekannt',
                      '$act_datetime', '$act_datetime', '$g_organization')
      ";
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);
      //erfragen der id
      $ap_id=mysql_insert_id($g_adm_con);
   }
   //Verzeichnis erstellen
      if ($aufgabe=="makenew"){
         $ordnerneu = "$beginn"."_"."$ap_id";
         //testen ob Schreibrechte für adm_my_files bestehen
         if (decoct(fileperms("../../../adm_my_files/photos"))!=40777){
            //Wenn keine Schreibrechte Löschen der Daten aus der Datenbank
            $sql ="DELETE
               FROM adm_photo 
               WHERE (ap_id ='$ap_id')";      
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            $load_url = urlencode("$g_root_path/adm_program/moduls/photos/photos.php");
            $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=write_access&err_text=adm_my_files/photos&url=$load_url";
            header($location);
            exit();
         }
         //wenn Rechte OK, Ordner erstellen
         $ordnererstellt = mkdir("../../../adm_my_files/photos/$ordnerneu",0777);
         chmod("../../../adm_my_files/photos/$ordnerneu", 0777);
      }//if
   //Bearbeiten Anfangsdatum und Ordner ge&auml;ndert
      if ($aufgabe=="makechange" && $ordner!="../../../adm_my_files/photos/"."$beginn"."_"."$ap_id"){
         $ordnerneu = "$beginn"."_$adm_photo[0]";
         //testen ob Schreibrechte für adm_my_files bestehen
         if (decoct(fileperms("../../../adm_my_files/photos"))!=40777){
            $load_url = urlencode("$g_root_path/adm_program/moduls/photos/photos.php");
            $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=write_access&err_text=adm_my_files/photos&url=$load_url";
            header($location);
            exit();
         }
         //wenn Rechte OK, Ordner erstellen
         $ordnererstellt = mkdir("../../../adm_my_files/photos/$ordnerneu",0777);
         chmod("../../../adm_my_files/photos/$ordnerneu", 0777);
         //Dateien verschieben
         for($x=1; $x<=$adm_photo[1]; $x++){
            chmod("$ordner/$x.jpg", 0777);
            copy("$ordner/$x.jpg", "../../../adm_my_files/photos/$ordnerneu/$x.jpg");
            unlink("$ordner/$x.jpg");
         }
         //alten ordner loeschen
         chmod("$ordner", 0777);
         rmdir("$ordner");
      }//if
//&Auml;ndern  der Daten in der Datenbank
         $sql= "UPDATE adm_photo
                SET   ap_name = '$veranstaltung',
                     ap_begin ='$beginn',
                     ap_end ='$ende',
                     ap_photographers ='$photographen',
                     ap_last_change ='$act_datetime'
               WHERE ap_id = '$ap_id'";
      //SQL Befehl ausführen
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);
   //daten aus Datenbank neu laden
   //erfassen der Veranstaltung 
   $sql = "   SELECT * 
            FROM adm_photo 
            WHERE ap_id ='$ap_id'";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   $neudaten = mysql_fetch_array($result);
//Speicherort
   $ordner = "../../../adm_my_files/photos/"."$adm_photo[3]"."_$adm_photo[0]";
   
   }// If submit

//*************************************************************************************
//Beginn HTML
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>$g_title - Veranstaltungsverwaltung</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

      <!--[if gte IE 5.5000]>
      <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      require("../../../adm_config/header.php");
   echo "</head>";

   require("../../../adm_config/body_top.php");
   echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";
//*************************************************************************************
//Bericht
   if($_POST["submit"]){
      $dt_neudate_beginn = mysqldate("d.m.y", $neudaten[3]);
      $dt_neudate_ende = mysqldate("d.m.y", $neudaten[4]);
      $dt_online = mysqldatetime("d.m.y h:i", $neudaten[6]);
      $dt_change = mysqldatetime("d.m.y h:i", $neudaten[7]);
      echo"<div style=\"width: 430px\" align=\"center\" class=\"formHead\">Bericht</div>";
      echo"
      <div style=\"width: 430px\" align=\"center\" class=\"formBody\">
          <table cellspacing=3 cellpadding=0 border=\"0\">
            <tr><td colspan=\"2\" align=\"center\">Die Veranstaltung Wurde erfolgreich angelegt/ge&auml;ndert:</td></tr>
            <tr><td align=\"right\" width=\"50%\">Aktuelle Bilderzahl:</td><td align=\"left\">$neudaten[1]</td></tr>
            <tr><td align=\"right\">Veranstaltung:</td><td align=\"left\">$neudaten[2]</td></tr>
            <tr><td align=\"right\">Anfangsdatum:</td><td align=\"left\">$dt_neudate_beginn</td></tr>
            <tr><td align=\"right\">Enddatum:</td><td align=\"left\">$dt_neudate_ende</td></tr>
            <tr><td align=\"right\">Fotografen:</td><td align=\"left\">$neudaten[5]</td></tr>
            <tr><td align=\"right\">Online seit:</td><td align=\"left\">$dt_online</td></tr>
            <tr><td align=\"right\">Letze &Auml;nderung:</td><td align=\"left\">$dt_change</td></tr>
            <tr><td align=\"right\">Gruppierung:</td><td align=\"left\">$neudaten[8]</td></tr>
         </table>
         <hr width=\"85%\" />
         <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/moduls/photos/photos.php'\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">Zur&uuml;ck
         </button>
      </div>
      <br><br>
      ";
   }//submit
//*************************************************************************************
//Formular Veranstaltung anlegen oder ändern
if($_GET["aufgabe"]=="change" || $_GET["aufgabe"]=="new"){
   //Kopfzeile
   echo"<div class=\"formHead\" style=\"width: 430px\">";
      if($_GET["aufgabe"]=="new") echo "Neue Veranstaltung anlegen";
      if($_GET["aufgabe"]=="change") echo "Veranstaltung bearbeiten";
   echo"</div>";
   //Body
   echo"
      <div style=\"width: 430px\" align=\"center\" class=\"formBody\">
         <form method=\"POST\" action=\"event.php?ap_id=$ap_id";
            if($_GET["aufgabe"]=="new")echo "&aufgabe=makenew\">";
            if($_GET["aufgabe"]=="change")echo "&aufgabe=makechange\">";
            //Veranstaltung
            echo"
              <div>      
            <div style=\"text-align: right; width: 170px; float: left;\">Veranstaltung:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"veranstaltung\" size=\"30\" maxlength=\"40\" tabindex=\"1\">";
               if($_GET["aufgabe"]=="change")echo "<input type=\"text\" name=\"veranstaltung\" size=\"30\" maxlength=\"40\" tabindex=\"1\" value=\"$adm_photo[2]\">";
            echo"</div></div>";   
            //Beginn
            echo"
            <div style=\"margin-top: 6px;\">
              <div style=\"text-align: right; width: 170px; float: left;\">Beginn:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";   
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"beginn\" size=\"10\" tabindex=\"1\" maxlength=\"10\" >";
               if($_GET["aufgabe"]=="change"){
                  $dt_date = mysqldate("d.m.y", $adm_photo[3]);
                  echo "<input type=\"text\" name=\"beginn\" size=\"10\" tabindex=\"1\" maxlength=\"10\" value=\"$dt_date\">";
               }
            echo"</div></div>";
            //Ende
            echo"
            <div style=\"margin-top: 6px;\">
              <div style=\"text-align: right; width: 170px; float: left;\">Ende:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";   
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"ende\" size=\"10\" tabindex=\"1\" maxlength=\"10\">";
               if($_GET["aufgabe"]=="change"){
                  $dt_date = mysqldate("d.m.y", $adm_photo[4]);
                  echo "<input type=\"text\" name=\"ende\" size=\"10\" tabindex=\"1\" maxlength=\"10\" value=\"$dt_date\">";
               }
            echo"</div></div>";
            //Photographen
            echo"
            <div style=\"margin-top: 6px;\">
              <div style=\"text-align: right; width: 170px; float: left;\">Fotografen:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";   
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"photographen\" size=\"30\" tabindex=\"1\">";
               if($_GET["aufgabe"]=="change")echo "<input type=\"text\" name=\"photographen\" size=\"30\" tabindex=\"1\" value=\"$adm_photo[5]\">";
            echo"</div></div>";
            //Online seit
            echo"
            <div style=\"margin-top: 6px;\">
              <div style=\"text-align: right; width: 170px; float: left;\">Online seit:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";   
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"onlineseit\" size=\"10\" tabindex=\"1\" value=\"(Auto)\" class=\"readonly\" readonly=\"readonly\">";
               if($_GET["aufgabe"]=="change"){
                  $dt_datetime = mysqldatetime("d.m.y h:i", $adm_photo[6]);
                  echo "<input type=\"text\" name=\"onlineseit\" size=\"15\" tabindex=\"1\" value=\"$dt_datetime\" class=\"readonly\" readonly=\"readonly\">";
               }
            echo"</div></div>";
            //Letzte &Auml;nderung
            echo"
            <div style=\"margin-top: 6px;\">
              <div style=\"text-align: right; width: 170px; float: left;\">Letzte &Auml;nderung:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";   
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"onlineseit\" size=\"10\" tabindex=\"1\" value=\"(Auto)\" class=\"readonly\" readonly=\"readonly\">";
               if($_GET["aufgabe"]=="change"){
                  $dt_datetime = mysqldatetime("d.m.y h:i", $adm_photo[7]);
                  echo "<input type=\"text\" name=\"onlineseit\" size=\"15\" tabindex=\"1\" value=\"$dt_datetime\" class=\"readonly\" readonly=\"readonly\">";
               }
            echo"</div></div>";
            //Gruppiereung
            echo"
            <div style=\"margin-top: 6px;\">
              <div style=\"text-align: right; width: 170px; float: left;\">Gruppierung:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";   
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"gruppierung\" size=\"10\" tabindex=\"1\" value=\"$g_organization\" class=\"readonly\" readonly=\"readonly\">";
               if($_GET["aufgabe"]=="change")echo "<input type=\"text\" name=\"gruppierung\" size=\"10\" tabindex=\"1\" value=\"$adm_photo[8]\" class=\"readonly\" readonly=\"readonly\">";
            echo"</div></div>";
            //Enthaltene Bilder
            echo"
            <div style=\"margin-top: 6px;\">
              <div style=\"text-align: right; width: 170px; float: left;\">Enthaltene Bilder:</div>
            <div style=\"text-align: left; margin-left: 180px;\">";   
               if($_GET["aufgabe"]=="new")echo "<input type=\"text\" name=\"bilderzahl\" size=\"5\" tabindex=\"1\" value=\"0\" class=\"readonly\" readonly=\"readonly\">";
               if($_GET["aufgabe"]=="change")echo "<input type=\"text\" name=\"bilderzahl\" size=\"5\" tabindex=\"1\" value=\"$adm_photo[1]\" class=\"readonly\" readonly=\"readonly\">";
            echo"</div></div>";
            //Submit
            echo"
            <div style=\"margin-top: 6px;\">
            <hr width=\"85%\" />
               Hilfe: <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: middle;\" vspace=\"1\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=veranst_help','Message','width=500, height=230, left=310,top=200,scrollbars=no')\">
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
//*************************************************************************************
//Veranstaltung Löschen
if($_GET["aufgabe"]=="delete"){
   echo"<div style=\"width: 430px\" align=\"center\" class=\"formHead\">Bericht</div>";
   echo"<div style=\"width: 430px\" align=\"center\" class=\"formBody\">";
      chmod("$ordner", 0777);
      //Löschen der Bilder
      for($x=1; $x<=$adm_photo[1]; $x++){
         chmod("$ordner/$x.jpg", 0777);
         if(unlink("$ordner/$x.jpg"))echo"Datei $x.jpg wurde erfolgreich GEL&Ouml;SCHT.<br>";
      }
      
      //Löschen der Daten aus der Datenbank
      $sql ="DELETE
            FROM adm_photo 
            WHERE (ap_id ='$ap_id')";      
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);
      if($result)echo"Der zugehörige Datensatz wurde aus der Datenbank GEL&Ouml;SCHT.";
      
      //Löschen der Ordners
       if(rmdir("$ordner"))echo"Die Veranstaltung Wurde erfolgreich GEL&Ouml;SCHT.<br>";
       echo"
       <hr width=\"85%\" />
         <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/moduls/photos/photos.php'\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">Zur&uuml;ck
         </button>
      </div>";
}
//Ende Veranstaltung loeschen
//*************************************************************************************
//Ende der Seite
   echo"</div>";
   require("../../../adm_config/body_bottom.php");
   echo "</body>
      </html>";
};//Moderation
?>