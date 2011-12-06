<?php
/******************************************************************************
 * Ein Bild aus adm_my_files anzeigen 
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * module : Name des Moduls (Unterverzeichnisses) in dem das Bild in adm_my_files liegt
 * file   : Name der Bilddatei die angezeigt werden soll (ohne Pfadangaben)
 *
 *****************************************************************************/
require('common.php');
require('classes/image.php');

// Initialize and check the parameters
$getModule = admFuncVariableIsValid($_GET, 'module', 'file', null, true, null, true);
$getFile   = admFuncVariableIsValid($_GET, 'file', 'file', null, true, null, true);

// lokale Variablen initialisieren
$imageServerPath = SERVER_PATH. '/adm_my_files/'.$getModule.'/images/'.$getFile;

// falls das Bild existiert, dann ausgeben
if(file_exists($imageServerPath))
{
    $image = new Image($imageServerPath);
    header('Content-Type: '. $image->getMimeType());
    $image->copyToBrowser();
    $image->delete();
}

?>
