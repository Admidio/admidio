<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class UploadHandlerPhoto
 * @brief Improved checks and update of database after upload of photos.
 *
 * This class extends the UploadHandler of the jquery-file-upload library. After
 * the upload of a photo we do some checks on the file and if no check fails then
 * the Admidio database will be updated. If you want do upload files for the download
 * module just create an instance of this class.
 * @par Examples
 * @code // create object and do upload
 * $uploadHandler = new UploadHandlerPhoto(array('upload_dir' => $uploadDir,
 *                                               'upload_url' => $uploadUrl,
 *                                               'image_versions' => array(),
 *                                               'accept_file_types' => '/\.(jpe?g|png)$/i'), true,
 *                                               'array('accept_file_types' => $gL10n->get('PHO_PHOTO_FORMAT_INVALID'))); @endcode
 */
class UploadHandlerPhoto extends UploadHandler
{
    /**
     * Override the default method to handle the specific things of the photo module and
     * update the database after file was successful uploaded.
     * This method has the same parameters as the default.
     * @param string $uploadedFile
     * @param string $name
     * @param int    $size
     * @param        $type
     * @param        $error
     * @param        $index
     * @param        $contentRange
     * @return \stdClass
     */
    protected function handle_file_upload($uploadedFile, $name, $size, $type, $error, $index = null, $contentRange = null)
    {
        global $photoAlbum, $gSettingsManager, $gL10n;

        $file = parent::handle_file_upload($uploadedFile, $name, $size, $type, $error, $index, $contentRange);

        if(!isset($file->error))
        {
            try
            {
                $fileLocation = ADMIDIO_PATH . FOLDER_DATA . '/photos/upload/' . $file->name;
                $albumFolder  = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $photoAlbum->getValue('pho_id');

                // create folder if not exists
                if(!is_dir($albumFolder))
                {
                    $error = $photoAlbum->createFolder();

                    if(is_array($error))
                    {
                        $file->error = $gL10n->get($error['text'], $error['path']);
                        return $file;
                    }
                }

                $newPhotoFileNumber = $photoAlbum->getValue('pho_quantity') + 1;

                // read image size
                $imageProperties = getimagesize($fileLocation);
                if ($imageProperties === false)
                {
                    throw new AdmException('PHO_PHOTO_FORMAT_INVALID');
                }

                // check mime type and set file extension
                switch ($imageProperties['mime'])
                {
                    case 'image/jpeg':
                        $fileExtension = 'jpg';
                        break;
                    case 'image/png':
                        $fileExtension = 'png';
                        break;
                    default:
                        throw new AdmException('PHO_PHOTO_FORMAT_INVALID');
                }

                $imageDimensions = $imageProperties[0] * $imageProperties[1];
                if ($imageDimensions > admFuncProcessableImageSize())
                {
                    $imageSize = round(admFuncProcessableImageSize() / 1000000, 2);
                    throw new AdmException($gL10n->get('PHO_RESOLUTION_MORE_THAN') . ' ' . $imageSize . ' ' . $gL10n->get('MEGA_PIXEL'));
                }

                // create image object and scale image to defined size of preferences
                $image = new Image($fileLocation);
                $image->setImageType('jpeg');
                $image->scaleLargerSide($gSettingsManager->get('photo_save_scale'));
                $image->copyToFile(null, $albumFolder.'/'.$newPhotoFileNumber.'.jpg');
                $image->delete();

                // if enabled then save original image
                if ($gSettingsManager->get('photo_keep_original') == 1)
                {
                    if(!is_dir($albumFolder.'/originals'))
                    {
                        $folder = new Folder($albumFolder);
                        $folder->createFolder('originals', true);
                    }

                    rename($fileLocation, $albumFolder.'/originals/'.$newPhotoFileNumber.'.'.$fileExtension);
                }

                // save thumbnail
                if(!is_dir($albumFolder.'/thumbnails'))
                {
                    $folder = new Folder($albumFolder);
                    $folder->createFolder('thumbnails', true);
                }

                $image = new Image($fileLocation);
                $image->scaleLargerSide($gSettingsManager->get('photo_thumbs_scale'));
                $image->copyToFile(null, $albumFolder.'/thumbnails/'.$newPhotoFileNumber.'.jpg');
                $image->delete();

                // delete image from upload folder
                if(is_file($fileLocation))
                {
                    unlink($fileLocation);
                }

                // if image was successfully saved in filesystem then update image count of album
                if(is_file($albumFolder.'/'.$newPhotoFileNumber.'.jpg'))
                {
                    $photoAlbum->setValue('pho_quantity', $photoAlbum->getValue('pho_quantity') + 1);
                    $photoAlbum->save();
                }
                else
                {
                    throw new AdmException('PHO_PHOTO_PROCESSING_ERROR');
                }
            }
            catch(AdmException $e)
            {
                $file->error = $e->getText();
                unlink($this->options['upload_dir'].$file->name);
                return $file;
            }
        }

        return $file;
    }
}
