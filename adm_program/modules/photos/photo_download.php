<?php
/**
 ***********************************************************************************************
 * Photo download
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Creates a zip file on the fly with all photos including sub-albums and returns it
 *
 * Parameters:
 *
 * pho_id   : id of album to download
 * photo_nr : Number of photo that should be downloaded
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id',   'int');
$getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'int');

// tempfolder
// change this value if your provider requires the usage of special directories (e.g. HostEurope)
//$tempfolder = "/is/htdocs/user_tmp/xxxxxx/";
$tempfolder = sys_get_temp_dir();

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif($gPreferences['enable_photo_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// check if download function is enabled
if ($gPreferences['photo_download_enabled'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('PHO_DOWNLOAD_DISABLED'));
    // => EXIT
}

// Fotoalbumobjekt anlegen
$photo_album = new TablePhotos($gDb);

// get id of album
$photo_album->readDataById($getPhotoId);

// check whether album belongs to the current organization
if((int) $photo_album->getValue('pho_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// check whether album is locked
if($photo_album->getValue('pho_locked') == 1 && !$gCurrentUser->editPhotoRight())
{
    $gMessage->show($gL10n->get('PHO_ALBUM_NOT_APPROVED'));
    // => EXIT
}

$albumFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photo_album->getValue('pho_begin', 'Y-m-d') . '_' . $photo_album->getValue('pho_id');

if((int) $photo_album->getValue('pho_quantity') === 0)
{
    $gMessage->show($gL10n->get('PHO_NO_ALBUM_CONTENT'));
    // => EXIT
}

// check whether to take original version instead of scaled one
$takeOriginalsIfAvailable = false;
if ($gPreferences['photo_keep_original'] == 1)
{
    $takeOriginalsIfAvailable = true;
}

// check folder vs single download
if($getPhotoNr == null)
{
    // get number of photos in total
    $quantity = $photo_album->getValue('pho_quantity');

    $zipname = tempnam($tempfolder, 'zip');
    $nicename = $photo_album->getValue('pho_name').' - '.$photo_album->getValue('pho_photographers').'.zip';

    $zip = new ZipArchive();
    $zip->open($zipname, ZipArchive::CREATE);

    for ($i = 1; $i <= $quantity; ++$i)
    {
        if ($takeOriginalsIfAvailable)
        {
            // try to find the original version if available, if not fallback to the scaled one
            $path = $albumFolder.'/originals/'.$i;
            if(is_file($path.'.jpg'))
            {
                $path .= '.jpg';
                $zip->addFromString(basename($path),  file_get_contents($path));
                continue;
            }
            elseif(is_file($path.'.png'))
            {
                $path .= '.png';
                $zip->addFromString(basename($path),  file_get_contents($path));
                continue;
            }
        }

        $path = $albumFolder.'/'.$i.'.jpg';
        if(is_file($path))
        {
            $zip->addFromString(basename($path),  file_get_contents($path));
        }
    }

    /************************add sub albums as subfolders*************************************/

    // get sub albums
    $sql = 'SELECT pho_id
              FROM '.TBL_PHOTOS.'
             WHERE pho_org_id = '.$gCurrentOrganization->getValue('org_id');
    if($getPhotoId === 0)
    {
        $sql .= ' AND (pho_pho_id_parent IS NULL) ';
    }
    if($getPhotoId > 0)
    {
        $sql .= ' AND pho_pho_id_parent = '.$getPhotoId.'';
    }
    if (!$gCurrentUser->editPhotoRight())
    {
        $sql .= ' AND pho_locked = 0 ';
    }

    $sql .= ' ORDER BY pho_begin DESC';
    $pdoStatement = $gDb->query($sql);

    // number of sub albums
    $albums = $pdoStatement->rowCount();

    for($x = 0; $x < $albums; ++$x)
    {
        // get id of album
        $photo_album->readDataById((int) $pdoStatement->fetchColumn());

        // ignore locked albums owned by others
        if($photo_album->getValue('pho_locked') == 0 || $gCurrentUser->editPhotoRight())
        {
            $albumFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photo_album->getValue('pho_begin', 'Y-m-d') . '_' . $photo_album->getValue('pho_id');
            // get number of photos in total
            $quantity = $photo_album->getValue('pho_quantity');
            $photo_album_name = $photo_album->getValue('pho_name');
            for ($i = 1; $i <= $quantity; ++$i)
            {
                if ($takeOriginalsIfAvailable)
                {
                    // try to find the original version if available, if not fallback to the scaled one
                    $path = $albumFolder.'/originals/'.$i;
                    if(is_file($path.'.jpg'))
                    {
                        $path .= '.jpg';
                        $zip->addFromString($photo_album_name.'/'.basename($path),  file_get_contents($path));
                        continue;
                    }
                    elseif(is_file($path.'.png'))
                    {
                        $path .= '.png';
                        $zip->addFromString($photo_album_name.'/'.basename($path),  file_get_contents($path));
                        continue;
                    }
                }
                $path = $albumFolder.'/'.$i.'.jpg';
                if(is_file($path))
                {
                    $zip->addFromString($photo_album_name.'/'.basename($path),  file_get_contents($path));
                }
            }
        }
    }

    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Description: File Transfer');
    header('Content-disposition: attachment; filename="'.$nicename.'"');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: private');

    // send the file, use fpassthru for chunkwise transport
    $fp = fopen($zipname, 'rb');
    fpassthru($fp);

    unlink($zipname);
}
else
{
    // download single file
    header('Content-Description: File Transfer');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: private');

    if ($takeOriginalsIfAvailable)
    {
        // try to find the original version if available, if not fallback to the scaled one
        $path = $albumFolder.'/originals/'.$getPhotoNr;
        if(is_file($path.'.jpg'))
        {
            header('Content-Type: application/jpeg');
            header('Content-disposition: attachment; filename="'.$getPhotoNr.'.jpg"');
            $fp = fopen($path.'.jpg', 'rb');
            fpassthru($fp);
            exit;
        }
        elseif(is_file($path.'.png'))
        {
            header('Content-Type: application/png');
            header('Content-disposition: attachment; filename="'.$getPhotoNr.'.png"');
            $fp = fopen($path.'.png', 'rb');
            fpassthru($fp);
            exit;
        }
    }

    $path = $albumFolder.'/'.$getPhotoNr.'.jpg';

    if(is_file($path))
    {
        header('Content-Type: application/jpeg');
        header('Content-disposition: attachment; filename="'.$getPhotoNr.'.jpg"');
        $fp = fopen($path, 'rb');
        fpassthru($fp);
    }

}
