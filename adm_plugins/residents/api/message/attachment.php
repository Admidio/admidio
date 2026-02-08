<?php
/**
 ***********************************************************************************************
 * API endpoint to download Email attachment
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
use Admidio\Infrastructure\Entity\Entity;

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

// check if mail module is enabled
if ($gSettingsManager->getInt('mail_module_enabled') === 0) {
    admidioApiError('Messages module is disabled', 403, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ));
}

$getMsaUUID = admFuncVariableIsValid($_GET, 'msa_uuid', 'uuid', array('requireValue' => true));
$getView = admFuncVariableIsValid($_GET, 'view', 'bool');

try {
    $attachment = new Entity($gDb, TBL_MESSAGES_ATTACHMENTS, 'msa');
    $attachment->readDataByUuid($getMsaUUID);
    $message = new \Admidio\Messages\Entity\Message($gDb, $attachment->getValue('msa_msg_id'));

    // user of message is not current user than he is not allowed to view the attachment
    if ($currentUserId !== $message->getValue('msg_usr_id_sender')) {
        admidioApiError('Attachment cannot be viewed', 403, array(
        'endpoint' => $endpointName,
        'user_id' => $currentUserId
        ));
    }    
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

$completePath = ADMIDIO_PATH . FOLDER_DATA . '/messages_attachments/' . $attachment->getValue('msa_file_name');

if (!is_file($completePath)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode(array('error' => 'Attachment not found.'));
    exit;
}

$fileSize = filesize($completePath);
$content = $getView ? 'inline' : 'attachment';

// header('Content-Type: ' . $attachment->getMimeType());
header('Content-Length: ' . $fileSize);
header('Content-Disposition: ' . $content . '; filename="' . $attachment->getValue('msa_file_name') . '"');
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
