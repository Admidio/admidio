<?php
/******************************************************************************
 * Neuen Ordner Anlegen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * folder_id : Ordner Id des uebergeordneten Ordners
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_folder.php');

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'numeric', null, true);

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

$_SESSION['navigation']->addUrl(CURRENT_URL);

if(isset($_SESSION['download_request']))
{
   $form_values = strStripSlashesDeep($_SESSION['download_request']);
   unset($_SESSION['download_request']);
}
else
{
   $form_values['new_folder'] = null;
   $form_values['new_description'] = null;
}

//Folderobject erstellen
$folder = new TableFolder($gDb);
$folder->getFolderForDownload($getFolderId);

//pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
if (!$folder->getValue('fol_id'))
{
    //Datensatz konnte nicht in DB gefunden werden...
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$parentFolderName = $folder->getValue('fol_name');


// Html-Kopf ausgeben
$gLayout['title']  = $gL10n->get('DOW_CREATE_FOLDER');
$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#new_folder").focus();
        }); 
    //--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<form method="post" action="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=3&amp;folder_id='.$getFolderId.'">
<div class="formLayout" id="edit_download_folder_form">
    <div class="formHead">'.$gLayout['title'].'</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt>'.$gL10n->get('DOW_CREATE_FOLDER_DESC', $parentFolderName).'</dt>
                    <dd>&nbsp;</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="new_folder">'.$gL10n->get('SYS_NAME').':</label></dt>
                    <dd>
                        <input type="text" id="new_folder" name="new_folder" value="'.$form_values['new_folder'].'" style="width: 90%;" maxlength="255" />
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="new_description">'.$gL10n->get('SYS_DESCRIPTION').':</label></dt>
                    <dd>
                        <textarea id="new_description" name="new_description" style="width: 90%;" rows="4" cols="40" >'.$form_values['new_description'].'</textarea>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class="formSubmit">
            <button id="btnCreate" type="submit">
            <img src="'. THEME_PATH. '/icons/folder_create.png" alt="'.$gL10n->get('DOW_CREATE_FOLDER').'" />
            &nbsp;'.$gL10n->get('DOW_CREATE_FOLDER').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'.THEME_PATH.'/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>