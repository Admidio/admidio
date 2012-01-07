<?php
/******************************************************************************
 * Saves photos from file upload in filesystem
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * pho_id       : album id to which the photos are assigned
 * uploadmethod : 1 - classic html upload
 *				  2 - Flexuploader
 *
 *****************************************************************************/
if($_GET['uploadmethod'] == 2)
{
    // Cookies wurden uebergeben, nun wieder in Cookievariable kopieren
    foreach($_GET as $key => $value)
    {
        if(strpos($key, 'ADMIDIO') !== false)
        {
            $_COOKIE[$key] = $value;
        }
    }
}

require_once('../../system/classes/table_photos.php');
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/image.php');

// Initialize and check the parameters
$getPhotoId      = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', null, true);
$getUploadmethod = admFuncVariableIsValid($_GET, 'uploadmethod', 'numeric', null, true);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$gCurrentUser->editPhotoRight())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
}
//var_dump($_FILES['Filedata']['size']);exit();
//Bei Klassischem upload erstmal testen ob Alle Dateien angekommen sind bei Flex reichen die Kontrollen in der Verarbeitung
if($getUploadmethod == 1)
{
    //zaehlen wieviele Fotos hochgeladen werden sollen und ob alle Uploads Fehlerfrei sind
    $counter=0;
    if(isset($_FILES['Filedata']))
    {
        for($x=0; $x<=4; $x++)
        {
            //Datei wurde hochgeladen
            if(isset($_FILES['Filedata']['name'][$x]) && $_FILES['Filedata']['size'][$x]!=0)
            {
                $counter++;
    
                //Die hochgeladene Datei ueberschreitet die in der Anweisung upload_max_filesize in php.ini festgelegte Groesse.
                if($_FILES['Filedata']['error'][$x]==1)
                {
                    $gMessage->show($gL10n->get('PHO_PHOTO_FILES_TO_LARGE', admFuncMaxUploadSize()));
                    $x = 5;
                }
            }
        }
    }
    //Kontrolle ob Fotos ausgewaehlt wurden
    if($counter==0)
    {
        $gMessage->show($gL10n->get('PHO_NO_FILES_SELECTED'));
    }
    // Fotos wurden erfolgreich hochgeladen -> Upload-Seite aus der Navi-Klasse entfernen
    $_SESSION['navigation']->deleteLastUrl();
}

