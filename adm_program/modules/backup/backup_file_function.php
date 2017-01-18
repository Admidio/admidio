<?php
/**
 ***********************************************************************************************
 * Backup
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * job      - get_file : die uebergebene Backupdatei wird heruntergeladen
 *          - delete   : die uebergebene Backupdatei wird geloescht
 * filename : Der Name der Datei, welche heruntergeladen werden soll
 ***********************************************************************************************
 */
require('../../system/common.php');
require('../../system/login_valid.php');

// Initialize and check the parameters
$getJob      = admFuncVariableIsValid($_GET, 'job',      'string', array('requireValue' => true, 'validValues' => array('delete', 'get_file')));
$getFilename = admFuncVariableIsValid($_GET, 'filename', 'file',   array('requireValue' => true));

// only administrators are allowed to create backups
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$backupAbsolutePath = ADMIDIO_PATH . FOLDER_DATA . '/backup/'; // make sure to include trailing slash

// kompletten Pfad der Datei holen
$completePath = $backupAbsolutePath.$getFilename;

// pruefen ob File ueberhaupt physikalisch existiert
if(!is_file($completePath))
{
    $gMessage->show($gL10n->get('SYS_FILE_NOT_EXIST'));
    // => EXIT
}

switch($getJob)
{
    case 'get_file':
        // Dateigroese ermitteln
        $fileSize = filesize($completePath);

        // for IE the filename must have special chars in hexadecimal
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)
        {
            $getFilename = urlencode($getFilename);
        }

        // Passenden Datentyp erzeugen.
        header('Content-Type: application/octet-stream');
        header('Content-Length: '.$fileSize);
        header('Content-Disposition: attachment; filename="'.$getFilename.'"');

        // necessary for IE, because without it the download with SSL has problems
        header('Cache-Control: private');
        header('Pragma: public');

        // Datei ausgeben.
        echo readfile($completePath);
        break;

    case 'delete':
        // Backupdatei loeschen
        if(unlink($completePath))
        {
            echo 'done';
        }
        exit();
        break;
}
