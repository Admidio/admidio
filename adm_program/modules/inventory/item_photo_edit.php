<?php
/**
 ***********************************************************************************************
 * Upload and save new item photo
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * inv_id           : id of item whose photo should be changed
 * mode - choose    : default mode to choose the photo file you want to upload
 *        save      : save new photo in item recordset
 *        dont_save : delete photo in session and show message
 *        upload    : save new photo in session and show dialog with old and new photo
 *        delete    : delete current photo in database
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getItemId = admFuncVariableIsValid($_GET, 'inv_id', 'int',    array('requireValue' => true));
$getMode   = admFuncVariableIsValid($_GET, 'mode',   'string', array('defaultValue' => 'choose', 'validValues' => array('choose', 'save', 'dont_save', 'upload', 'delete')));

// only users with the right to edit inventory could use this script
if (!$gCurrentUser->editInventory())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// in ajax mode only return simple text on error
if($getMode === 'delete')
{
    $gMessage->showHtmlTextOnly(true);
}

// checks if the server settings for file_upload are set to ON
if (ini_get('file_uploads') !== '1')
{
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
    // => EXIT
}

// read user data and show error if user doesn't exists
$gInventoryFields = new InventoryFields($gDb, $gCurrentOrganization->getValue('org_id'));
$inventory = new Inventory($gDb, $gInventoryFields, $getItemId);

// bei Ordnerspeicherung pruefen ob der Unterordner in adm_my_files mit entsprechenden Rechten existiert
if($gPreferences['profile_photo_storage'] == 1)
{
    // ggf. Ordner für Userfotos in adm_my_files anlegen
    $myFilesProfilePhotos = new MyFiles('USER_PROFILE_PHOTOS');
    if(!$myFilesProfilePhotos->checkSettings())
    {
        $gMessage->show($gL10n->get($myFilesProfilePhotos->errorText, $myFilesProfilePhotos->errorPath, '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
        // => EXIT
    }
}

if((int) $inventory->getValue('inv_id') === 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

if($getMode === 'save')
{
    /*****************************Foto speichern*************************************/

    if($gPreferences['profile_photo_storage'] == 1)
    {
        // Foto im Dateisystem speichern

        // Nachsehen ob fuer den User ein Photo gespeichert war
        $fileOld = ADMIDIO_PATH . FOLDER_DATA . '/item_photos/' . $getItemId . '_new.jpg';
        if(is_file($fileOld))
        {
            $fileNew = ADMIDIO_PATH . FOLDER_DATA . '/item_photos/' . $getItemId . '.jpg';
            if(is_file($fileNew))
            {
                unlink($fileNew);
            }

            rename($fileOld, $fileNew);
        }
    }
    else
    {
        // Foto in der Datenbank speichern

        // Nachsehen ob fuer den User ein Photo gespeichert war
        if(strlen($gCurrentSession->getValue('ses_binary')) > 0)
        {
            // Fotodaten in User-Tabelle schreiben
            $inventory->setValue('inv_photo', $gCurrentSession->getValue('ses_binary'));
            $inventory->save();

            // Foto aus Session entfernen und neues Einlesen des Users veranlassen
            $gCurrentSession->setValue('ses_binary', '');
            $gCurrentSession->save();
            $gCurrentSession->renewUserObject($getItemId);
        }
    }

    // zur Ausgangsseite zurueck
    $gNavigation->deleteLastUrl();
    admRedirect(ADMIDIO_URL . FOLDER_MODULES.'/inventory/item.php?item_id=' . $getItemId);
    // => EXIT
}
elseif($getMode === 'dont_save')
{
    /*****************************Foto nicht speichern*************************************/
    // Ordnerspeicherung
    if($gPreferences['profile_photo_storage'] == 1)
    {
        $file = ADMIDIO_PATH . FOLDER_DATA . '/item_photos/' . $getItemId . '_new.jpg';
        if(is_file($file))
        {
            unlink($file);
        }
    }
    // Datenbankspeicherung
    else
    {
        $gCurrentSession->setValue('ses_binary', '');
        $gCurrentSession->save();
    }
    // zur Ausgangsseite zurueck
    $gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/inventory/item.php?item_id='.$getItemId, 2000);
    $gMessage->show($gL10n->get('SYS_PROCESS_CANCELED'));
    // => EXIT
}
elseif($getMode === 'delete')
{
    /***************************** Foto loeschen *************************************/
    // Ordnerspeicherung, Datei löschen
    if($gPreferences['profile_photo_storage'] == 1)
    {
        unlink(ADMIDIO_PATH . FOLDER_DATA . '/item_photos/' . $getItemId . '.jpg');
    }
    // Datenbankspeicherung, Daten aus Session entfernen
    else
    {
        $inventory->setValue('inv_photo', '');
        $inventory->save();
        $gCurrentSession->renewUserObject($getItemId);
    }

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
    exit();
}

