<?php
   /******************************************************************************
 * Photofunktionen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id:      Id des Albums
 * job: - do_delete
 *      - rotate
 *      - delete_request
 * direction:   Drehrichtung links oder rechts
 * bild:        Nr des Bildes welches verarbeitet werden soll
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_photos.php');
require_once('../../system/classes/image.php');

// die Funktionen sollten auch ausgeloggt irgendwo benutzt werden koennen
if(isset($_GET['job']))
{
    if ($g_preferences['enable_photo_module'] == 0)
    {
        // das Modul ist deaktiviert
        $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
    }

    // erst pruefen, ob der User Fotoberarbeitungsrechte hat
    if(!$g_current_user->editPhotoRight())
    {
        $g_message->show($g_l10n->get('PHO_NO_RIGHTS'));
    }

    //URL auf Navigationstack ablegen
    $_SESSION['navigation']->addUrl(CURRENT_URL);
}

// Uebergabevariablen pruefen

//ID Pruefen
if(isset($_GET['pho_id']) && is_numeric($_GET['pho_id']))
{
    $pho_id = $_GET['pho_id'];
}
else 
{
    $pho_id = NULL;
}

if(isset($_GET['job']) == false 
|| ($_GET['job'] != 'rotate' && $_GET['job'] != 'delete_request' && $_GET['job'] != 'do_delete'))
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['direction']) && $_GET['direction'] != 'left' && $_GET['direction'] != 'right')
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['job']) && (isset($_GET['bild']) == false || is_numeric($_GET['bild']) == false) )
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}


//Loeschen eines Thumbnails
// photo_album : Referenz auf Objekt des relevanten Albums
// pic_nr      : Nr des Bildes dessen Thumbnail geloescht werden soll
function deleteThumbnail(&$photo_album, $pic_nr)
{
    if(is_numeric($pic_nr))
    {
        //Ordnerpfad zusammensetzen
        $photo_path = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin','Y-m-d').'_'.$photo_album->getValue('pho_id').'/thumbnails/'.$pic_nr.'.jpg';
		
        //Thumbnail loeschen
        if(file_exists($photo_path))
        {
            chmod($photo_path, 0777);
            unlink($photo_path);
        }
    }
}

//Loeschen eines Bildes
function deletePhoto($pho_id, $pic_nr)
{
    global $g_current_user, $g_db, $g_organization;

    // nur bei gueltigen Uebergaben weiterarbeiten
    if(is_numeric($pho_id) && is_numeric($pic_nr))
    {
        // einlesen des Albums
        $photo_album = new TablePhotos($g_db, $pho_id);
        
        //Speicherort
        $album_path = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin','Y-m-d').'_'.$photo_album->getValue('pho_id');
        
        //Bilder loeschen
        if(file_exists($album_path.'/'.$pic_nr.'.jpg'))
        {
            chmod($album_path.'/'.$pic_nr.'.jpg', 0777);
            unlink($album_path.'/'.$pic_nr.'.jpg');
        }

        // Umbenennen der Restbilder und Thumbnails loeschen
        $new_pic_nr = $pic_nr;
        $thumbnail_delete = false;

        for($act_pic_nr = 1; $act_pic_nr <= $photo_album->getValue('pho_quantity'); $act_pic_nr++)
        {
            if(file_exists($album_path.'/'.$act_pic_nr.'.jpg'))
            {
                if($act_pic_nr > $new_pic_nr)
                {
                    chmod($album_path.'/'.$act_pic_nr.'.jpg', 0777);
                    rename($album_path.'/'.$act_pic_nr.'.jpg', $album_path.'/'.$new_pic_nr.'.jpg');
                    $new_pic_nr++;
                }                
            }
            else
            {
                $thumbnail_delete = true;
            }
            
            if($thumbnail_delete)
            {
                // Alle Thumbnails ab dem geloeschten Bild loeschen
                deleteThumbnail($photo_album, $act_pic_nr);
            }
        }//for

        // Aendern der Datenbankeintaege
        $photo_album->setValue('pho_quantity', $photo_album->getValue('pho_quantity')-1);
        $photo_album->save();
    }
};


// Foto um 90° drehen
if($_GET['job'] == 'rotate')
{
    // nur bei gueltigen Uebergaben weiterarbeiten
    if(is_numeric($pho_id) && is_numeric($_GET['bild']) && ($_GET['direction'] == 'left' || $_GET['direction'] == 'right'))
    {
        //Aufruf des ggf. uebergebenen Albums
        $photo_album = new TablePhotos($g_db, $pho_id);

        //Thumbnail loeschen
        deleteThumbnail($photo_album, $_GET['bild']);
        
        //Ordnerpfad zusammensetzen
        $photo_path = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin','Y-m-d').'_'.$photo_album->getValue('pho_id'). '/'. $_GET['bild']. '.jpg';
        
        // Bild drehen
        $image = new Image($photo_path);
        $image->rotate($_GET['direction']);
        $image->delete();
    }    
}
elseif($_GET['job'] == 'do_delete')
{
    // das entsprechende Bild wird physikalisch und in der DB geloescht
    deletePhoto($pho_id, $_GET['bild']);
    
    //Neu laden der Albumdaten
    $photo_album = new TablePhotos($g_db);
    if($pho_id > 0)
    {
        $photo_album->readData($pho_id);
    }

    $_SESSION['photo_album'] =& $photo_album;
    
    // zur Ausgangsseite zurueck
    $_SESSION['navigation']->deleteLastUrl();
}
?>