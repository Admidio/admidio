<?php
/******************************************************************************
 * Download Script
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * file_id      :  Die Id der Datei, welche heruntergeladen werden soll
 *
 *****************************************************************************/

require("../../system/common.php");

//pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1) {
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

//pruefen ob eine brauchbare File_ID uebergeben wurde
if (array_key_exists("file_id", $_GET))
{
    if (is_numeric($_GET["file_id"]) == false)
    {
        //FileId ist nicht numerisch
    	$g_message->show("invalid");
    }
}
else
{
    // ohne FileId gehts auch nicht weiter
	$g_message->show("invalid");
}


//Fileobject erstellen
$file = new File($g_db);

//Fileproperties aus DB lesen
$file->getFile($_GET['file_id']);

//TODO: pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...

//TODO: pruefen ob der User die Berechtigung hat das File runterzuladen...

//Dateinamen und Pfad zusammen setzen
$fileName     = $file->getValue("fil_name");
$folderPath   = $file->getValue("fol_path");
$folderName   = $file->getValue("fol_name");
$completePath = "$folderPath/$folderName/$fileName";

//pruefen ob File ueberhaupt physikalisch existiert
if (!file_exists($completePath)) {
    $g_message->show("file_not_exist");
}

//Downloadcounter inkrementieren
$newCounter = $file->getValue("fil_counter") + 1;
$file->setValue("fil_counter",$newCounter);
$file->save();


//Dateigroese ermitteln
$fileSize   = filesize($completePath);

// Passenden Datentyp erzeugen.
header("Content-Type: application/octet-stream");
header("Content-Length: $fileSize");
header("Content-Disposition: attachment; filename=\"$fileName\"");
// noetig fuer IE6, da sonst pdf und doc nicht direkt geoeffnet werden kann
header('Cache-Control: private');

// Datei ausgeben.
readfile($completePath);
?>