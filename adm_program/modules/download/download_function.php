<?php
/******************************************************************************
 * Downloads auflisten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Guenzler
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
 *
 * Uebergaben:
 *
 * mode   :  1 - Datei / Ordner hochladen
 *           2 - Datei / Ordner loeschen
 *           3 - Ordner erstellen
 *           4 - Datei / Ordner umbenennen
 *               5 - Datei /Ordner loeschen abfrage
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

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if(!$g_current_user->editDownloadRight())
{
    $g_message->show("norights");
}

//testen ob Schreibrechte fuer adm_my_files bestehen
if (is_writeable("../../../adm_my_files/download") == false)
{
    $g_message->show("invalid_folder");
}

// lokale Variablen initialisieren
$act_folder = "../../../adm_my_files/download";

// lokale Variablen der Uebergabevariablen initialisieren
$req_mode   = 0;
$req_folder = null;
$req_default_folder = null;
$req_file   = null;

// Uebergabevariablen pruefen
if(isset($_GET["mode"]))
{
    if(is_numeric($_GET["mode"]) == false
    || $_GET["mode"] < 1 || $_GET["mode"] > 5)
    {
        $g_message->show("invalid");
    }
    $req_mode = $_GET["mode"];
}

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

if (isset($_GET['file']))
{
    $ret_code = isValidFileName(urldecode($_GET['file']), true);
    if($ret_code == 0)
    {
        $req_file = urldecode($_GET['file']);
    }
    else
    {
        if($ret_code == -2)
        {
            $g_message->show("invalid_file_name");
        }
        elseif($ret_code == -3)
        {
            $g_message->show("invalid_file_extension");
        }
    }
}

//Pruefrotine ob Ordner/Datei
function file_or_folder ($act_dir, $file)
{
    if(strlen($file) > 0)
    {
        if(is_file("$act_dir/$file"))
        {
            return false;
        }
        else
        {
            if(is_dir("$act_dir/$file"))
            {
                return true;
            }
            else
            {
                return -1;
            }
        }
    }
};

// rekursive Funktion um ganze Ordner mit Unterordnern zu loeschen
function removeDir ($dir)
{
    $fHandle = opendir($dir);
    if($fHandle > 0)
    {
        while (false !== ($fName = readdir($fHandle)))
        {
            if($fName != "." && $fName != "..")
            {
                if(is_dir("$dir/$fName"))
                {
                    removeDir("$dir/$fName");
                }
                else
                {
                    unlink("$dir/$fName");
                }
            }
        }
        return rmdir($dir);
    };
    return false;
};

// Ordnerpfad zusammensetzen
if(strlen($req_default_folder) > 0)
{
    $act_folder = "$req_default_folder/$act_folder";
}
if(strlen($req_folder) > 0)
{
    $act_folder = "$act_folder/$req_folder";
}

// pruefen, ob Datei oder Ordner uebergeben wurde
$is_folder = file_or_folder($act_folder, $req_file);

$_SESSION['download_request'] = $_REQUEST;

if($req_mode == 1)
{
    // Dateien hochladen
    if (empty($_POST))
    {
        $g_message->show("empty_upload_post",ini_get(post_max_size));
    }
       
    $local_file = $_FILES['userfile']['name'];

    //Dateigroesse ueberpruefen Servereinstellungen
    if ($_FILES['userfile']['error']==1)
    {
        $g_message->show("file_2big_server",ini_get(post_max_size));
    }
    
    //Dateigroesse ueberpruefen Administratoreinstellungen
    if ($_FILES['userfile']['size']>($g_preferences['max_file_upload_size'])*1000)
    {
        $g_message->show("file_2big", $g_preferences['max_file_upload_size']);
    }

    // Datei-Extension ermitteln
    if(strpos($local_file, ".") !== false)
    {
        $file_ext  = substr($local_file, strrpos($local_file, ".")+1);
        $file_name = substr($local_file, 0, strrpos($local_file, "."));
    }
    else
    {
        $file_ext  = "";
        $file_name = $local_file;
    }

    // wenn neuer Name uebergeben wurde, dann diesen nehmen
    if(strlen($_POST['new_name']) > 0)
    {
        $file_name = $_POST['new_name'];
    }

    // Zielpfad mit Dateinamen zusammensetzen
    if(strlen($file_ext) > 0)
    {
        $file_name = "$file_name.$file_ext";
    }

    // pruefen, ob der Dateiname gueltig ist
    $ret_code = isValidFileName($file_name, true);

    if($ret_code != 0)
    {
        if($ret_code == -1)
        {
            $g_message->show("feld", "Datei auswählen");
        }
        elseif($ret_code == -2)
        {
            $g_message->show("invalid_file_name");
        }
        elseif($ret_code == -3)
        {
            $g_message->show("invalid_file_extension");
        }
    }
    
    if (file_exists("$act_folder/$file_name"))
    {
        $g_message->show("file_exists","$file_name");
    }
    
    // Datei hochladen
    if(move_uploaded_file($_FILES['userfile']['tmp_name'], "$act_folder/$file_name"))
    {
        $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php");
        $g_message->show("upload_file",$file_name);
    }
    else
    {
        $g_message->show("file_upload_error");
    }
}
elseif($req_mode == 2)
{
   // Loeschen der Datei/Ordner

    if($is_folder)
    {
        if( removeDir ("$act_folder/$req_file"))
        {
                $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php");
                $g_message->show("delete_folder",$file);
        }
        else
        {
            $g_message->show("delete_error");
        }
    }
    else
    {
        if(unlink("$act_folder/$req_file"))
        {
            $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php");
            $g_message->show("delete_file",$req_file);
        }
        else
        {
            $g_message->show("delete_error");
        }
    }
}
elseif($req_mode == 3)
{
    // Ordner erstellen
    $req_new_folder = null;

    if(strlen($_POST['new_folder']) > 0)
    {
        if(isValidFileName($_POST['new_folder']) == 0)
        {
            $req_new_folder = $_POST['new_folder'];
        }
        else
        {
            if($ret == -1)
            {
                $g_message->show("feld", "Datei auswählen");
            }
            elseif($ret == -2)
            {
                $g_message->show("invalid_file_name");
            }
            elseif($ret == -3)
            {
                $g_message->show("invalid_file_extension");
            }
        }
    }
    else
    {
        $g_message->show("feld", "Name");
    }

    //Test ob der Ordner schon existiert
    $ordnerarray = array();
    $ordnerinhalt = dir($act_folder);
    
    while ($inhalt = $ordnerinhalt->read())
    {
        if ($inhalt != "." AND $inhalt != "..")
        {
            $ordnerarray[] = strtoupper($inhalt);
        }
    }

    if(in_array(strtoupper($req_new_folder), $ordnerarray))
    {
        $g_message->addVariableContent($req_new_folder, 1);
        $g_message->show("folder_exists");
    }
    else
    {
        // Ordner erstellen
        mkdir("$act_folder/$req_new_folder",0777);
        chmod("$act_folder/$req_new_folder", 0777);
        $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php");
        $g_message->show("create_folder", $req_new_folder);
    }
}
elseif($req_mode == 4)
{
    // Datei / Ordner umbenennen
    $req_new_name = null;

    if(strlen($_POST['new_name']) > 0)
    {
        $ret_code = isValidFileName($_POST['new_name']);
        if($ret_code == 0)
        {
            $req_new_name = $_POST['new_name'];
        }
        else
        {
            if($ret_code == -1)
            {
                $g_message->show("feld", "Datei auswählen");
            }
            elseif($ret_code == -2)
            {
                $g_message->show("invalid_file_name");
            }
            elseif($ret_code == -3)
            {
                $g_message->show("invalid_file_extension");
            }
        }
    }
    else
    {
        $g_message->show("feld", "Name");
    }   
   
    // Test ob der Ordner / Datei schon existiert
    $ordnerinhalt = dir($act_folder);
    while ($inhalt = $ordnerinhalt->read())
    {
        if ($inhalt != "." AND $inhalt != "..")
        {
            $ordnerarray[] = $inhalt;
        }
    }

    //Datei oder Ordner?
    if($is_folder)
    {
        //Gibt es den Ordner schon?
        if(in_array($req_new_name, $ordnerarray))
        {
            $g_message->show("folder_exists", $req_new_name);
        }
        else
        {
            //Umbenennen der Datei
            if(rename("$act_folder/$req_file","$act_folder/$req_new_name"))
            {
                $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php");
                $g_message->show("rename_folder",$req_file);
            }
        }
    }
    else
    {
        //Wegstreichen der Endung
        If (strchr(strrev($req_new_name),'.')) 
        {
            $req_new_name = strrev(substr(strchr(strrev($req_new_name),'.'),1));
        };
        
        if(strpos($req_file, ".") !== false)
        {
            $file_ext = substr($req_file, strrpos($req_file, "."));
        }
        else
        {
            $file_ext = "";
        }
        $req_new_name = $req_new_name. $file_ext;

        //Gibt es die Datei schon?
        if(in_array($req_new_name, $ordnerarray))
        {
            $g_message->show("file_exists",$req_file);
        }
        else
        {
            $ret_code = isValidFileName($req_new_name, true);
            if($ret_code == 0)
            {
                //Umbenennen der Datei
                if(rename("$act_folder/$req_file","$act_folder/$req_new_name"))
                {
                    $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php");
                    $g_message->show("rename_file",$req_file);
                }
            }
            else
            {
                if($ret_code  == -2)
                {
                    $g_message->show("invalid_file_name");
                }
                elseif($ret_code  == -3)
                {
                    $g_message->show("invalid_file_extension");
                }
            }
        }
    }
}
elseif($req_mode == 5)
{   
    $_SESSION['navigation']->addUrl($g_current_url);
    $g_message->setForwardYesNo("$g_root_path/adm_program/modules/download/download_function.php?mode=2&amp;folder=$req_folder&amp;file=$req_file&amp;default_folder=$req_default_folder");
    $g_message->show("delete_file_folder",$req_file);
}
?>