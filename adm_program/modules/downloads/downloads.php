<?php
/******************************************************************************
 * Show a list of all downloads
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * folder_id : akutelle OrdnerId
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_folder.php');
require_once('../../system/file_extension_icons.php');
unset($_SESSION['download_request']);

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'numeric', 0);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//Verwaltung der Session
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

//Informationen zum aktuellen Ordner aus der DB holen
$currentFolder = new TableFolder($gDb);
$returnValue   = $currentFolder->getFolderForDownload($getFolderId);

//pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
if ($returnValue < 0)
{
	if($returnValue == -2)
	{
		//oder Benutzer darf nicht zugreifen
		$gMessage->show($gL10n->get('DOW_FOLDER_NO_RIGHTS'));
	}
	else
	{
		//Datensatz konnte nicht in DB gefunden werden
		$gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
	}
}

$getFolderId = $currentFolder->getValue('fol_id');

//Ordnerinhalt zur Darstellung auslesen
$folderContent = $currentFolder->getFolderContentsForDownload();

//NavigationsLink erhalten
$navigationBar = $currentFolder->getNavigationForDownload();



// Html-Kopf ausgeben
$gLayout['title']  = $gL10n->get('DOW_DOWNLOADS');
$gLayout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/tooltip/text_tooltip.js"></script>
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});
        }); 
    //--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">'.$gLayout['title'].'</h1>';

echo $navigationBar;


//Button Upload, Neuer Ordner und Ordnerkonfiguration
if ($gCurrentUser->editDownloadRight())
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/downloads/folder_new.php?folder_id='.$getFolderId.'"><img
                src="'. THEME_PATH. '/icons/folder_create.png" alt="'.$gL10n->get('DOW_CREATE_FOLDER').'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/downloads/folder_new.php?folder_id='.$getFolderId.'">'.$gL10n->get('DOW_CREATE_FOLDER').'</a>
            </span>
        </li>
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/downloads/upload.php?folder_id='.$getFolderId.'"><img
                src="'. THEME_PATH. '/icons/page_white_upload.png" alt="'.$gL10n->get('DOW_UPLOAD_FILE').'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/downloads/upload.php?folder_id='.$getFolderId.'">'.$gL10n->get('DOW_UPLOAD_FILE').'</a>
            </span>
        </li>
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/downloads/folder_config.php?folder_id='.$getFolderId.'"><img
                src="'. THEME_PATH. '/icons/options.png" alt="'.$gL10n->get('DOW_SET_PERMISSIONS').'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/downloads/folder_config.php?folder_id='.$getFolderId.'">'.$gL10n->get('DOW_SET_PERMISSIONS').'</a>
            </span>
        </li>
    </ul>';
};

//Anlegen der Tabelle
echo '
<table class="tableList" cellspacing="0">
    <tr>
        <th style="width: 25px;"><img class="iconInformation"
            src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" title="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" />
        </th>
        <th>'.$gL10n->get('SYS_NAME').'</th>
        <th>'.$gL10n->get('SYS_DATE_MODIFIED').'</th>
        <th>'.$gL10n->get('SYS_SIZE').'</th>
        <th>'.$gL10n->get('DOW_COUNTER').'</th>';
        if ($gCurrentUser->editDownloadRight())
        {
           echo '<th style="text-align: center;">'.$gL10n->get('SYS_FEATURES').'</th>';
        }
    echo '</tr>';


//falls der Ordner leer ist
if (count($folderContent) == 0)
{
    if ($gCurrentUser->editDownloadRight())
    {
        $colspan = '6';
    }
    else
    {
        $colspan = '5';
    }

    echo'<tr>
       <td colspan="'.$colspan.'">'.$gL10n->get('DOW_FOLDER_NO_FILES').'</td>
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
                    <img src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('SYS_FOLDER').'" title="'.$gL10n->get('SYS_FOLDER').'" /></a>
                </td>
                <td><a href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php?folder_id='. $nextFolder['fol_id']. '">'. $nextFolder['fol_name']. '</a>';
                if($nextFolder['fol_description']!="")
                {
                    echo '<span class="iconLink" ><a class="textTooltip" title="'.$nextFolder['fol_description'].'" href="#"><img src="'. THEME_PATH. '/icons/info.png" alt="'.$gL10n->get('SYS_FOLDER').'"/></a></span>';
                }
                echo'</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>';
                if ($gCurrentUser->editDownloadRight())
                {
                    //Hier noch die Links zum Aendern und Loeschen
                    echo '
                    <td style="text-align: center;">
                        <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/downloads/rename.php?folder_id='. $nextFolder['fol_id']. '">
                        <img src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                        <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=fol&amp;element_id=row_folder_'.
                            $nextFolder['fol_id'].'&amp;name='.urlencode($nextFolder['fol_name']).'&amp;database_id='.$nextFolder['fol_id'].'"><img 
                            src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
                        if (!$nextFolder['fol_exists'])
                        {
                            echo '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_FOLDER_NOT_EXISTS&amp;inline=true"><img 
				                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DOW_FOLDER_NOT_EXISTS\',this)" onmouseout="ajax_hideTooltip()"
				                class="iconHelpLink" src="'. THEME_PATH. '/icons/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" /></a>';
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
                    <img src="'. THEME_PATH. '/icons/'.$iconFile.'" alt="'.$gL10n->get('SYS_FILE').'" title="'.$gL10n->get('SYS_FILE').'" /></a>
                </td>
                <td><a href="'.$g_root_path.'/adm_program/modules/downloads/get_file.php?file_id='. $nextFile['fil_id']. '">'. $nextFile['fil_name']. '</a>';
                if($nextFile['fil_description']!="")
                {
                    echo '<span class="iconLink" ><a class="textTooltip" title="'.$nextFile['fil_description'].'" href="#"><img src="'. THEME_PATH. '/icons/info.png" alt="'.$gL10n->get('SYS_FILE').'"/></a></span>';
                }
                echo'</td>
                <td>'. $timestamp->format($gPreferences['system_date'].' '.$gPreferences['system_time']). '</td>
                <td>'. $nextFile['fil_size']. ' kB&nbsp;</td>
                <td>'. $nextFile['fil_counter'].'</td>';
                if ($gCurrentUser->editDownloadRight())
                {
                    //Hier noch die Links zum Aendern und Loeschen
                    echo '
                    <td style="text-align: center;">
                        <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/downloads/rename.php?file_id='. $nextFile['fil_id']. '">
                        <img src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                        <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=fil&amp;element_id=row_file_'.
                            $nextFile['fil_id'].'&amp;name='.urlencode($nextFile['fil_name']).'&amp;database_id='.$nextFile['fil_id'].'"><img 
                            src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
                        if (!$nextFile['fil_exists']) {
                            echo '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_FILE_NOT_EXISTS&amp;inline=true"><img 
				                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DOW_FILE_NOT_EXISTS\',this)" onmouseout="ajax_hideTooltip()"
				                class="iconHelpLink" src="'. THEME_PATH. '/icons/warning.png" alt="'.$gL10n->get('SYS_WARNING').'" /></a>';
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
if ($gCurrentUser->editDownloadRight())
{
    //gucken ob ueberhaupt zusaetzliche Ordnerinhalte gefunden wurden
    if (isset($folderContent['additionalFolders']) || isset($folderContent['additionalFiles']))
    {

        echo '
        <h3>
            '.$gL10n->get('DOW_UNMANAGED_FILES').'
			<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_ADDITIONAL_FILES&amp;inline=true"><img 
                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DOW_ADDITIONAL_FILES\',this)" onmouseout="ajax_hideTooltip()"
                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>            
        </h3>

        <table class="tableList" cellspacing="0">
            <tr>
                <th style="width: 25px;"><img class="iconInformation"
                    src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" title="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" />
                </th>
                <th>'.$gL10n->get('SYS_NAME').'</th>
                <th style="text-align: right;">'.$gL10n->get('SYS_FEATURES').'</th>
            </tr>';


        //Erst die Ordner
        if (isset($folderContent['additionalFolders'])) {
            for($i=0; $i<count($folderContent['additionalFolders']); $i++) {

                $nextFolder = $folderContent['additionalFolders'][$i];

                echo '
                <tr class="tableMouseOver">
                    <td><img src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('SYS_FOLDER').'" title="'.$gL10n->get('SYS_FOLDER').'" /></td>
                    <td>'. $nextFolder['fol_name']. '</td>
                    <td style="text-align: right;">
                        <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=6&amp;folder_id='.$getFolderId.'&amp;name='. urlencode($nextFolder['fol_name']). '">
                        <img src="'. THEME_PATH. '/icons/database_in.png" alt="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" title="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" /></a>
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
                    <td><img src="'. THEME_PATH. '/icons/'.$iconFile.'" alt="'.$gL10n->get('SYS_FILE').'" title="'.$gL10n->get('SYS_FILE').'" /></a></td>
                    <td>'. $nextFile['fil_name']. '</td>
                    <td style="text-align: right;">
                        <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=6&amp;folder_id='.$getFolderId.'&amp;name='. urlencode($nextFile['fil_name']). '">
                        <img src="'. THEME_PATH. '/icons/database_in.png" alt="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" title="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" /></a>
                    </td>
                </tr>';
            }
        }
        echo '</table>';
    }
}

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>