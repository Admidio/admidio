<?php
/******************************************************************************
 * Photoupload
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id: id des Albums zu dem die Bilder hinzugefuegt werden sollen
 *
 *****************************************************************************/

require("../../system/classes/photo_album.php");
require("../../system/common.php");
require("photo_function.php");

// kontrolle ob das Upload funktioniert hat
if (! isset($_FILES['Filedata'])) {
	echo "Whooops! There is no file! (maybe filesize is greater than POST_MAX_SIZE directive in php.ini)";
	exit;
}
// Fotoalbums-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue("pho_id") == $_GET["pho_id"])
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $g_db;
}
else
{
    $photo_album = new PhotoAlbum($g_db, $_GET["pho_id"]);
    $_SESSION['photo_album'] =& $photo_album;
}

//Übergabevariable prüfen
if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]) == false)
{
    $g_message->show("invalid");
}

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue("pho_id") == $_GET["pho_id"])
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $g_db;
}
else
{
    $photo_album = new PhotoAlbum($g_db, $_GET["pho_id"]);
    $_SESSION['photo_album'] =& $photo_album;
}

//bei Bedarf Uploadodner erzeugen
if(!file_exists($ordner."/ad_my_files/upload"))
{
    mkdir($ordner."/ad_my_files/upload", 0777);
    chmod($ordner."/ad_my_files/upload", 0777);
}

//Ordnerpfad
$ordner = SERVER_PATH. "/adm_my_files/photos/".$photo_album->getValue("pho_begin")."_".$photo_album->getValue("pho_id");

// Sonderzeichen aus Dateinamen entfernen
$image = preg_replace("/[^a-zA-Z0-9._-]/", "_", $_FILES['Filedata']['name']);
// and set the directory
$image = SERVER_PATH. "/adm_my_files/photos/upload/".$image;
//neue Bilderanzahl
$bildnr=$photo_album->getValue("pho_quantity")+1;

// Verarbeitung
if (is_uploaded_file($_FILES['Filedata']['tmp_name'])) {
	if (move_uploaded_file($_FILES['Filedata']['tmp_name'], $image)) 
	{
		//Bild skalliert speichern
        image_save($image, $g_preferences['photo_save_scale'], $ordner."/".$bildnr.".jpg");
	    
        //Nachsehen ob Thumnailordner existiert
        if(!file_exists($ordner."/thumbnails"))
        {
            mkdir($ordner."/thumbnails", 0777);
            chmod($ordner."/thumbnails", 0777);
        }
        
        //Thumbnail speichern
        image_save($image, $g_preferences['photo_thumbs_scale'], $ordner."/thumbnails/".$bildnr.".jpg");
        
        //Loeschen des Bildes aus Arbeitsspeicher
        if(file_exists($image))
        {
            unlink($image);
        } 
        
        //Endkontrolle
        if(file_exists($ordner."/".$bildnr.".jpg"))
        {
            echo":-)";
            //Aendern der Datenbankeintaege
            $photo_album->setValue("pho_quantity", $photo_album->getValue("pho_quantity")+1);
            $photo_album->save();            
        }
        else
        {
            echo":-(";
        }

	} 
} 
?>