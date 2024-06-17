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
 *        delete    : delete current photo in database
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

try {
    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('requireValue' => true));
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'choose', 'validValues' => array('choose', 'save', 'dont_save', 'upload', 'delete')));

    if (in_array($getMode, array('delete', 'save', 'upload'))) {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    }

    // checks if the server settings for file_upload are set to ON
    if (!PhpIniUtils::isFileUploadEnabled()) {
        throw new AdmException('SYS_SERVER_NO_UPLOAD');
    }

    // read user data and show error if user doesn't exists
    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

    // checks whether the user has the necessary rights to change the corresponding profile
    if (!$gCurrentUser->hasRightEditProfile($user)) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    // when saving folders, check whether the subfolder in adm_my_files exists with the corresponding rights
    if ((int)$gSettingsManager->get('profile_photo_storage') === 1) {
        // Create folder for user photos in adm_my_files if necessary
        FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos');
    }

    if ((int)$user->getValue('usr_id') === 0) {
        throw new AdmException('SYS_INVALID_PAGE_VIEW');
    }

    if ($getMode === 'save') {
        // Save photo

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

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
        $page = new HtmlPage('admidio-profile-photo-edit', $headline);

        // show form
        $form = new HtmlForm('upload_files_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_edit.php', array('mode' => 'upload', 'user_uuid' => $getUserUuid)), $page, array('enableFileUpload' => true));
        $form->addCustomContent($gL10n->get('SYS_CURRENT_PROFILE_PICTURE'), '<img class="imageFrame" src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php', array('user_uuid' => $getUserUuid)) . '" alt="' . $gL10n->get('SYS_CURRENT_PROFILE_PICTURE') . '" />');
        $form->addFileUpload(
            'foto_upload_file',
            $gL10n->get('SYS_SELECT_PHOTO'),
            array(
                'allowedMimeTypes' => array('image/jpeg', 'image/png'),
                'helpTextId' => array('SYS_PROFILE_PICTURE_RESTRICTIONS', array(round(SystemInfoUtils::getProcessableImageSize() / 1000000, 2), round(PhpIniUtils::getUploadMaxSize() / 1024 ** 2, 2)))
            )
        );
        $form->addSubmitButton(
            'btn_upload',
            $gL10n->get('SYS_UPLOAD_PROFILE_PICTURE'),
            array('icon' => 'bi-upload')
        );

        // add form to html page and show page
        $page->addHtml($form->show());
        $page->show();
    } elseif ($getMode === 'upload') {
        // Confirm cache photo

        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

        // File size
        if ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE) {
            throw new AdmException('SYS_PHOTO_FILE_TO_LARGE', array(round(PhpIniUtils::getUploadMaxSize() / 1024 ** 2)));
        }

        // check if a file was really uploaded
        if (!file_exists($_FILES['userfile']['tmp_name'][0]) || !is_uploaded_file($_FILES['userfile']['tmp_name'][0])) {
            throw new AdmException('SYS_NO_PICTURE_SELECTED');
        }

        // File ending
        $imageProperties = getimagesize($_FILES['userfile']['tmp_name'][0]);
        if ($imageProperties === false || !in_array($imageProperties['mime'], array('image/jpeg', 'image/png'), true)) {
            throw new AdmException('SYS_PHOTO_FORMAT_INVALID');
        }

        // Auflösungskontrolle
        $imageDimensions = $imageProperties[0] * $imageProperties[1];
        if ($imageDimensions > SystemInfoUtils::getProcessableImageSize()) {
            throw new AdmException('SYS_PHOTO_RESOLUTION_TO_LARGE', array(round(SystemInfoUtils::getProcessableImageSize() / 1000000, 2)));
        }

        // Foto auf entsprechende Groesse anpassen
        $userImage = new Image($_FILES['userfile']['tmp_name'][0]);
        $userImage->setImageType('jpeg');
        $userImage->scale(130, 170);

        if ((int)$gSettingsManager->get('profile_photo_storage') === 1) {
            // Folder storage
            $userImage->copyToFile(null, ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '_new.jpg');
        } else {
            // Database storage
            // Foto in PHP-Temp-Ordner übertragen
            $userImage->copyToFile(null, $_FILES['userfile']['tmp_name'][0]);
            // Foto aus PHP-Temp-Ordner einlesen
            $userImageData = fread(fopen($_FILES['userfile']['tmp_name'][0], 'rb'), $_FILES['userfile']['size'][0]);

            // Zwischenspeichern des neuen Fotos in der Session
            $gCurrentSession->setValue('ses_binary', $userImageData);
            $gCurrentSession->save();
        }

        // Image-Objekt löschen
        $userImage->delete();

        if ((int)$user->getValue('usr_id') === $gCurrentUserId) {
            $headline = $gL10n->get('SYS_EDIT_MY_PROFILE_PICTURE');
        } else {
            $headline = $gL10n->get('SYS_EDIT_PROFILE_PIC_FROM', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));
        }

        // create html page object
        $page = new HtmlPage('admidio-profile-photo-edit', $headline);
        $page->addJavascript('$("#btn_cancel").click(function() {
            self.location.href=\'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_edit.php', array('mode' => 'dont_save', 'user_uuid' => $getUserUuid)) . '\';
         });', true);

        // show form
        $form = new HtmlForm('show_new_profile_picture_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_edit.php', array('mode' => 'save', 'user_uuid' => $getUserUuid)), $page);
        $form->addCustomContent($gL10n->get('SYS_CURRENT_PROFILE_PICTURE'), '<img class="imageFrame" src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php', array('user_uuid' => $getUserUuid)) . '" alt="' . $gL10n->get('SYS_CURRENT_PROFILE_PICTURE') . '" />');
        $form->addCustomContent($gL10n->get('SYS_NEW_PROFILE_PICTURE'), '<img class="imageFrame" src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php', array('user_uuid' => $getUserUuid, 'new_photo' => 1)) . '" alt="' . $gL10n->get('SYS_NEW_PROFILE_PICTURE') . '" />');
        $form->addLine();
        $form->addSubmitButton('btn_update', $gL10n->get('SYS_APPLY'), array('icon' => 'bi-upload'));

        // add form to html page and show page
        $page->addHtml($form->show());
        $page->show();
    }
} catch (AdmException|Exception|\Smarty\Exception|RuntimeException $e) {
    if ($getMode === 'delete') {
        echo $e->getMessage();
    } else {
        $gMessage->show($e->getMessage());
    }
}
