<?php
/******************************************************************************
 * Downloads auflisten
 *
 * Copyright    : (c) 2004 - 2010 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * folder_id : akutelle OrdnerId
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_folder.php');
require_once('../../system/file_extension_icons.php');


// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

// Uebergabevariablen pruefen
if (array_key_exists('folder_id', $_GET))
{
    if (is_numeric($_GET['folder_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $folderId = $_GET['folder_id'];
}
else
{
    // FolderId auf 0 setzen
    $folderId = 0;
}

//Verwaltung der Session
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);
unset($_SESSION['download_request']);


//Informationen zum aktuellen Ordner aus der DB holen
$currentFolder = new TableFolder($g_db);
$returnValue   = $currentFolder->getFolderForDownload($folderId);

//pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
if ($returnValue < 0)
{
	if($returnValue == -2)
	{
		//oder Benutzer darf nicht zugreifen
		$g_message->show($g_l10n->get('DOW_PHR_FOLDER_NO_RIGHTS'));
	}
	else
	{
		//Datensatz konnte nicht in DB gefunden werden
		$g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
	}
}

$folderId = $currentFolder->getValue('fol_id');

//Ordnerinhalt zur Darstellung auslesen
$folderContent = $currentFolder->getFolderContentsForDownload();

//NavigationsLink erhalten
$navigationBar = $currentFolder->getNavigationForDownload();



// Html-Kopf ausgeben
$g_layout['title']  = $g_l10n->get('DOW_DOWNLOADS');
$g_layout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/delete.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/tooltip/text_tooltip.js"></script>';
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">'.$g_layout['title'].'</h1>';

echo $navigationBar;


//Button Upload, Neuer Ordner und Ordnerkonfiguration
if ($g_current_user->editDownloadRight())
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/downloads/folder_new.php?folder_id='.$folderId.'"><img
                src="'. THEME_PATH. '/icons/folder_create.png" alt="'.$g_l10n->get('DOW_CREATE_FOLDER').'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/downloads/folder_new.php?folder_id='.$folderId.'">'.$g_l10n->get('DOW_CREATE_FOLDER').'</a>
            </span>
        </li>
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/downloads/upload.php?folder_id='.$folderId.'"><img
                src="'. THEME_PATH. '/icons/page_white_upload.png" alt="'.$g_l10n->get('DOW_UPLOAD_FILE').'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/downloads/upload.php?folder_id='.$folderId.'">'.$g_l10n->get('DOW_UPLOAD_FILE').'</a>
            </span>
        </li>
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/downloads/folder_config.php?folder_id='.$folderId.'"><img
                src="'. THEME_PATH. '/icons/options.png" alt="'.$g_l10n->get('DOW_SET_PERMISSIONS').'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/downloads/folder_config.php?folder_id='.$folderId.'">'.$g_l10n->get('DOW_SET_PERMISSIONS').'</a>
            </span>
        </li>
    </ul>';
};

//Anlegen der Tabelle
echo '
<table class="tableList" cellspacing="0">
    <tr>
        <th style="width: 25px;"><img class="iconInformation"
            src="'. THEME_PATH. '/icons/download.png" alt="'.$g_l10n->get('SYS_FOLDER').' / '.$g_l10n->get('DOW_FILE_TYPE').'" title="'.$g_l10n->get('SYS_FOLDER').' / '.$g_l10n->get('DOW_FILE_TYPE').'" />
        </th>
        <th>'.$g_l10n->get('SYS_NAME').'</th>
        <th>'.$g_l10n->get('SYS_DATE_MODIFIED').'</th>
        <th>'.$g_l10n->get('SYS_SIZE').'</th>
        <th>'.$g_l10n->get('DOW_COUNTER').'</th>';
        if ($g_current_user->editDownloadRight())
        {
           echo '<th style="text-align: center;">'.$g_l10n->get('SYS_FEATURES').'</th>';
        }
    echo '</tr>';


//falls der Ordner leer ist
if (count($folderContent) == 0)
{
    if ($g_current_user->editDownloadRight())
    {
        $colspan = '6';
    }
    else
    {
        $colspan = '5';
    }

    echo'<tr>
       <td colspan="'.$colspan.'">'.$g_l10n->get('DOW_PHR_FOLDER_NO_FILES').'</td>
    </tr>';
}
else
{
    //Ordnerinhalt ausgeben
    if (isset($folderContent['folders'])) {
        //als erstes die Unterordner
        for($i=0; $i<count($folderContent['folders']); $i++) {

            $nextFolder = $folderContent['folders'][$i];

            echo '
            <tr class="tableMouseOver" id="row_folder_'.$nextFolder['fol_id'].'">
                <td>
                      <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php?folder_id='. $nextFolder['fol_id']. '">
                    <img src="'. THEME_PATH. '/icons/download.png" alt="'.$g_l10n->get('SYS_FOLDER').'" title="'.$g_l10n->get('SYS_FOLDER').'" /></a>
                </td>
                <td><a href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php?folder_id='. $nextFolder['fol_id']. '">'. $nextFolder['fol_name']. '</a>';
                if($nextFolder['fol_description']!="")
                {
                    echo '<span class="iconLink" ><a class="textTooltip" title="'.$nextFolder['fol_description'].'" href="#"><img src="'. THEME_PATH. '/icons/info.png" alt="'.$g_l10n->get('SYS_FOLDER').'"/></a></span>';
                }
                echo'</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>';
                if ($g_current_user->editDownloadRight())
                {
                    //Hier noch die Links zum Aendern und Loeschen
                    echo '
                    <td style="text-align: center;">
                        <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/downloads/rename.php?folder_id='. $nextFolder['fol_id']. '">
                        <img src="'. THEME_PATH. '/icons/edit.png" alt="'.$g_l10n->get('SYS_EDIT').'" title="'.$g_l10n->get('SYS_EDIT').'" /></a>
                        <a class="iconLink" href="javascript:deleteObject(\'fol\', \'row_folder_'.$nextFolder['fol_id'].'\','.$nextFolder['fol_id'].',\''.$nextFolder['fol_name'].'\')">
                        <img src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('SYS_DELETE').'" title="'.$g_l10n->get('SYS_DELETE').'" /></a>';
                        if (!$nextFolder['fol_exists'])
                        {
                            echo '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_PHR_FOLDER_NOT_EXISTS&amp;inline=true"><img 
				                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DOW_PHR_FOLDER_NOT_EXISTS\',this)" onmouseout="ajax_hideTooltip()"
				                class="iconHelpLink" src="'. THEME_PATH. '/icons/warning.png" alt="'.$g_l10n->get('SYS_WARNING').'" /></a>';
                        }

                     echo '
                      </td>';
                }
            echo '</tr>';

        }
    }

    //als naechstes werden die enthaltenen Dateien ausgegeben
    if (isset($folderContent['files'])) {
        for($i=0; $i<count($folderContent['files']); $i++) {

            $nextFile = $folderContent['files'][$i];

            //Ermittlung der Dateiendung
            $fileExtension  = admStrToLower(substr($nextFile['fil_name'], strrpos($nextFile['fil_name'], '.')+1));

            //Auszugebendes Icon ermitteln
            $iconFile = 'page_white_question.png';
            if(array_key_exists($fileExtension, $icon_file_extension))
            {
                $iconFile = $icon_file_extension[$fileExtension];
            }
            
            // Zeitstempel formatieren
            $timestamp = new DateTimeExtended($nextFile['fil_timestamp'], 'Y-m-d H:i:s');

            echo '
            <tr class="tableMouseOver" id="row_file_'.$nextFile['fil_id'].'">
                <td>
                    <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/downloads/get_file.php?file_id='. $nextFile['fil_id']. '">
                    <img src="'. THEME_PATH. '/icons/'.$iconFile.'" alt="'.$g_l10n->get('SYS_FILE').'" title="'.$g_l10n->get('SYS_FILE').'" /></a>
                </td>
                <td><a href="'.$g_root_path.'/adm_program/modules/downloads/get_file.php?file_id='. $nextFile['fil_id']. '">'. $nextFile['fil_name']. '</a>';
                if($nextFile['fil_description']!="")
                {
                    echo '<span class="iconLink" ><a class="textTooltip" title="'.$nextFile['fil_description'].'" href="#"><img src="'. THEME_PATH. '/icons/info.png" alt="'.$g_l10n->get('SYS_FILE').'"/></a></span>';
                }
                echo'</td>
                <td>'. $timestamp->format($g_preferences['system_date'].' '.$g_preferences['system_time']). '</td>
                <td>'. $nextFile['fil_size']. ' KB&nbsp;</td>
                <td>'. $nextFile['fil_counter'];
                if ($g_current_user->editDownloadRight())
                {
                    //Hier noch die Links zum Aendern und Loeschen
                    echo '
                    <td style="text-align: center;">
                        <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/downloads/rename.php?file_id='. $nextFile['fil_id']. '">
                        <img src="'. THEME_PATH. '/icons/edit.png" alt="'.$g_l10n->get('SYS_EDIT').'" title="'.$g_l10n->get('SYS_EDIT').'" /></a>
                        <a class="iconLink" href="javascript:deleteObject(\'fil\', \'row_file_'.$nextFile['fil_id'].'\','.$nextFile['fil_id'].',\''.$nextFile['fil_name'].'\')">
                        <img src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('SYS_DELETE').'" title="'.$g_l10n->get('SYS_DELETE').'" /></a>';
                        if (!$nextFile['fil_exists']) {
                            echo '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_PHR_FILE_NOT_EXISTS&amp;inline=true"><img 
				                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DOW_PHR_FILE_NOT_EXISTS\',this)" onmouseout="ajax_hideTooltip()"
				                class="iconHelpLink" src="'. THEME_PATH. '/icons/warning.png" alt="'.$g_l10n->get('SYS_WARNING').'" /></a>';
                        }

                     echo '
                    </td>';
                }
            echo '</tr>';

        }
    }

}

//Ende der Tabelle
echo'</table>';

//Falls der User DownloadAdmin ist werden jetzt noch die zusaetzlich im Ordner enthaltenen Files angezeigt.
if ($g_current_user->editDownloadRight())
{
    //gucken ob ueberhaupt zusaetzliche Ordnerinhalte gefunden wurden
    if (isset($folderContent['additionalFolders']) || isset($folderContent['additionalFiles']))
    {

        echo '
        <h3>
            '.$g_l10n->get('DOW_UNMANAGED_FILES').'
			<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_PHR_ADDITIONAL_FILES&amp;inline=true"><img 
                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DOW_PHR_ADDITIONAL_FILES\',this)" onmouseout="ajax_hideTooltip()"
                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>            
        </h3>

        <table class="tableList" cellspacing="0">
            <tr>
                <th style="width: 25px;"><img class="iconInformation"
                    src="'. THEME_PATH. '/icons/download.png" alt="'.$g_l10n->get('SYS_FOLDER').' / '.$g_l10n->get('DOW_FILE_TYPE').'" title="'.$g_l10n->get('SYS_FOLDER').' / '.$g_l10n->get('DOW_FILE_TYPE').'" />
                </th>
                <th>'.$g_l10n->get('SYS_NAME').'</th>
                <th style="text-align: right;">'.$g_l10n->get('SYS_FEATURES').'</th>
            </tr>';


        //Erst die Ordner
        if (isset($folderContent['additionalFolders'])) {
            for($i=0; $i<count($folderContent['additionalFolders']); $i++) {

                $nextFolder = $folderContent['additionalFolders'][$i];

                echo '
                <tr class="tableMouseOver">
                    <td><img src="'. THEME_PATH. '/icons/download.png" alt="'.$g_l10n->get('SYS_FOLDER').'" title="'.$g_l10n->get('SYS_FOLDER').'" /></td>
                    <td>'. $nextFolder['fol_name']. '</td>
                    <td style="text-align: right;">
                        <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=6&amp;folder_id='.$folderId.'&amp;name='. urlencode($nextFolder['fol_name']). '">
                        <img src="'. THEME_PATH. '/icons/database_in.png" alt="'.$g_l10n->get('DOW_ADD_TO_DATABASE').'" title="'.$g_l10n->get('DOW_ADD_TO_DATABASE').'" /></a>
                    </td>
                </tr>';
            }


        }

        //Jetzt noch die Dateien
        if (isset($folderContent['additionalFiles'])) {
            for($i=0; $i<count($folderContent['additionalFiles']); $i++) {

                $nextFile = $folderContent['additionalFiles'][$i];

                //Ermittlung der Dateiendung
                $fileExtension  = admStrToLower(substr($nextFile['fil_name'], strrpos($nextFile['fil_name'], '.')+1));

                //Auszugebendes Icon ermitteln
                $iconFile = 'page_white_question.png';
                if(array_key_exists($fileExtension, $icon_file_extension))
                {
                    $iconFile = $icon_file_extension[$fileExtension];
                }

                echo '
                <tr class="tableMouseOver">
                    <td><img src="'. THEME_PATH. '/icons/'.$iconFile.'" alt="'.$g_l10n->get('SYS_FILE').'" title="'.$g_l10n->get('SYS_FILE').'" /></a></td>
                    <td>'. $nextFile['fil_name']. '</td>
                    <td style="text-align: right;">
                        <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=6&amp;folder_id='.$folderId.'&amp;name='. urlencode($nextFile['fil_name']). '">
                        <img src="'. THEME_PATH. '/icons/database_in.png" alt="'.$g_l10n->get('DOW_ADD_TO_DATABASE').'" title="'.$g_l10n->get('DOW_ADD_TO_DATABASE').'" /></a>
                    </td>
                </tr>';
            }
        }
        echo '</table>';
    }
}

require(THEME_SERVER_PATH. '/overall_footer.php');

?>