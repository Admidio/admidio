<?php
/**
 ***********************************************************************************************
 * Read a file from the adm_my_files folder
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * file_id  :  The id of the file that should be returned
 * view     :  If set to true than the output will not be send as attachement
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getFileId = admFuncVariableIsValid($_GET, 'file_id', 'int', array('requireValue' => true));
$getView   = admFuncVariableIsValid($_GET, 'view', 'bool');

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('documents_files_enable_module'))
{
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
    // => EXIT
}

// kompletten Pfad der Datei holen
$completePath = $file->getFullFilePath();

// pruefen ob File ueberhaupt physikalisch existiert
if (!is_file($completePath))
{
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
    // => EXIT
}

// Downloadcounter inkrementieren
$file->setValue('fil_counter', (int) $file->getValue('fil_counter') + 1);
$file->save();

// Dateigroese ermitteln
$fileSize = filesize($completePath);
$filename = FileSystemUtils::getSanitizedPathEntry($file->getValue('fil_name'));

if($getView)
{
    $content = 'inline';
}
else
{
    $content = 'attachment';
}

// Passenden Datentyp erzeugen.
//header('Content-Type: application/octet-stream');
header('Content-Type: ' . $file->getMimeType());
header('Content-Length: ' . $fileSize);
header('Content-Disposition: ' . $content . '; filename="' . $filename . '"');

// necessary for IE, because without it the download with SSL has problems
header('Cache-Control: private');
header('Pragma: public');

// file output
if ($fileSize > 10 * 1024 * 1024)
{
    // file output for large files (> 10MB)
    $chunkSize = 1024 * 1024;
    $handle = fopen($completePath, 'rb');
    while (!feof($handle))
    {
        $buffer = fread($handle, $chunkSize);
        echo $buffer;
        ob_flush();
        flush();
    }
    fclose($handle);
}
else
{
    // file output for small files (< 10MB)
    readfile($completePath);
}
