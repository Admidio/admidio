<?php
/******************************************************************************
 * Spalten einer CSV-Datei werden Datenbankfeldern zugeordnet
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

// Uebergabevariablen pruefen
if(isset($_POST["rol_id"]) == false || is_numeric($_POST["rol_id"]) == false)
{
    $g_message->show("invalid");
}

if(isset($_POST["user_import_mode"]) == false || is_numeric($_POST["user_import_mode"]) == false)
{
    $g_message->show("invalid");
}

// nur berechtigte User duerfen User importieren
if(!$g_current_user->editUser())
{
    $g_message->show("norights");
}

if(strlen($_FILES['userfile']['tmp_name']) == 0)
{
    $g_message->show("feld", "Datei");
}
else if($_FILES['userfile']['error'] == 1)
{
    //Dateigroesse ueberpruefen Servereinstellungen
    $g_message->show("file_2big_server", $g_preferences['max_file_upload_size']);
}
else if($_POST['rol_id'] == 0)
{
    $g_message->show("feld", "Rolle");
}

$_SESSION["rol_id"]           = $_POST["rol_id"];
$_SESSION["user_import_mode"] = $_POST["user_import_mode"];
$_SESSION["file_lines"]       = file($_FILES['userfile']['tmp_name']);

// Daten der Datei erst einmal in UTF8 konvertieren, damit es damit spaeter keine Probleme gibt
foreach($_SESSION["file_lines"] as $key => $value)
{
    $_SESSION["file_lines"][$key] = utf8_encode($value);
}

// CSV-Import (im Moment gibt es nur diesen, spaeter muss hier dann unterschieden werden)
$location = "Location: $g_root_path/adm_program/administration/members/import_csv_config.php";
header($location);
exit();

?>