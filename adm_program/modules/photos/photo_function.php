<?php
/**
 ***********************************************************************************************
 * Photofunktionen
 *
 * @copyright 2004-2018 The Admidio Team
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
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoId   = admFuncVariableIsValid($_GET, 'pho_id',    'int',    array('requireValue' => true));
$getJob       = admFuncVariableIsValid($_GET, 'job',       'string', array('requireValue' => true, 'validValues' => array('delete', 'rotate')));
$getPhotoNr   = admFuncVariableIsValid($_GET, 'photo_nr',  'int',    array('requireValue' => true));
$getDirection = admFuncVariableIsValid($_GET, 'direction', 'string', array('validValues' => array('left', 'right')));

if ((int) $gSettingsManager->get('enable_photo_module') === 0)
{
    // check if the module is activated
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

// check if current user has right to upload photos
if (!$gCurrentUser->editPhotoRight())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    // => EXIT
}

/**
 * Loeschen eines Thumbnails
 * @param TablePhotos $photoAlbum Referenz auf Objekt des relevanten Albums
 * @param int         $picNr      Nr des Bildes dessen Thumbnail geloescht werden soll
 */
function deleteThumbnail(TablePhotos $photoAlbum, $picNr)
{
    // Ordnerpfad zusammensetzen
    $photoPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/'.$photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . (int) $photoAlbum->getValue('pho_id') . '/thumbnails/' . $picNr . '.jpg';
    try
    {
        FileSystemUtils::deleteFileIfExists($photoPath);
    }
    catch (\RuntimeException $exception)
    {
    }
}

/**
 * Delete the photo from the filesystem and update number of photos in database.
 * @param TablePhotos $photoAlbum
 * @param int $picNr
 */
function deletePhoto(TablePhotos $photoAlbum, $picNr)
{
    // Speicherort
    $albumPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $photoAlbum->getValue('pho_id');

    // delete photos
    try
    {
        FileSystemUtils::deleteFileIfExists($albumPath.'/'.$picNr.'.jpg');
        FileSystemUtils::deleteFileIfExists($albumPath.'/originals/'.$picNr.'.jpg');
        FileSystemUtils::deleteFileIfExists($albumPath.'/originals/'.$picNr.'.png');
    }
    catch (\RuntimeException $exception)
    {
    }

    // Umbenennen der Restbilder und Thumbnails loeschen
    $newPicNr = $picNr;
    $thumbnailDelete = false;

    for ($actPicNr = 1; $actPicNr <= (int) $photoAlbum->getValue('pho_quantity'); ++$actPicNr)
    {
        if (is_file($albumPath.'/'.$actPicNr.'.jpg'))
        {
            if ($actPicNr > $newPicNr)
            {
                try
                {
                    FileSystemUtils::moveFile($albumPath.'/'.$actPicNr.'.jpg', $albumPath.'/'.$newPicNr.'.jpg');
                    FileSystemUtils::moveFile($albumPath.'/originals/'.$actPicNr.'.jpg', $albumPath.'/originals/'.$newPicNr.'.jpg');
                    FileSystemUtils::moveFile($albumPath.'/originals/'.$actPicNr.'.png', $albumPath.'/originals/'.$newPicNr.'.png');
                }
                catch (\RuntimeException $exception)
                {
                }
                ++$newPicNr;
            }
        }
        else
        {
            $thumbnailDelete = true;
        }

        if ($thumbnailDelete)
        {
            // Alle Thumbnails ab dem geloeschten Bild loeschen
            deleteThumbnail($photoAlbum, $actPicNr);
        }
    }//for

    // Aendern der Datenbankeintaege
    $photoAlbum->setValue('pho_quantity', $photoAlbum->getValue('pho_quantity')-1);
    $photoAlbum->save();
}

// create photo album object
$photoAlbum = new TablePhotos($gDb, $getPhotoId);

// check if the user is allowed to edit this photo album
if (!$photoAlbum->isEditable())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    // => EXIT
}

// Rotate the photo by 90°
if ($getJob === 'rotate')
{
    // nur bei gueltigen Uebergaben weiterarbeiten
    if ($getDirection !== '')
    {
        // Thumbnail loeschen
        deleteThumbnail($photoAlbum, $getPhotoNr);

        // Ordnerpfad zusammensetzen
        $photoPath = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $photoAlbum->getValue('pho_id') . '/' . $getPhotoNr . '.jpg';

        // Bild drehen
        $image = new Image($photoPath);
        $image->rotate($getDirection);
        $image->delete();
    }
}
// delete photo from filesystem and update photo album
elseif ($getJob === 'delete')
{
    deletePhoto($photoAlbum, $getPhotoNr);

    $_SESSION['photo_album'] = $photoAlbum;

    // Loeschen erfolgreich -> Rueckgabe fuer XMLHttpRequest
    echo 'done';
}
