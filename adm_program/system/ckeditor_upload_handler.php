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
require_once('common.php');
require_once('login_valid.php');

$getCKEditor        = admFuncVariableIsValid($_GET, 'CKEditor',        'string', array('directOutput' => true, 'requireValue' => true));
$getCKEditorFuncNum = admFuncVariableIsValid($_GET, 'CKEditorFuncNum', 'string', array('directOutput' => true, 'requireValue' => true));
$getlangCode        = admFuncVariableIsValid($_GET, 'langCode',        'string', array('directOutput' => true));

$message = '';

try
{
    // checks if the server settings for file_upload are set to ON
    if (ini_get('file_uploads') !== '1')
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
    }

    // set path to module folder in adm_my_files
    $myFilesProfilePhotos = new MyFiles($folderName);
    // upload photo to images folder of module folder
    if($myFilesProfilePhotos->checkSettings() && $myFilesProfilePhotos->setSubFolder('images'))
    {
        // create a filename with the unix timestamp,
        // so we have a scheme for the filenames and the risk of duplicates is low
        $localFile = time() . substr($_FILES['upload']['name'], strrpos($_FILES['upload']['name'], '.'));
        $serverUrl = $myFilesProfilePhotos->getServerPath().'/'.$localFile;
        if(is_file($serverUrl))
        {
            // if file exists than create a random number and append it to the filename
            $serverUrl = $myFilesProfilePhotos->getServerPath() . '/' .
                substr($localFile, 0, strrpos($localFile, '.')) . '_' .
                mt_rand().substr($localFile, strrpos($localFile, '.'));
        }
        $htmlUrl = ADMIDIO_URL.'/adm_program/system/show_image.php?module='.$folderName.'&file='.$localFile;
        move_uploaded_file($_FILES['upload']['tmp_name'], $serverUrl);
    }
    else
    {
        $message = strStripTags($gL10n->get(
            $myFilesProfilePhotos->errorText,
            $myFilesProfilePhotos->errorPath,
            '<a href="mailto:'.$gPreferences['email_administrator'].'">', '</a>'
        ));
    }

    // now call CKEditor function and send photo data
    echo '<html><body><script type="text/javascript">
            window.parent.CKEDITOR.tools.callFunction('.$getCKEditorFuncNum.', "'.$htmlUrl.'","'.$message.'");
        </script></body></html>';
}
catch(AdmException $e)
{
    $e->showHtml();
}
