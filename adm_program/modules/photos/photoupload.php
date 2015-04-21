<?php
/******************************************************************************
 * Photoupload
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode   : choose_files - (Default) Show a dialog with controls to select photo files to upload
 *          upload_files - Upload the selected files
 * pho_id : Id of album to which the files should be uploaded
 * 
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once(SERVER_PATH.'/adm_program/libs/jquery-file-upload/server/php/uploadhandler.php');

// Initialize and check the parameters
$getMode    = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'choose_files', 'validValues' => array('choose_files', 'upload_files')));
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', array('requireValue' => true));

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//nur von eigentlicher OragHompage erreichbar
if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) != 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $gHomepage));
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if (!$gCurrentUser->editPhotoRight())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
}

//Kontrolle ob Server Dateiuploads zulaesst
if (ini_get('file_uploads') != 1)
{
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
}

$headline = $gL10n->get('PHO_UPLOAD_PHOTOS');

// create photo object or read it from session
if (isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $getPhotoId)
{
    $photoAlbum =& $_SESSION['photo_album'];
    $photoAlbum->db =& $gDb;
}
else
{
    $photoAlbum = new TablePhotos($gDb, $getPhotoId);
    $_SESSION['photo_album'] =& $photoAlbum;
}

// check if album belongs to current organization
if($photoAlbum->getValue('pho_org_shortname') != $gCurrentOrganization->getValue('org_shortname'))
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
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
    $(function () {
        "use strict";
        $("#fileupload").fileupload({
            url: "photoupload.php?mode=upload_files&pho_id='.$getPhotoId.'",
            sequentialUploads: true,
            dataType: "json",
            done: function (e, data) {
                $.each(data.result.files, function (index, file) {
                    if(typeof file.error != "undefined") {
                        $("<p/>").html("<div class=\"alert alert-danger form-alert\"><span class=\"glyphicon glyphicon-exclamation-sign\"></span>" 
                            + file.name + " - <strong>" + file.error + "</strong></div>").appendTo("#files");
                    }
                    else {
                        $("<p/>").text(file.name).appendTo("#files");
                    }
                });
            },
            progressall: function (e, data) {
                var progress = parseInt(data.loaded / data.total * 100, 10);
                $("#progress .progress-bar").css(
                    "width",
                    progress + "%"
                );
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
            <p class="lead">'.$gL10n->get('PHO_PHOTO_UPLOAD_DESC', $photoAlbum->getValue('pho_name')).'</p>

            <span class="btn btn-primary fileinput-button"><img 
                src="'. THEME_PATH. '/icons/photo_upload.png" alt="'.$gL10n->get('PHO_UPLOAD_PHOTOS').'" />'.$gL10n->get('PHO_SELECT_FOTOS').'
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
    $uploadHandler = new UploadHandlerPhoto(array('upload_dir' => SERVER_PATH.'/adm_my_files/photos/upload/'
                                            ,'upload_url' => $g_root_path.'/adm_my_files/photos/upload/'
                                            ,'image_versions' => array()
                                            ,'accept_file_types' => '/\.(jpe?g|png)$/i'
                                            ), true,
                                            array('accept_file_types' => $gL10n->get('PHO_PHOTO_FORMAT_INVALID')));
}

?>