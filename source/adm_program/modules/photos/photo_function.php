<?php
   /******************************************************************************
 * Photofunktionen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id: id des Albums
 * job: - do_delete
 *      - rotate
 *      - delete_request
 * direction: drehrichtung links oder rechts
 * bild: Nr. des Bildes welches verarbeitet werden soll
 * thumb_seite: von welcher Thumnailseite aus wurde die Funktion aufgerufen
 *
 *****************************************************************************/

require_once("../../system/common.php");
require_once("../../system/photo_album_class.php");

// die Funktionen sollten auch ausgeloggt irgendwo benutzt werden koennen
if(isset($_GET["job"]))
{
    require_once("../../system/login_valid.php");

    if ($g_preferences['enable_photo_module'] == 0)
    {
        // das Modul ist deaktiviert
        $g_message->show("module_disabled");
    }
    elseif($g_preferences['enable_photo_module'] == 2)
    {
        // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
        require("../../system/login_valid.php");
    }

    // erst pruefen, ob der User Fotoberarbeitungsrechte hat
    if(!$g_current_user->editPhotoRight())
    {
        $g_message->show("photoverwaltunsrecht");
    }

    //URL auf Navigationstack ablegen
    $_SESSION['navigation']->addUrl(CURRENT_URL);
}

// Uebergabevariablen pruefen

//ID Pruefen
if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]))
{
    $pho_id = $_GET["pho_id"];
}
else 
{
    $pho_id = NULL;
}

if(isset($_GET["job"]) && $_GET["job"] != "rotate" && $_GET["job"] != "delete_request" && $_GET["job"] != "do_delete")
{
    $g_message->show("invalid");
}

if(isset($_GET["direction"]) && $_GET["direction"] != "left" && $_GET["direction"] != "right")
{
    $g_message->show("invalid");
}

if(isset($_GET["job"]) && (isset($_GET["bild"]) == false || is_numeric($_GET["bild"]) == false) )
{
    $g_message->show("invalid");
}

//Funktion zum Speichern von Bildern
//Kind (upload, thumb)

function image_save($orig_path, $scale, $destination_path)
{
    if(file_exists($orig_path))
    {
        //Speicher zur Bildbearbeitung bereit stellen, erst ab php5 noetig
        @ini_set('memory_limit', '50M');
        
        //Ermittlung der Original Bildgroesse
        $bildgroesse = getimagesize($orig_path);

        //Errechnung seitenverhaeltniss
        $seitenverhaeltnis = $bildgroesse[0]/$bildgroesse[1];

        //laengere seite soll skalliert werden
        //Errechnug neuen Bildgroesse Querformat
        if($bildgroesse[0]>=$bildgroesse[1])
        {
            $neubildsize = array ($scale, round($scale/$seitenverhaeltnis));
        }
        //Errechnug neuen Bildgroesse Hochformat
        if($bildgroesse[0]<$bildgroesse[1]){
            $neubildsize = array (round($scale*$seitenverhaeltnis), $scale);
        }
                    

        // Erzeugung neues Bild
        $neubild = imagecreatetruecolor($neubildsize[0], $neubildsize[1]);

        //Aufrufen des Originalbildes
        $bilddaten = imagecreatefromjpeg($orig_path);

        //kopieren der Daten in neues Bild
        imagecopyresampled($neubild, $bilddaten, 0, 0, 0, 0, $neubildsize[0], $neubildsize[1], $bildgroesse[0], $bildgroesse[1]);

        //falls Bild existiert: Loeschen
        if(file_exists($destination_path)){
            unlink($destination_path);
        }

        //Bild in Zielordner abspeichern
        imagejpeg($neubild, $destination_path, 90);
        chmod($destination_path,0777);

        imagedestroy($neubild);
    }    
}


//Loeschen eines Thumbnails
// photo_album : Referenz auf Objekt des relevanten Albums
// pic_nr      : Nr des Bildes dessen Thumbnail geloescht werden soll
function deleteThumbnail(&$photo_album, $pic_nr)
{
    //Ordnerpfad zusammensetzen
    $photo_path = SERVER_PATH. "/adm_my_files/photos/".$photo_album->getValue("pho_begin")."_".$photo_album->getValue("pho_id")."/thumbnails/".$pic_nr.".jpg";
    
    //Thumbnail loeschen
    if(file_exists($photo_path))
    {
        chmod($photo_path, 0777);
        unlink($photo_path);
    }
}

