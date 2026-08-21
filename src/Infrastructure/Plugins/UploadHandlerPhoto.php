<?php

namespace Admidio\Infrastructure\Plugins;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Photos\Service\PhotoService;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use RuntimeException;
use stdClass;
use UploadHandler;

/**
 * Improved checks and update of database after upload of photos.
 *
 * This class extends the UploadHandler of the jquery-file-upload library. After
 * the upload of a photo we do some checks on the file and if no check fails then
 * the Admidio database will be updated. If you want to upload photos for the photos
 * module just create an instance of this class.
 *
 * **Code example**
 * ```
 * // create object and do upload
 * $uploadHandler = new UploadHandlerPhoto(array('upload_dir' => $uploadDir,
 *                                               'upload_url' => $uploadUrl,
 *                                               'image_versions' => array(),
 *                                               'accept_file_types' => '/\.(jpe?g|png)$/i'), true,
 *                                               'array('accept_file_types' => $gL10n->get('SYS_PHOTO_FORMAT_INVALID')));
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class UploadHandlerPhoto extends UploadHandler
{
    /**
     * Override the default method to handle the specific things of the photo module and
     * update the database after file was successfully uploaded.
     * This method has the same parameters as the default.
     * @param string $uploaded_file
     * @param string $name
     * @param int $size
     * @param        $type
     * @param        $error
     * @param        $index
     * @param        $content_range
     * @return stdClass
     */
    protected function handle_file_upload($uploaded_file, $name, $size, $type, $error, $index = null, $content_range = null): stdClass
    {
        global $photoAlbum, $gDb, $gLogger, $gDisableFileUpload;

        $file = new stdClass();
        $file->name = $name;
        $fileLocation = $this->options['upload_dir'] . $name;

        try {
            $file = parent::handle_file_upload($uploaded_file, $name, $size, $type, $error, $index, $content_range);
            if (isset($file->error)) {
                throw new Exception($file->error);
            }

            if ($gDisableFileUpload) {
                throw new Exception('File upload disabled in global config file!');
            }

            $fileLocation = ADMIDIO_PATH . FOLDER_DATA . '/photos/upload/' . $file->name;

            // check filename and throw exception if something is wrong
            StringUtils::strIsValidFileName($file->name, false);

            // replace invalid characters in filename
            $file->name = FileSystemUtils::removeInvalidCharsInFilename($file->name);

            (new PhotoService($gDb, $photoAlbum))->uploadFromFile($fileLocation);

            try {
                FileSystemUtils::deleteFileIfExists($fileLocation);
            } catch (RuntimeException $exception) {
                $gLogger->error('Could not delete file!', array('filePath' => $fileLocation));
            }
        } catch (\Throwable $e) {
            try {
                FileSystemUtils::deleteFileIfExists($fileLocation);
            } catch (RuntimeException $exception) {
                $gLogger->error('Could not delete file!', array('filePath' => $fileLocation));
                // TODO
            }
            // remove XSS from filename before the name will be shown in the error message
            $file->name = SecurityUtils::encodeHTML(StringUtils::strStripTags($file->name));
            $file->error = $e->getMessage();

            return $file;
        }

        return $file;
    }

    /**
     * Override the default method to handle specific form data that will be set when creating the Javascript
     * file upload object. Here we validate the CSRF token that will be set. If the check failed an error will
     * be set and the file upload will be canceled.
     * @param string $file
     * @param int $index
     */
    protected function handle_form_data($file, $index)
    {
        // ADM Start
        try {
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_REQUEST['adm_csrf_token']);
        } catch (Exception $exception) {
            $file->error = $exception->getMessage();
            // => EXIT
        }
        // ADM End
    }
}
