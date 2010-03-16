<?php
/******************************************************************************
 * Umbenenn einer Datei oder eines Ordners im Downloadmodul
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * folder_id    :  OrdnerId des Ordners
 * file_id      :  FileId der Datei
 *
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/table_file.php');
require('../../system/classes/table_folder.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$g_current_user->editDownloadRight())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
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
    $folder_id = 0;
}

if (array_key_exists('file_id', $_GET))
{
    if (is_numeric($_GET['file_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $file_id = $_GET['file_id'];
}
else
{
    $file_id = 0;
}

if ( (!$file_id && !$folder_id) OR ($file_id && $folder_id) )
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
if ($file_id) {
    $class = new TableFile($g_db);
    $class->getFileForDownload($file_id);
}
else {
    $class = new TableFolder($g_db);
    $class->getFolderForDownload($folder_id);
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
if($file_id > 0)
{
    $g_layout['title']  = 'Datei bearbeiten';
}
else
{
    $g_layout['title']  = 'Ordner bearbeiten';
}
$g_layout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#new_name").focus();
        }); 
    //--></script>';
require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '
<form method="post" action="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=4&amp;folder_id='.$folder_id.'&amp;file_id='.$file_id.'">
<div class="formLayout" id="edit_download_form">
    <div class="formHead">'.$g_layout['title'].'</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt>Bisheriger Name:</dt>
                    <dd>'.$originalName.'&nbsp;</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="new_name">Neuer Name:</label></dt>
                    <dd>
                        <input type="text" id="new_name" name="new_name" value="'. $form_values['new_name']. '" style="width: 350px;" maxlength="255" tabindex="1" />
                        <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                        <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DOW_PHR_FILE_NAME_RULES&amp;inline=true"><img 
			                onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DOW_PHR_FILE_NAME_RULES\',this)" onmouseout="ajax_hideTooltip()"
			                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="" /></a>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="new_description">Beschreibung:</label></dt>
                    <dd>
                        <textarea id="new_description" name="new_description" style="width: 350px;" rows="5" tabindex="2" >'. $form_values['new_description']. '</textarea>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class="formSubmit">
            <button name="rename" type="submit" value="Speichern" tabindex="2">
            <img src="'. THEME_PATH. '/icons/disk.png" alt="Speichern" />
            &nbsp;Speichern</button>
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

require(THEME_SERVER_PATH. '/overall_footer.php');

?>