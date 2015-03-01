<?php
/******************************************************************************
 * Show a list of all photo albums
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * pho_id    : Id of album which photos should be shown
 * headline  : Headline of the module that will be displayed
 *             (Default) PHO_PHOTO_ALBUMS
 * start_thumbnail : Number of the thumbnail which is the first that should be shown
 * start     : Position of query recordset where the visual output should start
 * locked    : das Album soll freigegeben/gesperrt werden
 *
 *****************************************************************************/

require_once('../../system/common.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
else if ($gPreferences['enable_photo_module'] == 2)
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

// Initialize and check the parameters
$getPhotoId  = admFuncVariableIsValid($_GET, 'pho_id', 'numeric');
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('PHO_PHOTO_ALBUMS')));
$getStart    = admFuncVariableIsValid($_GET, 'start', 'numeric');
$getPhotoNr  = admFuncVariableIsValid($_GET, 'photo_nr', 'numeric');
$getStartThumbnail = admFuncVariableIsValid($_GET, 'start_thumbnail', 'numeric', array('defaultValue' => 1));
$getLocked   = admFuncVariableIsValid($_GET, 'locked', 'numeric', array('defaultValue' => -1));
$getPhotoNr  = admFuncVariableIsValid($_GET, 'photo_nr', 'numeric');

unset($_SESSION['photo_album_request']);
unset($_SESSION['ecard_request']);

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if (isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $getPhotoId)
{
    $photoAlbum =& $_SESSION['photo_album'];
    $photoAlbum->db =& $gDb;
}
else
{
    // einlesen des Albums falls noch nicht in Session gespeichert
    $photoAlbum = new TablePhotos($gDb);
    if($getPhotoId > 0)
    {
        $photoAlbum->readDataById($getPhotoId);
    }

    $_SESSION['photo_album'] =& $photoAlbum;
}

// set headline of module
if($getPhotoId > 0)
{
    $headline = $photoAlbum->getValue('pho_name');
}
else
{
    $headline = $getHeadline;
}

//Wurde keine Album uebergeben kann das Navigationsstack zurueckgesetzt werden
if ($getPhotoId == 0)
{
    $gNavigation->clear();
}

//URL auf Navigationstack ablegen
$gNavigation->addUrl(CURRENT_URL, $headline);


// pruefen, ob Album zur aktuellen Organisation gehoert
if($getPhotoId > 0 && $photoAlbum->getValue('pho_org_shortname') != $gCurrentOrganization->getValue('org_shortname'))
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

/*********************LOCKED************************************/
//Falls gefordert und Foto-edit-rechte, aendern der Freigabe
if($getLocked=='1' || $getLocked=='0')
{
    // erst pruefen, ob der User Fotoberarbeitungsrechte hat
    if(!$gCurrentUser->editPhotoRight())
    {
        $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    }
    
    $photoAlbum->setValue('pho_locked', $getLocked);
    $photoAlbum->save();

    //Zurueck zum Elternalbum    
    $getPhotoId = $photoAlbum->getValue('pho_pho_id_parent');
    $photoAlbum->readDataById($getPhotoId);
}

/*********************HTML_PART*******************************/

// create html page object
$page = new HtmlPage();

