<?php
/**
 ***********************************************************************************************
 * API endpoint to download a file from the documents and files module
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
use Admidio\Documents\Entity\File;

validateApiKey();

$getFileUuid = admFuncVariableIsValid($_GET, 'file_uuid', 'string', array('requireValue' => true));
$getView     = admFuncVariableIsValid($_GET, 'view', 'bool');

try {
    $file = new File($gDb);
    $file->getFileForDownload($getFileUuid);
} catch (AdmException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(array('error' => 'No permission to download this file.'));
    exit;
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(array('error' => 'Unable to download file.'));
    exit;
}

$completePath = $file->getFullFilePath();

if (!is_file($completePath)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode(array('error' => 'File not found.'));
    exit;
}

// Increment download counter
try {
    $file->setValue('fil_counter', (int) $file->getValue('fil_counter') + 1);
    $file->save();
} catch (Exception $e) {
    // ignore counter issues
}

$fileSize = filesize($completePath);
$content = $getView ? 'inline' : 'attachment';

header('Content-Type: ' . $file->getMimeType());
header('Content-Length: ' . $fileSize);
header('Content-Disposition: ' . $content . '; filename="' . $file->getValue('fil_name') . '"');
header('Cache-Control: private');
header('Pragma: public');

if ($fileSize > 10 * 1024 * 1024) {
    $chunkSize = 1024 * 1024;
    $handle = fopen($completePath, 'rb');
    while (!feof($handle)) {
        $buffer = fread($handle, $chunkSize);
        echo $buffer;
        @ob_flush();
        flush();
    }
    fclose($handle);
} else {
    readfile($completePath);
}
