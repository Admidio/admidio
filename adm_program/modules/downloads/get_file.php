<?php
/******************************************************************************
 * Download Script
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * file_id      :  Die Id der Datei, welche heruntergeladen werden soll
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/classes/table_file.php');

// Initialize and check the parameters
$getFileId = admFuncVariableIsValid($_GET, 'file_id', 'numeric', null, true);

//pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

try
{
    // get recordset of current file from databse
    $file = new TableFile($gDb);
    $file->getFileForDownload($getFileId);
}
catch(AdmException $e)
{
	$e->showHtml();
}

//kompletten Pfad der Datei holen
$completePath = $file->getCompletePathOfFile();


//pruefen ob File ueberhaupt physikalisch existiert
if (!file_exists($completePath))
{
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
}

//Downloadcounter inkrementieren
$file->setValue('fil_counter',$file->getValue('fil_counter') + 1);
$file->save();


//Dateigroese ermitteln
$fileSize   = filesize($completePath);
$filename   = $file->getValue('fil_name');

// for IE the filename must have special chars in hexadecimal 
if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']))
{
    $filename = urlencode($filename);
}

// Passenden Datentyp erzeugen.
header('Content-Type: application/octet-stream');
header('Content-Length: '.$fileSize);
header('Content-Disposition: attachment; filename="'.$filename.'"');

// neccessary for IE, because without it the download with SSL has problems
header('Cache-Control: private');
header('Pragma: public');

// Datei ausgeben.
readfile($completePath);
?>