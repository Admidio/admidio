<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * photo_nr: welches Bild soll angezeigt werden
 * pho_id:   Id des Albums aus der das Bild stammt
 *
 *****************************************************************************/

require_once('../../system/classes/table_photos.php');
require_once('../../system/common.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', null, true);
$getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'numeric', null, true);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_photo_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

//erfassen des Albums falls noch nicht in Session gespeichert
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

//Ordnerpfad zusammensetzen
$ordner_foto = '/adm_my_files/photos/'.$photo_album->getValue('pho_begin', 'Y-m-d').'_'.$photo_album->getValue('pho_id');
$ordner      = SERVER_PATH. $ordner_foto;
$ordner_url  = $g_root_path. $ordner_foto;

//Naechstes und Letztes Bild
$prev_image = $getPhotoNr - 1;
$next_image = $getPhotoNr + 1;
$url_prev_image = '#';
$url_next_image = '#';
$url_act_image  = $g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;pho_begin='.$photo_album->getValue('pho_begin', 'Y-m-d').'&amp;max_width='.$gPreferences['photo_show_width'].'&amp;max_height='.$gPreferences['photo_show_height'];

if($prev_image > 0)
{
    $url_prev_image = $g_root_path. '/adm_program/modules/photos/photo_presenter.php?photo_nr='. $prev_image. '&pho_id='. $getPhotoId;
}
if($next_image <= $photo_album->getValue('pho_quantity'))
{
    $url_next_image = $g_root_path. '/adm_program/modules/photos/photo_presenter.php?photo_nr='. $next_image. '&pho_id='. $getPhotoId;
}

$body_with   = $gPreferences['photo_show_width']  + 20;

if($gPreferences['photo_show_mode']==1)
{
	echo '<div style="width:'.$gPreferences['photo_show_width'].'px;height:'.$gPreferences['photo_show_height'].'px;"><img style="margin: auto; border: medium none; display: block; float: none; cursor: pointer;" id="cboxPhoto" src="'.$url_act_image.'" ></div>';
}
else
{
	
	//Photomodulspezifische CSS laden
	$gLayout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/photos.css" type="text/css" media="screen" />';
	
	// Html-Kopf ausgeben
	$gLayout['title']    = $gL10n->get('PHO_PHOTO_ALBUMS');
	
	//wenn Popupmode oder Colorbox, dann normalen Kopf unterdruecken
	if($gPreferences['photo_show_mode']==0)
	{                      
		$gLayout['includes'] = false;
	}
	
	require(SERVER_PATH. '/adm_program/system/overall_header.php');
	
	//Ausgabe der Kopfzelle mit Ueberschrift, Photographen und Datum
	//untere Zelle mit Buttons Bild und Fenster Schließen Button

	echo '
	<div class="formLayout" id="photo_presenter" style="width: '.$body_with.'px;">
		<div class="formHead">'.$photo_album->getValue('pho_name').'</div>
		<div class="formBody">';
	
	//Ausgabe Bild 
	if($next_image <= $photo_album->getValue('pho_quantity'))
	{
		echo '<div><a href="'.$url_next_image.'"><img class="photoOutput" src="'.$url_act_image.'" alt="Foto"></a></div>';
	}
	else
	{
		echo '<div><img class="photoOutput" src="'.$url_act_image.'" alt="'.$gL10n->get('SYS_PHOTO').'" /></div>';
	}
	
	//Vor und zurück Buttons
	echo'
	<ul class="iconTextLinkList">';
		//Vor und zurueck buttons
		if($prev_image > 0)
		{
			echo'<li>
				<span class="iconTextLink">
					<a href="'.$url_prev_image.'"><img src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('PHO_PREVIOUS_PHOTO').'" /></a>
					<a href="'.$url_prev_image.'">'.$gL10n->get('PHO_PREVIOUS_PHOTO').'</a>
				</span>
			</li>';
		}
		if($next_image <= $photo_album->getValue('pho_quantity'))
		{
			echo'<li>
				<span class="iconTextLink">
					<a href="'.$url_next_image.'">'.$gL10n->get('PHO_NEXT_PHOTO').'</a>
					<a href="'.$url_next_image.'"><img src="'. THEME_PATH. '/icons/forward.png" alt="'.$gL10n->get('PHO_NEXT_PHOTO').'" /></a>
				</span>
			</li>';
		}
		echo'
	</ul>';    
	
	if($gPreferences['photo_show_mode']==0)
	{   
		// im Popupmodus Fenster schliessen Button
		echo'<ul class="iconTextLinkList">
			<li>
				<span class="iconTextLink">
					<a href="javascript:parent.window.close()"><img src="'. THEME_PATH. '/icons/door_in.png" alt="'.$gL10n->get('SYS_CLOSE_WINDOW').'" /></a>
					<a href="javascript:parent.window.close()">'.$gL10n->get('SYS_CLOSE_WINDOW').'</a>
				</span>
			</li>
		</ul>';
	}
	elseif($gPreferences['photo_show_mode']==2)
	{   
		// im Fenstermodus zurueck zur Uebersicht Button
		echo'<ul class="iconTextLinkList">
			<li>
				<span class="iconTextLink">
					<a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$getPhotoId.'"><img src="'. THEME_PATH. '/icons/application_view_tile.png" alt="'.$gL10n->get('PHO_BACK_TO_ALBUM').'" /></a>
					<a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$getPhotoId.'">'.$gL10n->get('PHO_BACK_TO_ALBUM').'</a>
				</span>
			</li>
		</ul>';
	}
	
	
	//Zusatzinformationen zum Album nur wenn im gleichen Fenster
	if($gPreferences['photo_show_mode']==2)
	{	
		echo'
		<p>
			Datum: '.$photo_album->getValue('pho_begin', $gPreferences['system_date']);
			if($photo_album->getValue('pho_end') != $photo_album->getValue('pho_begin')
			&& strlen($photo_album->getValue('pho_end')) > 0)
			{
				echo ' bis '.$photo_album->getValue('pho_end', $gPreferences['system_date']);
			}
			echo '<br />Fotos von: '.$photo_album->getValue('pho_photographers').'
		</p>';
	}
	
	echo'</div></div>';
	require(SERVER_PATH. '/adm_program/system/overall_footer.php');
}

?>