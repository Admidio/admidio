<?php
/**
 ***********************************************************************************************
 * Various functions for categories
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * men_id: Id of the menu that should be edited
 * mode  : 1 - Create or edit menu
 *         2 - Delete menu
 *         3 - Change sequence for parameter men_id
 * sequence: New sequence for the parameter men_id
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getMenId    = admFuncVariableIsValid($_GET, 'men_id',    'int');
$getMode     = admFuncVariableIsValid($_GET, 'mode',      'int',    array('requireValue' => true));
$getSequence = admFuncVariableIsValid($_GET, 'sequence',  'string', array('validValues' => array('UP', 'DOWN')));

// check rights
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// create menu object
$menu = new TableMenu($gDb);

if($getMenId > 0)
{
    $menu->readDataById($getMenId);
}
else
{
    // create a new menu
    $menu->setValue('cat_org_id', $gCurrentOrganization->getValue('org_id'));
    $menu->setValue('cat_type', $getType);
}

// create menu or update it
if($getMode === 1)
{
    $_SESSION['menu_request'] = $_POST;

    // check all values from Checkboxes, because if there is no value set, we need
    // to set it on 0 as default
    $checkboxes = array('men_display_right', 'men_display_index', 'men_display_boot', 'men_need_enable', 'men_need_login', 'men_need_admin');

    foreach($checkboxes as $key => $value)
    {
        if(!isset($_POST[$value]) || $_POST[$value] != 1)
        {
            $_POST[$value] = 0;
        }
    }

    // write POST variables to the object
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'men_') === 0)
        {
            $menu->setValue($key, $value);
        }
    }

    // save Data to Table
    $returnCode = $menu->save();

    if($returnCode < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    $gNavigation->deleteLastUrl();
    unset($_SESSION['menu_request']);

    header('Location: '. $gNavigation->getUrl());
    exit();
}
elseif($getMode === 2)
{
    // delete menu
    try
    {
        if($menu->delete())
        {
            echo 'done';
        }
    }
    catch(AdmException $e)
    {
        $e->showText();
    }
}
elseif($getMode === 3)
{
    // Kategoriereihenfolge aktualisieren
    $menu->moveSequence($getSequence);
    exit();
}
// save view or upload rights for a folder
elseif ($getMode === 4)
{

    // only users with administration rights should
    if(!$gCurrentUser->isAdministrator())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    try
    {

        // Read current roles rights of the folder
        $rightFolderView = new RolesRights($gDb, 'men_display_right', $getMenId);
        $rolesFolderView = $rightFolderView->getRolesIds();

        if(in_array('0', $_POST['men_display_right'], true))
        {
            // set flag public for this folder and all child folders
            $folder->editPublicFlagOnFolder(true);
            // if all users have access then delete all existing roles
            $folder->removeRolesOnFolder('folder_view', $rolesFolderView);
        }
        else
        {
            // get new roles and removed roles
            $addRoles = array_diff($_POST['men_display_right'], $rolesFolderView);
            $removeRoles = array_diff($rolesFolderView, $_POST['men_display_right']);

            $folder->addRolesOnFolder('folder_view', $addRoles);
            $folder->removeRolesOnFolder('folder_view', $removeRoles);
        }

        // save upload right
        $rightFolderUpload = new RolesRights($gDb, 'men_display_index', $getMenId);
        $rolesFolderUpload = $rightFolderUpload->getRolesIds();

        if(in_array('0', $_POST['men_display_right'], true))
        {
            // set flag public for this folder and all child folders
            $folder->editPublicFlagOnFolder(true);
            // if all users have access then delete all existing roles
            $folder->removeRolesOnFolder('folder_view', $rolesFolderView);
        }
        else
        {
            // get new roles and removed roles
            $addRoles = array_diff($_POST['men_display_right'], $rolesFolderView);
            $removeRoles = array_diff($rolesFolderView, $_POST['men_display_right']);

            $folder->addRolesOnFolder('folder_view', $addRoles);
            $folder->removeRolesOnFolder('folder_view', $removeRoles);
        }
        
        // save upload right
        $rightFolderUpload = new RolesRights($gDb, 'men_display_index', $getMenId);
        $rolesFolderUpload = $rightFolderUpload->getRolesIds();

        if(in_array('0', $_POST['men_display_right'], true))
        {
            // set flag public for this folder and all child folders
            $folder->editPublicFlagOnFolder(true);
            // if all users have access then delete all existing roles
            $folder->removeRolesOnFolder('folder_view', $rolesFolderView);
        }
        else
        {
            // get new roles and removed roles
            $addRoles = array_diff($_POST['men_display_right'], $rolesFolderView);
            $removeRoles = array_diff($rolesFolderView, $_POST['men_display_right']);

            $folder->addRolesOnFolder('folder_view', $addRoles);
            $folder->removeRolesOnFolder('folder_view', $removeRoles);
        }

        $folder->save();

        $gMessage->setForwardUrl($g_root_path.'/adm_program/system/back.php');
        $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }
}
