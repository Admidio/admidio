<?php
/******************************************************************************
 * Spalten einer CSV-Datei werden Datenbankfeldern zugeordnet
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

$err_code = "";
$err_text = "";

// Uebergabevariablen pruefen
if(isset($_GET["rol_id"]) == false || is_numeric($_GET["rol_id"]) == false)
{
    $g_message->show("invalid");
}

if(isset($_GET["user_import_mode"]) == false || is_numeric($_GET["user_import_mode"]) == false)
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
    $err_code = "feld";
    $err_text = "Datei";
}
else if($_FILES['userfile']['error'] == 1)
{
    //Dateigroesse ueberpruefen Servereinstellungen
    $err_code = "file_2big_server";
    $err_text = $g_preferences['max_file_upload_size'];
}
else if($_POST['rol_id'] == 0)
{
    $err_code = "feld";
    $err_text = "Rolle";
}

if(strlen($err_code) > 0)
{
    $g_message->show($err_code, $err_text);
}

$_SESSION["rol_id"]           = $_POST["rol_id"];
$_SESSION["user_import_mode"] = $_POST["user_import_mode"];
$_SESSION["file_lines"]       = file($_FILES['userfile']['tmp_name']);

// CSV-Import (im Moment gibt es nur diesen, spaeter muss hier dann unterschieden werden)
$location = "Location: $g_root_path/adm_program/administration/members/import_csv_config.php";
header($location);
exit();

?>