/*****************************Foto hochladen*************************************/
if($getMode === 'choose')
{
    // set headline
    if($getItemId === (int) $gCurrentUser->getValue('inv_id'))
    {
        $headline = $gL10n->get('PRO_EDIT_MY_PROFILE_PICTURE');
    }
    else
    {
        $headline = $gL10n->get('PRO_EDIT_PROFILE_PIC_FROM', $inventory->getValue('FIRST_NAME'), $inventory->getValue('LAST_NAME'));
    }

    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = new HtmlPage($headline);

    // add back link to module menu
    $profilePhotoMenu = $page->getMenu();
    $profilePhotoMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

    // show form
    $form = new HtmlForm('upload_files_form', ADMIDIO_URL.FOLDER_MODULES.'/inventory/item_photo_edit.php?mode=upload&amp;inv_id='.$getItemId, $page, array('enableFileUpload' => true));
    $form->addCustomContent($gL10n->get('PRO_CURRENT_PICTURE'), '<img class="imageFrame" src="item_photo_show.php?inv_id='.$getItemId.'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');
    $form->addFileUpload('userfile', $gL10n->get('PRO_CHOOSE_PHOTO'), array('helpTextIdLabel' => 'profile_photo_up_help'));
    $form->addSubmitButton('btn_upload', $gL10n->get('PRO_UPLOAD_PHOTO'), array('icon' => THEME_URL.'/icons/photo_upload.png', 'class' => ' col-sm-offset-3'));

    // add form to html page and show page
    $page->addHtml($form->show(false));
    $page->show();
}
elseif($getMode === 'upload')
{
    /*****************************Foto zwischenspeichern bestaetigen***********************************/

    // Dateigroesse
    if ($_FILES['userfile']['error'][0] === UPLOAD_ERR_INI_SIZE)
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_FILE_TO_LARGE', round(admFuncMaxUploadSize()/pow(1024, 2))));
        // => EXIT
    }

    // Kontrolle ob Fotos ausgewaehlt wurden
    if(!is_file($_FILES['userfile']['tmp_name'][0]))
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_NOT_CHOOSEN'));
        // => EXIT
    }

    // Dateiendung
    $image_properties = getimagesize($_FILES['userfile']['tmp_name'][0]);
    if ($image_properties['mime'] !== 'image/jpeg' && $image_properties['mime'] !== 'image/png')
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_FORMAT_INVALID'));
        // => EXIT
    }

    // Auflösungskontrolle
    $image_dimensions = $image_properties[0]*$image_properties[1];
    if($image_dimensions > admFuncProcessableImageSize())
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_RESOLUTION_TO_LARGE', round(admFuncProcessableImageSize()/1000000, 2)));
        // => EXIT
    }

    // Foto auf entsprechende Groesse anpassen
    $user_image = new Image($_FILES['userfile']['tmp_name'][0]);
    $user_image->setImageType('jpeg');
    $user_image->scale(130, 170);

    // Ordnerspeicherung
    if($gPreferences['profile_photo_storage'] == 1)
    {
        $user_image->copyToFile(null, ADMIDIO_PATH . FOLDER_DATA . '/item_photos/' . $getItemId . '_new.jpg');
    }
    // Datenbankspeicherung
    else
    {
        // Foto in PHP-Temp-Ordner übertragen
        $user_image->copyToFile(null, ($_FILES['userfile']['tmp_name'][0]));
        // Foto aus PHP-Temp-Ordner einlesen
        $user_image_data = fread(fopen($_FILES['userfile']['tmp_name'][0], 'rb'), $_FILES['userfile']['size'][0]);

        // Zwischenspeichern des neuen Fotos in der Session
        $gCurrentSession->setValue('ses_binary', $user_image_data);
        $gCurrentSession->save();
    }

    // Image-Objekt löschen
    $user_image->delete();

    if($getItemId === (int) $gCurrentUser->getValue('inv_id'))
    {
        $headline = $gL10n->get('PRO_EDIT_MY_PROFILE_PICTURE');
    }
    else
    {
        $headline = $gL10n->get('PRO_EDIT_PROFILE_PIC_FROM', $inventory->getValue('FIRST_NAME'), $inventory->getValue('LAST_NAME'));
    }

    // create html page object
    $page = new HtmlPage($headline);
    $page->addJavascript('$("#btn_cancel").click(function() {
        self.location.href=\''.ADMIDIO_URL.FOLDER_MODULES.'/inventory/item_photo_edit.php?mode=dont_save&inv_id='.$getItemId.'\';
    });', true);

    // show form
    $form = new HtmlForm('show_new_profile_picture_form', ADMIDIO_URL.FOLDER_MODULES.'/inventory/item_photo_edit.php?mode=save&amp;inv_id='.$getItemId, $page);
    $form->addCustomContent($gL10n->get('PRO_CURRENT_PICTURE'), '<img class="imageFrame" src="item_photo_show.php?inv_id='.$getItemId.'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');
    $form->addCustomContent($gL10n->get('PRO_NEW_PICTURE'), '<img class="imageFrame" src="item_photo_show.php?inv_id='.$getItemId.'&new_photo=1" alt="'.$gL10n->get('PRO_NEW_PICTURE').'" />');
    $form->addLine();
    $form->openButtonGroup();
    $form->addButton('btn_cancel', $gL10n->get('SYS_ABORT'), array('icon' => THEME_URL.'/icons/error.png'));
    $form->addSubmitButton('btn_update', $gL10n->get('SYS_APPLY'), array('icon' => THEME_URL.'/icons/database_in.png'));
    $form->closeButtonGroup();

    // add form to html page and show page
    $page->addHtml($form->show(false));
    $page->show();
}
