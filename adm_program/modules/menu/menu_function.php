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

$postModuleName = admFuncVariableIsValid($_POST, 'men_name_intern',  'string', array('default' => ''));

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
    $_SESSION['menu_request'] = $_POST;

    $postTranslateDesc = admFuncVariableIsValid($_POST, 'men_description',  'string', array('default' => ''));
    $postTranslateName = admFuncVariableIsValid($_POST, 'men_name',  'string', array('default' => ''));
    $postIcon = admFuncVariableIsValid($_POST, 'men_icon',  'string', array('default' => ''));

    // Check if mandatory fields are filled
    // (bei Systemfeldern duerfen diese Felder nicht veraendert werden)
    if($postTranslateName === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }

    if($_POST['men_url'] === '')
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('ORG_URL'))));
        // => EXIT
    }

    try
    {
        if($postIcon !== '')
        {
            // get the name of the icon to save it.
            $arrayIcons = admFuncGetDirectoryEntries(THEME_ADMIDIO_PATH . '/icons');
            $menu->setValue('men_icon', $arrayIcons[$postIcon]);
        }

        $menu->setValue('men_men_id_parent', $_POST['men_men_id_parent']);
        $menu->setValue('men_name', $postTranslateName);
        $menu->setValue('men_description', $postTranslateDesc);

        if(!$menu->getValue('men_standart'))
        {
            $menu->setValue('men_url',  $_POST['men_url']);
            $menu->setValue('men_com_id',  $_POST['men_com_id']);
        }

        $getMenId = $menu->getValue('men_id');

        // save Data to Table
        $returnCode = $menu->save();

        if($returnCode < 0)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }

        // Read current roles of the menu
        $displayMenu = new RolesRights($gDb, 'menu_view', $getMenId);
        $rolesDisplayRight = $displayMenu->getRolesIds();

        if(!isset($_POST['menu_view']) || !is_array($_POST['menu_view']))
        {
            // remove all entries, so it is allowed without login
            $displayMenu->removeRoles($rolesDisplayRight);
        }
        else
        {
            // add new or update roles
            $displayMenu->addRoles($_POST['menu_view']);
        }

        if($gNavigation->count() > 1)
        {
            $gNavigation->deleteLastUrl();
        }
        else
        {
            $gNavigation->addUrl($gHomepage, 'Home');
        }

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
