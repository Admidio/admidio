<?php
/******************************************************************************
 * Profil bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
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

// User auslesen
$user = new User($g_db, $req_usr_id);

if($job=="save")
{
    /*****************************Bild speichern*************************************/
    
    //Nachsehen ob fuer den User ein Photo gespeichert war
    if(strlen($g_current_session->getValue("ses_blob")) > 0)
    {
        //Bilddaten in User-Tabelle schreiben
        $sql = "UPDATE ". TBL_USERS. "
                   SET usr_photo = '". addslashes($g_current_session->getValue("ses_blob")). "'
                 WHERE usr_id    = $req_usr_id ";
        $g_db->query($sql);

        $g_current_session->setValue("ses_blob", "");
        $g_current_session->setValue("ses_renew", 1);
        $g_current_session->save();
    }
    
    // zur Ausgangsseite zurueck
    $g_message->setForwardUrl("$g_root_path/adm_program/modules/profile/profile.php?user_id=$req_usr_id", 2000);
    $g_message->show("profile_photo_update");
}    
elseif($job=="dont_save")
{
    /*****************************Bild nicht speichern*************************************/
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
    $user->setValue("usr_photo", "");
    $user->save();
    
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
        $g_message->show("profile_photo_2big", ini_get(upload_max_filesize));
    }

    //Kontrolle ob Bilder ausgewaehlt wurden
    if(!file_exists($_FILES["bilddatei"]["tmp_name"]))
    {
        $g_message->show("profile_photo_nopic");
    }

    //Dateiendung
    $bildinfo=getimagesize($_FILES["bilddatei"]["tmp_name"]);
    if ($_FILES["bilddatei"]["name"]!=NULL && $bildinfo['mime']!="image/jpeg")
    {
        $g_message->show("dateiendungphotoup");
    }

}//Kontrollmechanismen


/***************************** HTML-Kopf *************************************/

$g_layout['title'] = "Profilfoto";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");    
    
/*****************************Bild hochladen*************************************/
if($job==NULL)
{
    echo "
    <form name=\"photoup\" method=\"post\" action=\"$g_root_path/adm_program/modules/profile/profile_photo_edit.php?job=upload&usr_id=".$req_usr_id."\" enctype=\"multipart/form-data\">
    <div class=\"formLayout\" id=\"profile_photo_upload_form\">
        <div class=\"formHead\">";
            if($req_usr_id == $g_current_user->getValue("usr_id"))
            {
                echo "Mein Profilfoto &auml;ndern";
            }
            else
            {
                echo "Profilfoto von ". $user->getValue("Vorname"). " ". $user->getValue("Nachname"). " &auml;ndern";
            }
        echo "</div>

        <div class=\"formBody\">";
            echo"Aktuelles Bild:<br>";

            //Falls vorhanden Bild ausgeben
            if(strlen($user->getValue("usr_photo")) > 0)
            {
                echo"<img src=\"profile_photo_show.php?usr_id=$req_usr_id\"\">";
            }        
            else
            {
                //wenn nicht dann Schattenkopf
                echo"<img src=\"$g_root_path/adm_program/images/no_profile_pic.png\">";
            }
            echo"<br /><br />";

            //Bildupload
            echo"            
            Bitte hier ein neues Bild ausw&auml;hlen:
            <p><input type=\"file\" id=\"bilddatei\" name=\"bilddatei\" size=\"40\" value=\"durchsuchen\"></p>

            <hr />

            <div class=\"formSubmit\">
                <button name=\"upload\" type=\"submit\" value=\"speichern\">
                    <img src=\"$g_root_path/adm_program/images/page_white_get.png\" alt=\"Speichern\">
                    &nbsp;Bild Hochladen
                </button>
             </div>
        </div>
    </div>
    </form>
    
    <ul class=\"iconTextLink\">
        <li>
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
        </li>
    </ul>";
}

