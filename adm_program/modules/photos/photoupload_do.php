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
 * uploadmethod: 1 - Klassisch
 *				 2 - Flexuploader
 *
 *****************************************************************************/
if(isset($_GET['uploadmethod']) && is_numeric($_GET['pho_id']) && $_GET['pho_id'] == 1)
{
    $_COOKIE['admidio_session_id'] = $_GET['admidio_session_id'];
    $_COOKIE['admidio_php_session_id'] = $_GET['admidio_php_session_id'];
    $_COOKIE['admidio_data'] = $_GET['admidio_data'];
}

require_once('../../system/classes/table_photos.php');
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/image.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show('module_disabled');
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$g_current_user->editPhotoRight())
{
    $g_message->show('photoverwaltunsrecht');
}

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $_GET['pho_id'])
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $g_db;
}
else
{
    $photo_album = new TablePhotos($g_db, $_GET['pho_id']);
    $_SESSION['photo_album'] =& $photo_album;
}

//Übergabevariable prüfen
if(isset($_GET['pho_id']) && is_numeric($_GET['pho_id']) == false || !isset($_GET['pho_id']))
{
   $g_message->show('invalid');
}
if(!isset($_GET['uploadmethod']) || (isset($_GET['uploadmethod']) && !is_numeric($_GET['pho_id'])))
{
    $g_message->show('invalid');  
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($photo_album->getValue('pho_org_shortname') != $g_organization)
{
    $g_message->show('invalid');
}

if (empty($_POST) && $_GET['uploadmethod'] == 1)
{
    $g_message->show('empty_photo_post', ini_get(post_max_size));
}

//bei Bedarf Uploadodner erzeugen
if(!file_exists(SERVER_PATH. '/adm_my_files/photos/upload'))
{
    mkdir(SERVER_PATH. '/adm_my_files/photos/upload', 0777);
    chmod(SERVER_PATH. '/adm_my_files/photos/upload', 0777);
}

//Ordnerpfad
$ordner = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin').'_'.$photo_album->getValue('pho_id');

//Bei Klassischem Upload beginn der Seitenausgabe
if($_GET['uploadmethod'] == 1)
{
	//Photomodulspezifische CSS laden
	$g_layout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/photos.css" type="text/css" media="screen" />';

	// Html-Kopf ausgeben
	$g_layout['title'] = 'Fotos hochladen';
	require(THEME_SERVER_PATH. '/overall_header.php');
	
	echo '
	<h1 class="moduleHeadline">Fotogalerien - Upload</h1>
    <p> Bitte einen Moment Geduld...<br /><br />
        Die Bilder wurden dem Album <strong>'.$photo_album->getValue('pho_name').'</strong> erfolgreich hinzugefügt,<br /> wenn sie hier angezeigt werden.
    </p>';
}

//Bei Klassischem upload erstmal testen ob Alle Dateien angekommen sind bei Flex reichen die Kontrollen in der Verarbeitung
if($_POST['upload'] && $_GET['uploadmethod'] == 1)
{
    //zaehlen wieviele Bilder hochgeladen werden sollen und ob alle Uploads Fehlerfrei sind
    $counter=0;
    for($x=0; $x<=4; $x++)
    {
        //Datei wurde hochgeladen
        if(isset($_FILES['Filedata']['name'][$x]))
        {
            $counter++;

            //Die hochgeladene Datei ueberschreitet die in der Anweisung upload_max_filesize in php.ini festgelegte Groesse.
            if($_FILES['Filedata']['error'][$x]==1)
            {
                $g_message->show('photo_2big', maxUploadSize());
                $x = 5;
            }
        }
    }
    //Kontrolle ob Bilder ausgewaehlt wurden
    if($counter==0)
    {
        $g_message->show('photodateiphotoup');
    }
    // Bilder wurden erfolgreich hochgeladen -> Upload-Seite aus der Navi-Klasse entfernen
    $_SESSION['navigation']->deleteLastUrl();
}//Kontrollmechanismen

//Bildverarbeitung
$new_quantity = $photo_album->getValue('pho_quantity');
for($act_upload_nr = 0; $act_upload_nr < 5; $act_upload_nr++)
{
    if($_GET['uploadmethod'] == 1)
    {
        $temp_filename = $_FILES['Filedata']['tmp_name'][$act_upload_nr];
        $filename = $_FILES['Filedata']['name'][$act_upload_nr];
    }
    else
    {
        if(!is_uploaded_file($_FILES['Filedata']['tmp_name']))
        {
            echo 'Fehler beim Dateiupload!';
        }
        $temp_filename = $_FILES['Filedata']['tmp_name'];
        $filename = $_FILES['Filedata']['name'];
    }
    //Datei wurde hochgeladen
    if(isset($_FILES['Filedata']['name']) && is_uploaded_file($temp_filename))
    {
        $new_quantity++;
    	
    	if($_GET['uploadmethod'] == 1)
    	{
    		echo '<br /><br />Albumbild '.$new_quantity.':<br />';
    	}
    	
    	// Sonderzeichen aus Dateinamen entfernen
    	$image_file = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    	// and set the directory
    	$image_file = SERVER_PATH. '/adm_my_files/photos/upload/'.$image_file;	
    	
    	//Bildeigenschaften und Kontrolle
    	//Die Kontrolle muss vor der Objekterzeugung stattfinden
    	$image_properties = getimagesize($temp_filename);
    	
    	//Auflösungskontrolle
    	$image_dimensions = $image_properties[0]*$image_properties[1];
    	if($image_dimensions > processableImageSize())
    	{
        	echo 'Auflösung größer '.round(processableImageSize()/1000000, 2).' MPixel';
    	}
    	
    	//Typkontrolle
        elseif($image_properties['mime'] != 'image/jpeg' && $image_properties['mime'] != 'image/png')
        {
            $g_message->show('dateiendungphotoup');
        }
    	
    	//Bild in Tempordner verschieben und weiterverarbeiten
    	elseif (move_uploaded_file($temp_filename, $image_file)) 
    	{ 
    
    		//Bildobjekt erzeugen und scaliert speichern
    	    $image = new Image($image_file);
            $image->setImageType('jpeg');
            $image->scale($g_preferences['photo_save_scale']);
            $image->copyToFile(null, $ordner.'/'.$new_quantity.'.jpg');
            $image->delete();
            
            //Nachsehen ob Thumnailordner existiert
            if(!file_exists($ordner.'/thumbnails'))
            {
                mkdir($ordner.'/thumbnails', 0777);
                chmod($ordner.'/thumbnails', 0777);
            }
    
            //Thumbnail speichern
            $image = new Image($image_file);
            $image->scale($g_preferences['photo_thumbs_scale']);
            $image->copyToFile(null, $ordner.'/thumbnails/'.$new_quantity.'.jpg');
            $image->delete(); 
      
            //Loeschen des Bildes aus Arbeitsspeicher
            if(file_exists($image_file))
            {
                unlink($image_file);
            } 
    
            //Loeschen des Bildes aus Arbeitsspeicher
        	if(file_exists($image_file))
        	{
            	unlink($image_file);
        	}
        	
            //Endkontrolle
            if(file_exists($ordner.'/'.$new_quantity.'.jpg'))
            {
                //Aendern der Datenbankeintaege
                $photo_album->setValue('pho_quantity', $photo_album->getValue('pho_quantity')+1);
                $photo_album->save(); 
                
                if($_GET['uploadmethod']  == 1)
                {
                	 echo '
                	  <img class="photoOutput" 
                	  src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$photo_album->getValue('pho_id').'&pic_nr='.$new_quantity.'&pho_begin='.$photo_album->getValue('pho_begin').'&scal=300&side=x" 
                	  alt="Bild '.$new_quantity.'" title="Bild '.$new_quantity.'">
                	  <br />';
                }
                else
                {
                	echo 'Bild erfolgreich gespeichert!';exit();
                }          
            }
            else
            {
                $new_quantity --;
                echo 'Das Bild konnte nicht verarbeitet werden!';
            }	        
    	}
    	else
    	{
    	   echo 'Fehler beim Dateiupload!';
    	}
    }
}


//Bei Klassischem Upload Reste der Seitenausgabe
if($_GET['uploadmethod'] == 1)
{
	//Buttons
	echo '
		<hr />
		<ul class="iconTextLinkList">
		    <li>
		        <span class="iconTextLink">
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$photo_album->getValue('pho_id').'">
		            	<img src="'. THEME_PATH. '/icons/application_view_tile.png" alt="Übersicht" />
		            </a>
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$photo_album->getValue('pho_id').'">Übersicht</a>
		        </span>
		    </li>
		    <li>
		        <span class="iconTextLink">
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photoupload.php?pho_id='.$photo_album->getValue('pho_id').'&amp;mode=1">
		            	<img src="'. THEME_PATH. '/icons/photo_upload.png" alt="Weitere Bilder hochladen" />
		            </a>
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photoupload.php?pho_id='.$photo_album->getValue('pho_id').'&amp;mode=1">Weitere Bilder hochladen</a>
		        </span>
		    </li>
		 </ul>
   	<br /><br />';
    
    //Seitenende
	require(THEME_SERVER_PATH. '/overall_footer.php');
}      
?>