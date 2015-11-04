<?php
/******************************************************************************
 * Various functions for mylist module
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * lst_id : Id of the list configuration that should be edited
 * mode   : 1 - Save list configuration
 *          2 - Save list configuration and show list
 *          3 - Delete list configuration
 * name   : (optional) Name of the list that should be used to save list
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getListId = admFuncVariableIsValid($_GET, 'lst_id', 'numeric');
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('requireValue' => true));
$getName   = admFuncVariableIsValid($_GET, 'name', 'string');

$_SESSION['mylist_request'] = $_POST;

// Mindestens ein Feld sollte zugeordnet sein
if(isset($_POST['column1']) == false || strlen($_POST['column1']) == 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', 'Feld 1'));
}

// Rolle muss beim Anzeigen gefuellt sein
if($getMode == 2
&& (isset($_POST['sel_roles_ids']) == false || $_POST['sel_roles_ids'] == 0 || is_array($_POST['sel_roles_ids']) == false))
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', 'Rolle'));
}

if(isset($_POST['sel_show_members']) == false)
{
    $_POST['sel_show_members'] = 0;
}

// Listenobjekt anlegen
$list = new ListConfiguration($gDb, $getListId);

// pruefen, ob Benutzer die Rechte hat, diese Liste zu bearbeiten
if($getMode != 2)
{
    // globale Listen duerfen nur von Webmastern editiert werden
    if($list->getValue('lst_global') == 1 && $gCurrentUser->isWebmaster() == false)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
    elseif($list->getValue('lst_usr_id') != $gCurrentUser->getValue('usr_id')
    && $list->getValue('lst_global') == 0
    && $list->getValue('lst_id') > 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

// Liste speichern
if ($getMode == 1 || $getMode == 2)
{
    // alle vorhandenen Spalten durchgehen
    for($columnNumber = 1; isset($_POST['column'. $columnNumber]); $columnNumber++)
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
    if($getMode === 1 && $gCurrentUser->isWebmaster() && isset($_POST['cbx_global_configuration']))
    {
        $list->setValue('lst_global', $_POST['cbx_global_configuration']);
    }
    else
    {
        $list->setValue('lst_global', 0);
    }

    $list->save();

    if($getMode == 1)
    {
        // save new id to session so that we can restore the configuration with new list name
        $_SESSION['mylist_request']['sel_select_configuation'] = $list->getValue('lst_id');

        // go back to mylist configuration
        header('Location: '.$g_root_path.'/adm_program/modules/lists/mylist.php?lst_id='. $list->getValue('lst_id'));
        exit();
    }

    // save all roles in a session parameter for later use
    $_SESSION['role_ids'] = $_POST['sel_roles_ids'];

    // weiterleiten zur allgemeinen Listeseite
    header('Location: '.$g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$list->getValue('lst_id').'&mode=html&show_members='. $_POST['sel_show_members']);
    exit();
}
elseif ($getMode == 3)
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
    header('Location: '.$g_root_path.'/adm_program/modules/lists/mylist.php');
    exit();
}
