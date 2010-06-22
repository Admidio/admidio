<?php
/******************************************************************************
 * Profil bearbeiten
 *
 * Copyright    : (c) 2004 - 2010 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id: id des Users dessen Foto geaendert werden soll
 * job - save :   Welcher Teil des Skriptes soll ausgeführt werden
 *     - dont_save :
 *     - upload :
 *     - msg_delete : Nachfrage, ob Profilfoto wirklich geloescht werden soll
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/image.php');
require_once('../../system/classes/my_files.php');

//pruefen ob in den aktuellen Servereinstellungen file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $g_message->show($g_l10n->get('SYS_PHR_SERVER_NO_UPLOAD'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_usr_id = $g_current_user->getValue('usr_id');
$job        = NULL;

// Uebergabevariablen pruefen
// Benutzer-ID
if(isset($_GET['usr_id']))
{
    if(is_numeric($_GET['usr_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_usr_id = $_GET['usr_id'];
}

// Aufgabe
if(isset($_GET['job']))
{
    $job = $_GET['job'];
}

if($job != 'save' && $job!='delete' && $job != 'dont_save' && $job != 'upload' && $job != 'msg_delete' && $job != NULL)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
if($g_current_user->editProfile($req_usr_id) == false)
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// bei Ordnerspeicherung pruefen ob der Unterordner in adm_my_files mit entsprechenden Rechten existiert
if($g_preferences['profile_photo_storage'] == 1)
{
    // ggf. Ordner für Userfotos in adm_my_files anlegen
    $myFilesProfilePhotos = new MyFiles('USER_PROFILE_PHOTOS');
    if($myFilesProfilePhotos->checkSettings() == false)
    {
        $g_message->show($g_l10n->get($myFilesProfilePhotos->errorText, $myFilesProfilePhotos->errorPath, '<a href="mailto:'.$g_preferences['email_administrator'].'">', '</a>'));
    }
}

// User auslesen
$user = new User($g_db, $req_usr_id);

if($job=='save')
{
    /*****************************Foto speichern*************************************/
    
    if($g_preferences['profile_photo_storage'] == 1)
    {
        // Foto im Dateisystem speichern      

        //Nachsehen ob fuer den User ein Photo gespeichert war
        if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$req_usr_id.'_new.jpg'))
        {
            if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$req_usr_id.'.jpg'))
            {
                unlink(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$req_usr_id.'.jpg');
            }
            
            rename(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$req_usr_id.'_new.jpg', SERVER_PATH. '/adm_my_files/user_profile_photos/'.$req_usr_id.'.jpg');
        }
    }
    else
    {
        // Foto in der Datenbank speichern

        //Nachsehen ob fuer den User ein Photo gespeichert war
        if(strlen($g_current_session->getValue('ses_blob')) > 0)
        {
            //Fotodaten in User-Tabelle schreiben
            $user->setValue('usr_photo', $g_current_session->getValue('ses_blob'));
            $user->save();

            // Foto aus Session entfernen und neues Einlesen des Users veranlassen
            $g_current_session->setValue('ses_blob', '');
            $g_current_session->save();
            $g_current_session->renewUserObject($req_usr_id);
        }
    }
    
    // zur Ausgangsseite zurueck
    $_SESSION['navigation']->deleteLastUrl();
    $g_message->setForwardUrl($g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$req_usr_id, 2000);
    $g_message->show($g_l10n->get('PRO_PHR_PHOTO_SAVED'));
}    
elseif($job=='dont_save')
{
    /*****************************Foto nicht speichern*************************************/
    //Ordnerspeicherung
    if($g_preferences['profile_photo_storage'] == 1)
    {
        if(file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$req_usr_id.'_new.jpg'))
        {
            unlink(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$req_usr_id.'_new.jpg');
        }
    }
    //Datenbankspeicherung
    else
    {
        $g_current_session->setValue('ses_blob', '');
        $g_current_session->save();
    }
    // zur Ausgangsseite zurueck
    $g_message->setForwardUrl($g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$req_usr_id, 2000);
    $g_message->show($g_l10n->get('SYS_PHR_PROCESS_CANCELED'));
}
elseif($job=='msg_delete')
{
    /*********************** Nachfrage Foto loeschen *************************************/
    $g_message->setForwardYesNo($g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?usr_id='.$req_usr_id.'&job=delete');
    $g_message->show($g_l10n->get('PRO_PHR_WANT_DELETE_PHOTO'), $g_l10n->get('SYS_DELETE'));
}
elseif($job=='delete')
{
    /***************************** Foto loeschen *************************************/
    //Ordnerspeicherung, Datei löschen
    if($g_preferences['profile_photo_storage'] == 1)
    {
        unlink(SERVER_PATH. '/adm_my_files/user_profile_photos/'.$req_usr_id.'.jpg');
    }
    //Datenbankspeicherung, Daten aus Session entfernen
    else
    {
        $user->setValue('usr_photo', '');
        $user->save();
        $g_current_session->renewUserObject($req_usr_id);
    }
        
    // zur Ausgangsseite zurueck
    $g_message->setForwardUrl($g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$req_usr_id, 2000);
    $g_message->show($g_l10n->get('PRO_PHR_PHOTO_DELETED'));
}

/*********************** Kontrollmechanismen *********************************/
elseif( isset($_POST['upload']))
{
    //Dateigroesse
    if ($_FILES['foto_upload_file']['error']==1)
    {
        $g_message->show($g_l10n->get('PRO_PHR_PHOTO_FILE_TO_LARGE', round(maxUploadSize()/pow(1024, 2))));
    }

    //Kontrolle ob Fotos ausgewaehlt wurden
    if(file_exists($_FILES['foto_upload_file']['tmp_name']) == false)
    {
        $g_message->show($g_l10n->get('PRO_PHR_PHOTO_NOT_CHOOSEN'));
    }

    //Dateiendung
    $image_properties = getimagesize($_FILES['foto_upload_file']['tmp_name']);
    if ($image_properties['mime'] != 'image/jpeg' && $image_properties['mime'] != 'image/png')
    {
        $g_message->show($g_l10n->get('PRO_PHR_PHOTO_INVALID_FORMAT'));
    }

    //Auflösungskontrolle
    $image_dimensions = $image_properties[0]*$image_properties[1];
    if($image_dimensions > processableImageSize())
    {
    	$g_message->show($g_l10n->get('PRO_PHR_PHOTO_RESOLUTION_TO_LARGE', round(processableImageSize()/1000000, 2)));
    }
}//Kontrollmechanismen


/*****************************Foto hochladen*************************************/    
if($job==NULL)
{
    $_SESSION['navigation']->addUrl(CURRENT_URL);

    if($req_usr_id == $g_current_user->getValue('usr_id'))
    {
        $headline = $g_l10n->get('PRO_EDIT_MY_PROFILE_PICTURE');
    }
    else
    {
        $headline = $g_l10n->get('PRO_PHR_EDIT_PROFILE_PIC_FROM', $user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME'));
    }

    $g_layout['title']  = $headline;
    $g_layout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#foto_upload_file").focus();
        }); 
    //--></script>';
    require(THEME_SERVER_PATH. '/overall_header.php');
    
    echo '
    <form method="post" action="'.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?job=upload&amp;usr_id='.$req_usr_id.'" enctype="multipart/form-data">
    <div class="formLayout" id="profile_photo_upload_form">
        <div class="formHead">'.$headline.'</div>
        <div class="formBody">
            <p>'.$g_l10n->get('PRO_CURRENT_PICTURE').':</p>
            <img class="imageFrame" src="profile_photo_show.php?usr_id='.$req_usr_id.'" alt="'.$g_l10n->get('PRO_CURRENT_PICTURE').'" />
            <p>'.$g_l10n->get('PRO_PHR_SELECT_NEW_PIC_HERE').':</p>
            <p><input type="file" id="foto_upload_file" name="foto_upload_file" size="40" value="'.$g_l10n->get('SYS_SEARCH_AFTER').'" /></p>

            <hr />

            <div class="formSubmit">
                <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/photo_upload.png" alt="'.$g_l10n->get('SYS_SAVE').'" />&nbsp;'.$g_l10n->get('PRO_UPLOAD_PHOTO').'</button>
            </div>
        </div>
    </div>
    </form>
    
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
                src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
            </span>
        </li>
        <li>
            <span class="iconTextLink">
                <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=profile_photo_up_help&amp;inline=true"><img src="'. THEME_PATH. '/icons/help.png" alt="'.$g_l10n->get('SYS_HELP').'" /></a>
                <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=profile_photo_up_help&amp;inline=true">'.$g_l10n->get('SYS_HELP').'</a>
            </span>        
        </li>
    </ul>';    
}
elseif($job=='upload')
{
    /*****************************Foto zwischenspeichern bestaetigen***********************************/
    
    // Foto auf entsprechende Groesse anpassen
    $user_image = new Image($_FILES['foto_upload_file']['tmp_name']);
    $user_image->setImageType('jpeg');
    $user_image->scale(130, 170);
    
    //Ordnerspeicherung
    if($g_preferences['profile_photo_storage'] == 1)
    {
        $user_image->copyToFile(null, SERVER_PATH. '/adm_my_files/user_profile_photos/'.$req_usr_id.'_new.jpg');
    }
    //Datenbankspeicherung
    else
    {
        //Foto in PHP-Temp-Ordner übertragen
        $user_image->copyToFile(null, ($_FILES['foto_upload_file']['tmp_name']));
        // Foto aus PHP-Temp-Ordner einlesen
        $user_image_data = fread(fopen($_FILES['foto_upload_file']['tmp_name'], 'r'), $_FILES['foto_upload_file']['size']);
        
        // Zwischenspeichern des neuen Fotos in der Session
        $g_current_session->setValue('ses_blob', $user_image_data);
        $g_current_session->save();
    }
    
    //Image-Objekt löschen	
    $user_image->delete();

    if($req_usr_id == $g_current_user->getValue('usr_id'))
    {
        $headline = $g_l10n->get('PRO_EDIT_MY_PROFILE_PICTURE');
    }
    else
    {
        $headline = $g_l10n->get('PRO_PHR_EDIT_PROFILE_PIC_FROM', $user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME'));
    }
    
    $g_layout['title'] = $headline;
    require(THEME_SERVER_PATH. '/overall_header.php');    
    
    echo '
    <div class="formLayout" id="profile_photo_after_upload_form">
        <div class="formHead">'.$headline.'</div>
        <div class="formBody">
            <table style="border: none; width: 100%; padding: 5px;">
                <tr style="text-align: center;">
                    <td>'.$g_l10n->get('PRO_CURRENT_PICTURE').':</td>
                    <td>'.$g_l10n->get('PRO_NEW_PICTURE').':</td>
                </tr>
                <tr style="text-align: center;">
                    <td><img class="imageFrame" src="profile_photo_show.php?usr_id='.$req_usr_id.'" alt="'.$g_l10n->get('PRO_CURRENT_PICTURE').'" /></td>
                    <td><img class="imageFrame" src="profile_photo_show.php?usr_id='.$req_usr_id.'&new_photo=1" alt="'.$g_l10n->get('PRO_NEW_PICTURE').'" /></td>
                </tr>
            </table>

            <hr />
            
            <div class="formSubmit">
                <button id="btnCancel" type="button" onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?job=dont_save&amp;usr_id='.$req_usr_id.'\'">
                    <img src="'.THEME_PATH.'/icons/error.png" alt="'.$g_l10n->get('SYS_ABORT').'" />
                    &nbsp;'.$g_l10n->get('SYS_ABORT').'
                </button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <button id="btnUpdate" type="button" onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?job=save&amp;usr_id='.$req_usr_id.'\'">
                    <img src="'.THEME_PATH.'/icons/database_in.png" alt="'.$g_l10n->get('SYS_UPDATE').'" />
                    &nbsp;'.$g_l10n->get('PRO_ACCEPT_NEW_PICTURE').'
                </button>
            </div>
        </div>
    </div>';
}

require(THEME_SERVER_PATH. '/overall_footer.php');

?>
