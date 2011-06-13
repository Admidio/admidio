<?php
/******************************************************************************
 * Umbenenn einer Datei oder eines Ordners im Downloadmodul
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
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

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$g_current_user->editDownloadRight())
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// Uebergabevariablen pruefen und ggf. initialisieren
$get_folder_id = admFuncVariableIsValid($_GET, 'folder_id', 'numeric', 0);
$get_file_id   = admFuncVariableIsValid($_GET, 'file_id', 'numeric', 0);


if ( (!$get_file_id && !$get_folder_id) OR ($get_file_id && $get_folder_id) )
{
    //Es muss entweder eine FileID ODER eine FolderId uebergeben werden
    //beides ist auch nicht erlaubt
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

//Informationen zur Datei/Ordner aus der DB holen,
//falls keine Daten gefunden wurden gibt es die Standardfehlermeldung (invalid)
if ($get_file_id) {
    $class = new TableFile($g_db);
    $class->getFileForDownload($get_file_id);
}
else {
    $class = new TableFolder($g_db);
    $class->getFolderForDownload($get_folder_id);
}

if (is_a($class,'TableFile')) {

    if ($class->getValue('fil_id')) {
        $originalName = $class->getValue('fil_name');
    }
    else {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
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
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    if ($form_values['new_name'] == null) {
        $form_values['new_name'] = $originalName;
    }

    if ($form_values['new_description'] == null) {
        $form_values['new_description'] = $class->getValue('fol_description');
    }

}



// Html-Kopf ausgeben
if($get_file_id > 0)
{
    $g_layout['title']  = $g_l10n->get('DOW_EDIT_FILE');
}
else
{
    $g_layout['title']  = $g_l10n->get('DOW_EDIT_FOLDER');
}
$g_layout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#new_name").focus();
        }); 
    //--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<form method="post" action="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=4&amp;folder_id='.$get_folder_id.'&amp;file_id='.$get_file_id.'">
<div class="formLayout" id="edit_download_form">
    <div class="formHead">'.$g_layout['title'].'</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt>'.$g_l10n->get('DOW_PREVIOUS_NAME').':</dt>
                    <dd>'.$originalName.'&nbsp;</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="new_name">'.$g_l10n->get('DOW_NEW_NAME').':</label></dt>
                    <dd>
                        <input type="text" id="new_name" name="new_name" value="'. $form_values['new_name']. '" style="width: 345px;" maxlength="255" />
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
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
                        <textarea id="new_description" name="new_description" style="width: 345px;" rows="5">'. $form_values['new_description']. '</textarea>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class="formSubmit">
            <button id="btnRename" type="submit">
            <img src="'. THEME_PATH. '/icons/disk.png" alt="'.$g_l10n->get('SYS_SAVE').'" />
            &nbsp;'.$g_l10n->get('SYS_SAVE').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>