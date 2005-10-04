<?php
/******************************************************************************
 * Installationsdialog fuer die MySql-Datenbank
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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
?>

<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org -->

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
   <title>Admidio - Installation</title>

   <meta http-equiv="content-type" content="text/html; charset=ISO-8859-15">
   <meta name="author"   content="Markus Fassbender">
   <meta name="robots"   content="index,follow">
   <meta name="language" content="de">

   <link rel="stylesheet" type="text/css" href="../adm_config/main.css">
   
   <script type="text/javascript">
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
   <script type="text/javascript" src="../adm_program/system/correct_png.js"></script>
   <![endif]-->
</head>
<body>
<div align="center">
   <div class="formHead" style="text-align: left;">
        <img style="float:left; padding: 5px 0px 0px 0px;" src="../adm_images/admidio_logo_50.png" border="0" alt="www.admidio.org" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <div style="font-size: 16pt; font-weight: bold; text-align: right; padding: 5px 10px 10px 0px;">Version <?php echo getVersion(); ?></div>
        <div style="font-size: 11pt; padding: 0px 0px 5px 0px;">Das Online-Verwaltungssystem f&uuml;r Vereine, Gruppen und Organisationen</div>
   </div>

   <div class="formBody">
      <div align="center">
      <h1>Installation &amp; Einrichtung<br />der MySQL-Datenbank</h1>

      <form name="server" action="inst_do.php" method="post">
         <table class="groupBox" width="350" cellpadding="5">
            <tr>
               <td class="groupBoxHeadline" colspan="2">Zugangsdaten MySql-Datenbank</td>
            </tr>
            <tr>
               <td width="120px">Server:</td>
               <td><input type="text" name="server" size="25" maxlength="50" /></td>
            </tr>
            <tr>
               <td width="120px">Login:</td>
               <td><input type="text" name="user" size="25" maxlength="50" /></td>
            </tr>
            <tr>
               <td width="120px">Passwort:</td>
               <td><input type="text" name="password" size="25" maxlength="50" /></td>
            </tr>
            <tr>
               <td width="120px">Datenbank:</td>
               <td><input type="text" name="database" size="25" maxlength="50" /></td>
            </tr>
         </table>

         <br />

         <table class="groupBox" width="350" cellpadding="5">
            <tr>
               <td class="groupBoxHeadline" colspan="2">Optionen</td>
            </tr>
            <tr>
               <td colspan="2"><input type="checkbox" id="struktur" name="struktur" value="1"
                  <?php if($first_install) echo " checked "; ?>
                  />
                  <label for="struktur">Datenbankstruktur anlegen<br />
                  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(L&ouml;scht eine bereits vorhandene Datenbank)</label></td>
            </tr>
            <tr>
               <td colspan="2"><input type="checkbox" id="update" name="update" value="1"
                  <?php if(!$first_install) echo " checked "; ?>
                  />
                  <label for="update">Datenbankstruktur updaten</label></td>
            </tr>
            <tr>
               <td width="120px" align="right">&nbsp;</td>
               <td>
                  <select size="1" name="version">
                     <option value="0" selected="selected">bisherige Version</option>
                     <option value="1">1.0 - 1.0.3</option>
                  </select>
               </td>
            </tr>
            <tr>
               <td colspan="2"><input type="checkbox" id="verein" name="verein" value="1"
                  <?php if($first_install) echo " checked "; ?>
                  />
                  <label for="verein">Neue Gruppierung / Verein anlegen</label>
               </td>
            </tr>
            <tr>
               <td width="120px" align="right">Name (Abk.):</td>
               <td><input type="text" name="verein-name-kurz" size="10" maxlength="10" /></td>
            </tr>
            <tr>
               <td width="120px" align="right">Name (lang):</td>
               <td><input type="text" name="verein-name-lang" size="25" maxlength="60" /></td>
            </tr>
         </table>

         <br />

         <table class="groupBox" width="350" cellpadding="5">
            <tr>
               <td class="groupBoxHeadline" colspan="2">Benutzer anlegen</td>
            </tr>
            <tr>
               <td colspan="2"><input type="checkbox" id="user-webmaster" name="user-webmaster" value="1"
                  <?php if($first_install) echo " checked "; ?>
                  />
                  <label for="user-webmaster">Benutzer <i>Webmaster</i> anlegen</label></td>
            </tr>
            <tr>
               <td width="120px">Nachname:</td>
               <td><input type="text" name="user-surname" size="25" maxlength="50" /></td>
            </tr>
            <tr>
               <td width="120px">Vorname:</td>
               <td><input type="text" name="user-firstname" size="25" maxlength="50" /></td>
            </tr>
            <tr>
               <td width="120px">Benutername:</td>
               <td><input type="text" name="user-login" size="25" maxlength="50" /></td>
            </tr>
            <tr>
               <td width="120px">Passwort:</td>
               <td><input type="text" name="user-passwort" size="25" maxlength="50" /></td>
            </tr>
         </table>

         <br />
         <?php
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
         else
         {
            echo "
            <button name=\"updaten\" type=\"button\" value=\"updaten\" onclick=\"installDatabase()\">Datenbank updaten</button>";
         }
         ?>
      </form>
      </div>
   </div>
   <div class="formHead" style="font-size: 8pt; text-align: center; width: 550px; border-top-width: 0px;">
      &copy; 2004 - 2005&nbsp;&nbsp;<a href="http://www.admidio.org" target="_blank">The Admidio Team</a>
   </div>
</div>
</body>
</html>