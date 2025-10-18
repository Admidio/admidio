<?php
/**
 ***********************************************************************************************
 * Handle image uploads from CKEditor
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * id : ID of textarea, that had triggered the upload
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;

try {
    require_once(__DIR__ . '/common.php');
    require(__DIR__ . '/login_valid.php');

    $getCKEditorID = admFuncVariableIsValid($_GET, 'id', 'string', array('requireValue' => true));

    // check if a file was really uploaded
    if (!file_exists($_FILES['upload']['tmp_name']) || !is_uploaded_file($_FILES['upload']['tmp_name'])) {
        throw new Exception('SYS_FILE_NOT_EXIST');
    }

    // checks if the server settings for file_upload are set to ON
    if (!PhpIniUtils::isFileUploadEnabled()) {
        throw new Exception('SYS_SERVER_NO_UPLOAD');
    }

    if (!FileSystemUtils::allowedFileExtension($_FILES['upload']['name'])) {
        throw new Exception('SYS_FILE_EXTENSION_INVALID');
    }

    // if necessary create the module folders in adm_my_files
    switch ($getCKEditorID) {
        case 'ann_description':
            $folderName = 'announcements';
            break;
        case 'dat_description':
            $folderName = 'events';
            break;
        case 'fop_text':
            $folderName = 'forum';
            break;
        case 'lnk_description':
            $folderName = 'weblinks';
            break;
        case 'msg_body':
            $folderName = 'mail';
            break;
        case 'room_description':
            $folderName = 'rooms';
            break;
        case 'usf_description':
            $folderName = 'user_fields';
            break;
        default:
            $folderName = 'plugins';
            break;
    }

    $imagesPath = ADMIDIO_PATH . FOLDER_DATA . '/' . $folderName . '/images';
    FileSystemUtils::createDirectoryIfNotExists($imagesPath);

    // create a filename with a timestamp and 16 chars secure-random string,
    // so we have a scheme for the filenames and the risk of duplicates is negligible.
    // Format: 20180131-123456_0123456789abcdef.jpg
    $fileName = FileSystemUtils::getGeneratedFilename($_FILES['upload']['name']);
    $fileNamePath = $imagesPath . '/' . $fileName;

    move_uploaded_file($_FILES['upload']['tmp_name'], $fileNamePath);

    // check if the file contains a valid image
    if (!getimagesize($fileNamePath)) {
        FileSystemUtils::deleteFileIfExists($fileNamePath);
        throw new Exception('SYS_PHOTO_FORMAT_INVALID');
    }

    echo json_encode(
        array(
            'url' => SecurityUtils::encodeUrl(
                ADMIDIO_URL . FOLDER_SYSTEM . '/show_image.php',
                array(
                    'module' => $folderName,
                    'file' => $fileName
                )
            )
        )
    );
} catch (Throwable $e) {
    handleException($e, true);
}
