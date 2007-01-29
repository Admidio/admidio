<?php
/******************************************************************************
 * Downloads auflisten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin GÃ¼nzler
 *
 * Uebergaben:
 *    
 * folder : akuteller Ordner (relativer Pfad in Abhaengigkeit adm_my_files/download
 *          und default_folder
 * default_folder : gibt den Ordner in adm_my_files/download an, ab dem die
 *                  Verzeichnisstruktur angezeigt wird. Wurde ein Default-Ordner
 *                  gesetzt, kann der Anwender nur noch in Unterordner und nicht
 *                  in hoehere Ordner des Default-Ordners navigieren
 * sort   : Gibt die Art der Sortierung an. Default ist aufsteigend. Bei der Uebergabe
 *          von "desc" wird absteigend sortiert.
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

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_folder = null;
$req_default_folder = null;
$req_sort   = "asc";

// Uebergabevariablen pruefen

if(isset($_GET['folder']))
{
    if(strpos($_GET['folder'], "..") !== false
    || strpos($_GET['folder'], ":/") !== false)
    {
        $g_message->show("invalid_folder");
    }
    $req_folder = strStripTags(urldecode($_GET['folder']));
}

if (isset($_GET['default_folder']))
{
    if(strpos($_GET['default_folder'], "..") !== false
    || strpos($_GET['default_folder'], ":/") !== false)
    {
        $g_message->show("invalid_folder");
    }
    $req_default_folder = strStripTags(urldecode($_GET['default_folder']));
}

if(isset($_GET["sort"]))  
{
    if(strtolower($_GET["sort"]) != "asc" && strtolower($_GET["sort"]) != "desc")
    {
        $g_message->show("invalid");
    }
    $req_sort = strtolower($_GET["sort"]);
}

//Verwaltung der Session
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl($g_current_url);
unset($_SESSION['download_request']);

$act_folder = "../../../adm_my_files/download";

// Ordnerpfad zusammensetzen
if(strlen($req_default_folder) > 0)
{
    $act_folder = "$act_folder/$req_default_folder";
}

if(strlen($req_folder) > 0)
{
    $act_folder = "$act_folder/$req_folder";
}

//Erstellen des Links vom Menü
$path = explode("/",$req_folder);
$next_folder = "";
if (strlen($req_default_folder) > 0)
{
    $text = $req_default_folder;
}
else
{
    $text = "Downloads";
}

if (strlen($req_default_folder) > 0 || strlen($req_folder) > 0)
{
    $link = "<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/download/download.php?default_folder=". urlencode($req_default_folder). "\"><img 
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/application_view_list.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Downloads\"></a>
             <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/download/download.php?default_folder=". urlencode($req_default_folder). "\">$text</a>";
}

$i=0;
While ($i <> count($path)-1)
{
    If ($i==0)
    {
        $next_folder = $path[0];
    }
    else
    {
        $next_folder = $next_folder."/".$path[$i];
    };
    $link = $link." &gt; <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/download/download.php?folder=".urlencode($next_folder). "&amp;default_folder=". urlencode($req_default_folder). "\">$path[$i]</a>";
    $i++;
}

if(strlen($req_folder) > 0)
{
    $link = "<p><span class=\"iconLink\">$link &gt; $path[$i]</span></p>";
}

//Auslesen des Ordners und schreiben in array
if(!is_dir($act_folder))
{
    $g_message->show("folder_not_exist");
}

// Ordnerinhalt sortieren
$dh  = opendir($act_folder);
while (false !== ($filename = readdir($dh)))
{
    $ordnerarray[] = $filename;
}
$ordnerarray = array_slice ($ordnerarray,2);
if ($req_sort == "desc")
{
    // Absteigend
    rsort($ordnerarray);
}
else
{
    // Aufsteigend
    sort($ordnerarray);
};

echo "
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Downloadbereich</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");

    echo"<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
    <h1>Downloadbereich</h1>";
    
    if(strlen($req_folder) > 0)
    {
        echo "$link";
    }

    //Button Upload und Neuer Ordner
    if ($g_session_valid && $g_current_user->editDownloadRight())
    {
        echo "<p>
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/download/folder_new.php?folder=". urlencode($req_folder). "&amp;default_folder=". urlencode($req_default_folder). "\"><img
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/folder_create.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Ordner erstellen\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/download/folder_new.php?folder=". urlencode($req_folder). "&amp;default_folder=". urlencode($req_default_folder). "\">Ordner anlegen</a>
            </span>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/download/upload.php?folder=". urlencode($req_folder). "&amp;default_folder=". urlencode($req_default_folder). "\"><img
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/page_white_get.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Hochladen\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/download/upload.php?folder=". urlencode($req_folder). "&amp;default_folder=". urlencode($req_default_folder). "\">Datei hochladen</a>
            </span>
        </p>";
    };

    //Anlegen der Tabelle
    echo" <table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
            <tr>
               <th class=\"tableHeader\" width=\"25\" style=\"text-align: center;\"><img src=\"$g_root_path/adm_program/images/folder.png\" border=\"0\" alt=\"Ordner\"></th>
               <th class=\"tableHeader\" style=\"text-align: left;\">Name</th>
               <th class=\"tableHeader\" style=\"text-align: center;\">Erstellungsdatum</th>
               <th class=\"tableHeader\" style=\"text-align: right;\">Gr&ouml;&szlig;e&nbsp;</th>";
               if ($g_session_valid && $g_current_user->editDownloadRight())
               {
                   echo "<th class=\"tableHeader\" align=\"center\">Editieren</th>";
               }
            echo "</tr>";


    //falls der Ordner leer ist
    if(Count($ordnerarray)==0){
      echo"
            <tr>
               <td colspan=\"2\">Dieser Ordner ist leer</td>
               <td></td>
               <td></td>";
               if ($g_session_valid && $g_current_user->editDownloadRight()) echo "<td></td>";
      echo "</tr>";
    }


    //durchlafen des Ordnerarrays und Ordnerlinkausgabe in Tabellenzeilen, ruft erneut die download.txt auf nur mit neuem Ordner
    for($i=0; $i<count($ordnerarray); $i++)
    {
         if(filetype("$act_folder/$ordnerarray[$i]")=="dir")
         {
            if(strlen($req_folder) > 0)
               $next_folder = "$req_folder/$ordnerarray[$i]";
            else
               $next_folder = $ordnerarray[$i];

            echo "
               <tr class=\"listMouseOut\" onMouseOver=\"this.className='listMouseOver'\" onMouseOut=\"this.className='listMouseOut'\">
                  <td style=\"text-align: center;\"><a href=\"$g_root_path/adm_program/modules/download/download.php?folder=". urlencode($next_folder). "&amp;default_folder=". urlencode($req_default_folder). "\">
                     <img src=\"$g_root_path/adm_program/images/folder.png\" border=\"0\" alt=\"Ordner\" title=\"Ordner\"></a></td>
                  <td style=\"text-align: left;\"><a href=\"$g_root_path/adm_program/modules/download/download.php?folder=". urlencode($next_folder). "&amp;default_folder=". urlencode($req_default_folder). "\">$ordnerarray[$i]</a></td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>";
            if ($g_session_valid && $g_current_user->editDownloadRight())
            {
               echo "
               <td style=\"text-align: center;\">&nbsp;
                  <a href=\"$g_root_path/adm_program/modules/download/rename.php?folder=". urlencode($req_folder). "&amp;file=". urlencode($ordnerarray[$i]). "&amp;default_folder=". urlencode($req_default_folder). "\"><img 
                    src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Umbenennen\"></a>&nbsp;&nbsp;&nbsp;
                  <a href=\"$g_root_path/adm_program/modules/download/download_function.php?mode=5&amp;file=". urlencode($ordnerarray[$i]). "&amp;folder=". urlencode($req_folder). "&amp;default_folder=". urlencode($req_default_folder). "\"><img 
                    src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"></a>
               </td>";
            }
            echo "</tr>";
         };
    };
    
    //durchlaufen des Ordnerarrays und Dateilinkausgabe in Tabellenzeilen
    for($i=0; $i<count($ordnerarray); $i++)
    {
        if(filetype("$act_folder/$ordnerarray[$i]")=="file")
        {
            //ermittlung der Dateigroesse
            $dateigroesse = round(filesize("$act_folder/$ordnerarray[$i]")/1024);
            // Ermittlung des Datums
            $dateidatum   = date ("d.m.Y", filemtime("$act_folder/$ordnerarray[$i]"));
            //Ermittlung der dateiendung
            $dateiendung  = strtolower(substr($ordnerarray[$i], strrpos($ordnerarray[$i], ".")+1));

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

            //Link und Dateiinfo Ausgabe
            echo "<tr class=\"listMouseOut\" onMouseOver=\"this.className='listMouseOver'\" onMouseOut=\"this.className='listMouseOut'\">
                     <td style=\"text-align: center;\"><a href=\"get_file.php?folder=". urlencode($req_folder). "&amp;file=". urlencode($ordnerarray[$i]). "&amp;default_folder=". urlencode($req_default_folder). "\"><img src=\"$g_root_path/adm_program/images/$dateiendung.png\" border=\"0\" alt=\"Datei\" title=\"Datei\"></a></td>
                     <td style=\"text-align: left;\"><a href=\"get_file.php?folder=". urlencode($req_folder). "&amp;file=". urlencode($ordnerarray[$i]). "&amp;default_folder=". urlencode($req_default_folder). "\">$ordnerarray[$i]</a></td>
                     <td style=\"text-align: center;\">$dateidatum</td>
                     <td style=\"text-align: right;\">$dateigroesse kB&nbsp;</td>";

            //Moderation
            if ($g_session_valid && $g_current_user->editDownloadRight())
            {
               echo "
               <td align=\"center\">&nbsp;
                  <a href=\"$g_root_path/adm_program/modules/download/rename.php?folder=". urlencode($req_folder). "&amp;file=". urlencode($ordnerarray[$i]). "&amp;default_folder=". urlencode($req_default_folder). "\">
                     <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Umbenennen\"></a>&nbsp;&nbsp;&nbsp;
                  <a href=\"$g_root_path/adm_program/modules/download/download_function.php?mode=5&amp;file=". urlencode($ordnerarray[$i]). "&amp;folder=". urlencode($req_folder). "&amp;default_folder=". urlencode($req_default_folder). "\">
                     <img src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"></a>
               </td>";
            }
            echo "</tr>";
         };
    };
    //Ende der Tabelle
    echo"</table>
    </div>";
    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>