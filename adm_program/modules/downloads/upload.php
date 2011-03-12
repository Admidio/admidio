<?php
/******************************************************************************
 * Neue Datei hochladen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * folder_id : ID des akutellen Ordner
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_folder.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}

//maximaler Fileupload fuer das Downloadmodul muss groesser 0 sein
if ($g_preferences['max_file_upload_size'] == 0) {

    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!$g_current_user->editDownloadRight())
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

//pruefen ob in den aktuellen Servereinstellungen ueberhaupt file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $g_message->show($g_l10n->get('SYS_SERVER_NO_UPLOAD'));
}

// Uebergabevariablen pruefen
if (array_key_exists('folder_id', $_GET))
{
    if (is_numeric($_GET['folder_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $folder_id = $_GET['folder_id'];
}
else
{
    // ohne FolderId gehts auch nicht weiter
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

if(isset($_SESSION['download_request']))
{
   $form_values = strStripSlashesDeep($_SESSION['download_request']);
   unset($_SESSION['download_request']);
}
else
{
   $form_values['new_name'] = null;
   $form_values['new_description'] = null;
}

//Folderobject erstellen
$folder = new TableFolder($g_db);
$folder->getFolderForDownload($folder_id);

//pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
if (!$folder->getValue('fol_id'))
{
    //Datensatz konnte nicht in DB gefunden werden...
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

$parentFolderName = $folder->getValue('fol_name');


// Html-Kopf ausgeben
$g_layout['title']  = $g_l10n->get('DOW_UPLOAD_FILE');
$g_layout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#userfile").focus();
        }); 
    //--></script>';
require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '
<form action="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=1&amp;folder_id='.$folder_id.'" method="post" enctype="multipart/form-data">
<div class="formLayout" id="upload_download_form">
    <div class="formHead">'.$g_layout['title'].'</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt>'.$g_l10n->get('DOW_UPLOAD_FILE_TO_FOLDER', $parentFolderName).'</dt>
                    <dd>&nbsp;</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="userfile">'.$g_l10n->get('DOW_CHOOSE_FILE').':</label></dt>
                    <dd>
                        <input type="hidden" name="MAX_FILE_SIZE" value="'.($g_preferences['max_file_upload_size'] * 1024).'" />
                        <input type="file" id="userfile" name="userfile" tabindex="1" size="30" style="width: 345px;" />
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="new_name">'.$g_l10n->get('DOW_NEW_FILE_NAME').':</label></dt>
                    <dd>
                        <input type="text" id="new_name" name="new_name" tabindex="2" value="'.$form_values['new_name'].'" style="width: 250px;" maxlength="255" />
                        &nbsp;('.$g_l10n->get('SYS_OPTIONAL').')
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_FILE_NAME_RULES&amp;inline=true"><img 
			                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DOW_FILE_NAME_RULES\',this)" onmouseout="ajax_hideTooltip()"
			                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="new_description">'.$g_l10n->get('SYS_DESCRIPTION').':</label></dt>
                    <dd>
                        <textarea id="new_description" name="new_description" style="width: 345px;" rows="4" cols="40" tabindex="3" >'.$form_values['new_description'].'</textarea>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class="formSubmit">
            <button id="btnUpload" type="submit" tabindex="4"><img 
            src="'.THEME_PATH.'/icons/page_white_upload.png" alt="'.$g_l10n->get('SYS_UPLOAD').'" />
            &nbsp;'.$g_l10n->get('SYS_UPLOAD').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'.THEME_PATH.'/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>