<?php
/******************************************************************************
 * Photoupload
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id: id des Albums zu dem die Fotos hinzugefuegt werden sollen
 * uploadmethod: 1 - Klassisch
 *				 2 - Flexuploader
 *
 *****************************************************************************/
if(isset($_GET['uploadmethod']) && is_numeric($_GET['pho_id']) && $_GET['uploadmethod'] == 2)
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

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$g_current_user->editPhotoRight())
{
    $g_message->show($g_l10n->get('PHO_PHR_NO_RIGHTS'));
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
   $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}
if(!isset($_GET['uploadmethod']) || (isset($_GET['uploadmethod']) && !is_numeric($_GET['pho_id'])))
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));  
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($photo_album->getValue('pho_org_shortname') != $g_organization)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if (empty($_POST) && $_GET['uploadmethod'] == 1)
{
    $g_message->show($g_l10n->get('PHO_PHR_NO_FILES_OR_TO_LARGE', ini_get(post_max_size)));
}

//bei Bedarf Uploadodner erzeugen
if(!file_exists(SERVER_PATH. '/adm_my_files/photos/upload'))
{
    require_once('../../system/classes/folder.php');
    $folder = new Folder(SERVER_PATH. '/adm_my_files/photos');
    $folder->createFolder('upload', true);
}

//Ordnerpfad
$ordner = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin', 'Y-m-d').'_'.$photo_album->getValue('pho_id');

//Bei Klassischem Upload beginn der Seitenausgabe
if($_GET['uploadmethod'] == 1)
{
	//Photomodulspezifische CSS laden
	$g_layout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/photos.css" type="text/css" media="screen" />';

	// Html-Kopf ausgeben
	$g_layout['title'] = $g_l10n->get('PHO_UPLOAD_PHOTOS');
	require(THEME_SERVER_PATH. '/overall_header.php');
	
	echo '
	<h1 class="moduleHeadline">'.$g_l10n->get('PHO_UPLOAD_PHOTOS').'</h1>
    <p> '.$g_l10n->get('SYS_PLEASE_WAIT').'...<br /><br />
        '.$g_l10n->get('PHO_PHR_SHOWN_ON_READY').'<strong>('.$photo_album->getValue('pho_name').')</strong>
    </p>';
}

//Bei Klassischem upload erstmal testen ob Alle Dateien angekommen sind bei Flex reichen die Kontrollen in der Verarbeitung
if(isset($_POST['upload']) && $_GET['uploadmethod'] == 1)
{
    //zaehlen wieviele Fotos hochgeladen werden sollen und ob alle Uploads Fehlerfrei sind
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
                $g_message->show($g_l10n->get('PHO_PHR_PHOTO_FILES_TO_LARGE', maxUploadSize()));
                $x = 5;
            }
        }
    }
    //Kontrolle ob Fotos ausgewaehlt wurden
    if($counter==0)
    {
        $g_message->show($g_l10n->get('PHO_PHR_NO_FILES_SELECTED'));
    }
    // Fotos wurden erfolgreich hochgeladen -> Upload-Seite aus der Navi-Klasse entfernen
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
            echo $g_l10n->get('SYS_UPLOAD_ERROR');
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
    		echo '<br /><br />'.$g_l10n->get('PHO_PHOTO').$new_quantity.':<br />';
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
        	echo $g_l10n->get('PHP_RESOLUTION_MORE_THAN').' '.round(processableImageSize()/1000000, 2).' '.$g_l10n->get('MEGA_PIXEL');
    	}
    	
    	//Typkontrolle
        elseif($image_properties['mime'] != 'image/jpeg' && $image_properties['mime'] != 'image/png')
        {
            $g_message->show($g_l10n->get('PHO_PHR_PHOTO_FORMAT_INVALID'));
        }
    	
    	//Bild in Tempordner verschieben und weiterverarbeiten
    	elseif (move_uploaded_file($temp_filename, $image_file)) 
    	{ 
    
    		//Bildobjekt erzeugen und scaliert speichern
    	    $image = new Image($image_file);
            $image->setImageType('jpeg');
            $image->scaleLargerSide($g_preferences['photo_save_scale']);
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
            $image->scaleLargerSide($g_preferences['photo_thumbs_scale']);
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
                	  src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$photo_album->getValue('pho_id').'&pic_nr='.$new_quantity.'&pho_begin='.$photo_album->getValue('pho_begin').'&max_width=300&max_height=200" 
                	  alt="'.$g_l10n->get('PHO_PHOTO').' '.$new_quantity.'" title="'.$g_l10n->get('PHO_PHOTO').' '.$new_quantity.'">
                	  <br />';
                }
                else
                {
                	echo $g_l10n->get('PHO_PHOTO_UPLOAD_SUCCESS');exit();
                }          
            }
            else
            {
                $new_quantity --;
                echo $g_l10n->get('PHO_PHOTO_PROCESSING_ERROR');
            }	        
    	}
    	else
    	{
    	   echo $g_l10n->get('SYS_UPLOAD_ERROR');
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
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$photo_album->getValue('pho_id').'"  title="'.$g_l10n->get('PHO_OVERVIEW').'">
		            	<img src="'. THEME_PATH. '/icons/application_view_tile.png" />
		            </a>
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$photo_album->getValue('pho_id').'"  title="'.$g_l10n->get('PHO_OVERVIEW').'">'.$g_l10n->get('PHO_OVERVIEW').'</a>
		        </span>
		    </li>
		    <li>
		        <span class="iconTextLink">
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photoupload.php?pho_id='.$photo_album->getValue('pho_id').'&amp;mode=1" title="'.$g_l10n->get('PHO_PHR_UPLOAD_MORE').'">
		            	<img src="'. THEME_PATH. '/icons/photo_upload.png" alt="Weitere Fotos hochladen" />
		            </a>
		            <a href="'.$g_root_path.'/adm_program/modules/photos/photoupload.php?pho_id='.$photo_album->getValue('pho_id').'&amp;mode=1"  title="'.$g_l10n->get('PHO_PHR_UPLOAD_MORE').'">'.$g_l10n->get('PHO_PHR_UPLOAD_MORE').'</a>
		        </span>
		    </li>
		 </ul>
   	<br /><br />';
    
    //Seitenende
	require(THEME_SERVER_PATH. '/overall_footer.php');
}      
?>