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
//require_once("../../system/login_valid.php");
require_once("../../system/classes/photo_album.php");
require_once("../../system/common.php");
require_once("../../system/classes/image.php");


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


//bei Bedarf Uploadodner erzeugen
if(!file_exists(SERVER_PATH. "/adm_my_files/photos/upload"))
{
    mkdir(SERVER_PATH. "/adm_my_files/photos/upload", 0777);
    chmod(SERVER_PATH. "/adm_my_files/photos/upload", 0777);
}

//Ordnerpfad
$ordner = SERVER_PATH. "/adm_my_files/photos/".$photo_album->getValue("pho_begin")."_".$photo_album->getValue("pho_id");

// Sonderzeichen aus Dateinamen entfernen
$image_file = preg_replace("/[^a-zA-Z0-9._-]/", "_", $_FILES['Filedata']['name']);
// and set the directory
$image_file = SERVER_PATH. "/adm_my_files/photos/upload/".$image_file;
//neue Bilderanzahl
$bildnr=$photo_album->getValue("pho_quantity")+1;
//Bildeigenschaften
$image_properties = getimagesize($_FILES['Filedata']['tmp_name']);
//Größenkontrolle
$image_dimensions = $image_properties[0]*$image_properties[1];
$memory_limit = trim(ini_get('memory_limit'));
switch(strtolower(substr($memory_limit,strlen($memory_limit/1),1)))
{
 case 'g':
     $memory_limit *= 1024;
 case 'm':
     $memory_limit *= 1024;
 case 'k':
     $memory_limit *= 1024;
}
//Für jeden Pixel werden 3Byte benötigt (RGB)
//der Speicher muss doppelt zur Verfügung stehen
$max_dimensions = $memory_limit/(3*2);
	    
if($image_dimensions > $max_dimensions)
{
    //Umrechnung in Megapixel
    $max_dimensions = round($max_dimensions/1000000, 2);
    //Fehlermeldung
    echo"Bild größer $max_dimensions MPixel";
    exit();
}

// Verarbeitung
if (is_uploaded_file($_FILES['Filedata']['tmp_name'])) {
	if (move_uploaded_file($_FILES['Filedata']['tmp_name'], $image_file)) 
	{ 
        //Bildobjekt erzeugen
	    $image = new Image($image_file);
	    //Bild skalliert speichern
        $image->scale($g_preferences['photo_save_scale']);
        $image->copyToFile(null, $ordner."/".$bildnr.".jpg");
        
        //Nachsehen ob Thumnailordner existiert
        if(!file_exists($ordner."/thumbnails"))
        {
            mkdir($ordner."/thumbnails", 0777);
            chmod($ordner."/thumbnails", 0777);
        }

        //Thumbnail speichern
        $image->scale($g_preferences['photo_thumbs_scale']);
        $image->copyToFile(null, $ordner."/thumbnails/".$bildnr.".jpg");
        $image->delete(); 
  
        //Loeschen des Bildes aus Arbeitsspeicher
        if(file_exists($image_file))
        {
            unlink($image_file);
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