<?php
/******************************************************************************
 * Profil bearbeiten
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id: id des Users dessen Bild geaendert werden soll
 * job - save :   Welcher Teil des Skriptes soll ausgeführt werden
 *     - dont_save :
 *     - upload :
 *     - msg_delete : Nachfrage, ob Profilfoto wirklich geloescht werden soll
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/image.php");
require("../../system/classes/htaccess.php");

//pruefen ob in den aktuellen Servereinstellungen file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $g_message->show("no_file_upload_server");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_usr_id = $g_current_user->getValue("usr_id");
$job        = NULL;

// Uebergabevariablen pruefen
// usr_id
if(isset($_GET["usr_id"]))
{
    if(is_numeric($_GET["usr_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $req_usr_id = $_GET["usr_id"];
}

//Aufgabe
if(isset($_GET["job"]))
{
    $job = $_GET["job"];
}

if($job != "save" && $job!="delete" && $job != "dont_save" && $job != "upload" && $job != "msg_delete" && $job != NULL)
{
    $g_message->show("invalid");
}

// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
if($g_current_user->editProfile($req_usr_id) == false)
{
    $g_message->show("norights");
}

//ggf. Ordner für Userfotos anlegen
if(!file_exists(SERVER_PATH. "/adm_my_files/user_profile_photos") && $g_preferences['profile_photo_storage'] == 1)
{
    mkdir(SERVER_PATH. "/adm_my_files/user_profile_photos", 0777);
    chmod(SERVER_PATH. "/adm_my_files/user_profile_photos", 0777);
}
$protection = new Htaccess(SERVER_PATH. "/adm_my_files");
$protection->protectFolder();                     

// User auslesen
$user = new User($g_db, $req_usr_id);

if($job=="save")
{
    /*****************************Bild speichern*************************************/
    
    if($g_preferences['profile_photo_storage'] == 1)
    {
    	// Bild im Dateisystem speichern
    
	    //Nachsehen ob fuer den User ein Photo gespeichert war
	    if(file_exists(SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id."_new.jpg"))
	    {
			if(file_exists(SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id.".jpg"))
			{
				unlink(SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id.".jpg");
			}
			
			rename(SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id."_new.jpg", SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id.".jpg");
	    }
	}
	else
	{
		// Bild in der Datenbank speichern

		//Nachsehen ob fuer den User ein Photo gespeichert war
		if(strlen($g_current_session->getValue("ses_blob")) > 0)
		{
		    //Bilddaten in User-Tabelle schreiben
		    $sql = "UPDATE ". TBL_USERS. "
		               SET usr_photo = '". addslashes($g_current_session->getValue("ses_blob")). "'
		             WHERE usr_id    = $req_usr_id ";
		    $g_db->query($sql, false);
		
		    $g_current_session->setValue("ses_blob", "");
		    $g_current_session->setValue("ses_renew", 1);
		    $g_current_session->save();
   		}
	}
    
    // zur Ausgangsseite zurueck
    $_SESSION['navigation']->deleteLastUrl();
    $g_message->setForwardUrl("$g_root_path/adm_program/modules/profile/profile.php?user_id=$req_usr_id", 2000);
    $g_message->show("profile_photo_update");
}    
elseif($job=="dont_save")
{
    /*****************************Bild nicht speichern*************************************/
    //Ordnerspeicherung
    if($g_preferences['profile_photo_storage'] == 1)
	{
    	if(file_exists(SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id."_new.jpg"))
		{
			unlink(SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id."_new.jpg");
		}
    }
    //Datenbankspeicherung
    else
    {
		$g_current_session->setValue("ses_blob", "");
    	$g_current_session->setValue("ses_renew", 1);
    	$g_current_session->save();
    }
    // zur Ausgangsseite zurueck
    $g_message->setForwardUrl("$g_root_path/adm_program/modules/profile/profile.php?user_id=$req_usr_id", 2000);
    $g_message->show("profile_photo_update_cancel");
}
elseif($job=="msg_delete")
{
    /*********************** Nachfrage Bild loeschen *************************************/
    $g_message->setForwardYesNo("$g_root_path/adm_program/modules/profile/profile_photo_edit.php?usr_id=$req_usr_id&job=delete");
    $g_message->show("delete_photo", "", "Löschen");
}
elseif($job=="delete")
{
    /***************************** Bild loeschen *************************************/
	//Ordnerspeicherung, Datei löschen
	if($g_preferences['profile_photo_storage'] == 1)
	{
	    unlink(SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id.".jpg");
	}
	//Datenbankspeicherung, Daten aus Session entfernen
	else
	{
	    $user->setValue("usr_photo", "");
	    $user->save();
	}
	    
    // zur Ausgangsseite zurueck
    $g_message->setForwardUrl("$g_root_path/adm_program/modules/profile/profile.php?user_id=$req_usr_id", 2000);
    $g_message->show("profile_photo_deleted");
}
elseif( isset($_POST["upload"]))
{
    /*********************** Kontrollmechanismen *********************************/
    
    //Dateigroesse
    if ($_FILES["bilddatei"]["error"]==1)
    {
        $g_message->show("profile_photo_2big", ini_get("upload_max_filesize"));
    }

    //Kontrolle ob Bilder ausgewaehlt wurden
    if(file_exists($_FILES["bilddatei"]["tmp_name"]) == false)
    {
        $g_message->show("profile_photo_nopic");
    }

    //Dateiendung
    $bildinfo = getimagesize($_FILES["bilddatei"]["tmp_name"]);
    if ($bildinfo['mime'] != "image/jpeg" && $bildinfo['mime'] != "image/png")
    {
        $g_message->show("dateiendungphotoup");
    }

}//Kontrollmechanismen

    
if($job==NULL)
{
    /*****************************Bild hochladen*************************************/
    
    $_SESSION['navigation']->addUrl(CURRENT_URL);

    if($req_usr_id == $g_current_user->getValue("usr_id"))
    {
        $headline = "Mein Profilfoto ändern";
    }
    else
    {
        $headline = "Profilfoto von ". $user->getValue("Vorname"). " ". $user->getValue("Nachname"). " ändern";
    }

    $g_layout['title'] = $headline;
    require(THEME_SERVER_PATH. "/overall_header.php");
    
    echo "
    <form method=\"post\" action=\"".$g_root_path."/adm_program/modules/profile/profile_photo_edit.php?job=upload&amp;usr_id=".$req_usr_id."\" enctype=\"multipart/form-data\">
    <div class=\"formLayout\" id=\"profile_photo_upload_form\">
        <div class=\"formHead\">".$headline."</div>
        <div class=\"formBody\">
            <p>Aktuelles Bild:</p>
            <img src=\"profile_photo_show.php?usr_id=".$req_usr_id."\" alt=\"Aktuelles Bild\" />
			<p>Bitte hier ein neues Bild auswählen:</p>
            <p><input type=\"file\" id=\"bilddatei\" name=\"bilddatei\" size=\"40\" value=\"durchsuchen\" /></p>

            <hr />

            <div class=\"formSubmit\">
                <button name=\"upload\" type=\"submit\" value=\"speichern\"><img src=\"". THEME_PATH. "/icons/photo_upload.png\" alt=\"Speichern\" />&nbsp;Bild Hochladen</button>
            </div>
        </div>
    </div>
    </form>
    
    <ul class=\"iconTextLinkList\">
        <li>
            <span class=\"iconTextLink\">
                <a href=\"".$g_root_path."/adm_program/system/back.php\"><img 
                src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
                <a href=\"".$g_root_path."/adm_program/system/back.php\">Zurück</a>
            </span>
        </li>
    </ul>
    
    <script type=\"text/javascript\"><!--
        document.getElementById(\"bilddatei\").focus();
    --></script>";    
}
elseif($job=="upload")
{
    /*****************************Bild zwischenspeichern bestaetigen***********************************/
    
    // Bild auf entsprechende Groesse anpassen
    $user_image = new Image($_FILES["bilddatei"]["tmp_name"]);
    $user_image->setImageType("jpeg");
    $user_image->resize(130, 170);
    
    //Ordnerspeicherung
	if($g_preferences['profile_photo_storage'] == 1)
	{
		$user_image->copyToFile(null, SERVER_PATH. "/adm_my_files/user_profile_photos/".$req_usr_id."_new.jpg");
	}
	//Datenbankspeicherung
	else
	{
		//Bild in PHP-Temp-Ordner übertragen
		$user_image->copyToFile(null, ($_FILES["bilddatei"]["tmp_name"]));
		// Foto aus PHP-Temp-Ordner einlesen
        $user_image_data = addslashes(fread(fopen($_FILES["bilddatei"]["tmp_name"], "r"), $_FILES["bilddatei"]["size"]));
        
		// Zwischenspeichern des neuen Bildes in der Session
        $sql = "UPDATE ". TBL_SESSIONS. "
                   SET ses_blob   = '".$user_image_data."'
                 WHERE ses_usr_id = ". $g_current_user->getValue("usr_id");
        $result = $g_db->query($sql, false);
	}
    
    //Image-Objekt löschen	
    $user_image->delete();

    if($req_usr_id == $g_current_user->getValue("usr_id"))
    {
        $headline = "Mein Profilfoto";
    }
    else
    {
        $headline = "Profilfoto von ". $user->getValue("Vorname"). " ". $user->getValue("Nachname");
    }
    
    $g_layout['title'] = $headline;
    require(THEME_SERVER_PATH. "/overall_header.php");    
    
    echo "
    <div class=\"formLayout\" id=\"profile_photo_after_upload_form\">
        <div class=\"formHead\">". $headline. "</div>
        <div class=\"formBody\">
            <table style=\"border: none; width: 100%; padding: 5px;\">
                <tr style=\"text-align: center;\">
                    <td>Aktuelles Bild:</td>
                    <td>Neues Bild:</td>
                </tr>
                <tr style=\"text-align: center;\">
                	<td><img src=\"profile_photo_show.php?usr_id=".$req_usr_id."\" alt=\"Aktuelles Profilbild\" /></td>
					<td><img src=\"profile_photo_show.php?usr_id=".$req_usr_id."&new_photo=1\" alt=\"Neues Profilbild\" /></td>
                </tr>
            </table>

            <hr />
            
            <div class=\"formSubmit\">
                <button name=\"cancel\" type=\"button\" value=\"abbrechen\" onclick=\"self.location.href='$g_root_path/adm_program/modules/profile/profile_photo_edit.php?job=dont_save&amp;usr_id=".$req_usr_id."'\">
                    <img src=\"". THEME_PATH. "/icons/error.png\" alt=\"Abbrechen\" />
                    &nbsp;Abbrechen
                </button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <button name=\"update\" type=\"button\" value=\"update\" onclick=\"self.location.href='$g_root_path/adm_program/modules/profile/profile_photo_edit.php?job=save&amp;usr_id=".$req_usr_id."'\">
                    <img src=\"". THEME_PATH. "/icons/database_in.png\" alt=\"Update\" />
                    &nbsp;Neues Bild &uuml;bernehmen
                </button>
            </div>
        </div>
    </div>";
}

require(THEME_SERVER_PATH. "/overall_footer.php");

?>
