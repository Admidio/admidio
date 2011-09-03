<?php
/******************************************************************************
 * Photoalben
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id:      id des Albums dessen Fotos angezeigt werden sollen
 * headline:    Ueberschrift, die ueber den Ankuendigungen steht
 *              (Default) PHO_PHOTO_ALBUMS
 * show_page:   welch Seite der Thumbnails ist die aktuelle
 * start:       mit welchem Element beginnt die Albumliste
 * locked:      das Album soll freigegebn/gesperrt werden
 *
 *****************************************************************************/

require_once('../../system/classes/table_photos.php');
require_once('../../system/common.php');
require_once('../../system/classes/image.php');

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

// Uebergabevariablen pruefen und ggf. initialisieren
$getPhotoId  = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $g_l10n->get('PHO_PHOTO_ALBUMS'));
$getStart    = admFuncVariableIsValid($_GET, 'start', 'numeric', 0);
$getShowPage = admFuncVariableIsValid($_GET, 'show_page', 'numeric', 1);
$getLocked   = admFuncVariableIsValid($_GET, 'locked', 'boolean');

unset($_SESSION['photo_album_request']);

//Wurde keine Album uebergeben kann das Navigationsstack zurueckgesetzt werden
if ($getPhotoId == 0)
{
    $_SESSION['navigation']->clear();
}

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $getPhotoId)
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $g_db;
}
else
{
    // einlesen des Albums falls noch nicht in Session gespeichert
    $photo_album = new TablePhotos($g_db);
    if($getPhotoId > 0)
    {
        $photo_album->readData($getPhotoId);
    }

    $_SESSION['photo_album'] =& $photo_album;
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($getPhotoId > 0 && $photo_album->getValue('pho_org_shortname') != $g_organization)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}   

/*********************LOCKED************************************/
//Falls gefordert und Foto-edit-rechte, aendern der Freigabe
if($getLocked=='1' || $getLocked=='0')
{
    // erst pruefen, ob der User Fotoberarbeitungsrechte hat
    if(!$g_current_user->editPhotoRight())
    {
        $g_message->show($g_l10n->get('PHO_NO_RIGHTS'));
    }
    
    $photo_album->setValue('pho_locked', $getLocked);
    $photo_album->save();

    //Zurueck zum Elternalbum    
    $getPhotoId = $photo_album->getValue('pho_pho_id_parent');
    $photo_album->readData($getPhotoId);
}

/*********************HTML_TEIL*******************************/

if($getPhotoId > 0)
{
    $g_layout['title'] = $photo_album->getValue('pho_name');
}
else
{
    $g_layout['title'] = $getHeadline;
}
$g_layout['header'] = '';

if($g_preferences['enable_rss'] == 1)
{
    $g_layout['header'] .=  '<link rel="alternate" type="application/rss+xml" title="'.$g_l10n->get('SYS_RSS_FEED_FOR_VAR', $g_current_organization->getValue('org_longname'). ' - '.$getHeadline).'"
		href="'.$g_root_path.'/adm_program/modules/photos/rss_photos.php?headline='.$getHeadline.'" />';
};

if($g_current_user->editPhotoRight())
{
    $g_layout['header'] = $g_layout['header']. '
        <script type="text/javascript"><!--
            $(document).ready(function() 
            {
                $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});
            }); 
        //--></script>
        <script type="text/javascript"><!--
            //Bild drehen
            function imgrotate(img, direction)
            {                    
                $.get("'.$g_root_path.'/adm_program/modules/photos/photo_function.php", {pho_id: '.$getPhotoId.', photo_nr: img, job: "rotate", direction: direction}, function(data){
                    //Anhängen der Zufallszahl ist nötig um den Browsercache zu überlisten                    
                    $("#img_"+img).attr("src", "photo_show.php?pho_id='.$getPhotoId.'&photo_nr="+img+"&pho_begin='.$photo_album->getValue('pho_begin', 'Y-m-d').'&thumb=true&rand="+Math.random());
                    return false;
                });
            }
        //--></script>';
}

