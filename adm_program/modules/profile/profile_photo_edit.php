<?php
/**
 ***********************************************************************************************
 * Upload and save new user photo
 *
 * @copyright 2004-2023 The Admidio Team
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

// Initialize and check the parameters
$getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'string', array('requireValue' => true));
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'choose', 'validValues' => array('choose', 'save', 'dont_save', 'upload', 'delete')));

// in ajax mode only return simple text on error
if ($getMode === 'delete') {
    $gMessage->showHtmlTextOnly(true);
}

if (in_array($getMode, array('delete', 'save', 'upload'))) {
    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        if ($getMode === 'delete') {
            $exception->showText();
        } else {
            $exception->showHtml();
        }
        // => EXIT
    }
}

// checks if the server settings for file_upload are set to ON
if (!PhpIniUtils::isFileUploadEnabled()) {
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
    // => EXIT
}

// read user data and show error if user doesn't exists
$user = new User($gDb, $gProfileFields);
$user->readDataByUuid($getUserUuid);

// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
if (!$gCurrentUser->hasRightEditProfile($user)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// bei Ordnerspeicherung pruefen ob der Unterordner in adm_my_files mit entsprechenden Rechten existiert
if ((int) $gSettingsManager->get('profile_photo_storage') === 1) {
    // ggf. Ordner für Userfotos in adm_my_files anlegen
    try {
        FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos');
    } catch (\RuntimeException $exception) {
        $gMessage->show($exception->getMessage());
        // => EXIT
    }
}

if ((int) $user->getValue('usr_id') === 0) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

if ($getMode === 'save') {
    // Foto speichern

    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        $exception->showHtml();
        // => EXIT
    }

    if ((int) $gSettingsManager->get('profile_photo_storage') === 1) {
        // Foto im Dateisystem speichern

        // Nachsehen ob fuer den User ein Photo gespeichert war
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
        // Foto in der Datenbank speichern

        // Nachsehen ob fuer den User ein Photo gespeichert war
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

    // zur Ausgangsseite zurueck
    $gNavigation->deleteLastUrl();
    admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $getUserUuid)));
// => EXIT
} elseif ($getMode === 'dont_save') {
    // Foto nicht speichern
    // Ordnerspeicherung
    if ((int) $gSettingsManager->get('profile_photo_storage') === 1) {
        $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '_new.jpg';
        try {
            FileSystemUtils::deleteFileIfExists($file);
        } catch (\RuntimeException $exception) {
            $gLogger->error('Could not delete file!', array('filePath' => $file));
            // TODO
        }
    }
    // Datenbankspeicherung
    else {
        $gCurrentSession->setValue('ses_binary', '');
        $gCurrentSession->save();
    }
    // zur Ausgangsseite zurueck
    $gMessage->setForwardUrl(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $getUserUuid)), 2000);
    $gMessage->show($gL10n->get('SYS_PROCESS_CANCELED'));
// => EXIT
} elseif ($getMode === 'delete') {
    // Foto loeschen
    // Ordnerspeicherung, Datei löschen
    if ((int) $gSettingsManager->get('profile_photo_storage') === 1) {
        $filePath = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '.jpg';
        try {
            FileSystemUtils::deleteFileIfExists($filePath);
        } catch (\RuntimeException $exception) {
            $gLogger->error('Could not delete file!', array('filePath' => $filePath));
            // TODO
        }
    }
    // Datenbankspeicherung, Daten aus Session entfernen
    else {
        $user->setValue('usr_photo', '');
        $user->save();
        $gCurrentSession->reload($user->getValue('usr_id'));
    }

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
    exit();
}

// Foto hochladen
if ($getMode === 'choose') {
    // set headline
    if ((int) $user->getValue('usr_id') === $gCurrentUserId) {
        $headline = $gL10n->get('PRO_EDIT_MY_PROFILE_PICTURE');
    } else {
        $headline = $gL10n->get('PRO_EDIT_PROFILE_PIC_FROM', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));
    }

    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = new HtmlPage('admidio-profile-photo-edit', $headline);

    // show form
    $form = new HtmlForm('upload_files_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_edit.php', array('mode' => 'upload', 'user_uuid' => $getUserUuid)), $page, array('enableFileUpload' => true));
    $form->addCustomContent($gL10n->get('PRO_CURRENT_PICTURE'), '<img class="imageFrame" src="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_show.php', array('user_uuid' => $getUserUuid)).'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');
    $form->addFileUpload(
        'foto_upload_file',
        $gL10n->get('PRO_CHOOSE_PHOTO'),
        array('allowedMimeTypes' => array('image/jpeg', 'image/png'), 'helpTextIdLabel' => 'profile_photo_up_help')
    );
    $form->addSubmitButton(
        'btn_upload',
        $gL10n->get('PRO_UPLOAD_PHOTO'),
        array('icon' => 'fa-upload', 'class' => ' offset-sm-3')
    );

    // add form to html page and show page
    $page->addHtml($form->show());
    $page->show();
} elseif ($getMode === 'upload') {
    // Foto zwischenspeichern bestaetigen

    try {
        // check the CSRF token of the form against the session token
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
    } catch (AdmException $exception) {
        $exception->showHtml();
        // => EXIT
    }

    // File size
    if ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE) {
        $gMessage->show($gL10n->get('PRO_PHOTO_FILE_TO_LARGE', array(round(PhpIniUtils::getUploadMaxSize()/1024** 2))));
        // => EXIT
    }

    // check if a file was really uploaded
    if (!file_exists($_FILES['userfile']['tmp_name'][0]) || !is_uploaded_file($_FILES['userfile']['tmp_name'][0])) {
        $gMessage->show($gL10n->get('PRO_PHOTO_NOT_CHOSEN'));
        // => EXIT
    }

    // File ending
    $imageProperties = getimagesize($_FILES['userfile']['tmp_name'][0]);
    if ($imageProperties === false || !in_array($imageProperties['mime'], array('image/jpeg', 'image/png'), true)) {
        $gMessage->show($gL10n->get('PRO_PHOTO_FORMAT_INVALID'));
        // => EXIT
    }

    // Auflösungskontrolle
    $imageDimensions = $imageProperties[0] * $imageProperties[1];
    if ($imageDimensions > admFuncProcessableImageSize()) {
        $gMessage->show($gL10n->get('PRO_PHOTO_RESOLUTION_TO_LARGE', array(round(admFuncProcessableImageSize()/1000000, 2))));
        // => EXIT
    }

    // Foto auf entsprechende Groesse anpassen
    $userImage = new Image($_FILES['userfile']['tmp_name'][0]);
    $userImage->setImageType('jpeg');
    $userImage->scale(130, 170);

    // Ordnerspeicherung
    if ((int) $gSettingsManager->get('profile_photo_storage') === 1) {
        $userImage->copyToFile(null, ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '_new.jpg');
    }
    // Datenbankspeicherung
    else {
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

    if ((int) $user->getValue('usr_id') === $gCurrentUserId) {
        $headline = $gL10n->get('PRO_EDIT_MY_PROFILE_PICTURE');
    } else {
        $headline = $gL10n->get('PRO_EDIT_PROFILE_PIC_FROM', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));
    }

    // create html page object
    $page = new HtmlPage('admidio-profile-photo-edit', $headline);
    $page->addJavascript('$("#btn_cancel").click(function() {
        self.location.href=\''.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_edit.php', array('mode' => 'dont_save', 'user_uuid' => $getUserUuid)).'\';
    });', true);

    // show form
    $form = new HtmlForm('show_new_profile_picture_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_edit.php', array('mode' => 'save', 'user_uuid' => $getUserUuid)), $page);
    $form->addCustomContent($gL10n->get('PRO_CURRENT_PICTURE'), '<img class="imageFrame" src="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_show.php', array('user_uuid' => $getUserUuid)).'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');
    $form->addCustomContent($gL10n->get('PRO_NEW_PICTURE'), '<img class="imageFrame" src="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_show.php', array('user_uuid' => $getUserUuid, 'new_photo' => 1)).'" alt="'.$gL10n->get('PRO_NEW_PICTURE').'" />');
    $form->addLine();
    $form->addSubmitButton('btn_update', $gL10n->get('SYS_APPLY'), array('icon' => 'fa-upload', 'class' => ' offset-sm-3'));

    // add form to html page and show page
    $page->addHtml($form->show());
    $page->show();
}
