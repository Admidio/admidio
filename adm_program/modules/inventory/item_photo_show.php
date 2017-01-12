<?php
/**
 ***********************************************************************************************
 * Show current profile photo or uploaded session photo
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * inv_id    : id of inventory whose photo should be changed
 * new_photo : false (Default) show current stored inventory photo
 *             true  show uploaded photo of current session
 ***********************************************************************************************
 */
require('../../system/common.php');
require('../../system/login_valid.php');

// Initialize and check the parameters
$getItemId   = admFuncVariableIsValid($_GET, 'inv_id',    'int', array('requireValue' => true));
$getNewPhoto = admFuncVariableIsValid($_GET, 'new_photo', 'bool');

// lokale Variablen der Uebergabevariablen initialisieren
$image   = null;
$picpath = THEME_ADMIDIO_PATH. '/images/no_profile_pic.png';

// only users with the right to edit inventory could use this script
if (!$gCurrentUser->editInventory())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// read inventory data and show error if inventory doesn't exists
$gInventoryFields = new InventoryFields($gDb, $gCurrentOrganization->getValue('org_id'));
$inventory = new Inventory($gDb, $gInventoryFields, $getItemId);

if((int) $inventory->getValue('inv_id') === 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

// Foto aus adm_my_files
if($gPreferences['profile_photo_storage'] == 1 && !$getNewPhoto)
{
    $file = ADMIDIO_PATH . FOLDER_DATA . '/item_photos/' . $getItemId . '.jpg';
    if(is_file($file))
    {
        $picpath = $file;
    }
    $image = new Image($picpath);
}
// Foto aus der Datenbank
elseif($gPreferences['profile_photo_storage'] == 0 && !$getNewPhoto)
{
    if(strlen($inventory->getValue('inv_photo')) != null)
    {
        $image = new Image();
        $image->setImageFromData($inventory->getValue('inv_photo'));
    }
    else
    {
        $image = new Image($picpath);
    }
}
// neues Foto, Ordnerspeicherung
elseif($gPreferences['profile_photo_storage'] == 1 && $getNewPhoto)
{
    $picpath = ADMIDIO_PATH . FOLDER_DATA . '/item_photos/'.$getItemId.'_new.jpg';
    $image = new Image($picpath);
}
// neues Foto, Datenbankspeicherung
elseif($gPreferences['profile_photo_storage'] == 0 && $getNewPhoto)
{
    $image = new Image();
    $image->setImageFromData($gCurrentSession->getValue('ses_binary'));
}

header('Content-Type: '. $image->getMimeType());
$image->copyToBrowser();
$image->delete();
