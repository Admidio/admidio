<?php
/**
 ***********************************************************************************************
 * Read a file from the adm_my_files folder
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * file_uuid :  The UUID of the file that should be returned
 * view      :  If set to true than the output will not be send as attachement
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Initialize and check the parameters
$getFileUuid = admFuncVariableIsValid($_GET, 'file_uuid', 'string', array('requireValue' => true));
$getView     = admFuncVariableIsValid($_GET, 'view', 'bool');

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('documents_files_enable_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

try {
    // get recordset of current file from database
    $file = new TableFile($gDb);
    $file->getFileForDownload($getFileUuid);
} catch (AdmException $e) {
    $e->showHtml();
    // => EXIT
}

// get complete path with filename of the file
$completePath = $file->getFullFilePath();

// check if the file already exists
if (!is_file($completePath)) {
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
    // => EXIT
}

// Increment download counter
$file->setValue('fil_counter', (int) $file->getValue('fil_counter') + 1);
$file->save();

// determine filesize
$fileSize = filesize($completePath);

if ($getView) {
    $content = 'inline';
} else {
    $content = 'attachment';
}

// Create appropriate header information of the file
header('Content-Type: ' . $file->getMimeType());
header('Content-Length: ' . $fileSize);
header('Content-Disposition: ' . $content . '; filename="' . $file->getValue('fil_name') . '"');

// necessary for IE, because without it the download with SSL has problems
header('Cache-Control: private');
header('Pragma: public');

// file output
if ($fileSize > 10 * 1024 * 1024) {
    // file output for large files (> 10MB)
    $chunkSize = 1024 * 1024;
    $handle = fopen($completePath, 'rb');
    while (!feof($handle)) {
        $buffer = fread($handle, $chunkSize);
        echo $buffer;
        ob_flush();
        flush();
    }
    fclose($handle);
} else {
    // file output for small files (< 10MB)
    readfile($completePath);
}
