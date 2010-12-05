<?php
/******************************************************************************
 * Download Script
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * file_id      :  Die Id der Datei, welche heruntergeladen werden soll
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/classes/table_file.php');

//pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}

//pruefen ob eine brauchbare File_ID uebergeben wurde
if (array_key_exists('file_id', $_GET))
{
    if (is_numeric($_GET['file_id']) == false)
    {
        //FileId ist nicht numerisch
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
}
else
{
    // ohne FileId gehts auch nicht weiter
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}


//Fileobject erstellen
$file = new TableFile($g_db);

//Fileproperties aus DB lesen fuer den Download
$file->getFileForDownload($_GET['file_id']);

//pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
if (!$file->getValue('fil_id'))
{
    //Datensatz konnte nicht in DB gefunden werden...
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

//kompletten Pfad der Datei holen
$completePath = $file->getCompletePathOfFile();


//pruefen ob File ueberhaupt physikalisch existiert
if (!file_exists($completePath))
{
    $g_message->show($g_l10n->get('SYS_FILE_NOT_EXIST'));
}

//Downloadcounter inkrementieren
$file->setValue('fil_counter',$file->getValue('fil_counter') + 1);
$file->save();


//Dateigroese ermitteln
$fileSize   = filesize($completePath);

// Passenden Datentyp erzeugen.
header('Content-Type: application/octet-stream');
header('Content-Length: '.$fileSize);
header('Content-Disposition: attachment; filename="'. $file->getValue('fil_name'). '"');
// noetig fuer IE6, da sonst pdf und doc nicht direkt geoeffnet werden kann
header('Cache-Control: private');
header('Pragma: public');

// Datei ausgeben.
readfile($completePath);
?>