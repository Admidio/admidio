<?php
/******************************************************************************
 * Upload and save new user photo
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id           : id of user whose photo should be changed
 * mode - choose    : default mode to choose the photo file you want to upload
 *        save      : save new photo in user recordset
 *        dont_save : delete photo in session and show message
 *        upload    : save new photo in session and show dialog with old and new photo
 *        delete    : delete current photo in database
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', null, true);
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'string', 'choose', false, array('choose', 'save', 'dont_save', 'upload', 'delete'));

// in ajax mode only return simple text on error
if($getMode == 'delete')
{
    $gMessage->showHtmlTextOnly(true);
}

// checks if the server settings for file_upload are set to ON
if (ini_get('file_uploads') != '1')
{
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
}

// read user data and show error if user doesn't exists
$user = new User($gDb, $gProfileFields, $getUserId);

// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
if($gCurrentUser->editProfile($user) == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// bei Ordnerspeicherung pruefen ob der Unterordner in adm_my_files mit entsprechenden Rechten existiert
if($gPreferences['profile_photo_storage'] == 1)
{
    // ggf. Ordner für Userfotos in adm_my_files anlegen
    $myFilesProfilePhotos = new MyFiles('USER_PROFILE_PHOTOS');
    if($myFilesProfilePhotos->checkSettings() == false)
    {
        $gMessage->show($gL10n->get($myFilesProfilePhotos->errorText, $myFilesProfilePhotos->errorPath, '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'));
    }
}

if($user->getValue('usr_id') == 0)
{
	$gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

if($getMode == 'save')
{
    /*****************************Foto speichern*************************************/
    
    if($gPreferences['profile_photo_storage'] == 1)
    {
        // Foto im Dateisystem speichern      

        //Nachsehen ob fuer den User ein Photo gespeichert war
        if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'_new.jpg'))
        {
            if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'.jpg'))
            {
                unlink(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'.jpg');
            }
            
            rename(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'_new.jpg', SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'.jpg');
        }
    }
    else
    {
        // Foto in der Datenbank speichern

        //Nachsehen ob fuer den User ein Photo gespeichert war
        if(strlen($gCurrentSession->getValue('ses_binary')) > 0)
        {
            //Fotodaten in User-Tabelle schreiben
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
    header('Location: '.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$getUserId);
    exit();
}    
elseif($getMode == 'dont_save')
{
    /*****************************Foto nicht speichern*************************************/
    //Ordnerspeicherung
    if($gPreferences['profile_photo_storage'] == 1)
    {
        if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'_new.jpg'))
        {
            unlink(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'_new.jpg');
        }
    }
    //Datenbankspeicherung
    else
    {
        $gCurrentSession->setValue('ses_binary', '');
        $gCurrentSession->save();
    }
    // zur Ausgangsseite zurueck
    $gMessage->setForwardUrl($g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$getUserId, 2000);
    $gMessage->show($gL10n->get('SYS_PROCESS_CANCELED'));
}
elseif($getMode == 'delete')
{
    /***************************** Foto loeschen *************************************/
    //Ordnerspeicherung, Datei löschen
    if($gPreferences['profile_photo_storage'] == 1)
    {
        unlink(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'.jpg');
    }
    //Datenbankspeicherung, Daten aus Session entfernen
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
if($getMode == 'choose')
{
    // set headline
    if($getUserId == $gCurrentUser->getValue('usr_id'))
    {
        $headline = $gL10n->get('PRO_EDIT_MY_PROFILE_PICTURE');
    }
    else
    {
        $headline = $gL10n->get('PRO_EDIT_PROFILE_PIC_FROM', $user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME'));
    }
    
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // create html page object
    $page = new HtmlPage();
    
    // show back link
    $page->addHtml($gNavigation->getHtmlBackButton());

    // show headline of module
    $page->addHeadline($headline);

    // show form
    $form = new HtmlForm('upload_files_form', $g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?mode=upload&amp;usr_id='.$getUserId, $page, 'default', true);
    $form->addCustomContent('current_image', $gL10n->get('PRO_CURRENT_PICTURE'), '<img class="imageFrame" src="profile_photo_show.php?usr_id='.$getUserId.'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');
    $form->addFileUpload('foto_upload_file', $gL10n->get('PRO_CHOOSE_PHOTO'), ($gPreferences['max_file_upload_size'] * 1024), false, null, false, FIELD_DEFAULT, 'profile_photo_up_help');
    $form->addSubmitButton('btn_upload', $gL10n->get('PRO_UPLOAD_PHOTO'), THEME_PATH.'/icons/photo_upload.png');

    // add form to html page and show page
    $page->addHtml($form->show(false));
    $page->show();
}
elseif($getMode == 'upload')
{
    /*****************************Foto zwischenspeichern bestaetigen***********************************/
    
    //Dateigroesse
    if ($_FILES['userfile']['error'][0]==1)
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_FILE_TO_LARGE', round(admFuncMaxUploadSize()/pow(1024, 2))));
    }

    //Kontrolle ob Fotos ausgewaehlt wurden
    if(file_exists($_FILES['userfile']['tmp_name'][0]) == false)
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_NOT_CHOOSEN'));
    }

    //Dateiendung
    $image_properties = getimagesize($_FILES['userfile']['tmp_name'][0]);
    if ($image_properties['mime'] != 'image/jpeg' && $image_properties['mime'] != 'image/png')
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_FORMAT_INVALID'));
    }

    //Auflösungskontrolle
    $image_dimensions = $image_properties[0]*$image_properties[1];
    if($image_dimensions > admFuncProcessableImageSize())
    {
    	$gMessage->show($gL10n->get('PRO_PHOTO_RESOLUTION_TO_LARGE', round(admFuncProcessableImageSize()/1000000, 2)));
    }
	
    // Foto auf entsprechende Groesse anpassen
    $user_image = new Image($_FILES['userfile']['tmp_name'][0]);
    $user_image->setImageType('jpeg');
    $user_image->scale(130, 170);
    
    //Ordnerspeicherung
    if($gPreferences['profile_photo_storage'] == 1)
    {
        $user_image->copyToFile(null, SERVER_PATH. '/adm_my_files/user_profile_photos/'.$getUserId.'_new.jpg');
    }
    //Datenbankspeicherung
    else
    {
        //Foto in PHP-Temp-Ordner übertragen
        $user_image->copyToFile(null, ($_FILES['userfile']['tmp_name'][0]));
        // Foto aus PHP-Temp-Ordner einlesen
        $user_image_data = fread(fopen($_FILES['userfile']['tmp_name'][0], 'r'), $_FILES['userfile']['size'][0]);
        
        // Zwischenspeichern des neuen Fotos in der Session
        $gCurrentSession->setValue('ses_binary', $user_image_data);
        $gCurrentSession->save();
    }
    
    //Image-Objekt löschen	
    $user_image->delete();

    if($getUserId == $gCurrentUser->getValue('usr_id'))
    {
        $headline = $gL10n->get('PRO_EDIT_MY_PROFILE_PICTURE');
    }
    else
    {
        $headline = $gL10n->get('PRO_EDIT_PROFILE_PIC_FROM', $user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME'));
    }
    
    // create html page object
    $page = new HtmlPage();
    $page->addJavascript('$("#btn_cancel").click(function() {
        self.location.href=\''.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?mode=dont_save&usr_id='.$getUserId.'\';
    });', true);
    
    // show headline of module
    $page->addHeadline($headline);

    // show form
    $form = new HtmlForm('show_new_profile_picture_form', $g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?mode=save&amp;usr_id='.$getUserId, $page);
    $form->addCustomContent('current_image', $gL10n->get('PRO_CURRENT_PICTURE'), '<img class="imageFrame" src="profile_photo_show.php?usr_id='.$getUserId.'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />');
    $form->addCustomContent('new_image', $gL10n->get('PRO_NEW_PICTURE'), '<img class="imageFrame" src="profile_photo_show.php?usr_id='.$getUserId.'&new_photo=1" alt="'.$gL10n->get('PRO_NEW_PICTURE').'" />');
    $form->addLine();
    $form->addSubmitButton('btn_update', $gL10n->get('SYS_APPLY'), THEME_PATH.'/icons/database_in.png');
    $form->addButton('btn_cancel', $gL10n->get('SYS_ABORT'), THEME_PATH.'/icons/error.png');

    // add form to html page and show page
    $page->addHtml($form->show(false));
    $page->show();
}
?>