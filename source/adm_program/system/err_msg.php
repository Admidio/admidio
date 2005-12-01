<?php
/******************************************************************************
 * Ausgabe von Fehlermeldungen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * err_code: Fehlerkuerzel
 * err_text: Text, der ggf. bei der Meldung angezeigt werden kann/soll
 * err_head: Ueberschrift des Hinweises
 *           Default: Fehlermeldung
 * button:   Anzahl der Buttons die angezeigt werden
 *           Default: 1
 * url:      Seite, auf die danach gegangen werden soll
 *           home = Startseite ($g_main_page)
 * timer:    Zeitintervall in ms nachdem automatisch die Nachricht verschwinden soll
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

require("common.php");
require("session_check.php");
require("err_text.php");

// Pruefung, ob die Datei ueber eine Header-Umleitung oder als include aufgerufen wurde
if(array_key_exists("err_code", $_GET))
   $inline = 0;
else
   $inline = 1;

// Uebergaben vorbelegen, falls sie nicht uebergeben werden
if(!isset($err_code))
{
   if(isset($_GET["err_code"]))
      $err_code = $_GET["err_code"];
   else
      $err_code = "";
}
if(!isset($err_text))
{
   if(isset($_GET["err_text"]))
      $err_text = $_GET["err_text"];
   else
      $err_text = "";
}

if(!isset($_GET["button"]))
   $_GET["button"] = 1;

if(!isset($_GET['timer']))
   $_GET['timer']    = 0;
if(!isset($_GET['url']))
   $load_url = "";
else
{
   if($_GET['url'] == "home")
      $load_url = "$g_root_path/$g_main_page";
   else
   {
      // die uebergebene Url wird in ihre Bestandteile zerlegt und danach wieder zusammengebaut,
      // wobei die PHP-Variablen alle mit urlencode kodiert werden, damit Umlaute korrekt übergeben werden
      $url_arr = parse_url($_GET['url']);
      parse_str($url_arr['query'], $var_arr);
      // Url wieder zusammenbauen
      $load_url = $url_arr['scheme']. "://". $url_arr['host']. $url_arr['path']. "?";
      reset($var_arr);
      // PHP-Variablen wieder hinzufuegen
      for($i = 0; $i < count($var_arr); $i++)
      {
         if($i > 0) $load_url = $load_url. "&";
         $load_url = $load_url. key($var_arr). "=". urlencode(current($var_arr));
         next($var_arr);
      }
   }
}

if(!isset($_GET['err_head']))
{
   if(strlen($load_url) > 0)
      $_GET['err_head'] = "Hinweis";
   else
      $_GET['err_head'] = "Fehlermeldung";
}

if($inline == 0)
{
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>". $g_orga_property['ag_shortname']. " - Messagebox</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

      <!--[if gte IE 5.5000]>
      <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      if($_GET['timer'] > 0)
      {
         echo "<script language=\"JavaScript1.2\" type=\"text/javascript\"><!--\n
               window.setTimeout(\"window.location.href='$load_url'\", ". $_GET['timer']. ");\n
               //--></script>";
      }

      require("../../adm_config/header.php");
   echo "</head>";

   require("../../adm_config/body_top.php");
}

echo "
   <div align=\"center\"><br /><br /><br />

   <div class=\"formHead\" style=\"width: 350px\">". strspace($_GET['err_head']). "</div>

   <div class=\"formBody\" style=\"width: 350px\">
      <p>". getErrorText($err_code, $err_text). "</p>
      <p>";
         if($_GET['timer'] > 0)
         {
            echo "&nbsp;";
         }
         else
         {
            if(strlen($load_url) > 0)
            {
               if($_GET['button'] == 1)
               {
                  echo "<button name=\"weiter\" type=\"button\" value=\"weiter\" onclick=\"window.location.href='$load_url'\">
                  <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Weiter\">
                  &nbsp;Weiter</button>";
               }
               else
               {
                  echo "<button name=\"ja\" type=\"button\" value=\"ja\"
                     onclick=\"self.location.href='$load_url'\">
                     <img src=\"$g_root_path/adm_program/images/ok.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Ja\">
                     &nbsp;&nbsp;Ja&nbsp;&nbsp;&nbsp;</button>
                  &nbsp;&nbsp;&nbsp;&nbsp;
                  <button name=\"nein\" type=\"button\" value=\"nein\"
                     onclick=\"history.back()\">
                     <img src=\"$g_root_path/adm_program/images/error.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Nein\">
                     &nbsp;Nein</button>";
               }
            }
            else
            {
               echo "<button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
               <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
               &nbsp;Zur&uuml;ck</button>";
            }
         }
      echo "</p>
   </div>
   </div>";

   require("../../adm_config/body_bottom.php");
echo "</body></html>";
?>