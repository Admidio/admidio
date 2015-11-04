<?php
/******************************************************************************
 * Default file upload dialog
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * module : photos       - Upload of photos for the albums
 *          downloads    - Upload of files for the download folder
 * mode   : choose_files - (Default) Show a dialog with controls to select files to upload
 *          upload_files - Upload the selected files
 * id     : Id of the current object (folder, album) where the files should be uploaded
 *
 *****************************************************************************/

require_once('common.php');
require_once('login_valid.php');

// Initialize and check the parameters
$getModule = admFuncVariableIsValid($_GET, 'module', 'string', array('validValues' => array('photos', 'downloads')));
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'choose_files', 'validValues' => array('choose_files', 'upload_files')));
$getId     = admFuncVariableIsValid($_GET, 'id', 'numeric', array('requireValue' => true));

// module specific checks
if($getModule === 'photos')
{
    // check if the module is activated
    if ($gPreferences['enable_photo_module'] == 0)
    {
        $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    }

    // check if current user has right to upload photos
    if (!$gCurrentUser->editPhotoRight())
    {
        $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
    }

    // create photo object or read it from session
    if (isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $getId)
    {
        $photoAlbum =& $_SESSION['photo_album'];
        $photoAlbum->setDatabase($gDb);
    }
    else
    {
        $photoAlbum = new TablePhotos($gDb, $getId);
        $_SESSION['photo_album'] = $photoAlbum;
    }

    // check if album belongs to current organization
    if($photoAlbum->getValue('pho_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    $uploadDir = SERVER_PATH.'/adm_my_files/photos/upload/';
    $uploadUrl = $g_root_path.'/adm_my_files/photos/upload/';

    $headline = $gL10n->get('PHO_UPLOAD_PHOTOS');
    $textFileUploaded = $gL10n->get('PHO_FILE_UPLOADED');
    $textUploadSuccessful = $gL10n->get('PHO_PHOTO_UPLOAD_SUCCESSFUL');
    $textUploadNotSuccessful = $gL10n->get('PHO_PHOTO_UPLOAD_NOT_SUCCESSFUL');
    $textUploadDescription = $gL10n->get('PHO_PHOTO_UPLOAD_DESC', $photoAlbum->getValue('pho_name'));
    $textSelectFiles = $gL10n->get('PHO_SELECT_FOTOS');
    $iconUploadPath = THEME_PATH. '/icons/photo_upload.png';
}
elseif($getModule === 'downloads')
{
    if ($gPreferences['enable_download_module'] != 1)
    {
        $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    }

    // check if current user has right to upload files
    if (!$gCurrentUser->editDownloadRight())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // download module can only be used from organization set in config.php
    if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) != 0)
    {
        $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $g_organization));
    }

    // upload only possible if upload filesize > 0
    if ($gPreferences['max_file_upload_size'] == 0)
    {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    try
    {
        // get recordset of current folder from databse
        $folder = new TableFolder($gDb);
        $folder->getFolderForDownload($getId);
        $uploadDir = $folder->getCompletePathOfFolder().'/';
        $uploadUrl = $g_root_path. $folder->getValue('fol_path'). '/'. $folder->getValue('fol_name').'/';
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }

    $headline = $gL10n->get('DOW_UPLOAD_FILES');
    $textFileUploaded = $gL10n->get('DOW_FILE_UPLOADED');
    $textUploadSuccessful = $gL10n->get('DOW_FILES_UPLOAD_SUCCESSFUL');
    $textUploadNotSuccessful = $gL10n->get('DOW_FILES_UPLOAD_NOT_SUCCESSFUL');
    $textUploadDescription = $gL10n->get('DOW_FILES_UPLOAD_DESC', $folder->getValue('fol_name'));
    $textSelectFiles = $gL10n->get('DOW_SELECT_FILES');
    $iconUploadPath = THEME_PATH. '/icons/page_white_upload.png';
}

// check if the server allow file uploads
if (ini_get('file_uploads') != 1)
{
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
}

