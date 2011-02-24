<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * Bild: welches Bild soll angezeigt werden
 * pho_id: Id des Albums aus der das Bild stammt
 *
 *****************************************************************************/

require_once('../../system/classes/table_photos.php');
require_once('../../system/common.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}
elseif($g_preferences['enable_photo_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Uebergabevariablen pruefen

if(isset($_GET['pho_id']) && is_numeric($_GET['pho_id']) == false)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['bild']) && is_numeric($_GET['bild']) == false)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

//Uebernahme der uebergebenen variablen
$pho_id = $_GET['pho_id'];
$bild   = $_GET['bild'];

//erfassen des Albums falls noch nicht in Session gespeichert
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $pho_id)
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $g_db;
}
else
{
    $photo_album = new TablePhotos($g_db, $pho_id);
    $_SESSION['photo_album'] =& $photo_album;
}

//Ordnerpfad zusammensetzen
$ordner_foto = '/adm_my_files/photos/'.$photo_album->getValue('pho_begin').'_'.$photo_album->getValue('pho_id');
$ordner      = SERVER_PATH. $ordner_foto;
$ordner_url  = $g_root_path. $ordner_foto;

//Naechstes und Letztes Bild
$prev_image = $bild-1;
$next_image = $bild+1;
$url_prev_image = '#';
$url_next_image = '#';
$url_act_image  = $g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$pho_id.'&amp;pic_nr='.$bild.'&amp;pho_begin='.$photo_album->getValue('pho_begin', 'Y-m-d').'&amp;max_width='.$g_preferences['photo_show_width'].'&amp;max_height='.$g_preferences['photo_show_height'];

if($prev_image > 0)
{
    $url_prev_image = $g_root_path. '/adm_program/modules/photos/photo_presenter.php?bild='. $prev_image. '&pho_id='. $pho_id;
}
if($next_image <= $photo_album->getValue('pho_quantity'))
{
    $url_next_image = $g_root_path. '/adm_program/modules/photos/photo_presenter.php?bild='. $next_image. '&pho_id='. $pho_id;
}

$body_with   = $g_preferences['photo_show_width']  + 20;

if($g_preferences['photo_show_mode']==1)
{
	echo '<div style="width:'.$g_preferences['photo_show_width'].'px;height:'.$g_preferences['photo_show_height'].'px;"><img style="margin: auto; border: medium none; display: block; float: none; cursor: pointer;" id="cboxPhoto" src="'.$url_act_image.'" ></div>';
}
else
{
	
	//Photomodulspezifische CSS laden
	$g_layout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/photos.css" type="text/css" media="screen" />';
	
	// Html-Kopf ausgeben
	$g_layout['title']    = $g_l10n->get('PHO_PHOTO_ALBUMS');
	
	//wenn Popupmode oder Colorbox, dann normalen Kopf unterdruecken
	if($g_preferences['photo_show_mode']==0)
	{                      
		$g_layout['includes'] = false;
	}
	
	require(THEME_SERVER_PATH. '/overall_header.php');
	
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
		echo '<div><img class="photoOutput" src="'.$url_act_image.'" alt="'.$g_l10n->get('SYS_PHOTO').'" /></div>';
	}
	
	//Vor und zurück Buttons
	echo'
	<ul class="iconTextLinkList">';
		//Vor und zurueck buttons
		if($prev_image > 0)
		{
			echo'<li>
				<span class="iconTextLink">
					<a href="'.$url_prev_image.'"><img src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('PHO_PREVIOUS_PHOTO').'" /></a>
					<a href="'.$url_prev_image.'">'.$g_l10n->get('PHO_PREVIOUS_PHOTO').'</a>
				</span>
			</li>';
		}
		if($next_image <= $photo_album->getValue('pho_quantity'))
		{
			echo'<li>
				<span class="iconTextLink">
					<a href="'.$url_next_image.'">'.$g_l10n->get('PHO_NEXT_PHOTO').'</a>
					<a href="'.$url_next_image.'"><img src="'. THEME_PATH. '/icons/forward.png" alt="'.$g_l10n->get('PHO_NEXT_PHOTO').'" /></a>
				</span>
			</li>';
		}
		echo'
	</ul>';    
	
	if($g_preferences['photo_show_mode']==0)
	{   
		// im Popupmodus Fenster schliessen Button
		echo'<ul class="iconTextLinkList">
			<li>
				<span class="iconTextLink">
					<a href="javascript:parent.window.close()"><img src="'. THEME_PATH. '/icons/door_in.png" alt="'.$g_l10n->get('SYS_CLOSE_WINDOW').'" /></a>
					<a href="javascript:parent.window.close()">'.$g_l10n->get('SYS_CLOSE_WINDOW').'</a>
				</span>
			</li>
		</ul>';
	}
	elseif($g_preferences['photo_show_mode']==2)
	{   
		// im Fenstermodus zurueck zur Uebersicht Button
		echo'<ul class="iconTextLinkList">
			<li>
				<span class="iconTextLink">
					<a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$pho_id.'"><img src="'. THEME_PATH. '/icons/application_view_tile.png" alt="'.$g_l10n->get('PHO_BACK_TO_ALBUM').'" /></a>
					<a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$pho_id.'">'.$g_l10n->get('PHO_BACK_TO_ALBUM').'</a>
				</span>
			</li>
		</ul>';
	}
	
	
	//Zusatzinformationen zum Album nur wenn im gleichen Fenster
	if($g_preferences['photo_show_mode']==2)
	{	
		echo'
		<p>
			Datum: '.$photo_album->getValue('pho_begin', $g_preferences['system_date']);
			if($photo_album->getValue('pho_end') != $photo_album->getValue('pho_begin')
			&& strlen($photo_album->getValue('pho_end')) > 0)
			{
				echo ' bis '.$photo_album->getValue('pho_end', $g_preferences['system_date']);
			}
			echo '<br />Fotos von: '.$photo_album->getValue('pho_photographers').'
		</p>';
	}
	
	echo'</div></div>';
	require(THEME_SERVER_PATH. '/overall_footer.php');
}

?>