<?php
/******************************************************************************
 * Verwaltung der Downloads
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Günzler
 *
 * Uebergaben:
 *
 * folder : akuteller Ordner (relativer Pfad in Abhaengigkeit adm_my_files/download
 *          und default_folder
 * default_folder : gibt den Ordner in adm_my_files/download an, ab dem die
 *                  Verzeichnisstruktur angezeigt wird. Wurde ein Default-Ordner
 *                  gesetzt, kann der Anwender nur noch in Unterordner und nicht
 *                  in hoehere Ordner des Default-Ordners navigieren
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
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

$_SESSION['navigation']->addUrl($g_current_url);
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
    <div class=\"formHead\" style=\"width: 400px\">Ordner erstellen</div>
    <div class=\"formBody\" style=\"width: 400px\">
        <div style=\"text-align: center; width: 100%;\">Neuer Ordner in <b>";
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
        </div>
        <div style=\"margin-top: 15px;\">
            <div style=\"text-align: right; width: 33%; float: left;\">Name:</div>
            <div style=\"text-align: left; margin-left: 35%;\">
                <input type=\"text\" id=\"new_folder\" name=\"new_folder\" value=\"". $form_values['new_folder']. "\" style=\"width: 200px;\" maxlength=\"255\">
                <input type=\"hidden\" id=\"folder\" value=\"$folder\" style=\"width: 200px;\" maxlength=\"255\">
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>

        <hr class=\"formLine\" style=\"margin-top: 10px; margin-bottom: 10px;\" width=\"85%\" />

        <div style=\"margin-top: 6px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
            <img src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"erstellen\" type=\"submit\" value=\"erstellen\">
            <img src=\"$g_root_path/adm_program/images/folder_create.png\" alt=\"Ordner erstellen\">
            &nbsp;Ordner erstellen</button>
        </div>
    </div>
</form>

<script type=\"text/javascript\"><!--
    document.getElementById('new_folder').focus();
--></script>";
    
require(SERVER_PATH. "/adm_program/layout/overall_footer.php"); 

?>