// add rss feed to announcements
if($gPreferences['enable_rss'] == 1)
{
    $page->addRssFile($g_root_path.'/adm_program/modules/photos/rss_photos.php?headline='.$getHeadline, $gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname'). ' - '.$getHeadline));
};

if($gCurrentUser->editPhotoRight())
{
    $page->addJavascript('
        // rotate image
        function imgrotate(img, direction) {
            $.get("'.$g_root_path.'/adm_program/modules/photos/photo_function.php", {pho_id: '.$getPhotoId.', photo_nr: img, job: "rotate", direction: direction}, function(data){
                //Anhängen der Zufallszahl ist nötig um den Browsercache zu überlisten                    
                $("#img_"+img).attr("src", "photo_show.php?pho_id='.$getPhotoId.'&photo_nr="+img+"&thumb=1&rand="+Math.random());
                return false;
            });
        }');
}

if($gPreferences['photo_show_mode']==1)
{
    $page->addCssFile($g_root_path.'/adm_program/libs/colorbox/colorbox.css');
    $page->addJavascriptFile($g_root_path.'/adm_program/libs/colorbox/jquery.colorbox.js');
    $page->addJavascriptFile($g_root_path.'/adm_program/libs/colorbox/i18n/jquery.colorbox-'.$gPreferences['system_language'].'.js');
    $page->addJavascript('
        $("a[rel=\'colorboxPictures\']").colorbox({slideshow:true,
                                                   slideshowAuto:false,
                                                   slideshowSpeed:'.($gPreferences['photo_slideshow_speed']*1000).',
                                                   preloading:true}); ', true);
}

$page->addJavascript('
    $("body").on("hidden.bs.modal", ".modal", function () { $(this).removeData("bs.modal"); location.reload(); });
    $("#menu_item_upload_photo").attr("data-toggle", "modal");
    $("#menu_item_upload_photo").attr("data-target", "#admidio_modal");', true);

// if a photo number was committed then simulate a left mouse click
if($getPhotoNr > 0)
{
    $page->addJavascript('$("#img_'.$getPhotoNr.'").trigger("click");', true);
}

// show module headline
$page->addHeadline($headline);

// create module menu
$photosMenu = new HtmlNavbar('menu_photos', $headline, $page);

if($photoAlbum->getValue('pho_id') > 0)
{
    $photosMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
}

if($gCurrentUser->editPhotoRight())
{
	// show link to create new album
	$photosMenu->addItem('menu_item_new_album', $g_root_path.'/adm_program/modules/photos/photo_album_new.php?mode=new&amp;pho_id='.$getPhotoId, 
								$gL10n->get('PHO_CREATE_ALBUM'), 'add.png');
								
	if($getPhotoId > 0)
	{
		// show link to upload photos
		$photosMenu->addItem('menu_item_upload_photo', $g_root_path.'/adm_program/modules/photos/photoupload.php?pho_id='.$getPhotoId, 
									$gL10n->get('PHO_UPLOAD_PHOTOS'), 'photo_upload.png');
	}
}

//show link to download photos if enabled
if($gPreferences['photo_download_enabled']==1 && $getPhotoId > 0)
{
        //show link to download photos
        $photosMenu->addItem('menu_item_download_photos', $g_root_path.'/adm_program/modules/photos/photo_download.php?pho_id='.$getPhotoId, 
                                                $gL10n->get('PHO_DOWNLOAD_PHOTOS'), 'page_white_compressed.png');
}

if($gCurrentUser->isWebmaster())
{
	// show link to system preferences of photos
	$photosMenu->addItem('menu_item_preferences_photos', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=photos', 
								$gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right');
}

$page->addHtml($photosMenu->show(false));

//Breadcrump bauen
$navilink = '';
$pho_parent_id = $photoAlbum->getValue('pho_pho_id_parent');
$photoAlbum_parent = new TablePhotos($gDb);

while ($pho_parent_id > 0)
{
    // Einlesen des Eltern Albums
    $photoAlbum_parent->readDataById($pho_parent_id);
    
    //Link zusammensetzen
    $navilink = '<li><a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$photoAlbum_parent->getValue('pho_id').'">'.
        $photoAlbum_parent->getValue('pho_name').'</a></li>'.$navilink;

    //Elternveranst
    $pho_parent_id = $photoAlbum_parent->getValue('pho_pho_id_parent');
}

if($getPhotoId > 0)
{
    //Ausgabe des Linkpfads
    $page->addHtml('<ol class="breadcrumb">
            <li><a href="'.$g_root_path.'/adm_program/modules/photos/photos.php"><img src="'. THEME_PATH. '/icons/application_view_tile.png" alt="'.$gL10n->get('PHO_PHOTO_ALBUMS').'" /></a>
            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php">'.$gL10n->get('PHO_PHOTO_ALBUMS').'</a></li>'.$navilink.'
            &nbsp;&gt;&nbsp;'.$photoAlbum->getValue('pho_name').'         
        </ol>');
}

/*************************THUMBNAILS**********************************/
//Nur wenn uebergebenes Album Bilder enthaelt
if($photoAlbum->getValue('pho_quantity') > 0)
{        
    //Popupfenstergröße
    $popup_height = $gPreferences['photo_show_height']+210;
    $popup_width  = $gPreferences['photo_show_width']+70;
    
    // remember first and last photo nr
    $firstPhotoNr = 1;
    $lastPhotoNr  = 0;
    
    //Wenn Bild übergeben wurde richtige Albenseite öffnen
    if($getPhotoNr > 0)
    {
        $firstPhotoNr = (ceil($getPhotoNr/$gPreferences['photo_thumbs_page']) * $gPreferences['photo_thumbs_page']) + 1;
    }
              
    //Thumbnailtabelle
    $photoThumbnailTable = '<div class="row">';
    
    for($actThumbnail = 1; $actThumbnail <= $gPreferences['photo_thumbs_page'] && $actThumbnail <= $photoAlbum->getValue('pho_quantity'); $actThumbnail++)
    {
        //Errechnug welches Bild ausgegeben wird
        $lastPhotoNr = $getPhotoNr + $actThumbnail;

        if($lastPhotoNr <= $photoAlbum->getValue('pho_quantity'))
        {
            $photoThumbnailTable .= '<div class="col-sm-6 col-md-3 album-image" id="div_image_'.$lastPhotoNr.'">';
            
                //Popup-Mode
                if ($gPreferences['photo_show_mode'] == 0)
                {
                    $photoThumbnailTable .= '
                    <img class="thumbnail center-block" id="img_'.$lastPhotoNr.'" onclick="window.open(\''.$g_root_path.'/adm_program/modules/photos/photo_presenter.php?photo_nr='.$lastPhotoNr.'&amp;pho_id='.$getPhotoId.'\',\'msg\', \'height='.$popup_height.', width='.$popup_width.',left=162,top=5\')" 
                        src="photo_show.php?pho_id='.$getPhotoId.'&photo_nr='.$lastPhotoNr.'&thumb=1" alt="'.$lastPhotoNr.'" style="cursor: pointer"/>';
                }

                //Colorbox-Mode
                else if ($gPreferences['photo_show_mode'] == 1)
                {
                    $photoThumbnailTable .= '
                    <a rel="colorboxPictures" href="'.$g_root_path.'/adm_program/modules/photos/photo_presenter.php?photo_nr='.$lastPhotoNr.'&amp;pho_id='.$getPhotoId.'">
                    	<img class="thumbnail center-block" id="img_'.$lastPhotoNr.'" class="photoThumbnail" src="photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$lastPhotoNr.'&amp;thumb=1" alt="'.$lastPhotoNr.'" /></a>';
                }

                //Gleichesfenster-Mode
                else if ($gPreferences['photo_show_mode'] == 2)
                {
                    $photoThumbnailTable .= '
                    <img class="thumbnail center-block" id="img_'.$lastPhotoNr.'" onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/photos/photo_presenter.php?photo_nr='.$lastPhotoNr.'&amp;pho_id='.$getPhotoId.'\'" 
                        src="photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$lastPhotoNr.'&amp;thumb=1" style="cursor: pointer"/>';
                }
                
                if($gCurrentUser->editPhotoRight() || ($gValidLogin == true && $gPreferences['enable_ecard_module'] == 1) || $gPreferences['photo_download_enabled']==1)
                {
                   $photoThumbnailTable .= '<div class="text-center" id="image_preferences_'.$lastPhotoNr.'">'; 
                }
                
                //Buttons fuer Moderatoren
                if($gCurrentUser->editPhotoRight())
                {
                   $photoThumbnailTable .= '
                    <a class="icon-link"  href="javascript:void(0)" onclick="return imgrotate('.$lastPhotoNr.', \'left\')"><img 
                        src="'. THEME_PATH. '/icons/arrow_turn_left.png" alt="'.$gL10n->get('PHO_PHOTO_ROTATE_LEFT').'" title="'.$gL10n->get('PHO_PHOTO_ROTATE_LEFT').'" /></a>
                    <a class="icon-link" href="javascript:void(0)" onclick="return imgrotate('.$lastPhotoNr.', \'right\')"><img 
                        src="'. THEME_PATH. '/icons/arrow_turn_right.png" alt="'.$gL10n->get('PHO_PHOTO_ROTATE_RIGHT').'" title="'.$gL10n->get('PHO_PHOTO_ROTATE_RIGHT').'" /></a>
                    <a class="icon-link" data-toggle="modal" data-target="#admidio_modal"
                        href="'.$g_root_path.'/adm_program/system/popup_message.php?type=pho&amp;element_id=div_image_'.
                        $lastPhotoNr.'&amp;database_id='.$lastPhotoNr.'&amp;database_id_2='.$getPhotoId.'"><img 
                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
    
                }
                
                if($gValidLogin == true && $gPreferences['enable_ecard_module'] == 1)
                {
                    $photoThumbnailTable .= '
                    <a class="icon-link" href="'.$g_root_path.'/adm_program/modules/ecards/ecards.php?photo_nr='.$lastPhotoNr.'&amp;pho_id='.$getPhotoId.'&amp;show_page='.$getPhotoNr.'"><img 
                        src="'. THEME_PATH. '/icons/ecard.png" alt="'.$gL10n->get('PHO_PHOTO_SEND_ECARD').'" title="'.$gL10n->get('PHO_PHOTO_SEND_ECARD').'" /></a>';
                }
                
                if($gPreferences['photo_download_enabled']==1)
                {
                    //show link to download photo
                    $photoThumbnailTable .= '
                    <a class="icon-link" href="'.$g_root_path.'/adm_program/modules/photos/photo_download.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$lastPhotoNr.'"><img 
                                    src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('PHO_DOWNLOAD_SINGLE_PHOTO').'" title="'.$gL10n->get('PHO_DOWNLOAD_SINGLE_PHOTO').'"  /></a>';
                }
                
                if($gCurrentUser->editPhotoRight() || ($gValidLogin == true && $gPreferences['enable_ecard_module'] == 1) || $gPreferences['photo_download_enabled']==1)
                {
                   $photoThumbnailTable .= '</div>'; 
                }
            $photoThumbnailTable .= '</div>';
        }
    }
    
    $photoThumbnailTable .= '</div>';
    
    // Damit man mit der Colorbox auch alle anderen Bilder im Album sehen kann werden hier die restilichen Links zu den Bildern "unsichtbar" ausgegeben
    if ($gPreferences['photo_show_mode'] == 1)
    {
        $photoThumbnailTable_shown = false;
        
        for ($i = 1; $i <= $photoAlbum->getValue('pho_quantity'); $i++)
        {
            if($i >= $firstPhotoNr && $i <= $lastPhotoNr)
            {
                    if(!$photoThumbnailTable_shown)
                    {
                        $page->addHtml($photoThumbnailTable);
                        $photoThumbnailTable_shown = true;
                    }
            }
            else
            {
                $page->addHtml('<a rel="colorboxPictures" style="display:none;" href="'.$g_root_path.'/adm_program/modules/photos/photo_presenter.php?photo_nr='.$i.'&amp;pho_id='.$getPhotoId.'">&nbsp;</a>');
            }
        }
    }
    else // wenn die Fotos nicht mit der Colorbox aufgerufen werden
    {
        $page->addHtml($photoThumbnailTable);
    }		

    // show additional album informations
    $datePeriod = $photoAlbum->getValue('pho_begin', $gPreferences['system_date']);
    
	if($photoAlbum->getValue('pho_end') != $photoAlbum->getValue('pho_begin')
	&& strlen($photoAlbum->getValue('pho_end')) > 0)
	{
		$datePeriod .= ' '.$gL10n->get('SYS_DATE_TO').' '.$photoAlbum->getValue('pho_end', $gPreferences['system_date']);
	}
    
	$page->addHtml('<br />
	<div class="row">
	    <div class="col-sm-2 col-xs-4">'.$gL10n->get('SYS_DATE').'</div>
	    <div class="col-sm-4 col-xs-8"><strong>'.$datePeriod.'</strong></div>
    </div>
	<div class="row">
	    <div class="col-sm-2 col-xs-4">'.$gL10n->get('PHO_PHOTOGRAPHER').'</div>
	    <div class="col-sm-4 col-xs-8"><strong>'.$photoAlbum->getValue('pho_photographers').'</strong></div>
	</div>');

    // show informations about user who creates the recordset and changed it
    $page->addHtml(admFuncShowCreateChangeInfoById($photoAlbum->getValue('pho_usr_id_create'), $photoAlbum->getValue('pho_timestamp_create'), $photoAlbum->getValue('pho_usr_id_change'), $photoAlbum->getValue('pho_timestamp_change')));
        
    // show page navigations through thumbnails
    $url = $g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$photoAlbum->getValue('pho_id');
    $page->addHtml(admFuncGeneratePagination($url, $photoAlbum->getValue('pho_quantity'), $gPreferences['photo_thumbs_page'], $getPhotoNr, true, 'photo_nr'));

}
/************************Albumliste*************************************/

//erfassen der Alben die in der Albentabelle ausgegeben werden sollen
$sql='      SELECT *
            FROM '. TBL_PHOTOS. '
            WHERE pho_org_shortname = \''.$gCurrentOrganization->getValue('org_shortname').'\'';
if($getPhotoId == 0)
{
    $sql = $sql.' AND (pho_pho_id_parent IS NULL) ';
}
if($getPhotoId > 0)
{
    $sql = $sql.' AND pho_pho_id_parent = '.$getPhotoId.'';
}
if($gCurrentUser->editPhotoRight() == false)
{
    $sql = $sql.' AND pho_locked = 0 ';
}

$sql = $sql.' ORDER BY pho_begin DESC ';
$result_list = $gDb->query($sql);

//Gesamtzahl der auszugebenden Alben
$albumsCount = $gDb->num_rows($result_list);

// falls zum aktuellen Album Fotos und Unteralben existieren,
// dann einen Trennstrich zeichnen
if($photoAlbum->getValue('pho_quantity') > 0 && $albumsCount > 0)
{
    $page->addHtml('<hr />');
}

//Dateizeiger auf erstes auszugebendes Element setzen
if($albumsCount > 0)
{
    $gDb->data_seek($result_list, $getStart);
}
   
$childPhotoAlbum = new TablePhotos($gDb);

$page->addHtml('<div class="row">');

for($x = $getStart; $x <= $getStart + $gPreferences['photo_albums_per_page'] - 1 && $x < $albumsCount; $x++)
{
    $adm_photo_list = $gDb->fetch_array($result_list);
    // Daten in ein Photo-Objekt uebertragen
    $childPhotoAlbum->clear();
    $childPhotoAlbum->setArray($adm_photo_list);

    //Hauptordner
    $ordner = SERVER_PATH. '/adm_my_files/photos/'.$childPhotoAlbum->getValue('pho_begin', 'Y-m-d').'_'.$childPhotoAlbum->getValue('pho_id');

    //wenn ja Zeile ausgeben
    if(file_exists($ordner) && ($childPhotoAlbum->getValue('pho_locked')==0) || $gCurrentUser->editPhotoRight())
    {
        // Zufallsbild fuer die Vorschau ermitteln
        $shuffle_image = $childPhotoAlbum->shuffleImage();

        //Album angaben
        if(file_exists($ordner) || $childPhotoAlbum->hasChildAlbums())
        {
            $albumTitle = '<a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'">'.$childPhotoAlbum->getValue('pho_name').'</a><br />';
        }
        else
        {
            $albumTitle = $childPhotoAlbum->getValue('pho_name');
        }

        $albumDate = $childPhotoAlbum->getValue('pho_begin', $gPreferences['system_date']);
        if($childPhotoAlbum->getValue('pho_end') != $childPhotoAlbum->getValue('pho_begin'))
        {
            $albumDate .= ' '.$gL10n->get('SYS_DATE_TO').' '.$childPhotoAlbum->getValue('pho_end', $gPreferences['system_date']);
        }
        
        $page->addHtml('
          <div class="col-sm-6" id="panel_pho_'.$childPhotoAlbum->getValue('pho_id').'">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="pull-left"><h4 class="panel-title">'.$albumTitle.'</h4></div>
                    <div class="pull-right text-right">');
                        
                        // check if download option is enabled
                        if($gPreferences['photo_download_enabled']==1)
                        {
                            $page->addHtml('
                                <a class="icon-link" href="'.$g_root_path.'/adm_program/modules/photos/photo_download.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'"><img 
                                        src="'. THEME_PATH. '/icons/page_white_compressed.png" alt="'.$gL10n->get('PHO_DOWNLOAD_PHOTOS').'" title="'.$gL10n->get('PHO_DOWNLOAD_PHOTOS').'"  /></a>');
                        }

                        // if user has admin rights for photo module then show some functions
                        if ($gCurrentUser->editPhotoRight())
                        {
                            $page->addHtml('
                            <a class="icon-link" href="'.$g_root_path.'/adm_program/modules/photos/photo_album_new.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'&amp;mode=change"><img 
                                src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                            <a class="icon-link" data-toggle="modal" data-target="#admidio_modal"
                                href="'.$g_root_path.'/adm_program/system/popup_message.php?type=pho_album&amp;element_id=panel_pho_'.
                                $childPhotoAlbum->getValue('pho_id').'&amp;name='.urlencode($childPhotoAlbum->getValue('pho_name')).'&amp;database_id='.$childPhotoAlbum->getValue('pho_id').'"><img 
                                src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>');
                        }
                    $page->addHtml('</div>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-6 col-sm-7 col-md-6">
                            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'"><img 
                                class="thumbnail" src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$shuffle_image['shuffle_pho_id'].'&amp;photo_nr='.$shuffle_image['shuffle_img_nr'].'&amp;thumb=1" alt="'.$gL10n->get('PHO_PHOTOS').'" /></a>
                        </div>
                        <div class="col-xs-6 col-sm-5 col-md-6">');                            
                            $form = new HtmlForm('form_album_'.$childPhotoAlbum->getValue('pho_id'), null, $page, 'vertical');
                            $form->addStaticControl('pho_date', $gL10n->get('SYS_DATE'), $albumDate);
                            $form->addStaticControl('pho_count', $gL10n->get('SYS_PHOTOS'), $childPhotoAlbum->countImages());
                            if(strlen($childPhotoAlbum->getValue('pho_photographers')) > 0)
                            {
                                $form->addStaticControl('pho_photographer', $gL10n->get('PHO_PHOTOGRAPHER'), $childPhotoAlbum->getValue('pho_photographers'));
                            }
                            $page->addHtml($form->show(false));                            
                    $page->addHtml('</div>
                    </div>');

                    // Notice for users with foto edit rights that the folder of the album doesn't exists
                    if(file_exists($ordner) == false && $childPhotoAlbum->hasChildAlbums() == false && $gCurrentUser->editPhotoRight())
                    {
                        $page->addHtml('<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('PHO_FOLDER_NOT_FOUND').'</div>');
                    }
                    
                    // Notice for users with foto edit right that this album is locked
                    if($adm_photo_list['pho_locked'] == 1 && file_exists($ordner))
                    {
                        $page->addHtml('<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('PHO_ALBUM_NOT_APPROVED').'</div>');
                    }

                    // if user has admin rights for photo module then show some functions
                    if ($gCurrentUser->editPhotoRight() && file_exists($ordner))
                    {
                        $page->addHtml('<div class="btn-group" style="width: 100%;">
                            <a id="btn_album_upload" class="btn btn-default icon-text-link" style="width: 50%;" data-toggle="modal" data-target="#admidio_modal"
                                href="'.$g_root_path.'/adm_program/modules/photos/photoupload.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'"><img 
                                src="'. THEME_PATH. '/icons/photo_upload.png" alt="'.$gL10n->get('PHO_UPLOAD_PHOTOS').'" />'.$gL10n->get('PHO_UPLOAD_PHOTOS').'</a>');

                            if($childPhotoAlbum->getValue('pho_locked')==1)
                            {
                                $page->addHtml('
                                <a class="btn btn-default icon-text-link" style="width: 50%;" href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'&amp;locked=0"><img 
                                    src="'. THEME_PATH. '/icons/key.png"  alt="'.$gL10n->get('PHO_ALBUM_UNLOCK').'" />'.$gL10n->get('PHO_ALBUM_UNLOCK').'</a>');
                            }
                            elseif($childPhotoAlbum->getValue('pho_locked')==0)
                            {
                                $page->addHtml('
                                <a class="btn btn-default icon-text-link" style="width: 50%;" href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'&amp;locked=1"><img 
                                    src="'. THEME_PATH. '/icons/key.png" alt="'.$gL10n->get('PHO_ALBUM_LOCK').'" />'.$gL10n->get('PHO_ALBUM_LOCK').'</a>');
                            }
                        $page->addHtml('</div>');
                    }
                $page->addHtml('</div>
            </div>
          </div>');
    }//Ende wenn Ordner existiert
};//for

$page->addHtml('</div>');
    
/****************************Leeres Album****************/
//Falls das Album weder Fotos noch Unterordner enthaelt
if(($photoAlbum->getValue('pho_quantity')=='0' || strlen($photoAlbum->getValue('pho_quantity')) == 0) && $albumsCount < 1)  // alle vorhandenen Albumen werden ignoriert
{
    $page->addHtml($gL10n->get('PHO_NO_ALBUM_CONTENT'));
}

// If neccessary show links to navigate to next and previous albums of the query
$base_url = $g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$getPhotoId;
$page->addHtml(admFuncGeneratePagination($base_url, $albumsCount, $gPreferences['photo_albums_per_page'], $getStart, TRUE));

// show html of complete page
$page->show();

?>