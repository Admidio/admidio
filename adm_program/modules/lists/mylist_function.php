<?php
/**
 ***********************************************************************************************
 * Various functions for mylist module
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * lst_id : Id of the list configuration that should be edited
 * mode   : 1 - Save list configuration
 *          2 - Save list configuration and show list
 *          3 - Delete list configuration
 * name   : (optional) Name of the list that should be used to save list
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getListId = admFuncVariableIsValid($_GET, 'lst_id', 'int');
$getMode   = admFuncVariableIsValid($_GET, 'mode',   'int', array('requireValue' => true));
$getName   = admFuncVariableIsValid($_GET, 'name',   'string');

$_SESSION['mylist_request'] = $_POST;

// Mindestens ein Feld sollte zugeordnet sein
if(!isset($_POST['column1']) || strlen($_POST['column1']) === 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', '1. '.$gL10n->get('LST_COLUMN')));
    // => EXIT
}

// Rolle muss beim Anzeigen gefuellt sein
if($getMode === 2
&& (!isset($_POST['sel_roles_ids']) || (int) $_POST['sel_roles_ids'] === 0 || !is_array($_POST['sel_roles_ids'])))
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_ROLE')));
    // => EXIT
}

if(!isset($_POST['sel_show_members']))
{
    $_POST['sel_show_members'] = 0;
}

if(!isset($_POST['sel_relationtype_ids']))
{
    $_POST['sel_relationtype_ids'] = array();
}

// Listenobjekt anlegen
$list = new ListConfiguration($gDb, $getListId);

// pruefen, ob Benutzer die Rechte hat, diese Liste zu bearbeiten
if($getMode !== 2)
{
    // global lists can only be edited by administrator
    if($list->getValue('lst_global') == 1 && !$gCurrentUser->isAdministrator())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
    elseif((int) $list->getValue('lst_usr_id') !== (int) $gCurrentUser->getValue('usr_id')
    && $list->getValue('lst_global') == 0 && $list->getValue('lst_id') > 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

// Liste speichern
if ($getMode === 1 || $getMode === 2)
{
    $globalConfiguration = admFuncVariableIsValid($_POST, 'cbx_global_configuration', 'bool', array('defaultValue' => false));

    // alle vorhandenen Spalten durchgehen
    for($columnNumber = 1; isset($_POST['column'. $columnNumber]); ++$columnNumber)
    {
        if(strlen($_POST['column'. $columnNumber]) > 0)
        {
            $list->addColumn($columnNumber, $_POST['column'. $columnNumber], $_POST['sort'. $columnNumber], $_POST['condition'. $columnNumber]);
        }
        else
        {
            $list->deleteColumn($columnNumber, true);
        }
    }

    if($getName !== '')
    {
        $list->setValue('lst_name', $getName);
    }

    // set list global only in save mode
    if($getMode === 1 && $gCurrentUser->isAdministrator())
    {
        $list->setValue('lst_global', $globalConfiguration);
    }
    else
    {
        $list->setValue('lst_global', 0);
    }

    $list->save();

    if($getMode === 1)
    {
        // save new id to session so that we can restore the configuration with new list name
        $_SESSION['mylist_request']['sel_select_configuation'] = $list->getValue('lst_id');

        // go back to mylist configuration
        admRedirect(ADMIDIO_URL . FOLDER_MODULES.'/lists/mylist.php?lst_id=' . $list->getValue('lst_id'));
        // => EXIT
    }

    // weiterleiten zur allgemeinen Listeseite
    admRedirect(
        ADMIDIO_URL . FOLDER_MODULES.'/lists/lists_show.php?lst_id=' . $list->getValue('lst_id') .
        '&mode=html&show_members=' . $_POST['sel_show_members'] . '&rol_ids=' . implode(',', array_map('intval', $_POST['sel_roles_ids'])) .
        '&urt_ids=' . implode(',', $_POST['sel_relationtype_ids'])
    );
    // => EXIT
}
elseif ($getMode === 3)
{
    try
    {
        // delete list configuration
        $list->delete();
        unset($_SESSION['mylist_request']);
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }

    // go back to list configuration
    admRedirect(ADMIDIO_URL . FOLDER_MODULES.'/lists/mylist.php');
    // => EXIT
}