// Bild entsprechend der Uebergabe drehen
// pho_id: Albumid
// pic_nr: nr des Bildes das gedreht werden soll
// direction: left/right in die Richtung um 90° drehen
function rotatePhoto($pho_id, $pic_nr, $direction)
{
    global $g_db;

    // nur bei gueltigen Uebergaben weiterarbeiten
    if(is_numeric($pho_id) && is_numeric($pic_nr) && ($direction == "left" || $direction == "right"))
    {
        //Aufruf des ggf. uebergebenen Albums
        $photo_album = new PhotoAlbum($g_db, $pho_id);

        //Thumbnail loeschen
        deleteThumbnail($photo_album, $pic_nr);
        
        //Ordnerpfad zusammensetzen
        $photo_path = SERVER_PATH. "/adm_my_files/photos/".$photo_album->getValue("pho_begin")."_".$photo_album->getValue("pho_id"). "/". $pic_nr. ".jpg";
      
        //Ermittlung der Original Bildgroessee
        $bildgroesse = getimagesize($photo_path);

        // Erzeugung neues Bild
        $photo_rotate = imagecreatetruecolor($bildgroesse[1], $bildgroesse[0]);

        //Aufrufen des Originalbildes
        $photo_original = imagecreatefromjpeg($photo_path);

        //kopieren der Daten in neues Bild
        for($y=0; $y<$bildgroesse[1]; $y++)
        {
            for($x=0; $x<$bildgroesse[0]; $x++)
            {
                if($direction == "right")
                {
                    imagecopy($photo_rotate, $photo_original, $bildgroesse[1]-$y-1, $x, $x, $y, 1,1 );
                }
                elseif($direction == "left")
                {
                    imagecopy($photo_rotate, $photo_original, $y, $bildgroesse[0]-$x-1, $x, $y, 1,1 );
                }
            }
        }
      
        //Ursprungsdatei loeschen
        if(file_exists($photo_path))
        {
            chmod($photo_path, 0777);
            unlink($photo_path);
        }

        //speichern
        imagejpeg($photo_rotate, $photo_path, 90);
        chmod($photo_path,0777);

        //Loeschen des Bildes aus Arbeitsspeicher
        imagedestroy($photo_rotate);
        imagedestroy($photo_original);
    }
};

//Loeschen eines Bildes
function deletePhoto($pho_id, $pic_nr)
{
    global $g_current_user, $g_db, $g_organization;

    // nur bei gueltigen Uebergaben weiterarbeiten
    if(is_numeric($pho_id) && is_numeric($pic_nr))
    {
        // einlesen des Albums
        $photo_album = new PhotoAlbum($g_db, $pho_id);
        
        //Speicherort
        $album_path = SERVER_PATH. "/adm_my_files/photos/".$photo_album->getValue("pho_begin")."_".$photo_album->getValue("pho_id");
        
        //Bilder loeschen
        if(file_exists("$album_path/$pic_nr.jpg"))
        {
            chmod("$album_path/$pic_nr.jpg", 0777);
            unlink("$album_path/$pic_nr.jpg");
        }

        // Umbenennen der Restbilder und Thumbnails loeschen
        $new_pic_nr = $pic_nr;
        $thumbnail_delete = false;

        for($act_pic_nr = 1; $act_pic_nr <= $photo_album->getValue("pho_quantity"); $act_pic_nr++)
        {
            if(file_exists("$album_path/$act_pic_nr.jpg"))
            {
                if($act_pic_nr > $new_pic_nr)
                {
                    chmod("$album_path/$act_pic_nr.jpg", 0777);
                    rename("$album_path/$act_pic_nr.jpg", "$album_path/$new_pic_nr.jpg");
                    $new_pic_nr++;
                }                
            }
            else
            {
                $thumbnail_delete = true;
            }
            
            if($thumbnail_delete)
            {
                // Alle Thumbnails ab dem geloeschten Bild loeschen
                deleteThumbnail($photo_album, $act_pic_nr);
            }
        }//for

        // Aendern der Datenbankeintaege
        $photo_album->setValue("pho_quantity", $photo_album->getValue("pho_quantity")-1);
        $photo_album->save();
    }
};


//Nutzung der rotatefunktion
if(isset($_GET["job"]) && $_GET["job"]=="rotate")
{
    // Foto um 90° drehen
    rotatePhoto($pho_id, $_GET["bild"], $_GET["direction"]);
    
    // zur Ausgangsseite zurueck
    $location = "Location: $g_root_path/adm_program/system/back.php";
    header($location);
    exit();
}

//Nachfrage ob geloescht werden soll
if(isset($_GET["job"]) && $_GET["job"]=="delete_request")
{
   $g_message->setForwardYesNo("$g_root_path/adm_program/modules/photos/photo_function.php?pho_id=$pho_id&bild=". $_GET["bild"]."&job=do_delete");
   $g_message->show("delete_photo");
}

//Nutzung der Loeschfunktion
if(isset($_GET["job"]) && $_GET["job"]=="do_delete")
{
    //Aufruf der entsprechenden Funktion
    deletePhoto($pho_id, $_GET["bild"]);
    
    //Neu laden der Albumdaten
    $photo_album = new PhotoAlbum($g_db);
    if($pho_id > 0)
    {
        $photo_album->getPhotoAlbum($pho_id);
    }

    $_SESSION['photo_album'] =& $photo_album;
    
    $_SESSION['navigation']->deleteLastUrl();
    $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php", 2000);
    $g_message->show("photo_deleted");
}
?>