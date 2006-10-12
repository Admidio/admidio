<?php
/******************************************************************************
 * Downloads auflisten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Guenzler
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

require("../../system/common.php");
require("../../system/login_valid.php");

// Uebergabevariablen pruefen
	if(is_numeric($_GET["mode"]) == false
	|| $_GET["mode"] < 1 || $_GET["mode"] > 4)
	{
    	$g_message->show("invalid");
	}

//Pruefrotine ob Ordner/Datei
function file_or_folder ($act_dir,$file) 
{
    if(strlen($file) > 0)
    {
        if(is_file("$act_dir/$file"))
        {
            return false;
        }
        else
        {
            if(is_dir("$act_dir/$file"))
            {
                return true;
            }
            else
            {
                return -1;
            }
        }
    }
};

// rekursive Funktion um ganze Ordner mit Unterordnern zu loeschen
function removeDir ($dir) 
{
    $fHandle = opendir($dir);
    if($fHandle > 0) 
    {
        while (false !== ($fName = readdir($fHandle))) 
        {     
            if($fName != "." && $fName != "..")
            {
                if(is_dir("$dir/$fName"))
                {
                    removeDir("$dir/$fName");               
                }
                else
                {
                    unlink("$dir/$fName");
                }
            }
        }
        return rmdir($dir);      
    };
    return false;
};


// erst pruefen, ob der User auch die entsprechenden Rechte hat
if(!editDownload())
{
    $g_message->show("norights");
}

//testen ob Schreibrechte fuer adm_my_files bestehen
if (decoct(fileperms("../../../adm_my_files/download"))!=40777)
{
    $g_message->show("invalid_folder");
}

if (isset($_GET['folder']))
	$folder = strStripTags(urldecode($_GET['folder']));
else
	$folder = ""; 

if (isset($_GET['file']))
	$file = strStripTags(urldecode($_GET['file']));
else
	$file = "";

if (isset($_GET['default_folder']))
	$folder = strStripTags(urldecode($_GET['default_folder']));
else
	$default_folder = "";

$url        = "";
$act_folder = "../../../adm_my_files/download";

// uebergebene Ordner auf Gueltigkeit pruefen
// und Ordnerpfad zusammensetzen
if(strlen($default_folder) > 0)
{
    if(strpos($default_folder, "..") !== false)
    {
        $g_message->show("invalid_folder");
    }
    $act_folder = "$act_folder/$default_folder";
}
if(strlen($folder) > 0)
{
    if(strpos($folder, "..") !== false)
    {
        $g_message->show("invalid_folder");
    }
    $act_folder = "$act_folder/$folder";
}

// pruefen, ob Datei oder Ordner uebergeben wurde
$is_folder = file_or_folder ($act_folder,$file);

if($_GET["mode"] == 1)
{
    if (empty($_POST))
    {
        $g_message->show("empty_upload_post",ini_get(post_max_size));
    }
    
    // Dateien hochladen
    if(strpos($_POST['new_name'], "..") !== false)
    {
        $g_message->show("invalid_file");
    }
    else
    {
        $local_file = $_FILES['userfile']['name'];
        //Dateigroesse ueberpruefen Servereinstellungen
        if ($_FILES['userfile']['error']==1)
        {
                    $g_message->show("file_2big_server",ini_get(post_max_size));
        }
        
        //Dateigroesse ueberpruefen Administratoreinstellungen
        if ($_FILES['userfile']['size']>($g_preferences['max_file_upload_size'])*1000)
        {
            $g_message->show("file_2big_server",ini_get(post_max_size));
        }
        
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
        if(strlen($_POST['new_name']) > 0)
        {
            $file_name = $_POST['new_name'];
        }

        // Zielpfad mit Dateinamen zusammensetzen
        if(strlen($file_ext) > 0)
        {
            $file_name = "$file_name.$file_ext";
        }
		
        $ret = isValidFileName($file_name, true);
        if($ret == 0)
        {
            // Datei hochladen
            if(move_uploaded_file($_FILES['userfile']['tmp_name'], "$act_folder/$file_name"))
            {
                $g_message->show("upload_file",$file_name);
            }
            else
            {
                $url= "$g_root_path/adm_program/modules/download/download.php?default_folder=$default_folder&folder=$folder";
            }
        }
        else
        {
        	if($ret == -1)
        	{
        		$g_message->show("feld", "Datei auswählen");
        	}
            elseif($ret == -2)
            {
                $g_message->show("invalid_file_name",$file_name);
            }
            elseif($ret == -3)
            {
                $g_message->show("invalid_file_extension");            
            }
        }
    }
}
elseif($_GET["mode"] == 2)
{
   // Loeschen der Datei/Ordner
   
    if($is_folder)
    {
        if( removeDir ("$act_folder/$file"))
        {
        		$g_message->show("delete_folder",$file);
        }
    }
    else
    {
        if(unlink("$act_folder/$file"))
        {
            $g_message->show("delete_file",$file);
        }
    }
    $url = urlencode("$g_root_path/adm_program/modules/download/download.php?folder=$folder&default_folder=$default_folder");
}
elseif($_GET["mode"] == 3)
{
   // Ordner erstellen
   $new_folder = $_POST['new_folder'];

   if(strpos($new_folder, "..") !== false)
      $g_message->show("invalid_folder");
   else
   {
      if(strlen($new_folder) == 0)
      {
         $g_message->show("feld", "Name");
      }
      else
      {
         //Test ob der Ordner schon existiert
         $ordnerarray = array();
         $ordnerinhalt = dir($act_folder);
         while ($inhalt = $ordnerinhalt->read())
         {
            if ($inhalt != "." AND $inhalt != "..")
               $ordnerarray[] = $inhalt;
         }

         if(in_array($new_folder, $ordnerarray))
         {
            $g_message->show(folder_exists, $new_folder);
         }
         else
         {
            // Ordner erstellen
            mkdir("$act_folder/$new_folder",0777);
            chmod("$act_folder/$new_folder", 0777);

            $g_message->show("create_folder", $new_folder);
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
      $g_message->show("feld", "Name");
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
            $g_message->show("folder_exists");
         }
         else
         {
            //Umbenennen der Datei
            if(rename("$act_folder/$file","$act_folder/$new_name"))
            {
               $g_message->show("rename_folder",$file);
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
            $g_message->show("file_exists",$file);
         }
         else
         {
            $ret = isValidFileName($new_name, true);
            if($ret == 0)
            {
            //Umbenennen der Datei
            if(rename("$act_folder/$file","$act_folder/$new_name"))
            {
            	$g_message->show("rename_file",$file);
				}
            }
            else
            {
               if($ret == -2)
               {
                  $g_message->show("invalid_file_name",$new_name);
               }
               elseif($ret == -3)
                  $g_message->show("invalid_file_extension");
            }
         }
      }
   }
}

?>