<?php
/******************************************************************************
 * Verwaltung der Downloads
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Günzler
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
 *
 * Uebergaben:
 *
 * folder : akuteller Ordner (relativer Pfad in Abhaengigkeit adm_my_files/download
 *          und default_folder
 * default_folder : gibt den Ordner in adm_my_files/download an, ab dem die
 *                  Verzeichnisstruktur angezeigt wird. Wurde ein Default-Ordner
 *                  gesetzt, kann der Anwender nur noch in Unterordner und nicht
 *                  in hoehere Ordner des Default-Ordners navigieren
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

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if(!$g_current_user->editDownloadRight())
{
    $g_message->show("no_file_upload_server");
    header($location);
    exit();
}

//pruefen ob in den aktuellen Servereinstellungen ueberhaupt file_uploads auf ON gesetzt ist...
if (ini_get('file_uploads') != '1')
{
    $g_message->show("no_fileuploads");
}

$_SESSION['navigation']->addUrl($g_current_url);
$default_folder = strStripTags(urldecode($_GET['default_folder']));
$folder     = strStripTags(urldecode($_GET['folder']));

// uebergebene Ordner auf Gueltigkeit pruefen
// und Ordnerpfad zusammensetzen
if(strlen($default_folder) > 0)
{
   if(strpos($default_folder, "..") !== false
   || strpos($default_folder, ":/") !== false)
    {
        $g_message->show("invalid_folder");
    }
}

if(strlen($folder) > 0)
{
   if(strpos($folder, "..") !== false
   || strpos($folder, ":/") !== false)
    {
        $g_message->show("invalid_folder");
    }
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

// Html-Kopf ausgeben
$g_layout['title'] = "Umbenennen";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "<br>
<form action=\"$g_root_path/adm_program/modules/download/download_function.php?mode=1&amp;folder=". urlencode($folder). "&amp;default_folder=". urlencode($default_folder). "\" method=\"post\" enctype=\"multipart/form-data\">
<div class=\"formLayout\" id=\"upload_download_form\">
    <div class=\"formHead\">Datei hochladen</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt style=\"width: 95%;\">Datei in den Ordner <b>";
                        if(strlen($folder) == 0)
                        {
                            if(strlen($default_folder) == 0)
                            {
                                echo "Download";
                            }
                            else
                            {
                                echo ucfirst($default_folder);
                            }
                        }
                        else
                        {
                            echo ucfirst($folder);
                        }
                        echo "</b> hochladen
                    </dt>
                    <dd>&nbsp;</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"userfile\">Datei ausw&auml;hlen:</label></dt>
                    <dd>
                        <input id=\"userfile\" name=\"userfile\" size=\"30\" type=\"file\">
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"new_name\">Neuer Dateiname:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"new_name\" name=\"new_name\" size=\"25\" tabindex=\"1\" value=\"". $form_values['new_name']. "\">
                        &nbsp;(optional)&nbsp;<img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=dateiname','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"hochladen\" type=\"submit\" value=\"hochladen\" tabindex=\"2\">
            <img src=\"$g_root_path/adm_program/images/page_white_get.png\" alt=\"Hochladen\">
            &nbsp;Hochladen</button>
        </div>
    </div>
</div>
</form>

<ul class=\"iconTextLink\">
    <li>
        <a href=\"$g_root_path/adm_program/system/back.php\"><img 
        src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
        <a href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
    </li>
</ul>

<script type=\"text/javascript\"><!--
    document.getElementById('userfile').focus();
--></script>";
    
require(SERVER_PATH. "/adm_program/layout/overall_footer.php"); 

?>