if($getMode == 'choose_files')
{
    // delete old stuff in upload folder
    $uploadFolder = new Folder(SERVER_PATH.'/adm_my_files/photos/upload');
    $uploadFolder->delete('', true);

    // create html page object
    $page = new HtmlPage();
    $page->hideThemeHtml();
    $page->hideMenu();

    $page->addCssFile($g_root_path.'/adm_program/libs/jquery-file-upload/css/jquery.fileupload.css');
    $page->addJavascriptFile($g_root_path.'/adm_program/libs/jquery-file-upload/js/vendor/jquery.ui.widget.js');
    $page->addJavascriptFile($g_root_path.'/adm_program/libs/jquery-file-upload/js/jquery.iframe-transport.js');
    $page->addJavascriptFile($g_root_path.'/adm_program/libs/jquery-file-upload/js/jquery.fileupload.js');

    $page->addJavascript('
    var countErrorFiles = 0;
    var countFiles      = 0;

    $(function () {
        "use strict";
        $("#fileupload").fileupload({
            url: "../../system/file_upload.php?module='.$getModule.'&mode=upload_files&id='.$getId.'",
            sequentialUploads: true,
            dataType: "json",
            add: function (e, data) {
                $("#files").html("");
                countErrorFiles = 0;
                countFiles = 0;
                data.submit();
            },
            done: function (e, data) {
                $.each(data.result.files, function (index, file) {
                    if(typeof file.error != "undefined") {
                        $("<p/>").html("<div class=\"alert alert-danger\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span>"
                            + file.name + " - <strong>" + file.error + "</strong></div>").appendTo("#files");
                        countErrorFiles++;
                    }
                    else {
                        var message = "'.$textFileUploaded.'";
                        var newMessage = message.replace("%VAR1_BOLD%", "<strong>" + file.name + "</strong>");
                        $("<p/>").html(newMessage).appendTo("#files");
                        countFiles++
                    }
                });
            },
            progressall: function (e, data) {
                var progress = parseInt(data.loaded / data.total * 100, 10);
                $("#progress .progress-bar").css(
                    "width",
                    progress + "%"
                );
            },
            stop: function (e, data) {
                if(countErrorFiles == 0 && countFiles > 0) {
                    $("<p/>").html("<div class=\"alert alert-success\"><span class=\"glyphicon glyphicon-ok\"></span>'.$textUploadSuccessful.'</div>").appendTo("#files");
                }
                else {
                    $("<p/>").html("<div class=\"alert alert-danger\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span>'.$textUploadNotSuccessful.'</div>").appendTo("#files");
                }
            }
        }).prop("disabled", !$.support.fileInput)
            .parent().addClass($.support.fileInput ? undefined : "disabled");
    });', true);

    $page->addHtml('
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">'.$headline.'</h4>
        </div>
        <div class="modal-body">
            <p class="lead">'.$textUploadDescription.'</p>

            <span class="btn btn-primary fileinput-button"><img
                src="'. $iconUploadPath .'" alt="'.$textSelectFiles.'" />'.$textSelectFiles.'
                <input id="fileupload" type="file" name="files[]" multiple>
            </span>
            <br>
            <br>
            <div id="progress" class="progress">
                <div class="progress-bar progress-bar-success"></div>
            </div>
            <div id="files" class="files"></div>
        </div>');
    $page->show();
}
elseif($getMode == 'upload_files')
{
    // upload files to temp upload folder
    if($getModule === 'photos')
    {
        $uploadHandler = new UploadHandlerPhoto(array('upload_dir' => $uploadDir,
                                                      'upload_url' => $uploadUrl,
                                                      'image_versions' => array(),
                                                      'accept_file_types' => '/\.(jpe?g|png)$/i'), true,
                                                      array('accept_file_types' => $gL10n->get('PHO_PHOTO_FORMAT_INVALID')));
    }
    elseif($getModule === 'downloads')
    {
        $uploadHandler = new UploadHandlerDownload(array('upload_dir' => $uploadDir,
                                                         'upload_url' => $uploadUrl,
                                                         'image_versions' => array()));
    }
}