//bei Bedarf Uploadordner erzeugen
if(file_exists(SERVER_PATH. '/adm_my_files/photos/upload') == false)
{
    require_once('../../system/classes/folder.php');
    $folder = new Folder(SERVER_PATH. '/adm_my_files/photos');
    $folder->createFolder('upload', true);
}

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $getPhotoId)
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $gDb;
}
else
{
    $photo_album = new TablePhotos($gDb, $getPhotoId);
    $_SESSION['photo_album'] =& $photo_album;
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($photo_album->getValue('pho_org_shortname') != $gCurrentOrganization->getValue('org_shortname'))
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

//Ordnerpfad
$ordner = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin', 'Y-m-d').'_'.$photo_album->getValue('pho_id');

//Bei Klassischem Upload beginn der Seitenausgabe
if($getUploadmethod == 1)
{
	//Photomodulspezifische CSS laden
	$gLayout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/photos.css" type="text/css" media="screen" />';

	// Html-Kopf ausgeben
	$gLayout['title'] = $gL10n->get('PHO_UPLOAD_PHOTOS');
	require(SERVER_PATH. '/adm_program/system/overall_header.php');
	
	echo '
	<h1 class="moduleHeadline">'.$gL10n->get('PHO_UPLOAD_PHOTOS').'</h1>
    <p> '.$gL10n->get('SYS_PLEASE_WAIT').'...<br /><br />
        '.$gL10n->get('PHO_SHOWN_ON_READY').'<strong>('.$photo_album->getValue('pho_name').')</strong>
    </p>';
}


//Bildverarbeitung
$new_quantity = $photo_album->getValue('pho_quantity');
//Anzahl der Durchläufe
$numLoops = 1;
if($getUploadmethod == 1)
{
    $numLoops = 5;
}

for($act_upload_nr = 0; $act_upload_nr < $numLoops; $act_upload_nr++)
{
    if($getUploadmethod == 1)
    {
        $temp_filename = $_FILES['Filedata']['tmp_name'][$act_upload_nr];
        $filename = $_FILES['Filedata']['name'][$act_upload_nr];
    }
    else
    {
        if(!is_uploaded_file($_FILES['Filedata']['tmp_name']))
        {
            echo $gL10n->get('SYS_UPLOAD_ERROR');
        }
        $temp_filename = $_FILES['Filedata']['tmp_name'];
        $filename = $_FILES['Filedata']['name'];
    }
    //Datei wurde hochgeladen
    if(isset($_FILES['Filedata']['name']) && is_uploaded_file($temp_filename))
    {
        $new_quantity++;
    	
    	if($getUploadmethod == 1)
    	{
    		echo '<br /><br />'.$gL10n->get('PHO_PHOTO').' '.$new_quantity.':<br />';
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
    	if($image_dimensions > admFuncProcessableImageSize())
    	{
        	echo $gL10n->get('PHO_RESOLUTION_MORE_THAN').' '.round(admFuncProcessableImageSize()/1000000, 2).' '.$gL10n->get('MEGA_PIXEL');
    	}
    	
    	//Typkontrolle
        elseif($image_properties['mime'] != 'image/jpeg' && $image_properties['mime'] != 'image/png')
        {
            $gMessage->show($gL10n->get('PHO_PHOTO_FORMAT_INVALID'));
        }
    	
    	//Bild in Tempordner verschieben und weiterverarbeiten
    	elseif (move_uploaded_file($temp_filename, $image_file)) 
    	{ 
    
    		//Bildobjekt erzeugen und scaliert speichern
    	    $image = new Image($image_file);
            $image->setImageType('jpeg');
            $image->scaleLargerSide($gPreferences['photo_save_scale']);
            $image->copyToFile(null, $ordner.'/'.$new_quantity.'.jpg');
            $image->delete();
            
            //Nachsehen ob Thumnailordner existiert
            if(file_exists($ordner.'/thumbnails') == false)
            {
                require_once('../../system/classes/folder.php');
                $folder = new Folder($ordner);
                $folder->createFolder('thumbnails', true);
            }
    
            //Thumbnail speichern
            $image = new Image($image_file);
            $image->scaleLargerSide($gPreferences['photo_thumbs_scale']);
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
                
                if($getUploadmethod  == 1)
                {
                	 echo '
                	  <img class="photoOutput" 
                	  src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$photo_album->getValue('pho_id').'&photo_nr='.$new_quantity.'&pho_begin='.$photo_album->getValue('pho_begin', 'Y-m-d').'&max_width=300&max_height=200" 
                	  alt="'.$gL10n->get('PHO_PHOTO').' '.$new_quantity.'" title="'.$gL10n->get('PHO_PHOTO').' '.$new_quantity.'">
                	  <br />';
                }
                else
                {
                	echo $gL10n->get('PHO_PHOTO_UPLOAD_SUCCESS');exit();
                }          
            }
            else
            {
                $new_quantity --;
                echo $gL10n->get('PHO_PHOTO_PROCESSING_ERROR');
            }	        
    	}
    	else
    	{
    	   echo $gL10n->get('SYS_UPLOAD_ERROR');
    	}
    }
}

//Bei Klassischem Upload Reste der Seitenausgabe
if($getUploadmethod == 1)
{
	//Buttons
	echo '
		<hr />
		<ul class="iconTextLinkList">
		    <li>
		        <span class="iconTextLink">
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$photo_album->getValue('pho_id').'"  title="'.$gL10n->get('SYS_OVERVIEW').'">
		            	<img src="'. THEME_PATH. '/icons/application_view_tile.png" />
		            </a>
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$photo_album->getValue('pho_id').'"  title="'.$gL10n->get('SYS_OVERVIEW').'">'.$gL10n->get('SYS_OVERVIEW').'</a>
		        </span>
		    </li>
		    <li>
		        <span class="iconTextLink">
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photoupload.php?pho_id='.$photo_album->getValue('pho_id').'&amp;uploadmethod=1" title="'.$gL10n->get('PHO_UPLOAD_MORE').'">
		            	<img src="'. THEME_PATH. '/icons/photo_upload.png" alt="Weitere Fotos hochladen" />
		            </a>
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photoupload.php?pho_id='.$photo_album->getValue('pho_id').'&amp;uploadmethod=1"  title="'.$gL10n->get('PHO_UPLOAD_MORE').'">'.$gL10n->get('PHO_UPLOAD_MORE').'</a>
		        </span>
		    </li>
		 </ul>
   	<br /><br />';
    
    //Seitenende
	require(SERVER_PATH. '/adm_program/system/overall_footer.php');
}      
?>