<?php
/**
 ***********************************************************************************************
 * Default file upload dialog
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * module : photos          - Upload of photos for the albums
 *          documents_files - Upload of files for the documents & files folder
 * mode   : choose_files    - (Default) Show a dialog with controls to select files to upload
 *          upload_files    - Upload the selected files
 * uuid   : UUID of the current object (folder, album) where the files should be uploaded
 ***********************************************************************************************
 */

use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\UploadHandlerFile;
use Admidio\Infrastructure\Plugins\UploadHandlerPhoto;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Photos\Entity\Album;
use Admidio\UI\Component\FileUpload;
use Admidio\UI\Presenter\PagePresenter;

try {
    require_once(__DIR__ . '/common.php');
    require(__DIR__ . '/login_valid.php');

    // Initialize and check the parameters
    $getModule = admFuncVariableIsValid($_GET, 'module', 'string', array('validValues' => array('photos', 'documents_files')));
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'choose_files', 'validValues' => array('choose_files', 'upload_files')));
    $getUuid = admFuncVariableIsValid($_GET, 'uuid', 'uuid', array('requireValue' => true));

    // Initialize variables
    $destinationName = '';
    $uploadDir = '';
    $uploadUrl = '';

    // module specific checks
    if ($getModule === 'photos') {
        // check if the module is activated
        if ((int)$gSettingsManager->get('photo_module_enabled') === 0) {
            throw new Exception('SYS_MODULE_DISABLED');
        }

        // check if current user has right to upload photos
        if (!$gCurrentUser->isAdministratorPhotos()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // create photo object or read it from session
        if (isset($_SESSION['photo_album']) && (int)$_SESSION['photo_album']->getValue('pho_uuid') === $getUuid) {
            $photoAlbum =& $_SESSION['photo_album'];
        } else {
            $photoAlbum = new Album($gDb);
            $photoAlbum->readDataByUuid($getUuid);
            $_SESSION['photo_album'] = $photoAlbum;
        }

        // check if album belongs to current organization
        if ((int)$photoAlbum->getValue('pho_org_id') !== $gCurrentOrgId) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $uploadDir = ADMIDIO_PATH . FOLDER_DATA . '/photos/upload/';
        $uploadUrl = ADMIDIO_URL . FOLDER_DATA . '/photos/upload/';
        $destinationName = $photoAlbum->getValue('pho_name');
        $headline = $gL10n->get('SYS_UPLOAD_PHOTOS');

        if ($getMode === 'choose_files') {
            // delete old stuff in upload folder
            try {
                FileSystemUtils::deleteDirectoryContentIfExists(ADMIDIO_PATH . FOLDER_DATA . '/photos/upload');
            } catch (RuntimeException $exception) {
                $gLogger->error('Could not delete directory content!', array('directoryPath' => ADMIDIO_PATH . FOLDER_DATA . '/photos/upload'));
                // TODO
            }
        }
    } elseif ($getModule === 'documents_files') {
        if (!$gSettingsManager->getBool('documents_files_module_enabled')) {
            throw new Exception('SYS_MODULE_DISABLED');
        }

        $folder = new Folder($gDb);
        $folder->readDataByUuid($getUuid);

        // check if current user has right to upload files
        if (!$folder->hasUploadRight()) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // upload only possible if upload filesize > 0
        if ($gSettingsManager->getInt('documents_files_max_upload_size') === 0) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // get recordset of current folder from database
        $folder->getFolderForDownload($getUuid);
        $folderPath = $folder->getFolderPath() . 'file_upload.php/';
        $uploadDir = ADMIDIO_PATH . $folderPath;
        $uploadUrl = ADMIDIO_URL . $folderPath;
        $destinationName = $folder->getValue('fol_name');
        $headline = $gL10n->get('SYS_UPLOAD_FILES');
    }

    // check if the server allow file uploads
    if (!PhpIniUtils::isFileUploadEnabled()) {
        throw new Exception('SYS_SERVER_NO_UPLOAD');
    }

    if ($getMode === 'choose_files') {
        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create html page object
        $page = PagePresenter::withHtmlIDAndHeadline('admidio-file-upload', $headline);

        $fileUpload = new FileUpload($page, $getModule, $getUuid);
        $fileUpload->setHeaderData();

        $page->addHtml($fileUpload->getHtml($destinationName));
        $page->show();
    } elseif ($getMode === 'upload_files') {
        // upload files to temp upload folder
        if ($getModule === 'photos') {
            $uploadHandler = new UploadHandlerPhoto(
                array(
                    'upload_dir' => $uploadDir,
                    'upload_url' => $uploadUrl,
                    'image_versions' => array(),
                    'accept_file_types' => '/\.(jpe?g|png)$/i'
                ),
                true,
                array('accept_file_types' => $gL10n->get('SYS_PHOTO_FORMAT_INVALID'))
            );
        } elseif ($getModule === 'documents_files') {
            $uploadHandler = new UploadHandlerFile(array(
                'upload_dir' => $uploadDir,
                'upload_url' => $uploadUrl,
                'image_versions' => array(),
                'accept_file_types' => '/.+$/i'
            ));
        }
    }
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
