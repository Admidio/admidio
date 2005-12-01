<?php
/******************************************************************************
 * Downloads auflisten
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Günzler
 *
 * Uebergaben:
 *
 * folder : akuteller Ordner (relativer Pfad in Abhaengigkeit adm_my_files/download
 *          und default_folder
 * default_folder : gibt den Ordner in adm_my_files/download an, ab dem die
 *                  Verzeichnisstruktur angezeigt wird. Wurde ein Default-Ordner
 *                  gesetzt, kann der Anwender nur noch in Unterordner und nicht
 *                  in hoehere Ordner des Default-Ordners navigieren
 * info   : Ausgabe von Verwaltungsinformationen
 * sort	 : Gibt die Art der Sortierung an. Default ist aufsteigend. Bei der Übergabe
 *			   von "desc" wird absteigend sortiert.
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

   $default_folder = urldecode($_GET['default_folder']);
   $folder     = urldecode($_GET['folder']);
   $act_folder = "../../../adm_my_files/download";

   // uebergebene Ordner auf Gueltigkeit pruefen
   // und Ordnerpfad zusammensetzen
   if(strlen($default_folder) > 0)
   {
      if(strpos($default_folder, "..") !== false)
      {
         $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_folder";
         header($location);
         exit();
      }
      $act_folder = "$act_folder/$default_folder";
   }

   if(strlen($folder) > 0)
   {
      if(strpos($folder, "..") !== false)
      {
         $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_folder";
         header($location);
         exit();
      }
      $act_folder = "$act_folder/$folder";
   }

   $info= $_GET['info'];

   //Auslesen des Ordners und schreiben in array
   if(!is_dir($act_folder))
   {
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=folder_not_exist";
      header($location);
      exit();
   }

	// Ordnerinhalt sortieren
   if ($sort == "desc") {
   	$inhalt= scandir($act_folder,1);
   	$ordnerarray = array_slice ($inhalt,0,count($inhalt)-2);
   }
   else {
   	$inhalt = scandir($act_folder);
   	$ordnerarray = array_slice ($inhalt,2);
	};


   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>". $g_orga_property['ag_shortname']. " - Downloadbereich</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

      <!--[if gte IE 5.5000]>
      <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      require("../../../adm_config/header.php");
   echo "</head>";

   require("../../../adm_config/body_top.php");
   echo"<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
   <h1>Downloadbereich</h1>
   <p>";

   //Button zurück zur Downloadübersicht & Eins zurück
   if(strlen($folder) > 0)
   {
      $pfad = strrev(substr(strchr(strrev($folder),"/"),1));
      echo "<button name=\"uebersicht\" type=\"button\" value=\"uebersicht\" style=\"width: 165px;\" onclick=\"self.location.href='$g_root_path/adm_program/modules/download/download.php?folder=". urlencode($pfad). "&amp;default_folder=". urlencode($default_folder). "'\">
               <img src=\"$g_root_path/adm_program/images/folder.png\" style=\"vertical-align: bottom;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Download&uuml;bersicht\">
               Ordner schlie&szlig;en
            </button>
      ";
   };

   //Button Upload und Neuer Ordner
   if ($g_session_valid && editDownload())
   {
      echo "<button name=\"down\" type=\"button\" value=\"down\" style=\"width: 150px;\" onclick=\"self.location.href='$g_root_path/adm_program/modules/download/folder_new.php?folder=". urlencode($folder). "&amp;default_folder=". urlencode($default_folder). "'\">
      <img src=\"$g_root_path/adm_program/images/folder_create.png\" style=\"vertical-align: bottom;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Ordner erstellen\">
      Ordner anlegen
      </button>
      <button name=\"down\" type=\"button\" value=\"down\" style=\"width: 150px;\" onclick=\"self.location.href='$g_root_path/adm_program/modules/download/upload.php?folder=". urlencode($folder). "&amp;default_folder=". urlencode($default_folder). "'\">
      <img src=\"$g_root_path/adm_program/images/upload.png\" style=\"vertical-align: bottom;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hochladen\">
      Datei hochladen
      </button>";
   };
   echo "</p>";

   // Ausgabe von Verwaltungsinfos
   echo "$info";

   //Anlegen der Tabelle
   echo" <table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
            <tr>
               <th class=\"tableHeader\" width=\"25\" style=\"text-align: center;\"><img src=\"$g_root_path/adm_program/images/folder.png\" border=\"0\" alt=\"Ordner\"></th>
               <th class=\"tableHeader\" style=\"text-align: left;\">";
                  if(strlen($folder) == 0)
                  {
                     if(strlen($default_folder) == 0)
                        echo "Download";
                     else
                        echo $default_folder;
                  }
                  else
                     echo $folder;
               echo "</th>
               <th class=\"tableHeader\" style=\"text-align: center;\">Erstellungsdatum</th>
               <th class=\"tableHeader\" style=\"text-align: right;\">Gr&ouml;&szlig;e&nbsp;</th>";
               if ($g_session_valid && editDownload())
                  echo "<th class=\"tableHeader\" align=\"center\">Editieren</th>";
            echo "</tr>";


   //falls der Ordner leer ist
   if(Count($ordnerarray)==0){
      echo"
            <tr>
               <td colspan=\"2\">Dieser Ordner ist leer</td>
               <td></td>
               <td></td>";
               if ($g_session_valid && editDownload()) echo "<td></td>";
      echo "</tr>";
   }


   //durchlafen des Ordnerarrays und Ordnerlinkausgabe in Tabellenzeilen, ruft erneut die download.txt auf nur mit neuem Ordner
   for($i=0; $i<count($ordnerarray); $i++)
   {
         if(filetype("$act_folder/$ordnerarray[$i]")=="dir")
         {
            if(strlen($folder) > 0)
               $next_folder = "$folder/$ordnerarray[$i]";
            else
               $next_folder = $ordnerarray[$i];

            echo "
               <tr class=\"listMouseOut\" onMouseOver=\"this.className='listMouseOver'\" onMouseOut=\"this.className='listMouseOut'\">
                  <td style=\"text-align: center;\"><a href=\"$g_root_path/adm_program/modules/download/download.php?folder=". urlencode($next_folder). "&amp;default_folder=". urlencode($default_folder). "\">
                     <img src=\"$g_root_path/adm_program/images/folder.png\" border=\"0\" alt=\"Ordner\" title=\"Ordner\"></a></td>
                  <td style=\"text-align: left;\"><a href=\"$g_root_path/adm_program/modules/download/download.php?folder=". urlencode($next_folder). "&amp;default_folder=". urlencode($default_folder). "\">$ordnerarray[$i]</a></td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>";
            if ($g_session_valid && editDownload())
            {
               echo "
               <td style=\"text-align: center;\">&nbsp;
                  <a href=\"$g_root_path/adm_program/modules/download/rename.php?folder=". urlencode($folder). "&amp;file=". urlencode($ordnerarray[$i]). "&amp;default_folder=". urlencode($default_folder). "\">
                     <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Umbenennen\"></a>&nbsp;&nbsp;&nbsp;";
                  $load_url = urlencode("$g_root_path/adm_program/modules/download/download_function.php?mode=2&amp;folder=$folder&amp;file=$ordnerarray[$i]&amp;default_folder=$default_folder");
                  echo "<a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_file_folder&amp;err_text=". urlencode($ordnerarray[$i]). "&amp;err_head=". urlencode("L&ouml;schen"). "&amp;button=2&amp;url=$load_url\">
                    <img src=\"$g_root_path/adm_program/images/delete.png\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"></a>
               </td>";
            }
            echo "</tr>";
         };
   };
   //durchlafen des Ordnerarrays und Dateilinkausgabe in Tabellenzeilen
   for($i=0; $i<count($ordnerarray); $i++){
           if(filetype("$act_folder/$ordnerarray[$i]")=="file"){
            //ermittlung der Dateigröße
            $dateigroesse=round(filesize("$act_folder/$ordnerarray[$i]")/1024);
            // Ermittlung des Datums
            $dateidatum = date ("d.m.Y", filemtime("$act_folder/$ordnerarray[$i]"));
            //Ermittlung der dateiendung
            $datei = explode(".","$ordnerarray[$i]");
            $dateiendung = strtolower($datei[1]);

            //Auszugebendes Icon
            if($dateiendung=="gif"
            || $dateiendung=="cdr"
            || $dateiendung=="jpg"
            || $dateiendung=="png"
            || $dateiendung=="bmp" )
               $dateiendung = "img";
            elseif($dateiendung=="doc")
               $dateiendung = "doc";
            elseif($dateiendung=="xls"
            ||     $dateiendung=="csv")
               $dateiendung = "xls";
            elseif($dateiendung=="pps"
            ||     $dateiendung=="ppt")
               $dateiendung = "ppt";
            elseif($dateiendung=="txt")
               $dateiendung = "txt";
            elseif($dateiendung=="pdf")
               $dateiendung = "pdf";
            else
               $dateiendung = "file";

            //Link und Dateiinfo Ausgabe
            echo "<tr class=\"listMouseOut\" onMouseOver=\"this.className='listMouseOver'\" onMouseOut=\"this.className='listMouseOut'\">
                     <td style=\"text-align: center;\"><a href=\"$act_folder/$ordnerarray[$i]\"><img src=\"$g_root_path/adm_program/images/$dateiendung.gif\" border=\"0\" alt=\"Datei\" title=\"Datei\"></a></td>
                     <td style=\"text-align: left;\"><a href=\"$act_folder/$ordnerarray[$i]\">$ordnerarray[$i]</a></td>
                     <td style=\"text-align: center;\">$dateidatum</td>
                     <td style=\"text-align: right;\">$dateigroesse kB&nbsp;</td>";

            //Moderation
            if ($g_session_valid && editDownload())
            {
               echo "
               <td align=\"center\">&nbsp;
                  <a href=\"$g_root_path/adm_program/modules/download/rename.php?folder=". urlencode($folder). "&amp;file=". urlencode($ordnerarray[$i]). "&amp;default_folder=". urlencode($default_folder). "\">
                     <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Umbenennen\"></a>&nbsp;&nbsp;&nbsp;";
                  $load_url = urlencode("$g_root_path/adm_program/modules/download/download_function.php?mode=2&amp;folder=$folder&amp;file=$ordnerarray[$i]&amp;default_folder=$default_folder");
                  echo "<a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_file_folder&amp;err_text=". urlencode($ordnerarray[$i]). "&amp;err_head=". urlencode("L&ouml;schen"). "&amp;button=2&amp;url=$load_url\">
                     <img src=\"$g_root_path/adm_program/images/delete.png\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"></a>
               </td>";
            }
            echo "</tr>";
         };
   };
   //Ende der Tabelle
   echo"</table>
   </div>";
   require("../../../adm_config/body_bottom.php");
   echo "</body>
   </html>";
?>