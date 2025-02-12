<?php
/**
 ***********************************************************************************************
 * Upload and save new user photo
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * user_uuid        : Uuid of user whose photo should be changed
 * mode - choose    : default mode to choose the photo file you want to upload
 *        save      : save new photo in user recordset
 *        dont_save : delete photo in session and show message
 *        upload    : save new photo in session and show dialog with old and new photo
 *        review    : dialog shows current and new profile photo
 *        delete    : delete current photo in database
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Image;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\PhpIniUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\SystemInfoUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('requireValue' => true));
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'choose', 'validValues' => array('choose', 'save', 'dont_save', 'upload', 'review', 'delete')));

    // checks if the server settings for file_upload are set to ON
    if (!PhpIniUtils::isFileUploadEnabled()) {
        throw new Exception('SYS_SERVER_NO_UPLOAD');
    }

    // read user data and show error if user doesn't exists
    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

    // checks whether the user has the necessary rights to change the corresponding profile
    if (!$gCurrentUser->hasRightEditProfile($user)) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    // when saving folders, check whether the subfolder in adm_my_files exists with the corresponding rights
    if ((int)$gSettingsManager->get('profile_photo_storage') === 1) {
        // Create folder for user photos in adm_my_files if necessary
        FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos');
    }

    if ((int)$user->getValue('usr_id') === 0) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    if ($getMode === 'save') {
        // Save photo

        if ((int)$gSettingsManager->get('profile_photo_storage') === 1) {
            // Save photo in the file system

            // Check if a photo was saved for the user
            $fileOld = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '_new.jpg';
            if (is_file($fileOld)) {
                $fileNew = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '.jpg';
                try {
                    FileSystemUtils::deleteFileIfExists($fileNew);

                    try {
                        FileSystemUtils::moveFile($fileOld, $fileNew);
                    } catch (\RuntimeException $exception) {
                        $gLogger->error('Could not move file!', array('from' => $fileOld, 'to' => $fileNew));
                        // TODO
                    }
                } catch (\RuntimeException $exception) {
                    $gLogger->error('Could not delete file!', array('filePath' => $fileNew));
                    // TODO
                }
            }
        } else {
            // Save photo in the database

            // Check if a photo was saved for the user
            if (strlen($gCurrentSession->getValue('ses_binary')) > 0) {
                $gDb->startTransaction();
                // Fotodaten in User-Tabelle schreiben
                $user->setValue('usr_photo', $gCurrentSession->getValue('ses_binary'));
                $user->save();

                // Foto aus Session entfernen und neues Einlesen des Users veranlassen
                $gCurrentSession->setValue('ses_binary', '');
                $gCurrentSession->save();
                $gCurrentSession->reload($user->getValue('usr_id'));
                $gDb->endTransaction();
            }
        }

        // back to the home page
        $gNavigation->deleteLastUrl();
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $getUserUuid)));
        // => EXIT
    } elseif ($getMode === 'dont_save') {
        // Do not save photo
        if ((int)$gSettingsManager->get('profile_photo_storage') === 1) {
            // Folder storage
            $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '_new.jpg';
            try {
                FileSystemUtils::deleteFileIfExists($file);
            } catch (\RuntimeException $exception) {
                $gLogger->error('Could not delete file!', array('filePath' => $file));
                // TODO
            }
        } else {
            // Database storage
            $gCurrentSession->setValue('ses_binary', '');
            $gCurrentSession->save();
        }
        // zur Ausgangsseite zurueck
        $gMessage->setForwardUrl(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_id' => $getUserUuid)), 2000);
        $gMessage->show($gL10n->get('SYS_PROCESS_CANCELED'));
        // => EXIT
    } elseif ($getMode === 'delete') {
        // Delete photo

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        if ((int)$gSettingsManager->get('profile_photo_storage') === 1) {
            // Folder storage, delete file
            $filePath = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '.jpg';
            try {
                FileSystemUtils::deleteFileIfExists($filePath);
            } catch (\RuntimeException $exception) {
                $gLogger->error('Could not delete file!', array('filePath' => $filePath));
                // TODO
            }
        } else {
            // Database storage, remove data from session
            $user->setValue('usr_photo', '');
            $user->save();
            $gCurrentSession->reload($user->getValue('usr_id'));
        }

        echo 'done';
        exit();
    }

    // Upload photo
    if ($getMode === 'choose') {
        // set headline
        if ((int)$user->getValue('usr_id') === $gCurrentUserId) {
            $headline = $gL10n->get('SYS_EDIT_MY_PROFILE_PICTURE');
        } else {
            $headline = $gL10n->get('SYS_EDIT_PROFILE_PIC_FROM', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));
        }

        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create html page object
        $page = PagePresenter::withHtmlIDAndHeadline('admidio-profile-photo-edit', $headline);

        // show form
        $form = new FormPresenter(
            'adm_upload_photo_form',
            'modules/profile.new-photo.upload.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_edit.php', array('mode' => 'upload', 'user_uuid' => $getUserUuid)),
            $page,
            array('enableFileUpload' => true)
        );
        $form->addCustomContent(
            'admCurrentProfilePhoto',
            $gL10n->get('SYS_CURRENT_PROFILE_PICTURE'),
            '<img class="imageFrame" src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php', array('user_uuid' => $getUserUuid, 'timestamp' => $user->getValue('usr_timestamp_change', 'Y-m-d-H-i-s'))) . '" alt="' . $gL10n->get('SYS_CURRENT_PROFILE_PICTURE') . '" />'
        );
        $form->addFileUpload(
            'admPhotoUploadFile',
            $gL10n->get('SYS_SELECT_PHOTO'),
            array(
                'property' => FormPresenter::FIELD_REQUIRED,
                'allowedMimeTypes' => array('image/jpeg', 'image/png'),
                'helpTextId' => array('SYS_PROFILE_PICTURE_RESTRICTIONS', array(round(SystemInfoUtils::getProcessableImageSize() / 1000000, 2), round(PhpIniUtils::getUploadMaxSize() / 1024 ** 2, 2)))
            )
        );
        $form->addSubmitButton(
            'admButtonUpload',
            $gL10n->get('SYS_UPLOAD_PROFILE_PICTURE'),
            array('icon' => 'bi-upload', 'class' => 'offset-sm-3')
        );

        // add form to html page and show page
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
        $page->show();
    } elseif ($getMode === 'upload') {
        // Confirm cache photo

        // check form field input and sanitized it from malicious content
        $profilePhotoUploadForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $profilePhotoUploadForm->validate($_POST);

        // File size
        if ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE) {
            throw new Exception('SYS_PHOTO_FILE_TO_LARGE', array(round(PhpIniUtils::getUploadMaxSize() / 1024 ** 2)));
        }

        // check if a file was really uploaded
        if (!file_exists($_FILES['userfile']['tmp_name'][0]) || !is_uploaded_file($_FILES['userfile']['tmp_name'][0])) {
            throw new Exception('SYS_NO_PICTURE_SELECTED');
        }

        // File ending
        $imageProperties = getimagesize($_FILES['userfile']['tmp_name'][0]);
        if ($imageProperties === false || !in_array($imageProperties['mime'], array('image/jpeg', 'image/png'), true)) {
            throw new Exception('SYS_PHOTO_FORMAT_INVALID');
        }

        // Resolution control
        $imageDimensions = $imageProperties[0] * $imageProperties[1];
        if ($imageDimensions > SystemInfoUtils::getProcessableImageSize()) {
            throw new Exception('SYS_PHOTO_RESOLUTION_TO_LARGE', array(round(SystemInfoUtils::getProcessableImageSize() / 1000000, 2)));
        }

        // Adjust photo to appropriate size
        $userImage = new Image($_FILES['userfile']['tmp_name'][0]);
        $userImage->setImageType('jpeg');
        $userImage->scale(130, 170);

        if ((int)$gSettingsManager->get('profile_photo_storage') === 1) {
            // Folder storage
            $userImage->copyToFile(null, ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '_new.jpg');
        } else {
            // Database storage
            $userImage->copyToFile(null, $_FILES['userfile']['tmp_name'][0]);
            $userImageData = fread(fopen($_FILES['userfile']['tmp_name'][0], 'rb'), $_FILES['userfile']['size'][0]);

            $gCurrentSession->setValue('ses_binary', $userImageData);
            $gCurrentSession->save();
        }

        // delete image object
        $userImage->delete();

        echo json_encode(array(
            'status' => 'success',
            'url' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_edit.php', array('mode' => 'review', 'user_uuid' => $getUserUuid))
        ));
        exit();
    } elseif ($getMode === 'review') {
        // show dialog with current and new profile photo for review

        if ((int)$user->getValue('usr_id') === $gCurrentUserId) {
            $headline = $gL10n->get('SYS_EDIT_MY_PROFILE_PICTURE');
        } else {
            $headline = $gL10n->get('SYS_EDIT_PROFILE_PIC_FROM', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));
        }

        // create html page object
        $page = PagePresenter::withHtmlIDAndHeadline('admidio-profile-photo-edit', $headline);
        $page->addTemplateFile('modules/profile.new-photo.tpl');
        $page->assignSmartyVariable('urlCurrentProfilePhoto', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php', array('user_uuid' => $getUserUuid, 'timestamp' => $user->getValue('usr_timestamp_change', 'Y-m-d-H-i-s'))));
        $page->assignSmartyVariable('urlNewProfilePhoto', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php', array('user_uuid' => $getUserUuid, 'new_photo' => 1)));
        $page->assignSmartyVariable('urlNextPage', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_edit.php', array('mode' => 'save', 'user_uuid' => $getUserUuid)));
        $page->show();
    }
} catch (Exception|Exception|RuntimeException $e) {
    if (in_array($getMode, array('upload', 'delete'))) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    } else {
        $gMessage->show($e->getMessage());
    }
}