if($g_preferences['photo_show_mode']==1)
{
    $g_layout['header'] = $g_layout['header']. '
        <script type="text/javascript"><!--
            $(document).ready(function(){
                $("a[rel=\'colorboxPictures\']").colorbox({slideshow:true,
                                                           slideshowAuto:false,
                                                           preloading:true,
                                                           close:\''.$g_l10n->get('SYS_CLOSE').'\',
                                                           slideshowStart:\''.$g_l10n->get('SYS_SLIDESHOW_START').'\',
                                                           slideshowStop:\''.$g_l10n->get('SYS_SLIDESHOW_STOP').'\',
                                                           current:\''.$g_l10n->get('SYS_SLIDESHOW_CURRENT').'\',
                                                           previous:\''.$g_l10n->get('SYS_PREVIOUS').'\',
                                                           next:\''.$g_l10n->get('SYS_NEXT').'\'});
            });
        --></script>';
}

//Photomodulspezifische CSS laden
$g_layout['header'] = $g_layout['header']. '
		<link rel="stylesheet" href="'. THEME_PATH. '/css/photos.css" type="text/css" media="screen" />';

// Html-Kopf ausgeben
require(SERVER_PATH. '/adm_program/system/overall_header.php');


//Ueberschift
echo '<h1 class="moduleHeadline">'.$g_layout['title'].'</h1>';

//Breadcrump bauen
$navilink = '';
$pho_parent_id = $photo_album->getValue('pho_pho_id_parent');
$photo_album_parent = new TablePhotos($g_db);

while ($pho_parent_id > 0)
{
    // Einlesen des Eltern Albums
    $photo_album_parent->readData($pho_parent_id);
    
    //Link zusammensetzen
    $navilink = '&nbsp;&gt;&nbsp;<a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$photo_album_parent->getValue('pho_id').'">'.
        $photo_album_parent->getValue('pho_name').'</a>'.$navilink;

    //Elternveranst
    $pho_parent_id = $photo_album_parent->getValue('pho_pho_id_parent');
}

if($getPhotoId > 0)
{
    //Ausgabe des Linkpfads
    echo '<div class="navigationPath">
            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php"><img src="'. THEME_PATH. '/icons/application_view_tile.png" alt="'.$g_l10n->get('PHO_PHOTO_ALBUMS').'" /></a>
            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php">'.$g_l10n->get('PHO_PHOTO_ALBUMS').'</a>'.$navilink.'
            &nbsp;&gt;&nbsp;'.$photo_album->getValue('pho_name').'         
        </div>';
}

//bei Seitenaufruf mit Moderationsrechten
if($g_current_user->editPhotoRight())
{
    //Album anlegen
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/photos/photo_album_new.php?job=new&amp;pho_id='.$getPhotoId.'">
    	           <img src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('PHO_CREATE_ALBUM').'" title="'.$g_l10n->get('PHO_CREATE_ALBUM').'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/photos/photo_album_new.php?job=new&amp;pho_id='.$getPhotoId.'">'.$g_l10n->get('PHO_CREATE_ALBUM').'</a>
            </span>
    </li>';        
    if($getPhotoId > 0)
    {
        echo '
        <li>
            <span class="iconTextLink">
                <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/photos/photoupload.php?pho_id='.$getPhotoId.'">
                	<img src="'. THEME_PATH. '/icons/photo_upload.png" alt="'.$g_l10n->get('PHO_UPLOAD_PHOTOS').'" title="'.$g_l10n->get('PHO_UPLOAD_PHOTOS').'"/></a>
                <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/photos/photoupload.php?pho_id='.$getPhotoId.'">'.$g_l10n->get('PHO_UPLOAD_PHOTOS').'</a>
            </span>
        </li>';
    }
    echo '</ul>';
}


//Anlegen der Tabelle
echo '<div class="photoModuleContainer">';
    /*************************THUMBNAILS**********************************/
    //Nur wenn uebergebenes Album Bilder enthaelt
    if($photo_album->getValue('pho_quantity') > 0)
    {        
        //Aanzahl der Bilder
        $bilder = $photo_album->getValue('pho_quantity');
        
        //Differenz
        $difference = $g_preferences['photo_thumbs_row']-$g_preferences['photo_thumbs_column'];

		//Thumbnails pro Seite
        $thumbs_per_page = $g_preferences['photo_thumbs_row']*$g_preferences['photo_thumbs_column'];
        	
        //Popupfenstergröße
        $popup_height = $g_preferences['photo_show_height']+210;
        $popup_width  = $g_preferences['photo_show_width']+70;

        //Album Seitennavigation
        function photoAlbumPageNavigation($photo_album, $act_thumb_page, $thumbs_per_page)
        {
            global $g_root_path;
            global $g_l10n;
            $max_thumb_page = 0;
            
            //Ausrechnen der Seitenzahl
            if($photo_album->getValue('pho_quantity') > 0)
            {
                $max_thumb_page = round($photo_album->getValue('pho_quantity') / $thumbs_per_page);
            }
            
            if ($max_thumb_page * $thumbs_per_page < $photo_album->getValue('pho_quantity'))
            {
                $max_thumb_page++;
            }
            if($max_thumb_page > 1)
            {
                //Container mit Navigation
                echo ' <div class="pageNavigation" id="photoPageNavigation">'.$g_l10n->get('SYS_PAGE').':&nbsp;';
                
                    // link to previous page
                    $vorseite=$act_thumb_page-1;
                    if($vorseite>=1)
                    {
                        echo '
                        <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?show_page='.$vorseite.'&amp;pho_id='.$photo_album->getValue('pho_id').'">
                            <img src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_PAGE_PREVIOUS').'" />
                        </a>
                        <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?show_page='.$vorseite.'&amp;pho_id='.$photo_album->getValue('pho_id').'">'.$g_l10n->get('SYS_PAGE_PREVIOUS').'</a>&nbsp;';
                    }
                
                    // show page count
                    for($s=1; $s<=$max_thumb_page; $s++)
                    {
                        if($s==$act_thumb_page)
                        {
                            echo $act_thumb_page.'&nbsp;';
                        }
                        if($s!=$act_thumb_page){
                            echo'<a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?show_page='.$s.'&pho_id='.$photo_album->getValue('pho_id').'">'.$s.'</a>&nbsp;';
                        }
                    }
                
                    // link to next page
                    $nachseite=$act_thumb_page+1;
                    if($nachseite<=$max_thumb_page){
                        echo '
                        <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?show_page='.$nachseite.'&amp;pho_id='.$photo_album->getValue('pho_id').'">'.$g_l10n->get('SYS_PAGE_NEXT').'</a>
                        <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?show_page='.$nachseite.'&amp;pho_id='.$photo_album->getValue('pho_id').'">
                            <img src="'. THEME_PATH. '/icons/forward.png" alt="'.$g_l10n->get('SYS_PAGE_NEXT').'" />
                        </a>';
                    }
                echo '</div>';
            }
        }
                  
        //Thumbnailtabelle
        $photoThumbnailTable = '<ul class="photoThumbnailRow">';
            for($zeile=1;$zeile<=$g_preferences['photo_thumbs_row'];$zeile++)//durchlaufen der Tabellenzeilen
            {
                $photoThumbnailTable .= '<li><ul class="photoThumbnailColumn">';
                for($spalte=1;$spalte<=$g_preferences['photo_thumbs_column'];$spalte++)//durchlaufen der Tabellenzeilen
                {
                    //Errechnug welches Bild ausgegeben wird
                    $bild = ($getShowPage * $thumbs_per_page) - $thumbs_per_page + ($zeile * $g_preferences['photo_thumbs_column'])-$g_preferences['photo_thumbs_row']+$spalte+$difference;
                    $photoThumbnailTable .= '<li id="imgli_id_'.$bild.'">';

                    if ($bild <= $bilder)
                    {
                        //Popup-Mode
                        if ($g_preferences['photo_show_mode'] == 0)
                        {
                            $photoThumbnailTable .= '<div>
                                <img id="img_'.$bild.'" onclick="window.open(\''.$g_root_path.'/adm_program/modules/photos/photo_presenter.php?photo_nr='.$bild.'&amp;pho_id='.$getPhotoId.'\',\'msg\', \'height='.$popup_height.', width='.$popup_width.',left=162,top=5\')" 
                                    src="photo_show.php?pho_id='.$getPhotoId.'&photo_nr='.$bild.'&pho_begin='.$photo_album->getValue('pho_begin', 'Y-m-d').'&thumb=true" alt="'.$bild.'" style="cursor: pointer"/>
                            </div>';
                        }

                        //Colorbox-Mode
                        else if ($g_preferences['photo_show_mode'] == 1)
                        {
                            $photoThumbnailTable .= '<div>
                                <a rel="colorboxPictures" href="'.$g_root_path.'/adm_program/modules/photos/photo_presenter.php?photo_nr='.$bild.'&amp;pho_id='.$getPhotoId.'">
                                	<img id="img_'.$bild.'" class="photoThumbnail" src="photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$bild.'&amp;pho_begin='.$photo_album->getValue('pho_begin', 'Y-m-d').'&amp;thumb=true" alt="'.$bild.'" /></a>
                            </div>';
                        }

                        //Gleichesfenster-Mode
                        else if ($g_preferences['photo_show_mode'] == 2)
                        {
                            $photoThumbnailTable .= '<div>
                                <img id="img_'.$bild.'" onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/photos/photo_presenter.php?photo_nr='.$bild.'&amp;pho_id='.$getPhotoId.'\'" 
                                    src="photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$bild.'&amp;pho_begin='.$photo_album->getValue('pho_begin', 'Y-m-d').'&amp;thumb=true" style="cursor: pointer"/>
                            </div>';
                        }   
                        
                        //Buttons fuer Moderatoren
                        if($g_current_user->editPhotoRight())
                        {
                           $photoThumbnailTable .= '
                            <a class="iconLink"  href="javascript:void(0)" onclick="return imgrotate('.$bild.', \'left\')"><img 
                                src="'. THEME_PATH. '/icons/arrow_turn_left.png" alt="'.$g_l10n->get('PHO_PHOTO_ROTATE_LEFT').'" title="'.$g_l10n->get('PHO_PHOTO_ROTATE_LEFT').'" /></a>
                            <a class="iconLink" href="javascript:void(0)" onclick="return imgrotate('.$bild.', \'right\')"><img 
                                src="'. THEME_PATH. '/icons/arrow_turn_right.png" alt="'.$g_l10n->get('PHO_PHOTO_ROTATE_RIGHT').'" title="'.$g_l10n->get('PHO_PHOTO_ROTATE_RIGHT').'" /></a>
                            <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=pho&amp;element_id=imgli_id_'.
                                $bild.'&amp;database_id='.$bild.'&amp;database_id_2='.$getPhotoId.'"><img 
                                src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('SYS_DELETE').'" title="'.$g_l10n->get('SYS_DELETE').'" /></a>';

                        }
                        if($g_valid_login == true && $g_preferences['enable_ecard_module'] == 1)
                        {
                            $photoThumbnailTable .= '
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/ecards/ecard_form.php?photo_nr='.$bild.'&amp;pho_id='.$getPhotoId.'"><img 
                                src="'. THEME_PATH. '/icons/ecard.png" alt="'.$g_l10n->get('PHO_PHOTO_SEND_ECARD').'" title="'.$g_l10n->get('PHO_PHOTO_SEND_ECARD').'" /></a>';
                        }
                    }

                    //schleifen abbrechen
                    if ($bild == $bilder)
                    {
                        $zeile=$g_preferences['photo_thumbs_row'];
                        $spalte=$g_preferences['photo_thumbs_column'];
                    }
                    $photoThumbnailTable .= '</li>';
                }//for
                $photoThumbnailTable .= '</ul></li>'; //Zeilenende
            }//for
        $photoThumbnailTable .= '</ul>';
        
        // Damit man mit der Colobox auch alle anderen Bilder im Album sehen kann werden hier die restilichen Links zu den Bildern "unsichtbar" ausgegeben
        if ($g_preferences['photo_show_mode'] == 1)
        {
            $photoThumbnailTable_shown = false;
            for ($i = 1; $i <= $bilder; $i++)
            {
                if( $i <= $getShowPage * $thumbs_per_page && $i >= (($getShowPage * $thumbs_per_page)-$thumbs_per_page))
                {
                        if(!$photoThumbnailTable_shown)
                        {
                            echo $photoThumbnailTable;
                            $photoThumbnailTable_shown = true;
                        }
                }
                else
                {
                    echo '<a rel="colorboxPictures" style="display:none;" href="'.$g_root_path.'/adm_program/modules/photos/photo_presenter.php?photo_nr='.$i.'&amp;pho_id='.$getPhotoId.'">&nbsp;</a>';
                }
            }
        }
        else // wenn die Fotos nicht mit der Colorbox aufgerufen werden
        {
            echo $photoThumbnailTable;
        }		
        
        //Seitennavigation
        photoAlbumPageNavigation($photo_album, $getShowPage, $thumbs_per_page);

        //Datum des Albums
        echo '<div class="editInformation" id="photoAlbumInformation">
            '.$g_l10n->get('SYS_DATE').': '.$photo_album->getValue('pho_begin', $g_preferences['system_date']);
            if($photo_album->getValue('pho_end') != $photo_album->getValue('pho_begin'))
            {
                echo ' '.$g_l10n->get('SYS_DATE_TO').' '.$photo_album->getValue('pho_end', $g_preferences['system_date']);
            }
        echo '
        	<br />'.$g_l10n->get('PHO_PHOTOGRAPHER').': '.$photo_album->getValue('pho_photographers').'
        </div>';

        // Anleger und Veraendererinfos SYS_CREATED_BY
        echo '
        <div class="editInformation">';
            $user_create = new User($g_db, $photo_album->getValue('pho_usr_id_create'));
            echo $g_l10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $photo_album->getValue('pho_timestamp_create'));
                        
            if($photo_album->getValue('pho_usr_id_change') > 0)
            {
                $user_change = new User($g_db, $photo_album->getValue('pho_usr_id_change'));
                echo '<br />'.$g_l10n->get('SYS_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $photo_album->getValue('pho_timestamp_change'));
            }
        echo '</div>';
    }
    /************************Albumliste*************************************/

    //erfassen der Alben die in der Albentabelle ausgegeben werden sollen
    $sql='      SELECT *
                FROM '. TBL_PHOTOS. '
                WHERE pho_org_shortname = \''.$g_organization.'\'';
    if($getPhotoId == 0)
    {
        $sql = $sql.' AND (pho_pho_id_parent IS NULL) ';
    }
    if($getPhotoId > 0)
    {
        $sql = $sql.' AND pho_pho_id_parent = '.$getPhotoId.'';
    }
    if (!$g_current_user->editPhotoRight())
    {
        $sql = $sql.' AND pho_locked = 0 ';
    }

    $sql = $sql.' ORDER BY pho_begin DESC ';
    $result_list = $g_db->query($sql);

    //Gesamtzahl der auszugebenden Alben
    $albums = $g_db->num_rows($result_list);

    // falls zum aktuellen Album Fotos und Unteralben existieren,
    // dann einen Trennstrich zeichnen
    if($photo_album->getValue('pho_quantity') > 0 && $albums > 0)
    {
        echo '<hr />';
    }

    $ignored = 0; //Summe aller zu ignorierender Elemente
    $ignore  = 0; //Summe der zu ignorierenden Elemente auf dieser Seite
    for($x = 0; $x < $albums; $x++)
    {
        $adm_photo_list = $g_db->fetch_array($result_list);

        $albumStartDate = new DateTimeExtended($adm_photo_list['pho_begin'], 'Y-m-d', 'date');
        if($albumStartDate->valid())
        {
            //Hauptordner
            $ordner = SERVER_PATH. '/adm_my_files/photos/'.$albumStartDate->format('Y-m-d').'_'.$adm_photo_list['pho_id'];
            
            if((!file_exists($ordner) || $adm_photo_list['pho_locked']==1) && (!$g_current_user->editPhotoRight()))
            {
                $ignored++;
                if($x >= $getStart + $ignored - $ignore)
                    $ignore++;
            }
        }
    }

    //Dateizeiger auf erstes auszugebendes Element setzen
    if($albums > 0 && $albums != $ignored)
    {
        $g_db->data_seek($result_list, $getStart + $ignored - $ignore);
    }
       
    $counter = 0;
    $sub_photo_album = new TablePhotos($g_db);

    for($x = $getStart + $ignored - $ignore; $x <= $getStart + $ignored + 9 && $x < $albums; $x++)
    {
        $adm_photo_list = $g_db->fetch_array($result_list);
        // Daten in ein Photo-Objekt uebertragen
        $sub_photo_album->clear();
        $sub_photo_album->setArray($adm_photo_list);

        //Hauptordner
        $ordner = SERVER_PATH. '/adm_my_files/photos/'.$sub_photo_album->getValue('pho_begin', 'Y-m-d').'_'.$sub_photo_album->getValue('pho_id');

        //wenn ja Zeile ausgeben
        if(file_exists($ordner) && ($sub_photo_album->getValue('pho_locked')==0) || $g_current_user->editPhotoRight())
        {
            if($counter == 0)
            {
                echo '<ul class="photoAlbumList">';
            }

            // Zufallsbild fuer die Vorschau ermitteln
            $shuffle_image = $sub_photo_album->shuffleImage();

            if($shuffle_image['shuffle_pho_id'] > 0)
            {
                //Pfad des Beispielbildes
                $bsp_pic_path = SERVER_PATH. '/adm_my_files/photos/'.$shuffle_image['shuffle_img_begin'].'_'.$shuffle_image['shuffle_pho_id'].'/'.$shuffle_image['shuffle_img_nr'].'.jpg';
            }
            else
            {
                //Wenn kein Bild gefunden wurde
                $bsp_pic_path = THEME_PATH. '/images/nopix.jpg';
            }

            //Ausgabe
            echo '
            <li id="pho_'.$sub_photo_album->getValue('pho_id').'" style="height: '.($g_preferences['photo_thumbs_scale']+20).'px;">
            <dl>
                <dt>';
                    if(file_exists($ordner))
                    {
                        echo '
                        <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$sub_photo_album->getValue('pho_id').'">
                            <img src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$shuffle_image['shuffle_pho_id'].'&amp;photo_nr='.$shuffle_image['shuffle_img_nr'].'&amp;pho_begin='.$shuffle_image['shuffle_img_begin'].'&amp;thumb=true"
                            	class="imageFrame" alt="Zufallsfoto" />
                        </a>';
                    }
                echo '</dt>
                <dd style="margin-left: '.($g_preferences['photo_thumbs_scale']).'px;">
                    <ul>
                        <li>';
                        if((!file_exists($ordner) && $g_current_user->editPhotoRight()) || ($sub_photo_album->getValue('pho_locked')==1 && file_exists($ordner)))
                        {
                            //Warnung fuer Leute mit Fotorechten: Ordner existiert nicht
                            if(!file_exists($ordner) && $g_current_user->editPhotoRight())
                            {
                                echo '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=PHO_FOLDER_NOT_FOUND&amp;inline=true"><img 
                                    onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=PHO_FOLDER_NOT_FOUND\',this)" onmouseout="ajax_hideTooltip()"
                                    class="iconHelpLink" src="'. THEME_PATH. '/icons/warning.png" alt="'.$g_l10n->get('SYS_WARNING').'" /></a>';
                            }
                            
                            //Hinweis fur Leute mit Photorechten: Album ist gesperrt
                            if($adm_photo_list["pho_locked"]==1 && file_exists($ordner))
                            {
                                echo '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=PHO_ALBUM_NOT_APPROVED&amp;inline=true"><img 
                                    onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=PHO_ALBUM_NOT_APPROVED\',this)" onmouseout="ajax_hideTooltip()"
                                    class="iconHelpLink" src="'. THEME_PATH. '/icons/lock.png" alt="'.$g_l10n->get('SYS_LOCKED').'" /></a>';
                            }
                        }

                        //Album angaben
                        if(file_exists($ordner))
                        {
                            echo'<a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$sub_photo_album->getValue('pho_id').'">'.$sub_photo_album->getValue('pho_name').'</a><br />';
                        }
                        else
                        {
                            echo $sub_photo_album->getValue('pho_name');
                        }

                        echo '</li>
                            <li>'.$g_l10n->get('SYS_PHOTOS').': '.$sub_photo_album->countImages().' </li>
                            <li>'.$g_l10n->get('SYS_DATE').': '.$sub_photo_album->getValue('pho_begin', $g_preferences['system_date']);
                            if($sub_photo_album->getValue('pho_end') != $sub_photo_album->getValue('pho_begin'))
                            {
                                echo ' '.$g_l10n->get('SYS_DATE_TO').' '.$sub_photo_album->getValue('pho_end', $g_preferences['system_date']);
                            }
                            echo '</li> 
                            <li>'.$g_l10n->get('PHO_PHOTOGRAPHER').': '.$sub_photo_album->getValue('pho_photographers').'</li>';

                            //bei Moderationrecheten
                            if ($g_current_user->editPhotoRight())
                            {
                                echo '<li>';

                                if(file_exists($ordner))
                                {
                                    echo '
                                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/photos/photoupload.php?pho_id='.$sub_photo_album->getValue('pho_id').'"><img 
                                        src="'. THEME_PATH. '/icons/photo_upload.png" alt="'.$g_l10n->get('PHO_UPLOAD_PHOTOS').'" title="'.$g_l10n->get('PHO_UPLOAD_PHOTOS').'" /></a>

                                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/photos/photo_album_new.php?pho_id='.$sub_photo_album->getValue('pho_id').'&amp;job=change"><img 
                                        src="'. THEME_PATH. '/icons/edit.png" alt="'.$g_l10n->get('SYS_EDIT').'" title="'.$g_l10n->get('SYS_EDIT').'" /></a>';
                                    
                                    if($sub_photo_album->getValue('pho_locked')==1)
                                    {
                                        echo '
                                        <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$sub_photo_album->getValue('pho_id').'&amp;locked=0"><img 
                                            src="'. THEME_PATH. '/icons/key.png"  alt="'.$g_l10n->get('SYS_UNLOCK').'" title="'.$g_l10n->get('SYS_UNLOCK').'" /></a>';
                                    }
                                    elseif($sub_photo_album->getValue('pho_locked')==0)
                                    {
                                        echo '
                                        <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$sub_photo_album->getValue('pho_id').'&amp;locked=1"><img 
                                            src="'. THEME_PATH. '/icons/key.png" alt="'.$g_l10n->get('SYS_LOCK').'" title="'.$g_l10n->get('SYS_LOCK').'" /></a>';
                                    }
                                }

                                echo '
                                <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=pho_album&amp;element_id=pho_'.
                                    $sub_photo_album->getValue('pho_id').'&amp;name='.urlencode($sub_photo_album->getValue('pho_name')).'&amp;database_id='.$sub_photo_album->getValue('pho_id').'"><img 
                                    src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('SYS_DELETE').'" title="'.$g_l10n->get('SYS_DELETE').'" /></a>
                                </li>';
                            }
                    echo '</ul>
                </dd>
            </dl>
            </li>';
            $counter++;
        }//Ende wenn Ordner existiert
    };//for

    if($counter > 0)
    {
        //Tabellenende
        echo '</ul>';
    }
        
    /****************************Leeres Album****************/
    //Falls das Album weder Fotos noch Unterordner enthaelt
    if(($photo_album->getValue('pho_quantity')=='0' || strlen($photo_album->getValue('pho_quantity')) == 0) && $albums<1)  // alle vorhandenen Albumen werden ignoriert
    {
        echo $g_l10n->get('PHO_NO_ALBUM_CONTENT');
    }
    
    if($g_db->num_rows($result_list) > 2)
    {
        // Navigation mit Vor- und Zurueck-Buttons
        // erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
        $base_url = $g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$getPhotoId;
        echo admFuncGeneratePagination($base_url, $albums-$ignored, 10, $getStart, TRUE);
    }
echo '</div>';

/************************Buttons********************************/
if($photo_album->getValue('pho_id') > 0)
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
                src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
            </span>
        </li>
    </ul>';
}

/***************************Seitenende***************************/

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>