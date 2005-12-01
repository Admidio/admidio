<?php
/******************************************************************************
 * Verwaltung der Downloads
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
require("../../../adm_config/config.php");
require("../../system/common.php");
require("../../system/session_check_login.php");
   
   // erst prüfen, ob der User auch die entsprechenden Rechte hat
   if(!editDownload())
   {
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
      header($location);
      exit();
   }

   $default_folder = urldecode($_GET['default_folder']);
   $folder     = urldecode($_GET['folder']);

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
   }

   if(strlen($folder) > 0)
   {
      if(strpos($folder, "..") !== false)
      {
         $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_folder";
         header($location);
         exit();
      }
   }
   
   //Beginn der Seite
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>". $g_orga_property['ag_shortname']. " - Dateien hochladen</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

      <!--[if gte IE 5.5000]>
      <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      require("../../../adm_config/header.php");
   echo "</head>";
   
   require("../../../adm_config/body_top.php");
   //Beginn des Inhaltes
   echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
   
      <p>&nbsp;</p>
      <form action=\"download_function.php?mode=1&amp;folder=". urlencode($folder). "&amp;default_folder=". urlencode($default_folder). "\" method=\"post\" enctype=\"multipart/form-data\">
         <div class=\"formHead\">Datei hochladen</div>
         <div class=\"formBody\">
            <div style=\"text-align: center; width: 100%;\">Datei in den Ordner <b>";
               if(strlen($folder) == 0)
               {
                  if(strlen($default_folder) == 0)
                     echo "Download";
                  else
                     echo ucfirst($default_folder);
               }
               else
                  echo ucfirst($folder);
               echo "</b> hochladen
            </div>
            <div style=\"margin-top: 15px;\">
               <div style=\"text-align: right; width: 30%; float: left;\">Datei ausw&auml;hlen:</div>
               <div style=\"text-align: left; margin-left: 32%;\">
                  <input name=\"userfile\" size=\"30\" type=\"file\">
               </div>
            </div>
            <div style=\"margin-top: 10px;\">
               <div style=\"text-align: right; width: 30%; float: left;\">Neuer Dateiname:</div>
               <div style=\"text-align: left; margin-left: 32%;\">
                  <input type=\"text\" name=\"new_name\" size=\"25\" tabindex=\"1\">
                  &nbsp;(optional)&nbsp;<img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                  onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=dateiname','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\">
               </div>
            </div>
            
            <hr style=\"margin-top: 10px; margin-bottom: 10px;\" width=\"85%\" />

            <div style=\"margin-top: 6px;\">
               <button name=\"hochladen\" type=\"submit\" value=\"hochladen\" tabindex=\"2\">
               <img src=\"$g_root_path/adm_program/images/upload.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hochladen\">
               Hochladen</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

               <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
               <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
               Zur&uuml;ck</button>
            </div>
         </div>
      </form>
   </div>";
   //Ende des Seiten Inhalts
   require("../../../adm_config/body_bottom.php");
   echo "</body>
   </html>";
?>