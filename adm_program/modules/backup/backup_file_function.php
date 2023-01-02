<?php
/**
 ***********************************************************************************************
 * Backup functions
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * job - get_file : download backup file
 *     - delete   : delete backup file
 * filename       : The name of the file to be used
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getJob      = admFuncVariableIsValid($_GET, 'job', 'string', array('requireValue' => true, 'validValues' => array('delete', 'get_file')));
$getFilename = admFuncVariableIsValid($_GET, 'filename', 'file', array('requireValue' => true));

// only administrators are allowed to create backups
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$backupAbsolutePath = ADMIDIO_PATH . FOLDER_DATA . '/backup/'; // make sure to include trailing slash

// get complete path of the file
$completePath = $backupAbsolutePath.$getFilename;

// check if file exists physically at all
if (!is_file($completePath)) {
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
    // => EXIT
}

switch ($getJob) {
    case 'get_file':
        // Determine file size
        $fileSize = filesize($completePath);
        $filename = FileSystemUtils::getSanitizedPathEntry($getFilename);

        // Create suitable data type
        header('Content-Type: application/octet-stream');
        header('Content-Length: '.$fileSize);
        header('Content-Disposition: attachment; filename="'.$filename.'"');

        // necessary for IE, because without it the download with SSL has problems
        header('Cache-Control: private');
        header('Pragma: public');

        // get file output
        readfile($completePath);
        break;

    case 'delete':
        // Delete backup file

        try {
            // check the CSRF token of the form against the session token
            SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);

            FileSystemUtils::deleteFileIfExists($completePath);
            echo 'done';
        } catch (AdmException $e) {
            $e->showText();
            // => EXIT
        } catch (\RuntimeException $exception) {
            $gLogger->error('Could not delete file!', array('filePath' => $completePath));
            // TODO
        }
        exit();
        break;
}
