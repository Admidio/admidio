<?php
/******************************************************************************
 * Neue Datei hochladen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * folder_id : ID des akutellen Ordner
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/folder_class.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

//maximaler Fileupload fuer das Downloadmodul muss groesser 0 sein
if ($g_preferences['max_file_upload_size'] == 0) {

    $g_message->show("invalid");
}

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!$g_current_user->editDownloadRight())
{
    $g_message->show("norights");
}

//pruefen ob in den aktuellen Servereinstellungen ueberhaupt file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $g_message->show("no_file_upload_server");
}

// Uebergabevariablen pruefen
if (array_key_exists("folder_id", $_GET))
{
    if (is_numeric($_GET["folder_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $folder_id = $_GET["folder_id"];
}
else
{
    // ohne FolderId gehts auch nicht weiter
    $g_message->show("invalid");
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
}

//Folderobject erstellen
$folder = new Folder($g_db);
$folder->getFolderForDownload($folder_id);

//pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
if (!$folder->getValue('fol_id'))
{
    //Datensatz konnte nicht in DB gefunden werden...
    $g_message->show("invalid");
}

$parentFolderName = $folder->getValue('fol_name');


// Html-Kopf ausgeben
$g_layout['title'] = "Dateiupload";
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<form action=\"$g_root_path/adm_program/modules/downloads/download_function.php?mode=1&amp;folder_id=$folder_id\" method=\"post\" enctype=\"multipart/form-data\">
<div class=\"formLayout\" id=\"upload_download_form\">
    <div class=\"formHead\">Datei hochladen</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt>Datei in den Ordner <b>$parentFolderName</b> hochladen</dt>
                    <dd>&nbsp;</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"userfile\">Datei ausw&auml;hlen:</label></dt>
                    <dd>
                        <input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"" . ($g_preferences['max_file_upload_size'] * 1024) . "\" />
                        <input id=\"userfile\" name=\"userfile\" size=\"30\" type=\"file\" />
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"new_name\">Neuer Dateiname:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"new_name\" name=\"new_name\" size=\"25\" tabindex=\"1\" value=\"". $form_values['new_name']. "\" />
                        &nbsp;(optional)
                        <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" titel=\"\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=dateiname&amp;window=true','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\"
                        onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=dateiname',this);\" onmouseout=\"ajax_hideTooltip()\" />
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"hochladen\" type=\"submit\" value=\"hochladen\" tabindex=\"2\">
            <img src=\"". THEME_PATH. "/icons/page_white_get.png\" alt=\"Hochladen\" />
            &nbsp;Hochladen</button>
        </div>
    </div>
</div>
</form>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img
            src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
        </span>
    </li>
</ul>
<script type=\"text/javascript\"><!--
    document.getElementById('userfile').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>