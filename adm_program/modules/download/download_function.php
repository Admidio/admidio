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
 * mode   :  1 - Datei / Ordner hochladen
 *           2 - Datei / Ordner loeschen
 *           3 - Ordner erstellen
 *           4 - Datei / Ordner umbenennen
 * folder :  relativer Pfad zu der Datei / Ordners
 * default_folder : gibt den Ordner in adm_my_files/download an, ab dem die
 *                  Verzeichnisstruktur angezeigt wird. Wurde ein Default-Ordner
 *                  gesetzt, kann der Anwender nur noch in Unterordner und nicht
 *                  in hoehere Ordner des Default-Ordners navigieren
 * file   :  die Datei / der Ordner der / die verarbeitet wird
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
require("../../system/function.php");
require("../../system/date.php");
require("../../system/string.php");
require("../../system/tbl_user.php");
require("../../system/session_check_login.php");

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!editDownload())
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

//testen ob Schreibrechte für adm_my_files bestehen
if (decoct(fileperms("../../../adm_my_files/download"))!=40777)
{
   $load_url = urlencode("$g_root_path/adm_program/modules/download/download.php");
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=write_access&err_text=adm_my_files/download&url=$load_url";
   header($location);
   exit();
}

$folder = urldecode($_GET['folder']);
$file   = urldecode($_GET['file']);
$default_folder = urldecode($_GET['default_folder']);

$url        = "";
$err_code   = "";
$err_text   = "";
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

// pruefen, ob Datei oder Ordner uebergeben wurde
if(strlen($file) > 0)
{
   if(is_file("$act_folder/$file"))
      $is_folder = false;
   else
   {
      if(is_dir("$act_folder/$file"))
         $is_folder = true;
      else
         $is_folder = -1;
   }
}

if($_GET["mode"] == 1)
{
   // Dateien hochladen
   if(strpos($_POST['new_name'], "..") !== false)
      $err_code = "invalid_file";
   else
   {
      $local_file = $_FILES['userfile']['name'];
      // Datei-Extension ermitteln
      if(strpos($local_file, ".") !== false)
      {
         $file_ext  = substr($local_file, strrpos($local_file, ".")+1);
         $file_name = substr($local_file, 0, strrpos($local_file, "."));
      }
      else
      {
         $file_ext  = "";
         $file_name = $local_file;
      }

      // wenn neuer Name uebergeben wurde, dann diesen nehmen
      IF(strlen($_POST['new_name']) > 0)
         $file_name = $_POST['new_name'];

      // Zielpfad mit Dateinamen zusammensetzen
      if(strlen($file_ext) > 0)
         $file_name = "$file_name.$file_ext";

      $ret = isValidFileName($file_name, true);
      if($ret == 0)
      {
         // Datei hochladen
         if(move_uploaded_file($_FILES['userfile']['tmp_name'], "$act_folder/$file_name"))
         {
            $err_code = "upload_file";
            $err_text = $file_name;
            $url = urlencode("$g_root_path/adm_program/modules/download/download.php?folder=$folder&default_folder=$default_folder");
         }
      }
      else
      {
         if($ret == -2)
         {
            $err_code = "invalid_file_name";
            $err_text = $file_name;
         }
         elseif($ret == -3)
            $err_code = "invalid_file_extension";
      }
   }
}
elseif($_GET["mode"] == 2)
{
   //Löschen der Datei/Ordner
   if($is_folder)
   {
      If (rmdir ("$act_folder/$file"))
      {
         $err_code = "delete_folder";
         $err_text = $file;
      }
   }
   else
   {
      IF (unlink("$act_folder/$file"))
      {
         $err_code = "delete_file";
         $err_text = $file;
      }
   }
   $url = urlencode("$g_root_path/adm_program/modules/download/download.php?folder=$folder&default_folder=$default_folder");
}
elseif($_GET["mode"] == 3)
{
   // Ordner erstellen
   $new_folder = $_POST['new_folder'];

   if(strpos($new_folder, "..") !== false)
      $err_code = "invalid_folder";
   else
   {
      if(strlen($new_folder) == 0)
      {
         $err_code = "feld";
         $err_text = "Name";
      }
      else
      {
         //Test ob der Ordner schon existiert
         $ordnerinhalt = dir($act_folder);
         while ($inhalt = $ordnerinhalt->read())
         {
            if ($inhalt != "." AND $inhalt != "..")
               $ordnerarray[] = $inhalt;
         }

         if(in_array($new_folder, $ordnerarray))
         {
            $err_code = "folder_exists";
            $err_text = $new_folder;
         }
         else
         {
            // Ordner erstellen
            mkdir("$act_folder/$new_folder",0777);
            chmod("$act_folder/$new_folder", 0777);

            $err_code = "create_folder";
            $err_text = $new_folder;
            $url = urlencode("$g_root_path/adm_program/modules/download/download.php?folder=$folder&default_folder=$default_folder");
         }
      }
   }
}
elseif($_GET["mode"] == 4)
{
   // Datei / Ordner umbenennen
   $new_name = $_POST['new_name'];

   if(strlen($new_name) == 0)
   {
      $err_code = "feld";
      $err_text = "Name";
   }
   else
   {
      //Test ob der Ordner / Datei schon existiert
      $ordnerinhalt = dir($act_folder);
      while ($inhalt = $ordnerinhalt->read())
      {
         if ($inhalt != "." AND $inhalt != "..")
            $ordnerarray[] = $inhalt;
      }

      //Datei oder Ordner?
      if($is_folder)
      {
         //Gibt es den Ordner schon?
         if(in_array($new_name, $ordnerarray))
         {
            $err_code = "folder_exists";
            $err_text = $new_folder;
         }
         else
         {
            //Umbenennen der Datei
            if(rename("$act_folder/$file","$act_folder/$new_name"))
            {
               $err_code = "rename_folder";
               $err_text = $file;
               $url = urlencode("$g_root_path/adm_program/modules/download/download.php?folder=$folder&default_folder=$default_folder");
            }
         }
      }
      else
      {
         //Wegstreichen der Endung
         If (strchr(strrev($new_name),'.')) {
            $new_name = strrev(substr(strchr(strrev($new_name),'.'),1));
         };
         if(strpos($file, ".") !== false)
            $file_ext = substr($file, strrpos($file, "."));
         else
            $file_ext = "";
         $new_name = $new_name. $file_ext;

         //Gibt es die Datei schon?
         if(in_array($new_name, $ordnerarray))
         {
            $err_code = "file_exists";
            $err_text = $new_name;
         }
         else
         {
            $ret = isValidFileName($new_name, true);
            if($ret == 0)
            {
            //Umbenennen der Datei
            if(rename("$act_folder/$file","$act_folder/$new_name"))
            {
               $err_code = "rename_file";
               $err_text = $file;
               $url = urlencode("$g_root_path/adm_program/modules/download/download.php?folder=$folder&default_folder=$default_folder");
            }
            }
            else
            {
               if($ret == -2)
               {
                  $err_code = "invalid_file_name";
                  $err_text = $new_name;
               }
               elseif($ret == -3)
                  $err_code = "invalid_file_extension";
            }
         }
      }
   }
}

$location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text&url=$url";
header($location);
exit();
?>