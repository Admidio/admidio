<?php
/******************************************************************************
 * Upload and save new user photo
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id   : id of user whose photo should be changed
 * job      : the current mode of this script
 *   - save      : save new photo in user recordset
 *   - dont_save : delete photo in session and show message
 *   - upload    : save new photo in session and show dialog with old and new photo
 *   - delete    : delete current photo in database
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/image.php');
require_once('../../system/classes/my_files.php');

// checks if the server settings for file_upload are set to ON
if (ini_get('file_uploads') != '1')
{
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
}

// Initialize and check the parameters
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', null, true);
$getJob    = admFuncVariableIsValid($_GET, 'job', 'string', '', false, array('save', 'dont_save', 'upload', 'delete'));

// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
if($gCurrentUser->editProfile($getUserId) == false)
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

// read user data and show error if user doesn't exists
$user = new User($gDb, $gProfileFields, $getUserId);

if($user->getValue('usr_id') == 0)
{
	$gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

if($getJob=='save')
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
    $_SESSION['navigation']->deleteLastUrl();
    header('Location: '.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$getUserId);
    exit();
}    
elseif($getJob=='dont_save')
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
elseif($getJob=='delete')
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
if(strlen($getJob) == 0)
{
    $_SESSION['navigation']->addUrl(CURRENT_URL);

    if($getUserId == $gCurrentUser->getValue('usr_id'))
    {
        $headline = $gL10n->get('PRO_EDIT_MY_PROFILE_PICTURE');
    }
    else
    {
        $headline = $gL10n->get('PRO_EDIT_PROFILE_PIC_FROM', $user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME'));
    }

    $gLayout['title']  = $headline;
    $gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#foto_upload_file").focus();
        }); 
    //--></script>';
    require(SERVER_PATH. '/adm_program/system/overall_header.php');
    
    echo '
    <form method="post" action="'.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?job=upload&amp;usr_id='.$getUserId.'" enctype="multipart/form-data">
    <div class="formLayout" id="profile_photo_upload_form">
        <div class="formHead">'.$headline.'</div>
        <div class="formBody">
            <p>'.$gL10n->get('PRO_CURRENT_PICTURE').':</p>
            <img class="imageFrame" src="profile_photo_show.php?usr_id='.$getUserId.'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" />
            <p>'.$gL10n->get('PRO_SELECT_NEW_PIC_HERE').':</p>
            <p><input type="file" id="foto_upload_file" name="foto_upload_file" size="40" value="'.$gL10n->get('SYS_BROWSE').'" /></p>

            <hr />

            <div class="formSubmit">
                <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/photo_upload.png" alt="'.$gL10n->get('PRO_UPLOAD_PHOTO').'" />&nbsp;'.$gL10n->get('PRO_UPLOAD_PHOTO').'</button>
            </div>
        </div>
    </div>
    </form>
    
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
                src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
            </span>
        </li>
        <li>
            <span class="iconTextLink">
                <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=profile_photo_up_help&amp;message_title=SYS_WHAT_TO_DO&amp;inline=true"><img src="'. THEME_PATH. '/icons/help.png" alt="'.$gL10n->get('SYS_HELP').'" /></a>
                <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=profile_photo_up_help&amp;message_title=SYS_WHAT_TO_DO&amp;inline=true">'.$gL10n->get('SYS_HELP').'</a>
            </span>        
        </li>
    </ul>';    
}
elseif($getJob == 'upload')
{
    /*****************************Foto zwischenspeichern bestaetigen***********************************/
    
    //Dateigroesse
    if ($_FILES['foto_upload_file']['error']==1)
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_FILE_TO_LARGE', round(admFuncMaxUploadSize()/pow(1024, 2))));
    }

    //Kontrolle ob Fotos ausgewaehlt wurden
    if(file_exists($_FILES['foto_upload_file']['tmp_name']) == false)
    {
        $gMessage->show($gL10n->get('PRO_PHOTO_NOT_CHOOSEN'));
    }

    //Dateiendung
    $image_properties = getimagesize($_FILES['foto_upload_file']['tmp_name']);
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
    $user_image = new Image($_FILES['foto_upload_file']['tmp_name']);
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
        $user_image->copyToFile(null, ($_FILES['foto_upload_file']['tmp_name']));
        // Foto aus PHP-Temp-Ordner einlesen
        $user_image_data = fread(fopen($_FILES['foto_upload_file']['tmp_name'], 'r'), $_FILES['foto_upload_file']['size']);
        
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
    
    $gLayout['title'] = $headline;
    require(SERVER_PATH. '/adm_program/system/overall_header.php');    
    
    echo '
    <div class="formLayout" id="profile_photo_after_upload_form">
        <div class="formHead">'.$headline.'</div>
        <div class="formBody">
            <table style="border: none; width: 100%; padding: 5px;">
                <tr style="text-align: center;">
                    <td>'.$gL10n->get('PRO_CURRENT_PICTURE').':</td>
                    <td>'.$gL10n->get('PRO_NEW_PICTURE').':</td>
                </tr>
                <tr style="text-align: center;">
                    <td><img class="imageFrame" src="profile_photo_show.php?usr_id='.$getUserId.'" alt="'.$gL10n->get('PRO_CURRENT_PICTURE').'" /></td>
                    <td><img class="imageFrame" src="profile_photo_show.php?usr_id='.$getUserId.'&new_photo=1" alt="'.$gL10n->get('PRO_NEW_PICTURE').'" /></td>
                </tr>
            </table>

            <hr />
            
            <div class="formSubmit">
                <button id="btnCancel" type="button" onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?job=dont_save&amp;usr_id='.$getUserId.'\'">
                    <img src="'.THEME_PATH.'/icons/error.png" alt="'.$gL10n->get('SYS_ABORT').'" />
                    &nbsp;'.$gL10n->get('SYS_ABORT').'
                </button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <button id="btnUpdate" type="button" onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?job=save&amp;usr_id='.$getUserId.'\'">
                    <img src="'.THEME_PATH.'/icons/database_in.png" alt="'.$gL10n->get('SYS_UPDATE').'" />
                    &nbsp;'.$gL10n->get('PRO_ACCEPT_NEW_PICTURE').'
                </button>
            </div>
        </div>
    </div>';
}

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>
