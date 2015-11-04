<?php
/******************************************************************************
 * Show current profile photo or uploaded session photo
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * inv_id    : id of inventory whose photo should be changed
 * new_photo : 0 (Default) show current stored inventory photo
 *             1 show uploaded photo of current session
 *
 *****************************************************************************/
require('../../system/common.php');
require('../../system/login_valid.php');

// Initialize and check the parameters
$getItemId   = admFuncVariableIsValid($_GET, 'inv_id', 'numeric', array('requireValue' => true));
$getNewPhoto = admFuncVariableIsValid($_GET, 'new_photo', 'boolean');

// lokale Variablen der Uebergabevariablen initialisieren
$image         = null;
$picpath       = THEME_SERVER_PATH. '/images/no_profile_pic.png';

// only users with the right to edit inventory could use this script
if ($gCurrentUser->editInventory() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// read inventory data and show error if inventory doesn't exists
$gInventoryFields = new InventoryFields($gDb, $gCurrentOrganization->getValue('org_id'));
$inventory = new Inventory($gDb, $gInventoryFields, $getItemId);

if($inventory->getValue('inv_id') == 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

//Foto aus adm_my_files
if($gPreferences['profile_photo_storage'] == 1 && $getNewPhoto == 0)
{
    if(file_exists(SERVER_PATH. '/adm_my_files/item_photos/'.$getItemId.'.jpg'))
    {
        $picpath = SERVER_PATH. '/adm_my_files/item_photos/'.$getItemId.'.jpg';
    }
    $image = new Image($picpath);
}
//Foto aus der Datenbank
elseif($gPreferences['profile_photo_storage'] == 0 && $getNewPhoto == 0)
{
    if(strlen($inventory->getValue('inv_photo')) != NULL)
    {
        $image = new Image();
        $image->setImageFromData($inventory->getValue('inv_photo'));
    }
    else
    {
        $image = new Image($picpath);
    }
}
//neues Foto, Ordnerspeicherung
elseif($gPreferences['profile_photo_storage'] == 1 && $getNewPhoto == 1)
{
    $picpath = SERVER_PATH. '/adm_my_files/item_photos/'.$getItemId.'_new.jpg';
    $image = new Image($picpath);
}
//neues Foto, Datenbankspeicherung
elseif($gPreferences['profile_photo_storage'] == 0 && $getNewPhoto == 1)
{
    $image = new Image();
    $image->setImageFromData($gCurrentSession->getValue('ses_binary'));
}

header('Content-Type: '. $image->getMimeType());
$image->copyToBrowser();
$image->delete();
