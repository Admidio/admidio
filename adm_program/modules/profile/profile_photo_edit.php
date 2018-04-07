<?php
/**
 ***********************************************************************************************
 * Upload and save new user photo
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * usr_id           : id of user whose photo should be changed
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
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'int',    array('requireValue' => true));
$getMode   = admFuncVariableIsValid($_GET, 'mode',   'string', array('defaultValue' => 'choose', 'validValues' => array('choose', 'save', 'dont_save', 'upload', 'delete')));

// in ajax mode only return simple text on error
if($getMode === 'delete')
{
    $gMessage->showHtmlTextOnly(true);
}

// checks if the server settings for file_upload are set to ON
if (!PhpIniUtils::isFileUploadEnabled())
{
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
    // => EXIT
}

// read user data and show error if user doesn't exists
$user = new User($gDb, $gProfileFields, $getUserId);

// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
if(!$gCurrentUser->hasRightEditProfile($user))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// bei Ordnerspeicherung pruefen ob der Unterordner in adm_my_files mit entsprechenden Rechten existiert
if((int) $gSettingsManager->get('profile_photo_storage') === 1)
{
    // ggf. Ordner für Userfotos in adm_my_files anlegen
    try
    {
        FileSystemUtils::createDirectoryIfNotExists(ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos');
    }
    catch (\RuntimeException $exception)
    {
        $gMessage->show($exception->getMessage());
        // => EXIT
    }
}

if((int) $user->getValue('usr_id') === 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

if($getMode === 'save')
{
    /*****************************Foto speichern*************************************/

    if((int) $gSettingsManager->get('profile_photo_storage') === 1)
    {
        // Foto im Dateisystem speichern

        // Nachsehen ob fuer den User ein Photo gespeichert war
        $fileOld = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $getUserId . '_new.jpg';
        if(is_file($fileOld))
        {
            $fileNew = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $getUserId . '.jpg';
            try
            {
                FileSystemUtils::deleteFileIfExists($fileNew);
                FileSystemUtils::moveFile($fileOld, $fileNew);
            }
            catch (\RuntimeException $exception)
            {
            }
        }
    }
    else
    {
        // Foto in der Datenbank speichern

        // Nachsehen ob fuer den User ein Photo gespeichert war
        if(strlen($gCurrentSession->getValue('ses_binary')) > 0)
        {
            // Fotodaten in User-Tabelle schreiben
            $user->setValue('usr_photo', $gCurrentSession->getValue('ses_binary'));
            $user->save();

            // Foto aus Session entfernen und neues Einlesen des Users veranlassen
            $gCurrentSession->setValue('ses_binary', '');
            $gCurrentSession->save();
            $gCurrentSession->renewUserObject($getUserId);
        }
    }

    // zur Ausgangsseite zurueck
    $gNavigation->deleteLastUrl();
    admRedirect(safeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/profile.php', array('user_id' => $getUserId)));
    // => EXIT
}
elseif($getMode === 'dont_save')
{
    /*****************************Foto nicht speichern*************************************/
    // Ordnerspeicherung
    if((int) $gSettingsManager->get('profile_photo_storage') === 1)
    {
        $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $getUserId . '_new.jpg';
        try
        {
            FileSystemUtils::deleteFileIfExists($file);
        }
        catch (\RuntimeException $exception)
        {
        }
    }
    // Datenbankspeicherung
    else
    {
        $gCurrentSession->setValue('ses_binary', '');
        $gCurrentSession->save();
    }
    // zur Ausgangsseite zurueck
    $gMessage->setForwardUrl(safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $getUserId)), 2000);
    $gMessage->show($gL10n->get('SYS_PROCESS_CANCELED'));
    // => EXIT
}
elseif($getMode === 'delete')
{
    /***************************** Foto loeschen *************************************/
    // Ordnerspeicherung, Datei löschen
    if((int) $gSettingsManager->get('profile_photo_storage') === 1)
    {
        try
        {
            FileSystemUtils::deleteFileIfExists(ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $getUserId . '.jpg');
        }
        catch (\RuntimeException $exception)
        {
        }
    }
    // Datenbankspeicherung, Daten aus Session entfernen
    else
    {
        $user->setValue('usr_photo', '');
        $user->save();
        $gCurrentSession->renewUserObject($getUserId);
    }

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
    exit();
}

