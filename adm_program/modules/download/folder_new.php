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


// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!$g_current_user->editDownloadRight())
{
    $g_message->show("norights");
}

$_SESSION['navigation']->addUrl(CURRENT_URL);
$default_folder = strStripTags(urldecode($_GET['default_folder']));
$folder = strStripTags(urldecode($_GET['folder']));

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
   $form_values['new_folder'] = null;
}

// Html-Kopf ausgeben
$g_layout['title'] = "Ordner erstellen";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "<br>
<form method=\"post\" action=\"$g_root_path/adm_program/modules/download/download_function.php?mode=3&amp;folder=". urlencode($folder). "&amp;default_folder=". urlencode($default_folder). "\">
<div class=\"formLayout\" id=\"edit_download_folder_form\" style=\"width: 400px\">
    <div class=\"formHead\">Ordner erstellen</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt>
                        Neuer Ordner in <b>";
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
                        echo "</b> erstellen
                    </dt>
                    <dd>&nbsp;</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"new_folder\">Name:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"new_folder\" name=\"new_folder\" value=\"". $form_values['new_folder']. "\" style=\"width: 200px;\" maxlength=\"255\">
                        <input type=\"hidden\" id=\"folder\" value=\"$folder\" style=\"width: 200px;\" maxlength=\"255\">
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"erstellen\" type=\"submit\" value=\"erstellen\">
            <img src=\"$g_root_path/adm_program/images/folder_create.png\" alt=\"Ordner erstellen\">
            &nbsp;Ordner erstellen</button>
        </div>
    </div>
</div>
</form>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
        </span>
    </li>
</ul>

<script type=\"text/javascript\"><!--
    document.getElementById('new_folder').focus();
--></script>";
    
require(SERVER_PATH. "/adm_program/layout/overall_footer.php"); 

?>