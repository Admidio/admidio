<?php
/******************************************************************************
 * Photoupload
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id : id des Albums zu dem die Bilder hinzugefuegt werden sollen
 * mode   : Das entsprechende Formular wird erzwungen !!!
 *          1 - Klassisches Formular zur Bilderauswahl
 * 		    2 - Flexuploder 
 * 
 *****************************************************************************/

require_once('../../system/classes/table_photos.php');
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../libs/flexupload/class.flexupload.inc.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show('module_disabled');
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$g_current_user->editPhotoRight())
{
    $g_message->show('photoverwaltunsrecht');
}

// Uebergabevariablen pruefen

if(isset($_GET['pho_id']) && is_numeric($_GET['pho_id']) == false)
{
    $g_message->show('invalid');
}

// im Zweifel den klassischen Upload nehmen
if(!isset($_GET['mode']) || $_GET['mode'] < 1 || $_GET['mode'] > 2)
{
    $_GET['mode'] = 0;
}

//Kontrolle ob Server Dateiuploads zulaesst
$ini = ini_get('file_uploads');
if($ini!=1)
{
    $g_message->show('no_file_upload_server');
}

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue('pho_id') == $_GET['pho_id'])
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $g_db;
}
else
{
    $photo_album = new TablePhotos($g_db, $_GET['pho_id']);
    $_SESSION['photo_album'] =& $photo_album;
}

//ordner fuer Flexupload anlegen, falls dieser nicht existiert
if(!file_exists(SERVER_PATH. '/adm_my_files/photos/upload'))
{
    mkdir(SERVER_PATH. '/adm_my_files/photos/upload',0777);
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($photo_album->getValue('pho_org_shortname') != $g_organization)
{
    $g_message->show('invalid');
}

// Uploadtechnik auswaehlen
if(($g_preferences['photo_upload_mode'] == 1 || $_GET['mode'] == 2)
&&  $_GET['mode'] != 1)
{
	$flash = 'flashInstalled()';
}
else
{
	$flash = 'false';
}

// Html-Kopf ausgeben
$g_layout['title']  = 'Fotos hochladen';
$g_layout['header'] = '
<script type="text/javascript"><!--
	flash_installed = '.$flash.';

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
require(THEME_SERVER_PATH. '/overall_header.php');

echo '
<div class="formLayout" id="photo_upload_form">
	<form method="post" action="'.$g_root_path.'/adm_program/modules/photos/photoupload_do.php?pho_id='. $_GET['pho_id']. '&amp;uploadmethod=1" enctype="multipart/form-data">
	    <div class="formHead">Bilder hochladen</div>
	    <div class="formBody">
	        <p>
	            Die Bilder werden zu dem Album <strong>'.$photo_album->getValue("pho_name").'</strong> hinzugefügt.<br />
	            (Beginn: '. mysqldate("d.m.y", $photo_album->getValue("pho_begin")). ')
	        </p>
	
	        <ul class="formFieldList">
	            <li><dl>
	                <dt><label for="bilddatei1">Bild 1:</label></dt>
	                <dd><input type="file" id="bilddatei1" name="Filedata[]" value="durchsuchen" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="bilddatei1">Bild 2:</label></dt>
	                <dd><input type="file" id="bilddatei2" name="Filedata[]" value="durchsuchen" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="bilddatei1">Bild 3:</label></dt>
	                <dd><input type="file" id="bilddatei3" name="Filedata[]" value="durchsuchen" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="bilddatei1">Bild 4:</label></dt>
	                <dd><input type="file" id="bilddatei4" name="Filedata[]" value="durchsuchen" /></dd>
	            </dl></li>
	            <li><dl>
	                <dt><label for="bilddatei1">Bild 5:</label></dt>
	                <dd><input type="file" id="bilddatei5" name="Filedata[]" value="durchsuchen" /></dd>
	            </dl></li>
	        </ul>
	        <hr />
	        <div class="formSubmit">
	            <button name="upload" type="submit" value="speichern"><img src="'. THEME_PATH. '/icons/photo_upload.png" alt="Speichern" />&nbsp;Bilder hochladen</button>
	        </div>
	   </div>
	</form>
</div>

<div id="photo_upload_flash">
	<h2>Bilder hochladen</h2>
	<p>
        Die Bilder werden zu dem Album <strong>'.$photo_album->getValue('pho_name').'</strong> hinzugefügt.<br />
        (Beginn: '. mysqldate('d.m.y', $photo_album->getValue('pho_begin')). ')
    </p>';

    //neues Objekt erzeugen mit Ziel was mit den Dateien passieren soll
	$fup = new FlexUpload($g_root_path.'/adm_program/modules/photos/photoupload_do.php?pho_id='.$_GET['pho_id'].'&admidio_php_session_id='.$_COOKIE['admidio_php_session_id'].'&admidio_session_id='.$_COOKIE['admidio_session_id'].'&admidio_data='.$_COOKIE['admidio_data'].'&uploadmethod=2');
	$fup->setPathToSWF($g_root_path.'/adm_program/libs/flexupload/');		//Pfad zum swf-File
	$fup->setLocale($g_root_path.'/adm_program/libs/flexupload/de.xml');	//Pfad der Sprachdatei
	$fup->setMaxFileSize(maxUploadSize());	//maximale Dateigröße
	$fup->setMaxFiles(999);	//maximale Dateianzahl
	$fup->setWidth(560);	// Breite des Uploaders
	$fup->setHeight(400);	// Hoehe des Uploaders
	$fup->setFileExtensions('*.jpg;*.jpeg;*.png');	//erlaubte Dateiendungen (*.gif;*.jpg;*.jpeg;*.png)
	$fup->printHTML(true, 'flexupload');	//Ausgabe des Uploaders
echo '</div>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$_GET['pho_id'].'"><img 
            src="'. THEME_PATH. '/icons/application_view_tile.png" alt="Zum Album" /></a>
            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php?pho_id='.$_GET['pho_id'].'">Zum Album</a>
        </span>
    </li>    
    <li>
        <span class="iconTextLink">
            <img class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title=""
                onclick="window.open(\''. $g_root_path. '/adm_program/system/msg_window.php?err_code=photo_up_help&amp;&amp;window=true\',\'Message\',
                    \'width=500,height=300,left=310,top=200,scrollbars=yes\')"
                onmouseover="ajax_showTooltip(event,\''. $g_root_path. '/adm_program/system/msg_window.php?err_code=photo_up_help\',this);" onmouseout="ajax_hideTooltip()" />
            <a href="#" onclick="window.open(\''. $g_root_path. '/adm_program/system/msg_window.php?err_code=photo_up_help&amp;&amp;window=true\',\'Message\',
                    \'width=500,height=300,left=310,top=200,scrollbars=yes\')">Hilfe</a>
        </span>
    </li>
</ul>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>