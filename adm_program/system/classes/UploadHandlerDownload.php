<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Improved checks and update of database after upload of files.
 *
 * This class extends the UploadHandler of the jquery-file-upload library. After
 * the upload of the file we do some checks on the file and if no check fails then
 * the Admidio database will be updated. If you want do upload files for the download
 * module just create an instance of this class.
 *
 * **Code example:**
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
        global $gSettingsManager, $gL10n, $gDb, $getId, $gCurrentOrganization, $gCurrentUser;

        $file = parent::handle_file_upload($uploadedFile, $name, $size, $type, $error, $index, $contentRange);

        if(!isset($file->error))
        {
            try
            {
                // check filesize against module settings
                if ($file->size > $gSettingsManager->getInt('max_file_upload_size') * 1024 * 1024)
                {
                    throw new AdmException('DOW_FILE_TO_LARGE', $gSettingsManager->getInt('max_file_upload_size'));
                }

                // check filename and throw exception if something is wrong
                admStrIsValidFileName($file->name, true);

                // replace invalid characters in filename
                $file->name = FileSystemUtils::removeInvalidCharsInFilename($file->name);

                // get recordset of current folder from database and throw exception if necessary
                $targetFolder = new TableFolder($gDb);
                $targetFolder->getFolderForDownload($getId);

                // now add new file to database
                $newFile = new TableFile($gDb);
                $newFile->setValue('fil_fol_id', $targetFolder->getValue('fol_id'));
                $newFile->setValue('fil_name', $file->name);
                $newFile->setValue('fil_locked', $targetFolder->getValue('fol_locked'));
                $newFile->setValue('fil_counter', 0);
                $newFile->save();

                // Benachrichtigungs-Email für neue Einträge
                $fullName = $gCurrentUser->getValue('FIRST_NAME') . ' ' . $gCurrentUser->getValue('LAST_NAME');
                $message = $gL10n->get(
                    'DOW_EMAIL_NOTIFICATION_MESSAGE',
                    array(
                        $gCurrentOrganization->getValue('org_longname'),
                        $file->name,
                        $fullName,
                        date($gSettingsManager->getString('system_date'))
                    )
                );
                $notification = new Email();
                $notification->adminNotification(
                    $gL10n->get('DOW_EMAIL_NOTIFICATION_TITLE'),
                    $message,
                    $fullName,
                    $gCurrentUser->getValue('EMAIL')
                );
            }
            catch(AdmException $e)
            {
                $file->error = $e->getText();

                try
                {
                    FileSystemUtils::deleteFileIfExists($this->options['upload_dir'].$file->name);
                }
                catch (\RuntimeException $exception)
                {
                }

                return $file;
            }
        }

        return $file;
    }
}
