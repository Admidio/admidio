<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Improved checks and update of database after upload of photos.
 *
 * This class extends the UploadHandler of the jquery-file-upload library. After
 * the upload of a photo we do some checks on the file and if no check fails then
 * the Admidio database will be updated. If you want do upload photos for the photos
 * module just create an instance of this class.
 *
 * **Code example**
 * ```
 * // create object and do upload
 * $uploadHandler = new UploadHandlerPhoto(array('upload_dir' => $uploadDir,
 *                                               'upload_url' => $uploadUrl,
 *                                               'image_versions' => array(),
 *                                               'accept_file_types' => '/\.(jpe?g|png)$/i'), true,
 *                                               'array('accept_file_types' => $gL10n->get('PHO_PHOTO_FORMAT_INVALID')));
 * ```
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
        global $photoAlbum, $gSettingsManager, $gL10n, $gLogger;

        $file = parent::handle_file_upload($uploadedFile, $name, $size, $type, $error, $index, $contentRange);

        if (!isset($file->error)) {
            try {
                $fileLocation = ADMIDIO_PATH . FOLDER_DATA . '/photos/upload/' . $file->name;
                $albumFolder  = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $photoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . (int) $photoAlbum->getValue('pho_id');

                // check filename and throw exception if something is wrong
                StringUtils::strIsValidFileName($file->name, false);

                // replace invalid characters in filename
                $file->name = FileSystemUtils::removeInvalidCharsInFilename($file->name);

                // create folder if not exists
                if (!is_dir($albumFolder)) {
                    $error = $photoAlbum->createFolder();

                    if (is_array($error)) {
                        $file->error = $gL10n->get($error['text'], array($error['path']));
                        return $file;
                    }
                }

                $newPhotoFileNumber = $photoAlbum->getValue('pho_quantity') + 1;

                // check if the file contains a valid image and read image properties
                $imageProperties = getimagesize($fileLocation);
                if ($imageProperties === false) {
                    throw new AdmException('PHO_PHOTO_FORMAT_INVALID');
                }

                // check mime type and set file extension
                switch ($imageProperties['mime']) {
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
                $processableImageSize = admFuncProcessableImageSize();
                if ($imageDimensions > $processableImageSize) {
                    throw new AdmException($gL10n->get('PHO_RESOLUTION_MORE_THAN') . ' ' . round($processableImageSize / 1000000, 2) . ' ' . $gL10n->get('SYS_MEGAPIXEL'));
                }

                // create image object and scale image to defined size of preferences
                $image = new Image($fileLocation);
                $image->setImageType('jpeg');
                $image->scaleLargerSide($gSettingsManager->getInt('photo_save_scale'));
                $image->copyToFile(null, $albumFolder.'/'.$newPhotoFileNumber.'.jpg');
                $image->delete();

                // if enabled then save original image
                if ($gSettingsManager->getBool('photo_keep_original')) {
                    try {
                        FileSystemUtils::createDirectoryIfNotExists($albumFolder . '/originals');

                        try {
                            FileSystemUtils::moveFile($fileLocation, $albumFolder.'/originals/'.$newPhotoFileNumber.'.'.$fileExtension);
                        } catch (\RuntimeException $exception) {
                            $gLogger->error('Could not move file!', array('from' => $fileLocation, 'to' => $albumFolder.'/originals/'.$newPhotoFileNumber.'.'.$fileExtension));
                            // TODO
                        }
                    } catch (\RuntimeException $exception) {
                        $gLogger->error('Could not create directory!', array('directoryPath' => $albumFolder . '/originals'));
                        // TODO
                    }
                }

                // save thumbnail
                try {
                    FileSystemUtils::createDirectoryIfNotExists($albumFolder . '/thumbnails');
                } catch (\RuntimeException $exception) {
                }

                $image = new Image($fileLocation);
                $image->scaleLargerSide($gSettingsManager->getInt('photo_thumbs_scale'));
                $image->copyToFile(null, $albumFolder.'/thumbnails/'.$newPhotoFileNumber.'.jpg');
                $image->delete();

                // delete image from upload folder
                try {
                    FileSystemUtils::deleteFileIfExists($fileLocation);
                } catch (\RuntimeException $exception) {
                }

                // if image was successfully saved in filesystem then update image count of album
                if (is_file($albumFolder.'/'.$newPhotoFileNumber.'.jpg')) {
                    $photoAlbum->setValue('pho_quantity', (int) $photoAlbum->getValue('pho_quantity') + 1);
                    $photoAlbum->save();
                } else {
                    throw new AdmException('PHO_PHOTO_PROCESSING_ERROR');
                }
            } catch (AdmException $e) {
                try {
                    FileSystemUtils::deleteFileIfExists($this->options['upload_dir'].$file->name);
                } catch (RuntimeException $exception) {
                    $gLogger->error('Could not delete file!', array('filePath' => $this->options['upload_dir'].$file->name));
                    // TODO
                }
                // remove XSS from filename before the name will be shown in the error message
                $file->name = SecurityUtils::encodeHTML(StringUtils::strStripTags($file->name));
                $file->error = $e->getText();

                return $file;
            }
        }

        return $file;
    }

    /**
     * Override the default method to handle specific form data that will be set when creating the Javascript
     * file upload object. Here we validate the CSRF token that will be set. If the check failed an error will
     * be set and the file upload will be canceled.
     * @param string $file
     * @param int    $index
     */
    protected function handle_form_data($file, $index)
    {
        // ADM Start
        try {
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_REQUEST['admidio-csrf-token']);
        } catch (AdmException $exception) {
            $file->error = $exception->getText();
            // => EXIT
        }
        // ADM End
    }
}
