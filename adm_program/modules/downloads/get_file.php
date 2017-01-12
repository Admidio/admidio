<?php
/**
 ***********************************************************************************************
 * Download Script
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * file_id      :  Die Id der Datei, welche heruntergeladen werden soll
 ***********************************************************************************************
 */
require('../../system/common.php');

// Initialize and check the parameters
$getFileId = admFuncVariableIsValid($_GET, 'file_id', 'int', array('requireValue' => true));

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

try
{
    // get recordset of current file from database
    $file = new TableFile($gDb);
    $file->getFileForDownload($getFileId);
}
catch(AdmException $e)
{
    $e->showHtml();
}

// kompletten Pfad der Datei holen
$completePath = $file->getCompletePathOfFile();

// pruefen ob File ueberhaupt physikalisch existiert
if (!is_file($completePath))
{
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
    // => EXIT
}

// Downloadcounter inkrementieren
$file->setValue('fil_counter', $file->getValue('fil_counter') + 1);
$file->save();

// Dateigroese ermitteln
$fileSize = filesize($completePath);
$filename = $file->getValue('fil_name');

// for IE the filename must have special chars in hexadecimal
if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)
{
    $filename = urlencode($filename);
}

// Passenden Datentyp erzeugen.
header('Content-Type: application/octet-stream');
header('Content-Length: '.$fileSize);
header('Content-Disposition: attachment; filename="'.$filename.'"');

// necessary for IE, because without it the download with SSL has problems
header('Cache-Control: private');
header('Pragma: public');

// Datei ausgeben.
echo readfile($completePath);
