<?php
/**
 ***********************************************************************************************
 * Photofunktionen
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * pho_id:   Id des Albums
 * job:      delete - loeschen eines Bildes
 *           rotate - drehen eines Bildes
 * direction: left  - Bild nach links drehen
 *            right - Bild nach rechts drehen
 * photo_nr:  Nr des Bildes welches verarbeitet werden soll
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoId   = admFuncVariableIsValid($_GET, 'pho_id',    'int',    array('requireValue' => true));
$getJob       = admFuncVariableIsValid($_GET, 'job',       'string', array('requireValue' => true, 'validValues' => array('delete', 'rotate')));
$getPhotoNr   = admFuncVariableIsValid($_GET, 'photo_nr',  'int',    array('requireValue' => true));
$getDirection = admFuncVariableIsValid($_GET, 'direction', 'string', array('validValues' => array('left', 'right')));

if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if (!$gCurrentUser->editPhotoRight())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    // => EXIT
}

/**
 * Loeschen eines Thumbnails
 * @param \TablePhotos $photo_album Referenz auf Objekt des relevanten Albums
 * @param int          $pic_nr      Nr des Bildes dessen Thumbnail geloescht werden soll
 */
function deleteThumbnail(&$photo_album, $pic_nr)
{
    if(is_numeric($pic_nr))
    {
        // Ordnerpfad zusammensetzen
        $photo_path = ADMIDIO_PATH . FOLDER_DATA . '/photos/'.$photo_album->getValue('pho_begin', 'Y-m-d') . '_' . $photo_album->getValue('pho_id') . '/thumbnails/' . $pic_nr . '.jpg';

        // Thumbnail loeschen
        if(is_file($photo_path))
        {
            @chmod($photo_path, 0777);
            unlink($photo_path);
        }
    }
}

/**
 * @param string $path
 */
function tryDelete($path)
{
    if(is_file($path))
    {
        @chmod($path, 0777);
        unlink($path);
    }
}

/**
 * @param string $path
 * @param string $newPath
 */
function tryRename($path, $newPath)
{
    if(is_file($path))
    {
        @chmod($path, 0777);
        rename($path, $newPath);
    }
}

/**
 * Loeschen eines Bildes
 * @param int $pho_id
 * @param int $pic_nr
 */
function deletePhoto($pho_id, $pic_nr)
{
    global $gDb;

    // nur bei gueltigen Uebergaben weiterarbeiten
    if(is_numeric($pho_id) && is_numeric($pic_nr))
    {
        // einlesen des Albums
        $photo_album = new TablePhotos($gDb, $pho_id);

        // Speicherort
        $album_path = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photo_album->getValue('pho_begin', 'Y-m-d') . '_' . $photo_album->getValue('pho_id');

        // delete photos
        tryDelete($album_path.'/'.$pic_nr.'.jpg');
        tryDelete($album_path.'/originals/'.$pic_nr.'.jpg');
        tryDelete($album_path.'/originals/'.$pic_nr.'.png');

        // Umbenennen der Restbilder und Thumbnails loeschen
        $new_pic_nr = $pic_nr;
        $thumbnail_delete = false;

        for($act_pic_nr = 1; $act_pic_nr <= $photo_album->getValue('pho_quantity'); ++$act_pic_nr)
        {
            if(is_file($album_path.'/'.$act_pic_nr.'.jpg'))
            {
                if($act_pic_nr > $new_pic_nr)
                {
                    tryRename($album_path.'/'.$act_pic_nr.'.jpg', $album_path.'/'.$new_pic_nr.'.jpg');
                    tryRename($album_path.'/originals/'.$act_pic_nr.'.jpg', $album_path.'/originals/'.$new_pic_nr.'.jpg');
                    tryRename($album_path.'/originals/'.$act_pic_nr.'.png', $album_path.'/originals/'.$new_pic_nr.'.png');
                    ++$new_pic_nr;
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
}

// Foto um 90° drehen
if($getJob === 'rotate')
{
    // nur bei gueltigen Uebergaben weiterarbeiten
    if($getDirection !== '')
    {
        // Aufruf des ggf. uebergebenen Albums
        $photo_album = new TablePhotos($gDb, $getPhotoId);

        // Thumbnail loeschen
        deleteThumbnail($photo_album, $getPhotoNr);

        // Ordnerpfad zusammensetzen
        $photo_path = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photo_album->getValue('pho_begin', 'Y-m-d') . '_' . $photo_album->getValue('pho_id') . '/' . $getPhotoNr . '.jpg';

        // Bild drehen
        $image = new Image($photo_path);
        $image->rotate($getDirection);
        $image->delete();
    }
}
elseif($getJob === 'delete')
{
    // das entsprechende Bild wird physikalisch und in der DB geloescht
    deletePhoto($getPhotoId, $getPhotoNr);

    // Neu laden der Albumdaten
    $photo_album = new TablePhotos($gDb);
    if($getPhotoId > 0)
    {
        $photo_album->readDataById($getPhotoId);
    }

    $_SESSION['photo_album'] = $photo_album;

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
