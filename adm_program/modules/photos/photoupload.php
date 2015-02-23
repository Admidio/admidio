<?php
/******************************************************************************
 * Photoupload
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
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
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $gDb;
}
else
{
    $photo_album = new TablePhotos($gDb, $getPhotoId);
    $_SESSION['photo_album'] =& $photo_album;
}

// check if album belongs to current organization
if($photo_album->getValue('pho_org_shortname') != $gCurrentOrganization->getValue('org_shortname'))
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
    
    $page->addCssFile($g_root_path.'/adm_program/libs/jquery-file-upload/css/style.css');
    $page->addCssFile($g_root_path.'/adm_program/libs/jquery-file-upload/css/jquery.fileupload.css');
    $page->addJavascriptFile($g_root_path.'/adm_program/libs/jquery-file-upload/js/vendor/jquery.ui.widget.js');
    $page->addJavascriptFile($g_root_path.'/adm_program/libs/jquery-file-upload/js/jquery.iframe-transport.js');
    $page->addJavascriptFile($g_root_path.'/adm_program/libs/jquery-file-upload/js/jquery.fileupload.js');
    
    $page->addJavascript('
    /*jslint unparam: true */
    /*global window, $ */
    $(function () {
        "use strict";
        $("#fileupload").fileupload({
            url: "photoupload.php?mode=upload_files&pho_id='.$getPhotoId.'",
            sequentialUploads: true,
            dataType: "json",
            done: function (e, data) {
                $.each(data.result.files, function (index, file) {
                    if(typeof file.error != "undefined") {
                        $("<p/>").html("<div class=\"alert alert-danger form-alert\"><span class=\"glyphicon glyphicon-remove\"></span>" 
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
    
    // add headline and title of module
    $page->addHeadline($headline);
    
    // create module menu with back link
    $announcementsMenu = new HtmlNavbar('menu_announcements_create', $headline, $page);
    $announcementsMenu->addItem('menu_item_back', $gNavigation->getUrl(), $gL10n->get('SYS_BACK'), 'back.png');
    $page->addHtml($announcementsMenu->show(false));
    
    $page->addHtml('
        <!-- The fileinput-button span is used to style the file input field as button -->
        <span class="btn btn-success fileinput-button">
            <i class="glyphicon glyphicon-plus"></i>
            <span>Select files...</span>
            <!-- The file input field used as target for the file upload widget -->
            <input id="fileupload" type="file" name="files[]" multiple>
        </span>
        <br>
        <br>
        <!-- The global progress bar -->
        <div id="progress" class="progress">
            <div class="progress-bar progress-bar-success"></div>
        </div>
        <!-- The container for the uploaded files -->
        <div id="files" class="files"></div>');
    $page->show();
}
elseif($getMode == 'upload_files')
{
    // upload files to temp upload folder
    $uploadHandler = new UploadHandler(array('upload_dir' => SERVER_PATH.'/adm_my_files/photos/upload/'
                                            ,'upload_url' => $g_root_path.'/adm_my_files/photos/upload/'
                                            ,'image_versions' => array()
                                            ,'accept_file_types' => '/\.(jpe?g|png)$/i'
                                          //  ,'print_response' => false
                                              ), true,
                                            array('accept_file_types' => $gL10n->get('PHO_PHOTO_FORMAT_INVALID')));

    $fileLocation = SERVER_PATH. '/adm_my_files/photos/upload/'.$_FILES['files']['name'][0];
    $albumFolder  = SERVER_PATH. '/adm_my_files/photos/'.$photo_album->getValue('pho_begin', 'Y-m-d').'_'.$photo_album->getValue('pho_id');
    $newFotoFileNumber = $photo_album->getValue('pho_quantity') + 1;
    
    if(file_exists($fileLocation))
    {
    	// read image size
    	$imageProperties = getimagesize($fileLocation);
    	$imageDimensions = $imageProperties[0] * $imageProperties[1];
    	
    	if($imageDimensions > admFuncProcessableImageSize())
    	{
        	echo $gL10n->get('PHO_RESOLUTION_MORE_THAN').' '.round(admFuncProcessableImageSize()/1000000, 2).' '.$gL10n->get('MEGA_PIXEL');
    	}

        //$uploadHandler->generate_response($gL10n->get('PHO_RESOLUTION_MORE_THAN').' '.round(admFuncProcessableImageSize()/1000000, 2).' '.$gL10n->get('MEGA_PIXEL'));
    	
    	// check mime type and set file extension
        if($imageProperties['mime'] == 'image/jpeg')
        {
            $fileExtension = 'jpg';
        }
        elseif($imageProperties['mime'] == 'image/png')
        {
            $fileExtension = 'png';
        }
        else
        {
            $gMessage->show($gL10n->get('PHO_PHOTO_FORMAT_INVALID'));
        }
    	
		// create image object and scale image to defined size of preferences
	    $image = new Image($fileLocation);
        $image->setImageType('jpeg');
        $image->scaleLargerSide($gPreferences['photo_save_scale']);
        $image->copyToFile(null, $albumFolder.'/'.$newFotoFileNumber.'.jpg');
        $image->delete();
        
        // if enabled then save original image
        if ($gPreferences['photo_keep_original'] == 1)
        {
            if(file_exists($albumFolder.'/originals') == false)
            {
                $folder = new Folder($albumFolder);
                $folder->createFolder('originals', true);
            }
                            
            rename($fileLocation, $albumFolder.'/originals/'.$newFotoFileNumber.'.'.$fileExtension);
        }
        
        // save thumbnail
        if(file_exists($albumFolder.'/thumbnails') == false)
        {
            $folder = new Folder($albumFolder);
            $folder->createFolder('thumbnails', true);
        }

        $image = new Image($fileLocation);
        $image->scaleLargerSide($gPreferences['photo_thumbs_scale']);
        $image->copyToFile(null, $albumFolder.'/thumbnails/'.$newFotoFileNumber.'.jpg');
        $image->delete(); 
  
        // delete image from upload folder
        if(file_exists($fileLocation))
        {
            unlink($fileLocation);
        } 

        // if image was successfully saved in filesystem then update image count of album
        if(file_exists($albumFolder.'/'.$newFotoFileNumber.'.jpg'))
        {
            $photo_album->setValue('pho_quantity', $photo_album->getValue('pho_quantity')+1);
            $photo_album->save(); 
            
            //echo $uploadHandler->post();
        	//echo $gL10n->get('PHO_PHOTO_UPLOAD_SUCCESS');
        }
        else
        {
            $newFotoFileNumber --;
            echo $gL10n->get('PHO_PHOTO_PROCESSING_ERROR');
        }	        
    }
}

?>