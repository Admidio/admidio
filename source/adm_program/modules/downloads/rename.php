<?php
/******************************************************************************
 * Umbenenn einer Datei oder eines Ordners im Downloadmodul
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
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

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/file.php");
require("../../system/classes/folder.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$g_current_user->editDownloadRight())
{
    $g_message->show("norights");
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
    $folder_id = 0;
}

if (array_key_exists("file_id", $_GET))
{
    if (is_numeric($_GET["file_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $file_id = $_GET["file_id"];
}
else
{
    $file_id = 0;
}

if ( (!$file_id && !$folder_id) OR ($file_id && $folder_id) )
{
    //Es muss entweder eine FileID ODER eine FolderId uebergeben werden
    //beides ist auch nicht erlaubt
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

//Informationen zur Datei/Ordner aus der DB holen,
//falls keine Daten gefunden wurden gibt es die Standardfehlermeldung (invalid)
if ($file_id) {
    $class = new File($g_db);
    $class->getFileForDownload($file_id);
}
else {
    $class = new Folder($g_db);
    $class->getFolderForDownload($folder_id);
}

if (is_a($class,'File')) {
    if ($class->getValue('fil_id')) {
        $originalName = $class->getValue('fil_name');
    }
    else {
        $g_message->show("invalid");
    }

}
else {
if ($class->getValue('fol_id')) {
        $originalName = $class->getValue('fol_name');
    }
    else {
        $g_message->show("invalid");
    }

}



// Html-Kopf ausgeben
$g_layout['title'] = "Umbenennen";
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<form method=\"post\" action=\"$g_root_path/adm_program/modules/downloads/download_function.php?mode=4&amp;folder_id=$folder_id&amp;file_id=$file_id\">
<div class=\"formLayout\" id=\"edit_download_form\">
    <div class=\"formHead\">Datei/Ordner umbenennen</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt>Bisheriger Name:</dt>
                    <dd>$originalName&nbsp;</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"new_name\">Neuer Name:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"new_name\" name=\"new_name\" value=\"". $form_values['new_name']. "\" size=\"25\" tabindex=\"1\" />
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                        <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"\"                       onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=dateiname&amp;window=true','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\" onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=dateiname',this);\" onmouseout=\"ajax_hideTooltip()\" />
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"rename\" type=\"submit\" value=\"umbenennen\" tabindex=\"2\">
            <img src=\"". THEME_PATH. "/icons/edit.png\" alt=\"Umbenennen\" />
            &nbsp;Umbenennen</button>
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
    document.getElementById('new_name').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>