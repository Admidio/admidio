<?php
/**
 ***********************************************************************************************
 * Handle image uploads from CKEditor
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * CKEditor        : ID of textarea, that had triggered the upload
 * CKEditorFuncNum : function number, that will handle in the editor the new URL
 * langCode        : language code
 ***********************************************************************************************
 */
require_once(__DIR__ . '/common.php');
require(__DIR__ . '/login_valid.php');

$getCKEditor        = admFuncVariableIsValid($_GET, 'CKEditor',        'string', array('directOutput' => true, 'requireValue' => true));
$getCKEditorFuncNum = admFuncVariableIsValid($_GET, 'CKEditorFuncNum', 'string', array('directOutput' => true, 'requireValue' => true));
$getlangCode        = admFuncVariableIsValid($_GET, 'langCode',        'string', array('directOutput' => true));

$htmlUrl = '';
$message = '';

// checks if the server settings for file_upload are set to ON
if (!PhpIniUtils::isFileUploadEnabled())
{
    $message = $gL10n->get('SYS_SERVER_NO_UPLOAD');
}

// if necessary create the module folders in adm_my_files
switch ($getCKEditor)
{
    case 'ann_description':
        $folderName = 'announcements';
        break;
    case 'dat_description':
        $folderName = 'dates';
        break;
    case 'lnk_description':
        $folderName = 'weblinks';
        break;
    case 'msg_body':
        $folderName = 'mail';
        break;
    case 'plugin_CKEditor':
        $folderName = 'plugins';
        break;
    case 'room_description':
        $folderName = 'rooms';
        break;
    case 'usf_description':
        $folderName = 'user_fields';
        break;
    default:
        // TODO
}

// set path to module folder in adm_my_files
$myFilesUploadPhotos = new MyFiles($folderName);
// upload photo to images folder of module folder
if ($myFilesUploadPhotos->checkSettings())
{
    $imagesPath = ADMIDIO_PATH . FOLDER_DATA . '/' . $folderName . '/images';

    // create a filename with the unix timestamp,
    // so we have a scheme for the filenames and the risk of duplicates is low
    $extension = pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION);
    $now = time();
    $filename = $now . '.' . $extension;
    $storagePath = $imagesPath . '/' . $filename;
    if (is_file($storagePath))
    {
        // if file exists than create a random number and append it to the filename
        $filename = $now . '_' . mt_rand() . '.' . $extension;
        $storagePath = $imagesPath . '/' . $filename;
    }
    $htmlUrl = safeUrl(ADMIDIO_URL . '/adm_program/system/show_image.php', array('module' => $folderName, 'file' => $filename));

    move_uploaded_file($_FILES['upload']['tmp_name'], $storagePath);
}
else
{
    $message = strStripTags($gL10n->get(
        $myFilesUploadPhotos->errorText,
        array($myFilesUploadPhotos->errorPath,
        '<a href="mailto:'.$gSettingsManager->getString('email_administrator').'">', '</a>')
    ));
}

// now call CKEditor function and send photo data
echo '<!DOCTYPE html>
<html>
    <body>
        <script type="text/javascript">
            window.parent.CKEDITOR.tools.callFunction('.$getCKEditorFuncNum.', "'.$htmlUrl.'", "'.$message.'");
        </script>
    </body>
</html>';
