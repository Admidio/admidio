<?php
/******************************************************************************
 * Downloads auflisten
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * folder_id : akutelle OrdnerId
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/folder_class.php");


// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// Uebergabevariablen pruefen
if (array_key_exists("folder_id", $_GET))
{
    if (is_numeric($_GET["folder_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $folderId = $_GET["folder_id"];
}
else
{
    // FolderId auf 0 setzen
    $folderId = 0;
}

//Verwaltung der Session
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);
unset($_SESSION['download_request']);


//Informationen zum aktuellen Ordner aus der DB holen
$currentFolder = new Folder($g_db);
$currentFolder->getFolderForDownload($folderId);

//pruefen ob ueberhaupt ein Datensatz in der DB gefunden wurde...
if (!$currentFolder->getValue('fol_id'))
{
    //Datensatz konnte nicht in DB gefunden werden
    //oder Benutzer darf nicht zugreifen
    $g_message->show("invalid");
}

$folderId = $currentFolder->getValue('fol_id');

//Ordnerinhalt zur Darstellung auslesen
$folderContent = $currentFolder->getFolderContentsForDownload();

//NavigationsLink erhalten
$navigationBar = $currentFolder->getNavigationForDownload();



// Html-Kopf ausgeben
$g_layout['title'] = "Downloadbereich";
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<h1 class=\"moduleHeadline\">Downloadbereich</h1>";


echo "$navigationBar";


//Button Upload, Neuer Ordner und Ordnerkonfiguration
if ($g_current_user->editDownloadRight())
{
    echo "
    <ul class=\"iconTextLinkList\">
        <li>
            <span class=\"iconTextLink\">
                <a href=\"$g_root_path/adm_program/modules/downloads/folder_new.php?folder_id=$folderId\"><img
                src=\"". THEME_PATH. "/icons/folder_create.png\" alt=\"Ordner erstellen\" /></a>
                <a href=\"$g_root_path/adm_program/modules/downloads/folder_new.php?folder_id=$folderId\">Ordner anlegen</a>
            </span>
        </li>
        <li>
            <span class=\"iconTextLink\">
                <a href=\"$g_root_path/adm_program/modules/downloads/upload.php?folder_id=$folderId\"><img
                src=\"". THEME_PATH. "/icons/page_white_get.png\" alt=\"Hochladen\" /></a>
                <a href=\"$g_root_path/adm_program/modules/downloads/upload.php?folder_id=$folderId\">Datei hochladen</a>
            </span>
        </li>
        <li>
            <span class=\"iconTextLink\">
                <a href=\"$g_root_path/adm_program/modules/downloads/folder_config.php?folder_id=$folderId\"><img
                src=\"". THEME_PATH. "/icons/wrench.png\" alt=\"Ordnerberechtigung setzen\" /></a>
                <a href=\"$g_root_path/adm_program/modules/downloads/folder_config.php?folder_id=$folderId\">Berechtigungen setzen</a>
            </span>
        </li>
    </ul>";
};

//Anlegen der Tabelle
echo "
<table class=\"tableList\" cellspacing=\"0\">
    <tr>
        <th style=\"width: 25px;\"><img class=\"iconInformation\"
            src=\"". THEME_PATH. "/icons/folder.png\" alt=\"Ordner / Dateityp\" title=\"Ordner / Dateityp\" />
        </th>
        <th>Name</th>
        <th>&Auml;nderungsdatum</th>
        <th>Gr&ouml;&szlig;e</th>
        <th>Counter</th>";
        if ($g_current_user->editDownloadRight())
        {
           echo "<th style=\"text-align: center;\">Editieren</th>";
        }
    echo "</tr>";


//falls der Ordner leer ist
if (count($folderContent) == 0)
{
    if ($g_current_user->editDownloadRight())
    {
        $colspan = "6";
    }
    else
    {
        $colspan = "5";
    }

    echo"<tr>
       <td colspan=\"$colspan\">Dieser Ordner ist leer</td>
    </tr>";
}
else
{
    //Ordnerinhalt ausgeben
    if (isset($folderContent["folders"])) {
        //als erstes die Unterordner
        for($i=0; $i<count($folderContent["folders"]); $i++) {

            $nextFolder = $folderContent["folders"][$i];

            echo "
            <tr class=\"tableMouseOver\">
                <td>
                  	<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/downloads/downloads.php?folder_id=". $nextFolder['fol_id']. "\">
                    <img src=\"". THEME_PATH. "/icons/folder.png\" alt=\"Ordner\" title=\"Ordner\" /></a>
                </td>
                <td><a href=\"$g_root_path/adm_program/modules/downloads/downloads.php?folder_id=". $nextFolder['fol_id']. "\">". $nextFolder['fol_name']. "</a></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>";
                if ($g_current_user->editDownloadRight())
                {
                    //Hier noch die Links zum Aendern und Loeschen
                    echo "
                    <td style=\"text-align: center;\">
						<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/downloads/rename.php?folder_id=". $nextFolder['fol_id']. "\">
						<img src=\"". THEME_PATH. "/icons/edit.png\" alt=\"Umbenennen\" title=\"Umbenennen\" /></a>
						<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/downloads/download_function.php?mode=5&amp;folder_id=". $nextFolder['fol_id']. "\">
						<img src=\"". THEME_PATH. "/icons/cross.png\" alt=\"Löschen\" title=\"Löschen\" /></a>";
                        if (!$nextFolder['fol_exists']) {
                            echo "<img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/warning16.png\" alt=\"Warnung\" title=\"Warnung\"
                          onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=folderNotExists',this);\"
                          onmouseout=\"ajax_hideTooltip()\" />";
                        }

                     echo "
                      </td>";
                }
            echo "</tr>";

        }
    }

    //als naechstes werden die enthaltenen Dateien ausgegeben
    if (isset($folderContent["files"])) {
        for($i=0; $i<count($folderContent["files"]); $i++) {

            $nextFile = $folderContent["files"][$i];

            //Ermittlung der dateiendung
            $dateiendung  = strtolower(substr($nextFile['fil_name'], strrpos($nextFile['fil_name'], ".")+1));

            //Auszugebendes Icon
            if($dateiendung=="gif"
            || $dateiendung=="cdr"
            || $dateiendung=="jpg"
            || $dateiendung=="png"
            || $dateiendung=="bmp"
            || $dateiendung=="wmf" )
               $dateiendung = "page_white_camera";
            elseif($dateiendung=="doc"
            ||     $dateiendung=="dot"
            ||     $dateiendung=="rtf")
               $dateiendung = "page_white_word";
            elseif($dateiendung=="xls"
            ||     $dateiendung=="xlt"
            ||     $dateiendung=="csv")
               $dateiendung = "page_white_excel";
            elseif($dateiendung=="pps"
            ||     $dateiendung=="ppt")
               $dateiendung = "page_white_powerpoint";
            elseif($dateiendung=="txt"
            ||     $dateiendung=="php"
            ||     $dateiendung=="sql"
            ||     $dateiendung=="log")
               $dateiendung = "page_white_text";
            elseif($dateiendung=="pdf")
               $dateiendung = "page_white_acrobat";
            elseif($dateiendung=="zip"
            ||     $dateiendung=="gz"
            ||     $dateiendung=="rar"
            ||     $dateiendung=="tar")
               $dateiendung = "page_white_compressed";
            elseif($dateiendung=="swf")
               $dateiendung = "page_white_flash";
            else
               $dateiendung = "page_white_question";

            echo "
            <tr class=\"tableMouseOver\">
                <td>
					<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/downloads/get_file.php?file_id=". $nextFile['fil_id']. "\">
					<img src=\"". THEME_PATH. "/icons/$dateiendung.png\" alt=\"Datei\" title=\"Datei\" /></a>
                </td>
                <td><a href=\"$g_root_path/adm_program/modules/downloads/get_file.php?file_id=". $nextFile['fil_id']. "\">". $nextFile['fil_name']. "</a></td>
                <td>". $nextFile['fil_timestamp']. "</td>
                <td>". $nextFile['fil_size']. " KB&nbsp;</td>
                <td>". $nextFile['fil_counter'];
                if ($g_current_user->editDownloadRight())
                {
                    //Hier noch die Links zum Aendern und Loeschen
                    echo "
                    <td style=\"text-align: center;\">
						<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/downloads/rename.php?file_id=". $nextFile['fil_id']. "\">
						<img src=\"". THEME_PATH. "/icons/edit.png\" alt=\"Umbenennen\" title=\"Umbenennen\" /></a>
						<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/downloads/download_function.php?mode=5&amp;file_id=". $nextFile['fil_id']. "\">
						<img src=\"". THEME_PATH. "/icons/cross.png\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\" /></a>";
                        if (!$nextFile['fil_exists']) {
                            echo "<img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/warning16.png\" alt=\"Warnung\" title=\"Warnung\"
                          onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=fileNotExists',this);\"
                          onmouseout=\"ajax_hideTooltip()\" />";
                        }

                     echo "
                    </td>";
                }
            echo "</tr>";

        }
    }

}

//Ende der Tabelle
echo"</table>";

//Falls der User DownloadAdmin ist werden jetzt noch die zusaetzlich im Ordner enthaltenen Files angezeigt.
if ($g_current_user->editDownloadRight())
{
    //gucken ob ueberhaupt zusaetzliche Ordnerinhalte gefunden wurden
    if (isset($folderContent["additionalFolders"]) || isset($folderContent["additionalFiles"]))
    {

        echo "
        <h3>
            Nicht verwaltete Dateien
            <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" titel=\"Hilfe\"
              onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=additionalFiles&amp;window=true','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\";
              onmouseover=\"ajax_showTooltip(event,'$g_root_path/adm_program/system/msg_window.php?err_code=additionalFiles',this);\" onmouseout=\"ajax_hideTooltip()\"
            />
        </h3>";


        echo "
        <table class=\"tableList\" cellspacing=\"0\">
            <tr>
                <th style=\"width: 25px;\"><img class=\"iconInformation\"
                    src=\"". THEME_PATH. "/icons/folder.png\" alt=\"Ordner / Dateityp\" title=\"Ordner / Dateityp\" />
                </th>
                <th>Name</th>
                <th style=\"text-align: right;\">Aktionen</th>
            </tr>";


        //Erst die Ordner
        if (isset($folderContent["additionalFolders"])) {
            for($i=0; $i<count($folderContent["additionalFolders"]); $i++) {

                $nextFolder = $folderContent["additionalFolders"][$i];

                echo "
                <tr class=\"tableMouseOver\">
                    <td><img src=\"". THEME_PATH. "/icons/folder.png\" alt=\"Ordner\" title=\"Ordner\" /></td>
                    <td>". $nextFolder['fol_name']. "</td>
                    <td style=\"text-align: right;\">
						<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/downloads/download_function.php?mode=6&amp;folder_id=$folderId&amp;name=". urlencode($nextFolder['fol_name']). "\">
						<img src=\"". THEME_PATH. "/icons/database_in.png\" alt=\"Zur Datenbank hinzuf&uuml;gen\" title=\"Zur Datenbank hinzuf&uuml;gen\" /></a>
                    </td>
                </tr>";
            }


        }

        //Jetzt noch die Dateien
        if (isset($folderContent["additionalFiles"])) {
            for($i=0; $i<count($folderContent["additionalFiles"]); $i++) {

                $nextFile = $folderContent["additionalFiles"][$i];

                //Ermittlung der dateiendung
                $dateiendung  = strtolower(substr($nextFile['fil_name'], strrpos($nextFile['fil_name'], ".")+1));

                //Auszugebendes Icon
                if($dateiendung=="gif"
                || $dateiendung=="cdr"
                || $dateiendung=="jpg"
                || $dateiendung=="png"
                || $dateiendung=="bmp"
                || $dateiendung=="wmf" )
                    $dateiendung = "page_white_camera";
                elseif($dateiendung=="doc"
                ||     $dateiendung=="dot"
                ||     $dateiendung=="rtf")
                    $dateiendung = "page_white_word";
                elseif($dateiendung=="xls"
                ||     $dateiendung=="xlt"
                ||     $dateiendung=="csv")
                    $dateiendung = "page_white_excel";
                elseif($dateiendung=="pps"
                ||     $dateiendung=="ppt")
                       $dateiendung = "page_white_powerpoint";
                elseif($dateiendung=="txt"
                ||     $dateiendung=="php"
                ||     $dateiendung=="sql"
                ||     $dateiendung=="log")
                       $dateiendung = "page_white_text";
                elseif($dateiendung=="pdf")
                       $dateiendung = "page_white_acrobat";
                elseif($dateiendung=="zip"
                ||     $dateiendung=="gz"
                ||     $dateiendung=="rar"
                ||     $dateiendung=="tar")
                       $dateiendung = "page_white_compressed";
                elseif($dateiendung=="swf")
                       $dateiendung = "page_white_flash";
                else
                       $dateiendung = "page_white_question";

                echo "
                <tr class=\"tableMouseOver\">
                    <td><img src=\"". THEME_PATH. "/icons/$dateiendung.png\" alt=\"Datei\" title=\"Datei\" /></a></td>
                    <td>". $nextFile['fil_name']. "</td>
                    <td style=\"text-align: right;\">
						<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/downloads/download_function.php?mode=6&amp;folder_id=$folderId&amp;name=". urlencode($nextFile['fil_name']). "\">
						<img src=\"". THEME_PATH. "/icons/database_in.png\" alt=\"Zur Datenbank hinzuf&uuml;gen\" title=\"Zur Datenbank hinzuf&uuml;gen\" /></a>
                    </td>
                </tr>";
            }


        }





        echo "
        </table>";

    }

}

require(THEME_SERVER_PATH. "/overall_footer.php");

?>