/*****************************Foto hochladen*************************************/
if($getMode === 'choose')
{
    // set headline
    if($getUserId === (int) $gCurrentUser->getValue('usr_id'))
    {
        $headline = $gL10n->get('PRO_EDIT_MY_PROFILE_PICTURE');
    }
    else
    {
        $headline = $gL10n->get('PRO_EDIT_PROFILE_PIC_FROM', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));
    }

    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = new HtmlPage($headline);

    // add back link to module menu
    $profilePhotoMenu = $page->getMenu();
    $profilePhotoMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

    // show form
    $form = new HtmlForm('upload_files_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_edit.php', array('mode' => 'upload', 'usr_id' => $getUserId)), $page, array('enableFileUpload' => true));
    $form->addCustomContent($gL10n->get('PRO_CURRENT_PICTURE'), '<img class="imageFrame" src="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_show.php', array('usr_id' => $getUserId)).'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');
    $form->addFileUpload(
        'foto_upload_file', $gL10n->get('PRO_CHOOSE_PHOTO'),
        array('allowedMimeTypes' => array('image/jpeg', 'image/png'), 'helpTextIdLabel' => 'profile_photo_up_help')
    );
    $form->addSubmitButton(
        'btn_upload', $gL10n->get('PRO_UPLOAD_PHOTO'),
        array('icon' => THEME_URL.'/icons/photo_upload.png', 'class' => ' col-sm-offset-3')
    );

    // add form to html page and show page
    $page->addHtml($form->show());
    $page->show();
}
elseif($getMode === 'upload')
{
    /*****************************Foto zwischenspeichern bestaetigen***********************************/

    // File size
    if ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE)
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_FILE_TO_LARGE', array(round(PhpIniUtils::getUploadMaxSize()/pow(1024, 2)))));
        // => EXIT
    }

    // Kontrolle ob Fotos ausgewaehlt wurden
    if(!is_file($_FILES['userfile']['tmp_name'][0]))
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_NOT_CHOSEN'));
        // => EXIT
    }

    // File ending
    $imageProperties = getimagesize($_FILES['userfile']['tmp_name'][0]);
    if ($imageProperties === false || !in_array($imageProperties['mime'], array('image/jpeg', 'image/png'), true))
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_FORMAT_INVALID'));
        // => EXIT
    }

    // Auflösungskontrolle
    $imageDimensions = $imageProperties[0] * $imageProperties[1];
    if($imageDimensions > admFuncProcessableImageSize())
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_RESOLUTION_TO_LARGE', array(round(admFuncProcessableImageSize()/1000000, 2))));
        // => EXIT
    }

    // Foto auf entsprechende Groesse anpassen
    $userImage = new Image($_FILES['userfile']['tmp_name'][0]);
    $userImage->setImageType('jpeg');
    $userImage->scale(130, 170);

    // Ordnerspeicherung
    if((int) $gSettingsManager->get('profile_photo_storage') === 1)
    {
        $userImage->copyToFile(null, ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $getUserId . '_new.jpg');
    }
    // Datenbankspeicherung
    else
    {
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

    if($getUserId === (int) $gCurrentUser->getValue('usr_id'))
    {
        $headline = $gL10n->get('PRO_EDIT_MY_PROFILE_PICTURE');
    }
    else
    {
        $headline = $gL10n->get('PRO_EDIT_PROFILE_PIC_FROM', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));
    }

    // create html page object
    $page = new HtmlPage($headline);
    $page->addJavascript('$("#btn_cancel").click(function() {
        self.location.href=\''.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_edit.php', array('mode' => 'dont_save', 'usr_id' => $getUserId)).'\';
    });', true);

    // show form
    $form = new HtmlForm('show_new_profile_picture_form', safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_edit.php', array('mode' => 'save', 'usr_id' => $getUserId)), $page);
    $form->addCustomContent($gL10n->get('PRO_CURRENT_PICTURE'), '<img class="imageFrame" src="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_show.php', array('usr_id' => $getUserId)).'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');
    $form->addCustomContent($gL10n->get('PRO_NEW_PICTURE'), '<img class="imageFrame" src="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_show.php', array('usr_id' => $getUserId, 'new_photo' => 1)).'" alt="'.$gL10n->get('PRO_NEW_PICTURE').'" />');
    $form->addLine();
    $form->addSubmitButton('btn_update', $gL10n->get('SYS_APPLY'), array('icon' => THEME_URL.'/icons/database_in.png'));
    $form->addButton('btn_cancel', $gL10n->get('SYS_ABORT'), array('icon' => THEME_URL.'/icons/error.png'));

    // add form to html page and show page
    $page->addHtml($form->show());
    $page->show();
}
