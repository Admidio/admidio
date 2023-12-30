<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Improved checks and update of database after upload of files.
 *
 * This class extends the UploadHandler of the jquery-file-upload library. After
 * the upload of the file we do some checks on the file and if no check fails then
 * the Admidio database will be updated. If you want to upload files for the documents & files
 * module just create an instance of this class.
 *
 * **Code example**
 * ```
 * // create object and do upload
 * $uploadHandler = new UploadHandlerDownload(array('upload_dir' => $uploadDir,
 *                                                  'upload_url' => $uploadUrl,
 *                                                  'image_versions' => array()));
 * ```
 */

class UploadHandlerDownload extends UploadHandler
{
    /**
     * Override the default method to handle the specific things of the download module and
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
     * @throws Exception
     */
    protected function handle_file_upload($uploaded_file, $name, $size, $type, $error, $index = null, $content_range = null): stdClass
    {
        global $gSettingsManager, $gDb, $gLogger;

        $file = parent::handle_file_upload($uploaded_file, $name, $size, $type, $error, $index, $content_range);

        if (!isset($file->error)) {
            try {
                // check filesize against module settings
                if ($file->size > $gSettingsManager->getInt('documents_files_max_upload_size') * 1024 * 1024) {
                    throw new AdmException('SYS_FILE_TO_LARGE_SERVER', array($gSettingsManager->getInt('documents_files_max_upload_size')));
                }

                // check filename and throw exception if something is wrong
                StringUtils::strIsValidFileName($file->name, false);

                // replace invalid characters in filename
                $file->name = FileSystemUtils::removeInvalidCharsInFilename($file->name);

                // get recordset of current folder from database and throw exception if necessary
                $targetFolder = new TableFolder($gDb);
                $targetFolder->getFolderForDownload($GLOBALS['getUuid']);

                // now add new file to database
                $newFile = new TableFile($gDb);
                $newFile->setValue('fil_fol_id', (int) $targetFolder->getValue('fol_id'));
                $newFile->setValue('fil_name', $file->name);
                $newFile->setValue('fil_locked', $targetFolder->getValue('fol_locked'));
                $newFile->setValue('fil_counter', 0);

                if (!$newFile->allowedFileExtension()) {
                    throw new AdmException('SYS_FILE_EXTENSION_INVALID');
                }

                if($newFile->save()) {
                    // Notification an email for new or changed entries to all members of the notification role
                    $newFile->sendNotification();
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
                $file->error = $e->getMessage();

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
            $file->error = $exception->getMessage();
            // => EXIT
        }
        // ADM End
    }
}
