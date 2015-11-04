<?php
/******************************************************************************
 * Show an image of a module from adm_my_files folder
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * module : Name of module (Foldername) in adm_my_files where the image lies
 * file   : Name of image file that should be shown (without path)
 *
 *****************************************************************************/

require('common.php');

// Initialize and check the parameters
$getModule = admFuncVariableIsValid($_GET, 'module', 'file', array('requireValue' => true, 'directOutput' => true));
$getFile   = admFuncVariableIsValid($_GET, 'file',   'file', array('requireValue' => true, 'directOutput' => true));

// Initialize locale parameters
$imageServerPath = SERVER_PATH.'/adm_my_files/'.$getModule.'/images/'.$getFile;

// check if image exists
if(file_exists($imageServerPath))
{
    $image = new Image($imageServerPath);
    header('Content-Type: '.$image->getMimeType());
    $image->copyToBrowser();
    $image->delete();
}
