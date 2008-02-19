<?php
/******************************************************************************
 * Neuen Ordner Anlegen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Günzler
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * folder_id : Ordner Id des uebergeordneten Ordners
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


$_SESSION['navigation']->addUrl(CURRENT_URL);

if(isset($_SESSION['download_request']))
{
   $form_values = strStripSlashesDeep($_SESSION['download_request']);
   unset($_SESSION['download_request']);
}
else
{
   $form_values['new_folder'] = null;
}

//TODO: Informationen zum uebergeordnetenOrdner aus der DB holen
$parentFolderName = "OrdnerName"; //fuellen mit krempel aus der DB...

// Html-Kopf ausgeben
$g_layout['title'] = "Ordner erstellen";
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<form method=\"post\" action=\"$g_root_path/adm_program/modules/downloads/download_function.php?mode=3&amp;folder=$folder_id\">
<div class=\"formLayout\" id=\"edit_download_folder_form\" style=\"width: 400px; margin-top: 60px;\">
    <div class=\"formHead\">Ordner erstellen</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt>
                        Neuer Ordner in Ordner <b>$parentFolderName</b> erstellen
                    </dt>
                    <dd>&nbsp;</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"new_folder\">Name:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"new_folder\" name=\"new_folder\" value=\"". $form_values['new_folder']. "\" style=\"width: 200px;\" maxlength=\"255\" />
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"erstellen\" type=\"submit\" value=\"erstellen\">
            <img src=\"". THEME_PATH. "/icons/folder_create.png\" alt=\"Ordner erstellen\" />
            &nbsp;Ordner erstellen</button>
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
    document.getElementById('new_folder').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>