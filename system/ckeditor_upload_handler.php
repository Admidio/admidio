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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    SecurityUtils::validateCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    $getCKEditorID = admFuncVariableIsValid($_GET, 'id', 'string', array('requireValue' => true));

    // Authorize access to the selected module before processing files or creating
    // directories. These images are shared assets, so no existing entry is required.
    switch ($getCKEditorID) {
        case 'ann_description':
            $folderName = 'announcements';
            $uploadAllowed = $gSettingsManager->getInt('announcements_module_enabled') > 0
                && count($gCurrentUser->getAllEditableCategories('ANN')) > 0;
            break;
        case 'dat_description':
            $folderName = 'events';
            $uploadAllowed = $gSettingsManager->getInt('events_module_enabled') > 0
                && count($gCurrentUser->getAllEditableCategories('EVT')) > 0;
            break;
        case 'fop_text':
            $folderName = 'forum';
            $uploadAllowed = $gSettingsManager->getInt('forum_module_enabled') > 0
                && ($gCurrentUser->isAdministratorForum()
                    || count($gCurrentUser->getAllEditableCategories('FOT')) > 0);
            break;
        case 'lnk_description':
            $folderName = 'weblinks';
            $uploadAllowed = $gSettingsManager->getInt('weblinks_module_enabled') > 0
                && count($gCurrentUser->getAllEditableCategories('LNK')) > 0;
            break;
        case 'msg_body':
            $folderName = 'mail';
            // Private messages use a plain textarea; only HTML email uses CKEditor.
            $uploadAllowed = in_array($gSettingsManager->getInt('mail_module_enabled'), array(1, 2), true)
                && $gSettingsManager->getBool('mail_html_registered_users')
                && $gCurrentUser->hasEmail();
            break;
        case 'room_description':
            $folderName = 'rooms';
            $uploadAllowed = $gCurrentUser->isAdministrator();
            break;
        case 'usf_description':
            $folderName = 'user_fields';
            $uploadAllowed = $gCurrentUser->isAdministrator();
            break;
        default:
            $folderName = 'plugins';
            // Unknown editor IDs must not bypass the module permission checks.
            $uploadAllowed = $gCurrentUser->isAdministrator();
            break;
    }

    if (!$uploadAllowed) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    if (isset($gDisableFileUpload) && $gDisableFileUpload === true) {
        throw new Exception('File upload disabled in global config file!');
    }

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
