<?php
/******************************************************************************
 * Photoupload
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * pho_id       : id des Albums zu dem die Bilder hinzugefuegt werden sollen
 * uploadmethod : 1 - classic html upload
 *				  2 - Flexuploader
 * 
 *****************************************************************************/

require_once('../../system/classes/table_photos.php');
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../libs/flexupload/class.flexupload.inc.php');

// Initialize and check the parameters
$getPhotoId      = admFuncVariableIsValid($_GET, 'pho_id', 'numeric', null, true);
$getUploadmethod = admFuncVariableIsValid($_GET, 'uploadmethod', 'numeric', 0);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$gCurrentUser->editPhotoRight())
{
    $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
}

//Kontrolle ob Server Dateiuploads zulaesst
if(ini_get('file_uploads') != 1)
{
    $gMessage->show($gL10n->get('SYS_SERVER_NO_UPLOAD'));
}

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $getPhotoId)
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $gDb;
}
else
{
    $photo_album = new TablePhotos($gDb, $getPhotoId);
    $_SESSION['photo_album'] =& $photo_album;
}

//ordner fuer Flexupload anlegen, falls dieser nicht existiert
if(file_exists(SERVER_PATH. '/adm_my_files/photos/upload') == false)
{
    require_once('../../system/classes/folder.php');
    $folder = new Folder(SERVER_PATH. '/adm_my_files/photos');
    $folder->createFolder('upload', true);
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($photo_album->getValue('pho_org_shortname') != $gCurrentOrganization->getValue('org_shortname'))
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Uploadtechnik auswaehlen
if(($gPreferences['photo_upload_mode'] == 1 || $getUploadmethod == 2)
&&  $getUploadmethod != 1)
{
	$flash = 'flashInstalled()';
}
else
{
	$flash = 'false';
}

// Html-Kopf ausgeben
$gLayout['title'] = $gL10n->get('PHO_UPLOAD_PHOTOS');
$gLayout['header'] = '
<script type="text/javascript"><!--
	function flashInstalled()
	{
		if(navigator.mimeTypes.length) 
		{
			if(navigator.mimeTypes["application/x-shockwave-flash"]
			&& navigator.mimeTypes["application/x-shockwave-flash"].enabledPlugin != null)
			{
				return true;
			}
		}
		else if(window.ActiveXObject) 
		{
		    try 
		    {
				flash_test = new ActiveXObject("ShockwaveFlash.ShockwaveFlash.7");
				if( flash_test ) 
				{
					return true;
				}
		    }
		    catch(e){}
		}
		return false;
	}

	$(document).ready(function() 
	{
		flash_installed = '.$flash.';

		if(flash_installed == true)
		{
			$("#photo_upload_flash").show();
			$("#photo_upload_form").hide();
		}
		else
		{
			$("#photo_upload_flash").hide();
			$("#photo_upload_form").show();
			$("#bilddatei1").focus();
		}
 	});
--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '
<div class="formLayout" id="photo_upload_form" style="visibility: hide; display: none;">
	<form method="post" action="'.$g_root_path.'/adm_program/modules/photos/photoupload_do.php?pho_id='. $getPhotoId. '&amp;uploadmethod=1" enctype="multipart/form-data">
	    <div class="formHead">'.$gL10n->get('PHO_UPLOAD_PHOTOS').'</div>
	    <div class="formBody">
	        <p>
	            '.$gL10n->get('PHO_PHOTO_DESTINATION', $photo_album->getValue('pho_name')).'<br />
	            ('.$gL10n->get('SYS_DATE').': '. $photo_album->getValue('pho_begin', $gPreferences['system_date']). ')
	        </p>';
	        //Der Name "Filedata" wird so vom Flexuploader verwendet und darf deswegen nicht geändert werden
            echo '
	        <ul class="formFieldList">
	            <li><dl>
	                <dt><label for="admPhotoFile1">'.$gL10n->get('PHO_PHOTO').' 1:</label></dt>
	                <dd><input type="file" id="admPhotoFile1" name="Filedata[]" value="'.$gL10n->get('SYS_BROWSE').'" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="admPhotoFile2">'.$gL10n->get('PHO_PHOTO').' 2:</label></dt>
	                <dd><input type="file" id="admPhotoFile2" name="Filedata[]" value="'.$gL10n->get('SYS_BROWSE').'" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="admPhotoFile3">'.$gL10n->get('PHO_PHOTO').' 3:</label></dt>
	                <dd><input type="file" id="admPhotoFile3" name="Filedata[]" value="'.$gL10n->get('SYS_BROWSE').'" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="admPhotoFile4">'.$gL10n->get('PHO_PHOTO').' 4:</label></dt>
	                <dd><input type="file" id="admPhotoFile4" name="Filedata[]" value="'.$gL10n->get('SYS_BROWSE').'" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="admPhotoFile5">'.$gL10n->get('PHO_PHOTO').' 5:</label></dt>
	                <dd><input type="file" id="admPhotoFile5" name="Filedata[]" value="'.$gL10n->get('SYS_BROWSE').'" /></dd>
	            </dl></li>
	        </ul>
	        <hr />
	        <div class="formSubmit">
	            <button id="btnUpload" type="submit"><img src="'. THEME_PATH. '/icons/photo_upload.png" />&nbsp;'.$gL10n->get('PHO_UPLOAD_PHOTOS').'</button>
	        </div>
	   </div>
	</form>
</div>

<div id="photo_upload_flash" style="visibility: hide; display: none;">
	<h2>'.$gL10n->get('PHO_UPLOAD_PHOTOS').'</h2>
	<p>
       '.$gL10n->get('PHO_PHOTO_DESTINATION', $photo_album->getValue('pho_name')).'<br />
       ('.$gL10n->get('SYS_DATE').': '. $photo_album->getValue('pho_begin', $gPreferences['system_date']). ')
    </p>';

    //neues Objekt erzeugen mit Ziel was mit den Dateien passieren soll
	$fup = new FlexUpload($g_root_path.'/adm_program/modules/photos/photoupload_do.php?pho_id='.$getPhotoId.'&'.$gCookiePraefix. '_PHP_ID='.$_COOKIE[$gCookiePraefix. '_PHP_ID'].'&'.$gCookiePraefix. '_ID='.$_COOKIE[$gCookiePraefix. '_ID'].'&'.$gCookiePraefix.'_DATA='.$_COOKIE[$gCookiePraefix. '_DATA'].'&uploadmethod=2');
	$fup->setPathToSWF($g_root_path.'/adm_program/libs/flexupload/');		//Pfad zum swf-File
	$fup->setLocale($g_root_path.'/adm_program/libs/flexupload/language.php');	//Pfad der Sprachdatei
	$fup->setMaxFileSize(admFuncMaxUploadSize());	//maximale Dateigröße
	$fup->setMaxFiles(999);	//maximale Dateianzahl
	$fup->setWidth(560);	// Breite des Uploaders
	$fup->setHeight(400);	// Hoehe des Uploaders
	$fup->setFileExtensions('*.jpg;*.jpeg;*.png');	//erlaubte Dateiendungen (*.gif;*.jpg;*.jpeg;*.png)
	$fup->printHTML(true, 'flexupload');	//Ausgabe des Uploaders
echo '</div>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$getPhotoId.'" title="'.$gL10n->get('PHO_BACK_TO_ALBUM').'"><img 
            src="'. THEME_PATH. '/icons/application_view_tile.png" /></a>
            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$getPhotoId.'">'.$gL10n->get('PHO_BACK_TO_ALBUM').'</a>
        </span>
    </li>    
    <li>
        <span class="iconTextLink">
            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=photo_up_help&amp;message_title=SYS_WHAT_TO_DO&amp;inline=true" title="'.$gL10n->get('SYS_HELP').'"><img 
            	src="'. THEME_PATH. '/icons/help.png" alt="Help" /></a>
            <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=photo_up_help&amp;message_title=SYS_WHAT_TO_DO&amp;inline=true">'.$gL10n->get('SYS_HELP').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>