/*****************************Bild zwischenspeichern bestaetigen***********************************/
if($job=="upload")
{
    echo "
    <div class=\"formLayout\" id=\"profile_photo_after_upload_form\">
        <div class=\"formHead\">";
            if($req_usr_id == $g_current_user->getValue("usr_id"))
            {
                echo "Mein Profilfoto";
            }
            else
            {
                echo "Profilfoto von ". $user->getValue("Vorname"). " ". $user->getValue("Nachname");
            }
        echo "</div>

        <div class=\"formBody\">";
            $photo_max_x_size = 130;
            $photo_max_y_size = 170;

            //Ermittlung der Original Bildgroesse
            $bildgroesse = getimagesize($_FILES["bilddatei"]["tmp_name"]);

            //Errechnung seitenverhaeltniss
            $seitenverhaeltnis = $bildgroesse[0] / $bildgroesse[1];

            // schauen, ob das Bild von der Groesse geaendert werden muss
            if($bildgroesse[0] > $photo_max_x_size
            || $bildgroesse[1] > $photo_max_y_size)
            {
                //x-Seite soll scalliert werden
                if(($bildgroesse[0]/$photo_max_x_size) >= ($bildgroesse[1]/$photo_max_y_size))
                {
                    $photo_x_size = $photo_max_x_size;
                    $photo_y_size = round($photo_max_x_size / $seitenverhaeltnis);
                }

                //y-Seite soll scalliert werden
                if(($bildgroesse[0] / $photo_max_x_size) < ($bildgroesse[1] / $photo_max_y_size))
                {
                    $photo_x_size = round($photo_max_y_size * $seitenverhaeltnis);
                    $photo_y_size = $photo_max_y_size;
                }

                // Erzeugung neues Bild
                $resized_user_photo = imagecreatetruecolor($photo_x_size, $photo_y_size);

                //Aufrufen des Originalbildes
                $bilddaten = imagecreatefromjpeg($_FILES["bilddatei"]["tmp_name"]);

                //kopieren der Daten in neues Bild
                imagecopyresampled($resized_user_photo, $bilddaten, 0, 0, 0, 0, $photo_x_size, $photo_y_size, $bildgroesse[0], $bildgroesse[1]);

                imagejpeg($resized_user_photo, $_FILES["bilddatei"]["tmp_name"], 95);
                imagedestroy($resized_user_photo);
            }

            // Foto aus PHP-Temp-Ordner einlesen
            $user_photo = addslashes(fread(fopen($_FILES["bilddatei"]["tmp_name"], "r"), $_FILES["bilddatei"]["size"]));

            // Zwischenspeichern des neuen Bildes in der Session
            $sql = "UPDATE ". TBL_SESSIONS. "
                       SET ses_blob   = '$user_photo'
                     WHERE ses_usr_id = ". $g_current_user->getValue("usr_id");
            $result = $g_db->query($sql);

            //neues und altes Bild anzeigen
            echo"
            <table cellpadding=\"4\" cellspacing=\"0\" border=\"0\" style=\"width: 100%\">
                <tr style=\"text-align: center;\">
                    <td>Aktuelles Bild:<br>";
                        // Falls vorhanden Bild ausgeben

                        // es wird eine id uebergeben, damit immer ein eindeutiger Pfad vorhanden ist 
                        // und nicht ein altes Bild aus dem Cache genommen wird
                        if(strlen($user->getValue("usr_photo")) > 0)
                        {
                            echo"<img src=\"profile_photo_show.php?usr_id=$req_usr_id&amp;id=". time(). "\">";
                        }
                        //wenn nicht Schattenkopf
                        else
                        {
                            echo"<img src=\"$g_root_path/adm_program/images/no_profile_pic.png\">";
                        }
                        echo"
                    </td>
                    <td>Neues Bild:<br><img src=\"profile_photo_show.php?usr_id=$req_usr_id&amp;tmp_photo=1&amp;id=". time(). "\"></td>
                </tr>
            </table>

            <hr />
            
            <div class=\"formSubmit\">
                <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/modules/profile/profile_photo_edit.php?job=dont_save&usr_id=".$req_usr_id."'\">
                    <img src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\">
                    &nbsp;Abbrechen
                </button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <button name=\"update\" type=\"button\" value=\"update\" onclick=\"self.location.href='$g_root_path/adm_program/modules/profile/profile_photo_edit.php?job=save&usr_id=".$req_usr_id."'\">
                    <img src=\"$g_root_path/adm_program/images/database_in.png\" alt=\"Update\">
                    &nbsp;Neues Bild &uuml;bernehmen
                </button>
            </div>
        </div>
    </div>";
}

echo "
<script type=\"text/javascript\"><!--
    document.getElementById('bilddatei').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>
