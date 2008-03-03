<?php
/******************************************************************************
 * Verwaltung der Downloads
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Günzler
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * folder :  relativer Pfad zu der Datei / Ordners
 * default_folder : gibt den Ordner in adm_my_files/download an, ab dem die
 *                  Verzeichnisstruktur angezeigt wird. Wurde ein Default-Ordner
 *                  gesetzt, kann der Anwender nur noch in Unterordner und nicht
 *                  in hoehere Ordner des Default-Ordners navigieren
 * file   :  die Datei / der Ordner der / die verarbeitet wird
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!$g_current_user->editDownloadRight())
{
    $g_message->show("norights");
}

$_SESSION['navigation']->addUrl(CURRENT_URL);
$default_folder = strStripTags(urldecode($_GET['default_folder']));
$folder     = strStripTags(urldecode($_GET['folder']));
$file       = strStripTags($_GET['file']);
$act_folder = "../../../adm_my_files/download";
$datei = "";

// uebergebene Ordner auf Gueltigkeit pruefen
// und Ordnerpfad zusammensetzen
if(strlen($default_folder) > 0)
{
   if(strpos($default_folder, "..") !== false
   || strpos($default_folder, ":/") !== false)
    {
        $g_message->show("invalid_folder");
    }
    $act_folder = "$act_folder/$default_folder";
}

if(strlen($folder) > 0)
{
   if(strpos($folder, "..") !== false
   || strpos($folder, ":/") !== false)
    {
        $g_message->show("invalid_folder");
    }
    $act_folder = "$act_folder/$folder";
}

if(strpos($file, "..") !== false
|| strlen($file) == 0)
{
    $g_message->show("invalid_folder");
}

if(isset($_SESSION['download_request']))
{
   $form_values = strStripSlashesDeep($_SESSION['download_request']);
   unset($_SESSION['download_request']);
}
else
{
   $form_values['new_name'] = null;
}

// Endung der Datei ermitteln
$file_array = explode(".","$file");
if(count($file_array) == 1)
{
    $file_extension = null;
}
else
{
    $file_extension = ".". $file_array[1];
}

// Html-Kopf ausgeben
$g_layout['title'] = "Umbenennen";
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<form method=\"post\" action=\"$g_root_path/adm_program/modules/download/download_function.php?mode=4&amp;folder=". urlencode($folder). "&amp;default_folder=". urlencode($default_folder). "&amp;file=". urlencode($file). "\">
<div class=\"formLayout\" id=\"edit_download_form\" style=\"width: 400px; margin-top: 60px;\">
    <div class=\"formHead\">Datei/Ordner umbenennen</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt>Bisheriger Name:</dt>
                    <dd>$file_array[0]&nbsp;</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"new_name\">Neuer Name:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"new_name\" name=\"new_name\" value=\"". $form_values['new_name']. "\" size=\"25\" tabindex=\"1\" />$file_extension
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                        <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"Hilfe\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=dateiname&amp;window=true','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\" onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=dateiname',this);\" onmouseout=\"ajax_hideTooltip()\" />
                    </dd>
                </dl>
            </li>
        </ul>         

        <hr />

        <div class=\"formSubmit\">
            <button name=\"rename\" type=\"submit\" value=\"umbenennen\" tabindex=\"2\">
            <img src=\"". THEME_PATH. "/icons/edit.png\" alt=\"Hochladen\" />
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