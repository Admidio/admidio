<?
/******************************************************************************
 * Download Script
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Günzler
 *
 * Uebergaben:
 *
 * file	: 
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
// $download sei der Bezeichner für die zu ladende Datei
// etwa: 
$file = $_GET['file'];

// Dieses Verzeichnis liegt außerhalb des Document Root und
// ist nicht per URL erreichbar.
$basedir = "$g_root_path/adm_my_files/download";

// uebergebene Ordner auf Gueltigkeit pruefen
// und Ordnerpfad zusammensetzen
   if(strlen($file) > 0)
   {
      if(strpos($file, "..") !== false)
      {
         $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_file";
         header($location);
         exit();
      }
      $act_folder = "$act_folder/$default_folder";
   }

   if(strlen($file) > 0)
   {
      if(strpos($file, "..") !== false)
      {
         $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_file";
         header($location);
         exit();
      }
      $act_folder = "$act_folder/$folder";
   }

// Vertrauenswürdigen Dateinamen basteln.
$filename = sprintf("%s/%s", $basedir, $file);

// Passenden Datentyp erzeugen.
header("Content-Type: application/octet-stream");

// Passenden Dateinamen im Download-Requester vorgeben,
// z. B. den Original-Dateinamen
$save_as_name = basename($file);
header("Content-Disposition: attachment; filename=\"$save_as_name\"");

// Datei ausgeben.
readfile($filename);
?>