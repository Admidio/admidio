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

$postModuleName = admFuncVariableIsValid($_POST, 'men_modul_name',  'string', array('default' => ''));

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

// create menu or update it
if($getMode === 1)
{    
    try
    {
        $menu->setValue('men_group', $_POST['men_group']);
        $menu->setValue('men_modul_name', $postModuleName);
        $menu->setValue('men_url',  $_POST['men_url']);
        $menu->setValue('men_icon', $_POST['men_icon']);
        $menu->setValue('men_translate_name', $_POST['men_translate_name']);
        $menu->setValue('men_translate_desc', $_POST['men_translate_desc']);
        
        // check all values from Checkboxes, because if there is no value set, we need
        // to set it on 0 as default
        if(!isset($_POST['men_need_enable']) || $_POST['men_need_enable'] != 1)
        {
            $menu->setValue('men_need_enable', 0);
        }
        else
        {
            $menu->setValue('men_need_enable', 1);
        }
        
        $getMenId = $menu->getValue('men_id');

        // save Data to Table
        $returnCode = $menu->save();

        if($returnCode < 0)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
        
        // Read current roles rights of the menu
        $displayRight = new RolesRights($gDb, 'men_display_right', $getMenId);
        $rolesDisplayRight = $displayRight->getRolesIds();

        if(in_array('0', $_POST['men_display_right'], true))
        {
            // remove all entries, so it is allowed without login
            $displayRight->removeRoles($rolesDisplayRight);
        }
        else
        {
            // add new or update roles
            $displayRight->addRoles($_POST['men_display_right']);
        }

        $displayIndex = new RolesRights($gDb, 'men_display_index', $getMenId);
        $rolesDisplayIndex = $displayIndex->getRolesIds();

        if(in_array('0', $_POST['men_display_index'], true))
        {
            // remove all entries, so it is allowed without login
            $displayIndex->removeRoles($rolesDisplayIndex);
        }
        else
        {
            // add new or update roles
            $displayIndex->addRoles($_POST['men_display_index']);
        }
        
        if(isset($_POST['men_display_boot']))
        {
            $displayBoot = new RolesRights($gDb, 'men_display_boot', $getMenId);
            $rolesDisplayBoot = $displayBoot->getRolesIds();

            if(in_array('0', $_POST['men_display_boot'], true))
            {
                // remove all entries, so it is allowed without login
                $displayBoot->removeRoles($rolesDisplayIndex);
            }
            else
            {
                // add new or update roles
                $displayBoot->addRoles($_POST['men_display_boot']);
            }
        }

        $gNavigation->deleteLastUrl();
        unset($_SESSION['menu_request']);

        header('Location: '. $gNavigation->getUrl());
        exit();
        
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }
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
