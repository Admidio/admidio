<?php
/******************************************************************************
 * Installationsdialog fuer die MySql-Datenbank
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * mode : 0 (Default) Erster Dialog
 *        1 Admidio installieren - Config-Datei
 *        2 Datenbank installieren
 *        3 Datenbank updaten
 *        4 Neue Organisation anlegen
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
require("../adm_program/system/function.php");

// pruefen, ob es eine Erstinstallation ist
if(file_exists("../adm_config/config.php"))
   $first_install = false;
else
   $first_install = true;

if(!array_key_exists("mode", $_POST))
   $mode = 0;
else
{
	if($_POST['mode'] == 'install')
		$mode = 1;
	if($_POST['mode'] == 'update')
		$mode = 3;
	if($_POST['mode'] == 'orga')
		$mode = 4;
}

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org -->
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>Admidio - Installation</title>

   <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-15\">
   <meta name=\"author\"   content=\"Markus Fassbender\">
   <meta name=\"robots\"   content=\"index,follow\">
   <meta name=\"language\" content=\"de\">

   <link rel=\"stylesheet\" type=\"text/css\" href=\"../adm_config/main.css\">

   <script type=\"text/javascript\">
      function createConfigFile() {
        document.server.action = 'inst_do.php?file=1';
        document.server.submit();
      }

      function installDatabase() {
        document.server.action = 'inst_do.php?file=0';
        document.server.submit();
      }
   </script>

   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"../adm_program/system/correct_png.js\"></script>
   <![endif]-->
</head>
<body>
<div align=\"center\">
   <div class=\"formHead\" style=\"text-align: left;\">
        <img style=\"float:left; padding: 5px 0px 0px 0px;\" src=\"../adm_program/images/admidio_logo_50.png\" border=\"0\" alt=\"www.admidio.org\" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <div style=\"font-size: 16pt; font-weight: bold; text-align: right; padding: 5px 10px 10px 0px;\">Version ". getVersion(). "</div>
        <div style=\"font-size: 11pt; padding: 0px 0px 5px 0px;\">Das Online-Verwaltungssystem f&uuml;r Vereine, Gruppen und Organisationen</div>
   </div>

   <div class=\"formBody\">
      <div align=\"center\">";

      if($mode == 0)
      	echo "<h1>Installation &amp; Einrichtung</h1>";
      elseif($mode == 1)
      	echo "<h1>Konfigurationsdatei erstellen</h1>";
      elseif($mode == 2)
      	echo "<h1>Datenbank installieren</h1>";
      elseif($mode == 3)
      	echo "<h1>Datenbank updaten</h1>";
      elseif($mode == 4)
      	echo "<h1>Neue Organisation hinzufügen</h1>";

      if($mode == 0)
      {
      	echo "
			<form name=\"server\" action=\"index.php\" method=\"post\">
				<div class=\"groupBox\" style=\"width: 350px; text-align: left;\">
					<div class=\"groupBoxHeadline\">Aktion auswählen</div>
					<br>
					<div>&nbsp;
						<input type=\"radio\" id=\"install\" name=\"mode\" value=\"install\" ";
	               if($first_install) echo " checked ";
	               echo "/>&nbsp;
						<label for=\"install\">Admidio installieren und einrichten
					</div>
					<br>
					<div>&nbsp;
						<input type=\"radio\" id=\"update\" name=\"mode\" value=\"update\" ";
	               if(!$first_install) echo " checked ";
	               echo "/>&nbsp;
						<label for=\"update\">Admidio Datenbank updaten
					</div>
					<br>
					<div>&nbsp;
						<input type=\"radio\" id=\"orga\" name=\"mode\" value=\"orga\" />&nbsp;
						<label for=\"orga\">Neue Organisation hinzufügen
					</div>
					<br>
				</div>";
		}
		else
		{
      	echo "<form name=\"server\" action=\"inst_do.php?mode=$mode\" method=\"post\">";

			// Verbindungsdaten zur Datenbank
	      if($mode != 1)
	      {
				echo "
	         <table class=\"groupBox\" width=\"350\" cellpadding=\"5\">
	            <tr>
	               <td class=\"groupBoxHeadline\" colspan=\"2\">Zugangsdaten MySql-Datenbank</td>
	            </tr>
	            <tr>
	               <td width=\"120px\">Server:</td>
	               <td><input type=\"text\" name=\"server\" size=\"25\" maxlength=\"50\" /></td>
	            </tr>
	            <tr>
	               <td width=\"120px\">Login:</td>
	               <td><input type=\"text\" name=\"user\" size=\"25\" maxlength=\"50\" /></td>
	            </tr>
	            <tr>
	               <td width=\"120px\">Passwort:</td>
	               <td><input type=\"password\" name=\"password\" size=\"25\" maxlength=\"50\" /></td>
	            </tr>
	            <tr>
	               <td width=\"120px\">Datenbank:</td>
	               <td><input type=\"text\" name=\"database\" size=\"25\" maxlength=\"50\" /></td>
	            </tr>
	         </table>

	         <br />";
	      }

			// Updaten von Version
			if($mode == 3)
			{
				echo "<br />
	         <table class=\"groupBox\" width=\"350\" cellpadding=\"5\">
	            <tr>
	               <td class=\"groupBoxHeadline\" colspan=\"2\">Optionen</td>
	            </tr>
	            <tr>
	               <td width=\"120px\">Update von:</td>
	               <td>
	                  <select size=\"1\" name=\"version\">
	                     <option value=\"0\" selected=\"selected\">- Bitte w&auml;hlen -</option>
	                     <option value=\"2\">Version 1.1.*</option>
	                     <option value=\"1\">Version 1.0.*</option>
	                  </select>
	               </td>
	            </tr>
	         </table>

	         <br />";
			}

			// Organisation anlegen
			if($mode != 3)
			{
				echo "
	         <table class=\"groupBox\" width=\"350\" cellpadding=\"5\">
	            <tr>
	               <td class=\"groupBoxHeadline\" colspan=\"2\">Gruppierung / Verein</td>
	            </tr>
	            <tr>
	               <td width=\"120px\" align=\"right\">Name (Abk.):</td>
	               <td><input type=\"text\" name=\"verein-name-kurz\" size=\"10\" maxlength=\"10\" /></td>
	            </tr>
	            <tr>
	               <td width=\"120px\" align=\"right\">Name (lang):</td>
	               <td><input type=\"text\" name=\"verein-name-lang\" size=\"25\" maxlength=\"60\" /></td>
	            </tr>
	         </table>

	         <br />";
			}

			// Webmaster anlegen
			if($mode == 2
			|| $mode == 4)
			{
				echo "
	         <table class=\"groupBox\" width=\"350\" cellpadding=\"5\">
	            <tr>
	               <td class=\"groupBoxHeadline\" colspan=\"2\">Benutzer anlegen</td>
	            </tr>
	            <tr>
	               <td width=\"120px\">Nachname:</td>
	               <td><input type=\"text\" name=\"user-surname\" size=\"25\" maxlength=\"50\" /></td>
	            </tr>
	            <tr>
	               <td width=\"120px\">Vorname:</td>
	               <td><input type=\"text\" name=\"user-firstname\" size=\"25\" maxlength=\"50\" /></td>
	            </tr>
	            <tr>
	               <td width=\"120px\">Benutername:</td>
	               <td><input type=\"text\" name=\"user-login\" size=\"25\" maxlength=\"50\" /></td>
	            </tr>
	            <tr>
	               <td width=\"120px\">Passwort:</td>
	               <td><input type=\"password\" name=\"user-passwort\" size=\"25\" maxlength=\"50\" /></td>
	            </tr>
	         </table>

	         <br />";
			}

         if($first_install)
         {
            echo "
            <table class=\"groupBox\" width=\"350\" cellpadding=\"5\">
               <tr>
                  <td class=\"groupBoxHeadline\" colspan=\"2\">Installieren</td>
               </tr>
               <tr>
                  <td colspan=\"2\">1. Erzeugen Sie die <i>config.php</i> Datei und kopieren diese
                     in das <i>adm_config</i> Verzeichnis auf Ihrem Webserver:
                     <p align=\"center\">
                     <button name=\"config_file\" type=\"button\" value=\"config_file\"  style=\"width: 187px;\"
                        onclick=\"createConfigFile()\">1. Config-Datei erzeugen</button></p>
                  </td>
               </tr>
               <tr>
                  <td>2. Installieren Sie die Datenbank:
                     <p align=\"center\">
                     <button name=\"installieren\" type=\"button\" value=\"installieren\"  style=\"width: 187px;\"
                        onclick=\"installDatabase()\">2. Datenbank installieren</button></p>
                  </td>
               </tr>
            </table>
            ";
         }
		}
	   echo "
		<div style=\"margin-top: 15px; margin-bottom: 5px;\">";
			if($mode > 0)
			{
				echo "<button name=\"back\" type=\"button\" value=\"back\" onclick=\"history.back()\">
					<img src=\"../adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zurueck\">
					&nbsp;Zurück</button>&nbsp;&nbsp;";
			}
			echo "<button name=\"forward\" type=\"submit\" value=\"forward\">Weiter&nbsp;
				<img src=\"../adm_program/images/forward.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Weiter\">
			</button>
		</div>
		</form>
      </div>
   </div>
   <div class=\"formHead\" style=\"font-size: 8pt; text-align: center; border-top-width: 0px;\">
      &copy; 2004 - 2005&nbsp;&nbsp;<a href=\"http://www.admidio.org\" target=\"_blank\">The Admidio Team</a>
   </div>
</div>
</body>
</html>";
?>