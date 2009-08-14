<?php
/******************************************************************************
 * Profil bearbeiten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
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

require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/image.php');
require('../../system/classes/htaccess.php');

//pruefen ob in den aktuellen Servereinstellungen file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $g_message->show('no_file_upload_server');
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
        $g_message->show('invalid');
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
    $g_message->show('invalid');
}

// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
if($g_current_user->editProfile($req_usr_id) == false)
{
    $g_message->show('norights');
}                    

// User auslesen
$user = new User($g_db, $req_usr_id);

if($job=='save')
{
    /*****************************Foto speichern*************************************/
    
    if($g_preferences['profile_photo_storage'] == 1)
    {
    	// Foto im Dateisystem speichern
 
		//ggf. Ordner für Userfotos anlegen
		if(!file_exists(SERVER_PATH. '/adm_my_files/user_profile_photos') && $g_preferences['profile_photo_storage'] == 1)
		{
			require_once('../../system/classes/folder.php');
			$folder = new Folder(SERVER_PATH. '/adm_my_files');
			if($folder->createWriteableFolder('user_profile_photos') == false)
			{
				$g_message->addVariableContent($folder->getFolder() , 1);
				$g_message->addVariableContent($g_preferences['email_administrator'], 2 ,false);
				$g_message->show('write_access');
			}
		}
		$protection = new Htaccess(SERVER_PATH. '/adm_my_files');
		$protection->protectFolder();  

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
    $g_message->show('profile_photo_update');
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
    $g_message->show('profile_photo_update_cancel');
}
elseif($job=='msg_delete')
{
    /*********************** Nachfrage Foto loeschen *************************************/
    $g_message->setForwardYesNo($g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?usr_id='.$req_usr_id.'&job=delete');
    $g_message->show('delete_photo', '', 'Löschen');
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
    $g_message->show('profile_photo_deleted');
}

/*********************** Kontrollmechanismen *********************************/
elseif( isset($_POST['upload']))
{
    //Dateigroesse
    if ($_FILES['foto_upload_file']['error']==1)
    {
        $g_message->show('profile_photo_2big', round(maxUploadSize()/pow(1024, 2)));
    }

    //Kontrolle ob Fotos ausgewaehlt wurden
    if(file_exists($_FILES['foto_upload_file']['tmp_name']) == false)
    {
        $g_message->show('profile_photo_nopic');
    }

    //Dateiendung
    $image_properties = getimagesize($_FILES['foto_upload_file']['tmp_name']);
    if ($image_properties['mime'] != 'image/jpeg' && $image_properties['mime'] != 'image/png')
    {
        $g_message->show('dateiendungphotoup');
    }

    //Auflösungskontrolle
    $image_dimensions = $image_properties[0]*$image_properties[1];
    if($image_dimensions > processableImageSize())
    {
    	$g_message->show('profile_photo_resolution_2large', round(processableImageSize()/1000000, 2));
    }
}//Kontrollmechanismen


/*****************************Foto hochladen*************************************/    
if($job==NULL)
{
    $_SESSION['navigation']->addUrl(CURRENT_URL);

    if($req_usr_id == $g_current_user->getValue('usr_id'))
    {
        $headline = 'Mein Profilfoto bearbeiten';
    }
    else
    {
        $headline = 'Profilfoto von '. $user->getValue('Vorname'). ' '. $user->getValue('Nachname'). ' bearbeiten';
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
            <p>Aktuelles Foto:</p>
            <img class="imageFrame" src="profile_photo_show.php?usr_id='.$req_usr_id.'" alt="Aktuelles Foto" />
			<p>Bitte hier ein neues Foto auswählen:</p>
            <p><input type="file" id="foto_upload_file" name="foto_upload_file" size="40" value="durchsuchen" /></p>

            <hr />

            <div class="formSubmit">
                <button name="upload" type="submit" value="speichern"><img src="'. THEME_PATH. '/icons/photo_upload.png" alt="Speichern" />&nbsp;Foto Hochladen</button>
            </div>
        </div>
    </div>
    </form>
    
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
                src="'. THEME_PATH. '/icons/back.png" alt="Zurück" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">Zurück</a>
            </span>
        </li>
        <li>
	        <span class="iconTextLink">
	            <a class="thickbox" href="'. $g_root_path. '/adm_program/system/msg_window.php?err_code=profile_photo_up_help&amp;window=true&amp;KeepThis=true&amp;TB_iframe=true&amp;height=400&amp;width=580"><img 
	            	src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" /></a>
	            <a class="thickbox" href="'. $g_root_path. '/adm_program/system/msg_window.php?err_code=profile_photo_up_help&amp;window=true&amp;KeepThis=true&amp;TB_iframe=true&amp;height=400&amp;width=580">Hilfe</a>
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
        $headline = 'Mein Profilfoto';
    }
    else
    {
        $headline = 'Profilfoto von '. $user->getValue('Vorname'). ' '. $user->getValue('Nachname');
    }
    
    $g_layout['title'] = $headline;
    require(THEME_SERVER_PATH. '/overall_header.php');    
    
    echo '
    <div class="formLayout" id="profile_photo_after_upload_form">
        <div class="formHead">'.$headline.'</div>
        <div class="formBody">
            <table style="border: none; width: 100%; padding: 5px;">
                <tr style="text-align: center;">
                    <td>Aktuelles Foto:</td>
                    <td>Neues Foto:</td>
                </tr>
                <tr style="text-align: center;">
                	<td><img class="imageFrame" src="profile_photo_show.php?usr_id='.$req_usr_id.'" alt="Aktuelles Profilfoto" /></td>
					<td><img class="imageFrame" src="profile_photo_show.php?usr_id='.$req_usr_id.'&new_photo=1" alt="Neues Profilfoto" /></td>
                </tr>
            </table>

            <hr />
            
            <div class="formSubmit">
                <button name="cancel" type="button" value="abbrechen" onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?job=dont_save&amp;usr_id='.$req_usr_id.'\'">
                    <img src="'.THEME_PATH.'/icons/error.png" alt="Abbrechen" />
                    &nbsp;Abbrechen
                </button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <button name="update" type="button" value="update" onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/profile/profile_photo_edit.php?job=save&amp;usr_id='.$req_usr_id.'\'">
                    <img src="'.THEME_PATH.'/icons/database_in.png" alt="Update" />
                    &nbsp;Neues Foto übernehmen
                </button>
            </div>
        </div>
    </div>';
}

require(THEME_SERVER_PATH. '/overall_footer.php');

?>
