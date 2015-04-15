<?php
/******************************************************************************
 * Show the photo within the Admidio html
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * photo_nr : Number of the photo that should be shown
 * pho_id   : Id of the album of the photo that should be shown
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', array('requireValue' => true));
$getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'numeric', array('requireValue' => true));

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

//nur von eigentlicher OragHompage erreichbar
if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) != 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $gHomepage));
}

//erfassen des Albums falls noch nicht in Session gespeichert
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $getPhotoId)
{
    $photoAlbum =& $_SESSION['photo_album'];
    $photoAlbum->db =& $gDb;
}
else
{
    $photoAlbum = new TablePhotos($gDb, $getPhotoId);
    $_SESSION['photo_album'] =& $photoAlbum;
}

//Ordnerpfad zusammensetzen
$ordner_foto = '/adm_my_files/photos/'.$photoAlbum->getValue('pho_begin', 'Y-m-d').'_'.$photoAlbum->getValue('pho_id');
$ordner      = SERVER_PATH. $ordner_foto;
$ordner_url  = $g_root_path. $ordner_foto;

//Naechstes und Letztes Bild
$previousImage = $getPhotoNr - 1;
$nextImage = $getPhotoNr + 1;
$urlPreviousImage = '#';
$urlNextImage     = '#';
$urlCurrentImage  = $g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$getPhotoNr.'&amp;max_width='.$gPreferences['photo_show_width'].'&amp;max_height='.$gPreferences['photo_show_height'];

if($previousImage > 0)
{
    $urlPreviousImage = $g_root_path. '/adm_program/modules/photos/photo_presenter.php?photo_nr='. $previousImage. '&pho_id='. $getPhotoId;
}
if($nextImage <= $photoAlbum->getValue('pho_quantity'))
{
    $urlNextImage = $g_root_path. '/adm_program/modules/photos/photo_presenter.php?photo_nr='. $nextImage. '&pho_id='. $getPhotoId;
}

// create html page object
$page = new HtmlPage($photoAlbum->getValue('pho_name'));

//wenn Popupmode oder Colorbox, dann normalen Kopf unterdruecken
if($gPreferences['photo_show_mode'] == 0)
{                      
    $page->excludeThemeHtml();
}

if($gPreferences['photo_show_mode'] == 2)
{	
    // get module menu
    $photoPresenterMenu = $page->getMenu();

    // if you have no popup or colorbox then show a button back to the album
	if($gPreferences['photo_show_mode'] == 2)
	{   
    	$photoPresenterMenu->addItem('menu_item_back_to_album', $g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$getPhotoId,
    	                             $gL10n->get('PHO_BACK_TO_ALBUM'), 'application_view_tile.png');
	}

	// show link to navigate to next and previous photos
	if($previousImage > 0)
	{
    	$photoPresenterMenu->addItem('menu_item_previous_photo', $urlPreviousImage,
    	                             $gL10n->get('PHO_PREVIOUS_PHOTO'), 'back.png');
    }
    
	if($nextImage <= $photoAlbum->getValue('pho_quantity'))
	{
    	$photoPresenterMenu->addItem('menu_item_next_photo', $urlNextImage,
    	                             $gL10n->get('PHO_NEXT_PHOTO'), 'forward.png');
    }	
}

// Show photo with link to next photo
if($nextImage <= $photoAlbum->getValue('pho_quantity'))
{
	$page->addHtml('<div class="admidio-img-presenter"><a href="'.$urlNextImage.'"><img src="'.$urlCurrentImage.'" alt="Foto"></a></div>');
}
else
{
	$page->addHtml('<div class="admidio-img-presenter"><img src="'.$urlCurrentImage.'" alt="'.$gL10n->get('SYS_PHOTO').'" /></div>');
}

if($gPreferences['photo_show_mode'] == 0)
{
    // in popup mode show buttons for prev, next and close
    $page->addHtml('
    <div class="btn-group">
        <button class="btn btn-default" onclick="window.location.href=\''.$urlPreviousImage.'\'"><img 
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('PHO_PREVIOUS_PHOTO').'" />'.$gL10n->get('PHO_PREVIOUS_PHOTO').'</button>
        <button class="btn btn-default" onclick="parent.window.close()"><img 
            src="'. THEME_PATH. '/icons/door_in.png" alt="'.$gL10n->get('SYS_CLOSE_WINDOW').'" />'.$gL10n->get('SYS_CLOSE_WINDOW').'</button>
        <button class="btn btn-default" onclick="window.location.href=\''.$urlNextImage.'\'"><img 
            src="'. THEME_PATH. '/icons/forward.png" alt="'.$gL10n->get('PHO_NEXT_PHOTO').'" />'.$gL10n->get('PHO_NEXT_PHOTO').'</button>
    </div>');
}
elseif($gPreferences['photo_show_mode'] == 2)
{
    // if no popup mode then show additional album informations
    $datePeriod = $photoAlbum->getValue('pho_begin', $gPreferences['system_date']);
    
	if($photoAlbum->getValue('pho_end') != $photoAlbum->getValue('pho_begin')
	&& strlen($photoAlbum->getValue('pho_end')) > 0)
	{
		$datePeriod .= ' '.$gL10n->get('SYS_DATE_TO').' '.$photoAlbum->getValue('pho_end', $gPreferences['system_date']);
	}
    
	$page->addHtml('
	<div class="row">
	    <div class="col-sm-2 col-xs-4">'.$gL10n->get('SYS_DATE').'</div>
	    <div class="col-sm-4 col-xs-8"><strong>'.$datePeriod.'</strong></div>
    </div>
	<div class="row">
	    <div class="col-sm-2 col-xs-4">'.$gL10n->get('PHO_PHOTOGRAPHER').'</div>
	    <div class="col-sm-4 col-xs-8"><strong>'.$photoAlbum->getValue('pho_photographers').'</strong></div>
	</div>');
}
	
// show html of complete page
$page->show();

?>