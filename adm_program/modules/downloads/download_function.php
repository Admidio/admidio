<?php
/******************************************************************************
 * Downloadfunktionen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode   :  1 - Datei hochladen
 *           2 - Datei / Ordner loeschen
 *           3 - Ordner erstellen
 *           4 - Datei / Ordner umbenennen
 *           5 - Datei /Ordner loeschen abfrage
 * folder_id :  OrdnerId in der DB
 * file_id   :  FileId in der DB
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/folder_class.php");
require("../../system/file_class.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!$g_current_user->editDownloadRight())
{
    $g_message->show("norights");
}

//testen ob Schreibrechte fuer adm_my_files bestehen
if (is_writeable(SERVER_PATH. "/adm_my_files"))
{
    if (file_exists(SERVER_PATH. "/adm_my_files/download") == false)
    {
        // Ordner fuer die Downloads existiert noch nicht -> erst anlegen
        $b_return = @mkdir(SERVER_PATH. "/adm_my_files/download", 0777);
        if ($b_return)
        {
            $b_return = @chmod(SERVER_PATH. "/adm_my_files/download", 0777);
        }
        if ($b_return == false)
        {
            // der entsprechende Ordner konnte nicht angelegt werden
            $g_message->addVariableContent("adm_my_files/download", 1);
            $g_message->addVariableContent($g_preferences['email_administrator'], 2 ,false);
            $g_message->setForwardUrl("$g_root_path/adm_program/modules/downloads/download.php");
            $g_message->show("write_access");
        }
    }
}
else
{
    // der entsprechende Ordner konnte nicht angelegt werden
    $g_message->addVariableContent("adm_my_files", 1);
    $g_message->addVariableContent($g_preferences['email_administrator'], 2 ,false);
    $g_message->setForwardUrl("$g_root_path/adm_program/modules/downloads/download.php");
    $g_message->show("write_access");
}


// Uebergabevariablen pruefen
if (isset($_GET["mode"]))
{
    if (is_numeric($_GET["mode"]) == false)
    {
        $g_message->show("invalid");
    }

    $req_mode = $_GET["mode"];

}
else
{
    //ohne mode geht es nicht weiter
    $g_message->show("invalid");
}

if (isset($_GET['folder_id']))
{
    if (is_numeric($_GET["mode"]) == false)
    {
        $g_message->show("invalid");
    }

    $folder_id = $_GET['folder_id'];
}
else
{
    $folder_id = 0;
}

if (isset($_GET['file_id']))
{
    if (is_numeric($_GET["mode"]) == false)
    {
        $g_message->show("invalid");
    }

    $file_id = $_GET['file_id'];
}
else
{
    $file_id = 0;
}

$_SESSION['download_request'] = $_REQUEST;


// Dateien hochladen
if ($req_mode == 1)
{
    if ($folder_id == 0) {
        //FolderId ist zum hochladen erforderlich
        $g_message->show("invalid");
    }

    //Informationen zum Zielordner aus der DB holen
    $targetFolder = new Folder($g_db);
    $targetFolder->getFolderForDownload($folder_id);

    if (empty($_POST))
    {
        $g_message->show("empty_upload_post",ini_get('upload_max_filesize'));
    }

    $local_file = $_FILES['userfile']['name'];

    //Dateigroesse ueberpruefen Servereinstellungen
    if ($_FILES['userfile']['error']==1)
    {
        $g_message->show("file_2big_server",ini_get('upload_max_filesize'));
    }

    //Dateigroesse ueberpruefen Administratoreinstellungen
    if ($_FILES['userfile']['size']>($g_preferences['max_file_upload_size'])*1024)
    {
        $g_message->show("file_2big", $g_preferences['max_file_upload_size']);
    }

    // Dateinamen ermitteln
    $file_name = $local_file;

    // wenn neuer Name uebergeben wurde, dann diesen nehmen
    if(strlen($_POST['new_name']) > 0)
    {
        $file_name = $_POST['new_name'];
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

    if (file_exists($targetFolder->getCompletePathOfFolder(). "/$file_name"))
    {
        $g_message->show("file_exists","$file_name");
    }

    // Datei hochladen
    if(move_uploaded_file($_FILES['userfile']['tmp_name'], $targetFolder->getCompletePathOfFolder(). "/$file_name"))
    {
        //Neue Datei noch in der DB eintragen
        $newFile = new File($g_db);
        $newFile->setValue('fil_fol_id',$targetFolder->getValue('fol_id'));
        $newFile->setValue('fil_name',$file_name);
        $newFile->setValue('fil_locked',$targetFolder->getValue('fol_locked'));
        $newFile->setValue('fil_counter','0');
        $newFile->save();

        $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php");
        $g_message->show("upload_file",$file_name);
    }
    else
    {
        $g_message->show("file_upload_error");
    }
}


//Datei loeschen
elseif ($req_mode == 2)
{

    if ( (!$file_id && !$folder_id) OR ($file_id && $folder_id) )
    {
        //Es muss entweder eine FileID ODER eine FolderId uebergeben werden
        //beides ist auch nicht erlaubt
        $g_message->show("invalid");
    }

    if($file_id > 0)
    {
        $file = new File($g_db);
        $file->getFileForDownload($file_id);

        //Pruefen ob Datensatz gefunden
        if ($file->getValue('fil_id')) {
            $name = $file->getValue('fil_name');
        }
        else {
            $g_message->show("invalid");
        }


        if ($file->delete())
        {
                $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php");
                $g_message->show("delete_file",$name);
        }
        else
        {
            $g_message->show("delete_error");
        }
    }
    else if ($folder_id > 0)
    {
        $folder = new Folder($g_db);
        $folder->getFolderForDownload($folder_id);

        //Pruefen ob Datensatz gefunden
        if ($folder->getValue('fol_id')) {
            $name = $folder->getValue('fol_name');
        }
        else {
            $g_message->show("invalid");
        }


        if ($folder->delete())
        {
                $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php");
                $g_message->show("delete_folder",$name);
        }
        else
        {
            $g_message->show("delete_error");
        }

    }
}


// Ordner erstellen
elseif ($req_mode == 3)
{

    if ($folder_id == 0) {
        //FolderId ist Anlegen eines Unterordners erforderlich
        $g_message->show("invalid");
    }

    //Informationen zum Zielordner aus der DB holen
    $targetFolder = new Folder($g_db);
    $targetFolder->getFolderForDownload($folder_id);

    $newFolderName = null;

    if (strlen($_POST['new_folder']) > 0)
    {
        $ret_code = isValidFileName($_POST['new_folder']);

        if ($ret_code == 0)
        {
            $newFolderName = $_POST['new_folder'];
        }
        else
        {
            if ($ret_code == -1)
            {
                $g_message->show("feld", "Name");
            }
            elseif ($ret_code == -2)
            {
                $g_message->show("invalid_folder_name");
            }
        }
    }
    else
    {
        $g_message->show("feld", "Name");
    }

    //Test ob der Ordner schon existiert im Filesystem
    if (file_exists($targetFolder->getCompletePathOfFolder(). "/$newFolderName")) {
        $g_message->show("folder_exists");
    }
    else
    {
        // Ordner erstellen
        $b_return = @mkdir($targetFolder->getCompletePathOfFolder(). "/$newFolderName", 0777);
        if($b_return)
        {
            $b_return = @chmod($targetFolder->getCompletePathOfFolder(). "/$newFolderName", 0777);

            //Jetzt noch den Ordner der DB hinzufuegen...
            $newFolder = new Folder($g_db);

            $newFolder->setValue('fol_fol_id_parent', $targetFolder->getValue('fol_id'));
            $newFolder->setValue('fol_type', 'DOWNLOAD');
            $newFolder->setValue('fol_name', $newFolderName);
            $newFolder->setValue('fol_path', $targetFolder->getValue('fol_path'). "/".$targetFolder->getValue('fol_name'));
            $newFolder->setValue('fol_locked', $targetFolder->getValue('fol_locked'));
            $newFolder->setValue('fol_public', $targetFolder->getValue('fol_public'));
            $newFolder->save();

            //ToDo: Ordnerberechtigungen des ParentOrdners uebernehmen

        }
        if($b_return == false)
        {
            // der entsprechende Ordner konnte nicht angelegt werden
            $g_message->addVariableContent($targetFolder->getValue('fol_path'). "/". $targetFolder->getValue('fol_name'). "/$newFolderName", 1);
            $g_message->addVariableContent($g_preferences['email_administrator'], 2 ,false);
            $g_message->setForwardUrl("$g_root_path/adm_program/modules/downloads/download.php");
            $g_message->show("write_access");
        }

        $g_message->setForwardUrl("$g_root_path/adm_program/system/back.php");
        $g_message->show("create_folder", $newFolderName);
    }
}


elseif ($req_mode == 4)
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


elseif ($req_mode == 5)
{
    if ( (!$file_id && !$folder_id) OR ($file_id && $folder_id) )
    {
        //Es muss entweder eine FileID ODER eine FolderId uebergeben werden
        //beides ist auch nicht erlaubt
        $g_message->show("invalid");
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


    $_SESSION['navigation']->addUrl(CURRENT_URL);
    $g_message->setForwardYesNo("$g_root_path/adm_program/modules/downloads/download_function.php?mode=2&amp;folder_id=$folder_id&amp;file_id=$file_id");
    $g_message->show("delete_file_folder",$originalName);
}
?>