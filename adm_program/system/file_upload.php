<?php
/**
 ***********************************************************************************************
 * Default file upload dialog
 *
 * @copyright 2004-2023 The Admidio Team
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
require_once(__DIR__ . '/common.php');
require(__DIR__ . '/login_valid.php');

// Initialize and check the parameters
$getModule = admFuncVariableIsValid($_GET, 'module', 'string', array('validValues' => array('photos', 'documents_files')));
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'choose_files', 'validValues' => array('choose_files', 'upload_files')));
$getUuid   = admFuncVariableIsValid($_GET, 'uuid', 'string', array('requireValue' => true));

// Initialize variables
$destinationName         = '';
$uploadDir               = '';
$uploadUrl               = '';

// module specific checks
if ($getModule === 'photos') {
    // check if the module is activated
    if ((int) $gSettingsManager->get('enable_photo_module') === 0) {
        $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
        // => EXIT
    }

    // check if current user has right to upload photos
    if (!$gCurrentUser->editPhotoRight()) {
        $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
        // => EXIT
    }

    // create photo object or read it from session
    if (isset($_SESSION['photo_album']) && (int) $_SESSION['photo_album']->getValue('pho_uuid') === $getUuid) {
        $photoAlbum =& $_SESSION['photo_album'];
    } else {
        $photoAlbum = new TablePhotos($gDb);
        $photoAlbum->readDataByUuid($getUuid);
        $_SESSION['photo_album'] = $photoAlbum;
    }

    // check if album belongs to current organization
    if ((int) $photoAlbum->getValue('pho_org_id') !== $gCurrentOrgId) {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    $uploadDir = ADMIDIO_PATH . FOLDER_DATA . '/photos/upload/';
    $uploadUrl = ADMIDIO_URL . FOLDER_DATA . '/photos/upload/';
    $destinationName = $photoAlbum->getValue('pho_name');
    $headline = $gL10n->get('PHO_UPLOAD_PHOTOS');

    if($getMode === 'choose_files') {
        // delete old stuff in upload folder
        try {
            FileSystemUtils::deleteDirectoryContentIfExists(ADMIDIO_PATH . FOLDER_DATA . '/photos/upload');
        } catch (\RuntimeException $exception) {
            $gLogger->error('Could not delete directory content!', array('directoryPath' => ADMIDIO_PATH . FOLDER_DATA . '/photos/upload'));
            // TODO
        }
    }
} elseif ($getModule === 'documents_files') {
    if (!$gSettingsManager->getBool('documents_files_enable_module')) {
        $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
        // => EXIT
    }

    $folder = new TableFolder($gDb);
    $folder->readDataByUuid($getUuid);

    // check if current user has right to upload files
    if (!$folder->hasUploadRight()) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // upload only possible if upload filesize > 0
    if ($gSettingsManager->getInt('max_file_upload_size') === 0) {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
        // => EXIT
    }

    try {
        // get recordset of current folder from database
        $folder->getFolderForDownload($getUuid);
        $folderPath = $folder->getFolderPath() . '/';
        $uploadDir = ADMIDIO_PATH . $folderPath;
        $uploadUrl = ADMIDIO_URL . $folderPath;
        $destinationName = $folder->getValue('fol_name');
        $headline = $gL10n->get('SYS_UPLOAD_FILES');
    } catch (AdmException $e) {
        $e->showHtml();
        // => EXIT
    }
}

// check if the server allow file uploads
if (!PhpIniUtils::isFileUploadEnabled()) {
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
    // => EXIT
}

if ($getMode === 'choose_files') {
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = new HtmlPage('admidio-file-upload', $headline);

    $fileUpload = new FileUpload($page, $getModule, $getUuid);
    $fileUpload->setHeaderData();

    $page->addHtml($fileUpload->getHtml($destinationName));
    $page->show();
} elseif ($getMode === 'upload_files') {
    // upload files to temp upload folder
    if ($getModule === 'photos') {
        $uploadHandler = new UploadHandlerPhoto(
            array(
                'upload_dir'        => $uploadDir,
                'upload_url'        => $uploadUrl,
                'image_versions'    => array(),
                'accept_file_types' => '/\.(jpe?g|png)$/i'
            ),
            true,
            array('accept_file_types' => $gL10n->get('PHO_PHOTO_FORMAT_INVALID'))
        );
    } elseif ($getModule === 'documents_files') {
        $uploadHandler = new UploadHandlerDownload(array(
            'upload_dir'     => $uploadDir,
            'upload_url'     => $uploadUrl,
            'image_versions' => array(),
            'accept_file_types' => '/.+$/i'
        ));
    }
}
