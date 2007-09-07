<?php
/******************************************************************************
 * Download Script
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Günzler
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * folder :  relativer Pfad zu der Datei / Ordners
 * default_folder : gibt den Ordner in adm_my_files/download an, ab dem die
 *                  Verzeichnisstruktur angezeigt wird. Wurde ein Default-Ordner
 *                  gesetzt, kann der Anwender nur noch in Unterordner und nicht
 *                  in hoehere Ordner des Default-Ordners navigieren
 * file   :  die Datei / der Ordner der / die verarbeitet wird
 *
 *****************************************************************************/

require("../../system/common.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

$folder = strStripTags(urldecode($_GET['folder']));
$file   = strStripTags(urldecode($_GET['file']));
$default_folder = strStripTags(urldecode($_GET['default_folder']));
$act_folder     = SERVER_PATH. "/adm_my_files/download";

// uebergebene Ordner auf Gueltigkeit pruefen
// und Ordnerpfad zusammensetzen
if(strlen($default_folder) > 0)
{
   if(strpos($default_folder, "..") !== false
   || strpos($default_folder, ":/") !== false)
   {
      $g_message->show("invalid_folder");
   }
   $act_folder = "$act_folder/$default_folder";
}

if(strlen($folder) > 0)
{
   if(strpos($folder, "..") !== false
   || strpos($folder, ":/") !== false)
   {
      $g_message->show("invalid_folder");
   }
   $act_folder = "$act_folder/$folder";
}

$file_name   = "$act_folder/$file";
$file_length = filesize("$act_folder/$file");

// Passenden Datentyp erzeugen.
header("Content-Type: application/octet-stream");
header("Content-Length: $file_length");
header("Content-Disposition: attachment; filename=\"$file\"");
// noetig fuer IE6, da sonst pdf und doc nicht direkt geoeffnet werden kann
header('Cache-Control: private'); 

// Datei ausgeben.
readfile($file_name);
?>