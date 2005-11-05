<?php
/******************************************************************************
 * Einrichtungsscript fuer die MySql-Datenbank
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

function showError($err_msg, $err_head = "Fehler", $finish = false)
{
   global $g_root_path;

   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>Admidio - Installation</title>

      <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-15\">
      <meta name=\"author\"   content=\"Markus Fassbender\">
      <meta name=\"robots\"   content=\"index,follow\">
      <meta name=\"language\" content=\"de\">

      <link rel=\"stylesheet\" type=\"text/css\" href=\"../adm_config/main.css\">
   </head>
   <body>
      <div align=\"center\"><br /><br />
         <div class=\"formHead\" style=\"width: 300px;\">$err_head</div>
         <div class=\"formBody\" style=\"width: 300px;\">
            <p>$err_msg</p>";
            if($err_file_write)
            {
               echo "<p>Die Zugangsdaten f&uuml;r die Datenbank konnten nicht in die Datei
               <i>adm_config/config.php</i> geschrieben werden. Bitte tun Sie dies manuell. </p>";
            }

            echo "
            <p><button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"";
            if($finish)
               echo "self.location.href='../adm_program/index.php'\">Admidio &Uuml;bersicht";
            else
               echo "history.back()\">Zur&uuml;ck";
            echo "</button></p>
         </div>
      </div>
   </body>
   </html>";
   exit();
}

if(strlen($_POST['server'])    == 0
|| strlen($_POST['user'])     == 0
// bei localhost muss es kein Passwort geben
//|| strlen($_POST['password'])  == 0
|| strlen($_POST['database']) == 0 )
{
   showError("Es sind nicht alle Zugangsdaten zur MySql-Datenbank eingegeben worden !");
}

if(!array_key_exists("file", $_GET))
   $_GET['file'] = 0;

if(!array_key_exists("struktur", $_POST))
   $_POST['struktur'] = 0;

if(!array_key_exists("update", $_POST))
   $_POST['update'] = 0;

if($_POST['update'] == 1)
{
   if($_POST['version'] == 0)
   {
      showError("Bei einem Update m&uuml;ssen Sie Ihre bisherige Version angeben !");
   }
}

if(array_key_exists("user-webmaster", $_POST))
{
   if($_POST['user-webmaster'] == 1)
   {
      $_POST['user-surname']   = trim($_POST['user-surname']);
      $_POST['user-firstname'] = trim($_POST['user-firstname']);
      $_POST['user-login']     = trim($_POST['user-login']);
      
      if(strlen($_POST['user-surname'])   == 0
      || strlen($_POST['user-firstname']) == 0
      || strlen($_POST['user-login'])     == 0
      || strlen($_POST['user-passwort'])  == 0 )
      {
         showError("Es sind nicht alle Benutzerdaten eingegeben worden !");
      }

      if(strlen($_POST['verein-name-kurz']) == 0)
      {
         showError("Sie m&uuml;ssen den kurzen Namen der Gruppierung / des Vereins eingeben,
                    dem der Benutzer zugeordnet werden kann.");
      }
   }
}
else
   $_POST['user-webmaster'] = 0;

if(array_key_exists("verein", $_POST))
{
   if($_POST['verein'] == 1
   && (  strlen($_POST['verein-name-lang']) == 0
      || strlen($_POST['verein-name-kurz']) == 0 ))
   {
      showError("Sie m&uuml;ssen einen Namen f&uuml;r die Gruppierung / den Verein eingeben !");
   }
}
else
   $_POST['verein'] = 0;

// Verbindung zu Datenbank herstellen
$connection = mysql_connect ($_POST['server'], $_POST['user'], $_POST['password'])
              or showError("Es konnte keine Verbindung zur Datenbank hergestellt werden.<br /><br />
                            Pr&uuml;fen Sie noch einmal Ihre MySql-Zugangsdaten !");

if(!mysql_select_db($_POST['database'], $connection ))
   showError("Die angegebene Datenbank <b>". $_POST['database']. "</b> konnte nicht gefunden werden !");

if($_GET['file'])
{
   // MySQL-Zugangsdaten in config.php schreiben
   // Datei auslesen
   $filename     = "config.php";
   $config_file  = fopen($filename, "r");
   $file_content = fread($config_file, filesize($filename));
   fclose($config_file);

   // den Root-Pfad ermitteln
   $root_path = $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI'];
   $root_path = substr($root_path, 0, strpos($root_path, "/adm_install"));
   if(!strpos($root_path, "http://"))
      $root_path = "http://". $root_path;

   $file_content = str_replace("%SERVER%", $_POST['server'], $file_content);
   $file_content = str_replace("%USER%",   $_POST['user'],   $file_content);
   $file_content = str_replace("%PASSWORD%",  $_POST['password'], $file_content);
   $file_content = str_replace("%DATABASE%",  $_POST['database'], $file_content);
   $file_content = str_replace("%ROOT_PATH%", $root_path,         $file_content);
   $file_content = str_replace("%DOMAIN%", $_SERVER['HTTP_HOST'], $file_content);
   $file_content = str_replace("%ORGANIZATION%", $_POST['verein-name-kurz'], $file_content);

   // die erstellte Config-Datei an den User schicken
   $filename = "config.php";
   header("Content-Type: application/force-download");
   header("Content-Type: application/download");
   header("Content-Type: text/csv; charset=ISO-8859-1");
   header("Content-Disposition: attachment; filename=$filename");
   echo $file_content;
   exit();
}

if($_POST['struktur'] == 1)
{
   $filename = "db.sql";
   $file     = fopen($filename, "r")
               or showError("Die Datei <b>db.sql</b> konnte nicht im Verzeichnis <b>adm_install</b> gefunden werden.");
   $content  = fread($file, filesize($filename));
   $sql_arr = explode(";", $content);
   fclose($file);

   foreach($sql_arr as $sql)
   {
      if(strlen($sql) > 0)
      {
         $result = mysql_query($sql, $connection);
         if(!$result) showError(mysql_error());
      }
   }
   
   // Default-Daten anlegen
   
   // Messenger anlegen
   $sql = "INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description)
                VALUES (NULL, 'MESSENGER', 'AIM', 'AOL Instant Messenger') ";
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());
   
   $sql = "INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description)
                VALUES (NULL, 'MESSENGER', 'ICQ', 'ICQ') ";
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());
   
   $sql = "INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description)
                VALUES (NULL, 'MESSENGER', 'MSN', 'MSN Messenger') ";
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());
   
   $sql = "INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description)
                VALUES (NULL, 'MESSENGER', 'Yahoo', 'Yahoo! Messenger') ";
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());

   $sql = "INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description)
                VALUES (NULL, 'MESSENGER', 'Skype', 'Skype') ";
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());
   
   $sql = "INSERT INTO adm_user_field (auf_ag_shortname, auf_type, auf_name, auf_description)
                VALUES (NULL, 'MESSENGER', 'Google Talk', 'Google Talk') ";
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());
}

if($_POST['update'] == 1)
{
   if($_POST['version'] == 1)
   {
      $filename = "upd_1_1_db.sql";
      $file     = fopen($filename, "r")
                  or showError("Die Datei <b>upd_1_1_db.sql</b> konnte nicht im Verzeichnis <b>adm_install</b> gefunden werden.");
      $content  = fread($file, filesize($filename));
      $sql_arr = explode(";", $content);
      fclose($file);
      
      foreach($sql_arr as $sql)
      {
         if(strlen($sql) > 0)
         {
            $result = mysql_query($sql, $connection);
            if(!$result) showError(mysql_error());
         }
      }
      
      include("upd_1_1_konv.php");
   }
   if($_POST['version'] == 1 || $_POST['version'] == 2)
   {
      $filename = "upd_1_2_db.sql";
      $file     = fopen($filename, "r")
                  or showError("Die Datei <b>upd_1_1_db.sql</b> konnte nicht im Verzeichnis <b>adm_install</b> gefunden werden.");
      $content  = fread($file, filesize($filename));
      $sql_arr = explode(";", $content);
      fclose($file);

      foreach($sql_arr as $sql)
      {
         if(strlen($sql) > 0)
         {
            $result = mysql_query($sql, $connection);
            if(!$result) showError(mysql_error());
         }
      }
   }
}

if($_POST['verein'] == 1)
{
   // neue Gruppierung anlegen

   $sql = "SELECT * FROM adm_gruppierung WHERE ag_shortname = {0} ";
   $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());

   if(mysql_num_rows($result) > 0)
   {
      showError("Eine Gruppierung / ein Verein mit dem angegebenen kurzen Namen <b>21.01.2005". $_POST['verein-name-kurz']. "</b> existiert bereits.<br /><br />
                 W&auml;hlen Sie bitte einen anderen kurzen Namen !");
   }

   $sql = "INSERT INTO adm_gruppierung (ag_shortname, ag_longname)
                VALUES ({0}, {1}) ";
   $sql = prepareSQL($sql, array($_POST['verein-name-kurz'], $_POST['verein-name-lang']));
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());

   // nun die Default-Rollen anlegen

   $sql = "INSERT INTO adm_rolle (ar_ag_shortname, ar_funktion, ar_beschreibung, ar_valid,
                                  ar_r_moderation, ar_r_termine, ar_r_foto, ar_r_download, 
                                  ar_r_user_bearbeiten, ar_r_mail_logout, ar_r_mail_login)
                VALUES ({0}, 'Webmaster', 'Gruppe der Administratoren des Systems', 1, 
                                   1, 1, 1, 1, 1, 1, 1) ";
   $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());
}

if($_POST['user-webmaster'] == 1)
{
   // User Webmaster anlegen

   $pw_md5 = md5($_POST['user-passwort']);
   $sql = "INSERT INTO adm_user (au_name, au_vorname, au_login, au_password)
                VALUES ({0}, {1}, {2}, '$pw_md5' ) ";
   $sql = prepareSQL($sql, array($_POST['user-surname'], $_POST['user-firstname'], $_POST['user-login']));
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());

   $user_id = mysql_insert_id();

   $sql = "SELECT ar_id FROM adm_rolle
            WHERE ar_ag_shortname = {0}
              AND ar_funktion     = 'Webmaster' ";
   $sql = prepareSQL($sql, array($_POST['verein-name-kurz']));
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());
   $row = mysql_fetch_array($result);

   // Mitgliedschaft anlegen
   $sql = "INSERT INTO adm_mitglieder (am_ar_id, am_au_id, am_start, am_valid)
                VALUES ($row[0], $user_id, NOW(), 1) ";
   $result = mysql_query($sql, $connection);
   if(!$result) showError(mysql_error());
}

showError("Die Einrichtung der Datenbank konnte erfolgreich abgeschlossen werden.", "Fertig", true);
?>