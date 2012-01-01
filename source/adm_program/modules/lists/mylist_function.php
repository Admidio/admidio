<?php
/******************************************************************************
 * Verschiedene Funktionen fuer die eigene Liste
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * lst_id : ID der Liste, die aktuell bearbeitet werden soll
 * mode   : 1 - Listenkonfiguration speichern
 *          2 - Listenkonfiguration speichern und anzeigen
 *          3 - Listenkonfiguration loeschen
 *          4 - Listenkonfiguration zur Systemkonfiguration machen
 *          5 - Listenkonfiguration zur Standardkonfiguratoin machen
 * name   : (optional) die Liste wird unter diesem Namen gespeichert
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/list_configuration.php');

// Initialize and check the parameters
$getListId = admFuncVariableIsValid($_GET, 'lst_id', 'numeric', 0);
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'string', null, true);
$getName   = admFuncVariableIsValid($_GET, 'name', 'string', '');

// Mindestens ein Feld sollte zugeordnet sein
if(isset($_POST['column1']) == false || strlen($_POST['column1']) == 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', 'Feld 1'));
}

// Rolle muss beim Anzeigen gefuellt sein
if($getMode == 2
&& (isset($_POST['rol_id']) == false || $_POST['rol_id'] == 0 || is_numeric($_POST['rol_id']) == false))
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', 'Rolle'));
}

if(isset($_POST['show_members']) == false)
{
    $_POST['show_members'] = 0;
}

// Ehemalige
if(array_key_exists('former', $_POST))
{
    $member_status = 1;
}
else
{
    $member_status = 0;
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
if ($getMode == 1 || $getMode == 2 || $getMode == 4)
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
    
    if(strlen($getName) > 0)
    {
        $list->setValue('lst_name', $getName);
    }
    
    if($getMode == 4 && $gCurrentUser->isWebmaster())
    {
        $list->setValue('lst_global', 1);
    }
    else
    {
        $list->setValue('lst_global', 0);
    }
    
    $list->save();
    
    if($getMode == 1 || $getMode == 4)
    {
        // wieder zur eigenen Liste zurueck
        header('Location: '.$g_root_path.'/adm_program/modules/lists/mylist.php?lst_id='. $list->getValue('lst_id'). '&rol_id='. $_POST['rol_id']. '&show_members='.$_POST['show_members']);
        exit();
    }
    
    // anzuzeigende Rollen in Array schreiben und in Session merken
    $role_ids = array();
    $role_ids[] = $_POST['rol_id'];
    $_SESSION['role_ids'] = $role_ids;

    // weiterleiten zur allgemeinen Listeseite
    header('Location: '.$g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$list->getValue('lst_id').'&mode=html&show_members='. $_POST['show_members']);
    exit();
}
elseif ($getMode == 3)
{
    // Listenkonfiguration loeschen
    $list->delete();

    // weiterleiten zur Listenkonfiguration
    header('Location: '.$g_root_path.'/adm_program/modules/lists/mylist.php?rol_id='. $_POST['rol_id']. '&show_members='.$_POST['show_members']);
    exit();
}
elseif ($getMode == 5)
{
    // Listenkonfiguration zur Standardkonfiguration machen
    $list->setDefault();

    // wieder zur eigenen Liste zurueck
    header('Location: '.$g_root_path.'/adm_program/modules/lists/mylist.php?lst_id='. $list->getValue('lst_id'). '&rol_id='. $_POST['rol_id']. '&show_members='.$_POST['show_members']);
    exit();
}

?>