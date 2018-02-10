<?php
/**
 ***********************************************************************************************
 * Photo download
 *
 * @copyright 2004-2018 The Admidio Team
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

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id',   'int');
$getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'int');

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_photo_module') === 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif ((int) $gSettingsManager->get('enable_photo_module') === 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require(__DIR__ . '/../../system/login_valid.php');
}

// check if download function is enabled
if (!$gSettingsManager->getBool('photo_download_enabled'))
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('PHO_DOWNLOAD_DISABLED'));
    // => EXIT
}

// create photo album object
$photoAlbum = new TablePhotos($gDb);

// get id of album
$photoAlbum->readDataById($getPhotoId);

// check if the current user could view this photo album
if(!$photoAlbum->isVisible())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if ((int) $photoAlbum->getValue('pho_quantity') === 0)
{
    $gMessage->show($gL10n->get('PHO_NO_ALBUM_CONTENT'));
    // => EXIT
}

// check whether to take original version instead of scaled one
$takeOriginalsIfAvailable = $gSettingsManager->getBool('photo_keep_original');

$albumFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $photoAlbum->getValue('pho_id');

// check folder vs single download
if ($getPhotoNr == null)
{
    // get number of photos in total
    $quantity = $photoAlbum->getValue('pho_quantity');

    // tempfolder
    // change this value if your provider requires the usage of special directories (e.g. HostEurope)
    //$tempfolder = "/is/htdocs/user_tmp/xxxxxx/";
    $tempFolder  = sys_get_temp_dir();
    $zipTempName = tempnam($tempFolder, 'zip');

    $zip = new \ZipArchive();
    $zipOpenCode = $zip->open($zipTempName, \ZipArchive::CREATE);

    if ($zipOpenCode !== true)
    {
        $gMessage->show($gL10n->get('PHP_DOWNLOAD_ZIP_ERROR'));
        // => EXIT
    }

    for ($i = 1; $i <= $quantity; ++$i)
    {
        if ($takeOriginalsIfAvailable)
        {
            // try to find the original version if available, if not fallback to the scaled one
            $path = $albumFolder . '/originals/' . $i;
            if (is_file($path . '.jpg'))
            {
                $path .= '.jpg';
                $zip->addFile($path, basename($path));
                continue;
            }
            elseif (is_file($path . '.png'))
            {
                $path .= '.png';
                $zip->addFile($path, basename($path));
                continue;
            }
        }

        $path = $albumFolder . '/' . $i . '.jpg';
        if (is_file($path))
        {
            $zip->addFile($path, basename($path));
        }
    }

    /************************add sub albums as subfolders*************************************/

    // get sub albums
    $sql = 'SELECT pho_id
              FROM '.TBL_PHOTOS.'
             WHERE pho_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')';
    $queryParams = array($gCurrentOrganization->getValue('org_id'));
    if ($getPhotoId === 0)
    {
        $sql .= '
            AND (pho_pho_id_parent IS NULL)';
    }
    if ($getPhotoId > 0)
    {
        $sql .= '
            AND pho_pho_id_parent = ? -- $getPhotoId';
        $queryParams[] = $getPhotoId;
    }
    if (!$gCurrentUser->editPhotoRight())
    {
        $sql .= '
            AND pho_locked = 0 ';
    }

    $sql .= '
        ORDER BY pho_begin DESC';
    $pdoStatement = $gDb->queryPrepared($sql, $queryParams);

    // number of sub albums
    $albums = $pdoStatement->rowCount();

    for ($x = 0; $x < $albums; ++$x)
    {
        // get id of album
        $photoAlbum->readDataById((int) $pdoStatement->fetchColumn());

        // ignore locked albums owned by others
        if ($photoAlbum->getValue('pho_locked') == 0 || $gCurrentUser->editPhotoRight())
        {
            $albumFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $photoAlbum->getValue('pho_id');
            // get number of photos in total
            $quantity = $photoAlbum->getValue('pho_quantity');
            $photoAlbumName = $photoAlbum->getValue('pho_name');
            for ($i = 1; $i <= $quantity; ++$i)
            {
                if ($takeOriginalsIfAvailable)
                {
                    // try to find the original version if available, if not fallback to the scaled one
                    $path = $albumFolder . '/originals/' . $i;
                    if (is_file($path . '.jpg'))
                    {
                        $path .= '.jpg';
                        $zip->addFile($path, $photoAlbumName . '/' . basename($path));
                        continue;
                    }
                    elseif (is_file($path . '.png'))
                    {
                        $path .= '.png';
                        $zip->addFile($path, $photoAlbumName . '/' . basename($path));
                        continue;
                    }
                }
                $path = $albumFolder . '/' . $i . '.jpg';
                if (is_file($path))
                {
                    $zip->addFile($path, $photoAlbumName . '/' . basename($path));
                }
            }
        }
    }

    $zipCloseValue = $zip->close();
    if ($zipCloseValue === false)
    {
        $gMessage->show($gL10n->get('PHP_DOWNLOAD_ZIP_ERROR'));
        // => EXIT
    }

    $filename = $photoAlbum->getValue('pho_name').' - '.$photoAlbum->getValue('pho_photographers').'.zip';
    $filename = FileSystemUtils::getSanitizedPathEntry($filename);

    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($zipTempName));
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: private');

    // send the file, use fpassthru for chunkwise transport
    $fp = fopen($zipTempName, 'rb');
    fpassthru($fp);

    try
    {
        FileSystemUtils::deleteFileIfExists($zipTempName);
    }
    catch (\RuntimeException $exception)
    {
    }
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
        if (is_file($path.'.jpg'))
        {
            header('Content-Type: image/jpeg');
            header('Content-Length: ' . filesize($path.'.jpg'));
            header('Content-Disposition: attachment; filename="'.$getPhotoNr.'.jpg"');
            $fp = fopen($path.'.jpg', 'rb');
            fpassthru($fp);
            exit();
        }
        elseif (is_file($path.'.png'))
        {
            header('Content-Type: image/png');
            header('Content-Length: ' . filesize($path.'.png'));
            header('Content-Disposition: attachment; filename="'.$getPhotoNr.'.png"');
            $fp = fopen($path.'.png', 'rb');
            fpassthru($fp);
            exit();
        }
    }

    $path = $albumFolder.'/'.$getPhotoNr.'.jpg';

    if (is_file($path))
    {
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="'.$getPhotoNr.'.jpg"');
        $fp = fopen($path, 'rb');
        fpassthru($fp);
    }

}
