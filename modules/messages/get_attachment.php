<?php
/**
 ***********************************************************************************************
 * Read an attachment file from the adm_my_files folder messages_attachments
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * msa_uuid :  The UUID of the attachment file that should be returned
 * view     :  If set to true than the output will be shown in the browser
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Entity\Entity;

require_once(__DIR__ . '/../../system/common.php');

try {
    // Initialize and check the parameters
    $getMsaUUID = admFuncVariableIsValid($_GET, 'msa_uuid', 'uuid', array('requireValue' => true));
    $getView = admFuncVariableIsValid($_GET, 'view', 'bool');

    // check if the call of the page was allowed
    if ($gSettingsManager->getInt('mail_module_enabled') === 0) {
        throw new Exception('SYS_MODULE_DISABLED');
    } elseif ($gSettingsManager->getInt('mail_module_enabled') === 2 && !$gValidLogin) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // read data from database
    $attachment = new Entity($gDb, TBL_MESSAGES_ATTACHMENTS, 'msa');
    $attachment->readDataByUuid($getMsaUUID);
    $message = new \Admidio\Messages\Entity\Message($gDb, $attachment->getValue('msa_msg_id'));

    // user of message is not current user than he is not allowed to view the attachment
    if ($gCurrentUserId !== $message->getValue('msg_usr_id_sender')) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // get complete path with filename of the attachment
    $completePath = ADMIDIO_PATH . FOLDER_DATA . '/messages_attachments/' . $attachment->getValue('msa_file_name');

    // check if the file already exists
    if (!is_file($completePath)) {
        throw new Exception('SYS_FILE_NOT_EXIST');
    }

    // determine filesize
    $fileSize = filesize($completePath);
    $filename = FileSystemUtils::getSanitizedPathEntry($attachment->getValue('msa_file_name'));

    if ($getView) {
        $content = 'inline';
    } else {
        $content = 'attachment';
    }

    // Create appropriate header information of the file
    header('Content-Type: ' . FileSystemUtils::getFileMimeType($filename));
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: ' . $content . '; filename="' . $filename . '"');

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
} catch (Throwable $e) {
    handleException($e);
}
