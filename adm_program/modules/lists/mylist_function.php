<?php
/******************************************************************************
 * Verschiedene Funktionen fuer die eigene Liste
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * lst_id : ID der Liste, die aktuell bearbeitet werden soll
 * mode   : 1 - Listenkonfiguration speichern
 *          2 - Listenkonfiguration speichern und anzeigen
 *          3 - Listenkonfiguration loeschen
 * name   : (optional) die Liste wird unter diesem Namen gespeichert
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getListId = admFuncVariableIsValid($_GET, 'lst_id', 'numeric');
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'string', array('requireValue' => true));
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
    $columnNumber = 0;
    for($number = 1; isset($_POST['column'. $number]); $number++)
    {
        if(strlen($_POST['column'. $number]) > 0)
        {
            $columnNumber++;
            $list->addColumn($columnNumber, $_POST['column'. $number], $_POST['sort'. $number], $_POST['condition'. $number]);
        }
        else
        {
            $list->deleteColumn($number, true);
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
        // wieder zur eigenen Liste zurueck
        header('Location: '.$g_root_path.'/adm_program/modules/lists/mylist.php?lst_id='. $list->getValue('lst_id'). '&show_members='.$_POST['sel_show_members']);
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
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }

    // go back to list configuration
    header('Location: '.$g_root_path.'/adm_program/modules/lists/mylist.php?rol_id='. $_POST['rol_id']. '&show_members='.$_POST['sel_show_members']);
    exit();
}

?>
