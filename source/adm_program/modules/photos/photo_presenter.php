<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * Bild: welches Bild soll angezeigt werden
 * pho_id: Id des Albums aus der das Bild stammt
 *
 *****************************************************************************/

require('../../system/classes/table_photos.php');
require('../../system/common.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show('module_disabled');
}
elseif($g_preferences['enable_photo_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require('../../system/login_valid.php');
}

// Uebergabevariablen pruefen

if(isset($_GET['pho_id']) && is_numeric($_GET['pho_id']) == false)
{
    $g_message->show('invalid');
}

if(isset($_GET['bild']) && is_numeric($_GET['bild']) == false)
{
    $g_message->show('invalid');
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

//Naechstes und Letztes Bild
$prev_image = $bild-1;
$next_image = $bild+1;
$url_prev_image = '#';
$url_next_image = '#';

if($prev_image > 0)
{
    $url_prev_image = $g_root_path. '/adm_program/modules/photos/photo_presenter.php?bild='. $prev_image. '&pho_id='. $pho_id;
}
if($next_image <= $photo_album->getValue('pho_quantity'))
{
    $url_next_image = $g_root_path. '/adm_program/modules/photos/photo_presenter.php?bild='. $next_image. '&pho_id='. $pho_id;
}

//Ordnerpfad zusammensetzen
$ordner_foto = '/adm_my_files/photos/'.$photo_album->getValue('pho_begin').'_'.$photo_album->getValue('pho_id');
$ordner      = SERVER_PATH. $ordner_foto;
$ordner_url  = $g_root_path. $ordner_foto;

$body_with   = $g_preferences['photo_show_width']  + 20;

//Photomodulspezifische CSS laden
$g_layout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/photos.css" type="text/css" media="screen" />';

//Photomodulspezifische CSS laden
if($g_preferences['photo_show_mode']==1)
{
	$g_layout['header'] = '<style rel="stylesheet" type="text/css" media="screen">body{ padding: 0px;}</style>';
}


// Html-Kopf ausgeben
$g_layout['title']    = 'Fotogalerien';

//wenn Popupmode normalen kopf unterdruecken
if($g_preferences['photo_show_mode']==0 || $g_preferences['photo_show_mode']==1)
{                      
    $g_layout['includes'] = false;
}

require(THEME_SERVER_PATH. '/overall_header.php');

//Ausgabe der Eine Tabelle Kopfzelle mit &Uuml;berschrift, Photographen und Datum
//untere Zelle mit Buttons Bild und Fenster Schließen Button
if($g_preferences['photo_show_mode']==0 || $g_preferences['photo_show_mode']==2)
{ 
echo '
<div class="formLayout" id="photo_presenter" style="width: '.$body_with.'px;">
    <div class="formHead">'.$photo_album->getValue('pho_name').'</div>
    <div class="formBody">';
}   
        //Ermittlung der Original Bildgroesse
        $bildgroesse = getimagesize($ordner.'/'.$bild.'.jpg');
        //Entscheidung ueber scallierung
        //Hochformat Bilder
        if ($bildgroesse[0]<=$bildgroesse[1])
        {
            $side='y';
            if ($bildgroesse[1]>$g_preferences['photo_show_height']){
                $scal=$g_preferences['photo_show_height'];
            }
            else
            {
                $scal=$bildgroesse[1];
            }
        }
    
        //Querformat Bilder
        if ($bildgroesse[0]>$bildgroesse[1])
        {
            $side='x';
            if ($bildgroesse[0]>$g_preferences['photo_show_width'])
            {
                $scal=$g_preferences['photo_show_width'];
            }
            else{
                $scal=$bildgroesse[0];
            }
        }
    
        //Ausgabe Bild
        echo '
        <div><a href="$url_next_image">
            <img class="photoOutput" src="'.$g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$pho_id.'&amp;pic_nr='.$bild.'&amp;pho_begin='.$photo_album->getValue('pho_begin').'&amp;scal='.$scal.'&amp;side='.$side.'" alt="'.$ordner_url.$bild.'">
            </a>
        </div>';
    	
    	//Vor und zurück Buttons
    	echo'
        <ul class="iconTextLinkList">';
            //Vor und zurueck buttons
            if($prev_image > 0)
            {
                echo'<li>
                    <span class="iconTextLink">
                        <a href="'.$url_prev_image.'"><img src="'. THEME_PATH. '/icons/back.png" alt="Vorheriges Bild" /></a>
                        <a href="'.$url_prev_image.'">Vorheriges Bild</a>
                    </span>
                </li>';
            }
            if($next_image <= $photo_album->getValue('pho_quantity'))
            {
                echo'<li>
                    <span class="iconTextLink">
                        <a href="'.$url_next_image.'">Nächstes Bild</a>
                        <a href="'.$url_next_image.'"><img src="'. THEME_PATH. '/icons/forward.png" alt="Nächstes Bild" /></a>
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
                        <a href="javascript:parent.window.close()"><img src="'. THEME_PATH. '/icons/door_in.png" alt="Fenster schließen" /></a>
                        <a href="javascript:parent.window.close()">Fenster schließen</a>
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
                        <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$pho_id.'"><img src="'. THEME_PATH. '/icons/application_view_tile.png" alt="zur &Uuml;bersicht" /></a>
                        <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$pho_id.'">zur &Uuml;bersicht</a>
                    </span>
                </li>
            </ul>';
        }
        
        
        //Zusatzinformationen zum Album nur wenn im gleichen Fenster
        if($g_preferences['photo_show_mode']==2)
        {	
        	echo'
	        <p>
	            Datum: '.mysqldate('d.m.y', $photo_album->getValue('pho_begin'));
	            if($photo_album->getValue('pho_end') != $photo_album->getValue('pho_begin')
	            && strlen($photo_album->getValue('pho_end')) > 0)
	            {
	                echo ' bis '.mysqldate('d.m.y', $photo_album->getValue('pho_end'));
	            }
	            echo '<br />Fotos von: '.$photo_album->getValue('pho_photographers').'
	        </p>';
		}    
    echo'</div>
</div>';
        
require(THEME_SERVER_PATH. '/overall_footer.php');

?>