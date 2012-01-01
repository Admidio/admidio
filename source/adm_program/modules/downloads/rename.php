<?php
/******************************************************************************
 * Umbenenn einer Datei oder eines Ordners im Downloadmodul
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * folder_id    :  OrdnerId des Ordners
 * file_id      :  FileId der Datei
 *
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_file.php');
require_once('../../system/classes/table_folder.php');

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'numeric', 0);
$getFileId   = admFuncVariableIsValid($_GET, 'file_id', 'numeric', 0);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editDownloadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if ( (!$getFileId && !$getFolderId) OR ($getFileId && $getFolderId) )
{
    //Es muss entweder eine FileID ODER eine FolderId uebergeben werden
    //beides ist auch nicht erlaubt
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
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

//Informationen zur Datei/Ordner aus der DB holen,
//falls keine Daten gefunden wurden gibt es die Standardfehlermeldung (invalid)
if ($getFileId) {
    $class = new TableFile($gDb);
    $class->getFileForDownload($getFileId);
}
else {
    $class = new TableFolder($gDb);
    $class->getFolderForDownload($getFolderId);
}

if (is_a($class,'TableFile')) {

    if ($class->getValue('fil_id')) {
        $originalName = $class->getValue('fil_name');
    }
    else {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    if ($form_values['new_name'] == null) {
        $form_values['new_name'] = $originalName;
    }

    if ($form_values['new_description'] == null) {
        $form_values['new_description'] = $class->getValue('fil_description');
    }

}
else {

    if ($class->getValue('fol_id')) {
        $originalName = $class->getValue('fol_name');
    }
    else {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    if ($form_values['new_name'] == null) {
        $form_values['new_name'] = $originalName;
    }

    if ($form_values['new_description'] == null) {
        $form_values['new_description'] = $class->getValue('fol_description');
    }

}



// Html-Kopf ausgeben
if($getFileId > 0)
{
    $gLayout['title']  = $gL10n->get('DOW_EDIT_FILE');
}
else
{
    $gLayout['title']  = $gL10n->get('DOW_EDIT_FOLDER');
}
$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#new_name").focus();
        }); 
    //--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<form method="post" action="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=4&amp;folder_id='.$getFolderId.'&amp;file_id='.$getFileId.'">
<div class="formLayout" id="edit_download_form">
    <div class="formHead">'.$gLayout['title'].'</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt>'.$gL10n->get('DOW_PREVIOUS_NAME').':</dt>
                    <dd>'.$originalName.'&nbsp;</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="new_name">'.$gL10n->get('DOW_NEW_NAME').':</label></dt>
                    <dd>
                        <input type="text" id="new_name" name="new_name" value="'. $form_values['new_name']. '" style="width: 90%;" maxlength="255" />
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_FILE_NAME_RULES&amp;inline=true"><img 
			                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DOW_FILE_NAME_RULES\',this)" onmouseout="ajax_hideTooltip()"
			                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="new_description">'.$gL10n->get('SYS_DESCRIPTION').':</label></dt>
                    <dd>
                        <textarea id="new_description" name="new_description" style="width: 90%;" rows="5">'. $form_values['new_description']. '</textarea>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class="formSubmit">
            <button id="btnRename" type="submit">
            <img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />
            &nbsp;'.$gL10n->get('SYS_SAVE').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>