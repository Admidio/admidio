<?php
/******************************************************************************
 * Verwaltung der Downloads
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Günzler
 *
 * Uebergaben:
 *
 * ordner   download - (Default) Der Stammordner der Downloads
 *          download/XXX - Unterordner
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
	require("../../system/login_valid.php");
   
   // erst prüfen, ob der User auch die entsprechenden Rechte hat
   if(!editDownload())
   {
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
      header($location);
      exit();
   }
   
   $default_folder = urldecode($_GET['default_folder']);
   $folder = urldecode($_GET['folder']);

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
   <!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>$g_current_organization->longname - Ordner erstellen</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

      <!--[if gte IE 5.5000]>
      <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      require("../../../adm_config/header.php");
   echo "</head>";
   
   require("../../../adm_config/body_top.php");
   //Beginn des Inhaltes
   echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\"><br />
      <form method=\"post\" action=\"download_function.php?mode=3&amp;folder=". urlencode($folder). "&amp;default_folder=". urlencode($default_folder). "\">
         <div class=\"formHead\" style=\"width: 400px\">Ordner erstellen</div>
         <div class=\"formBody\" style=\"width: 400px\">
            <div style=\"text-align: center; width: 100%;\">Neuer Ordner in <b>";
               if(strlen($folder) == 0)
               {
                  if(strlen($default_folder) == 0)
                     echo "Download";
                  else
                     echo ucfirst($default_folder);
               }
               else
                  echo ucfirst($folder);
               echo "</b> erstellen
            </div>
            <div style=\"margin-top: 15px;\">
               <div style=\"text-align: right; width: 33%; float: left;\">Name:</div>
               <div style=\"text-align: left; margin-left: 35%;\">
                  <input type=\"text\" name=\"new_folder\" size=\"20\" tabindex=\"1\">
               </div>
            </div>
            
            <hr style=\"margin-top: 10px; margin-bottom: 10px;\" width=\"85%\" />

            <div style=\"margin-top: 6px;\">
               <button name=\"erstellen\" type=\"submit\" value=\"erstellen\" tabindex=\"2\">
               <img src=\"$g_root_path/adm_program/images/folder_create.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Ordner erstellen\">
               Ordner erstellen</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

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