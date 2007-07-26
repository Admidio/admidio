<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Profilfelder
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * usf_id: ID des Feldes
 * mode:   1 - Feld anlegen oder updaten
 *         2 - Feld loeschen
 *         3 - Frage, ob Feld geloescht werden soll
 *         4 - Reihenfolge fuer die uebergebene usf_id anpassen
 * sequence: neue Reihenfolge fuer die uebergebene usf_id
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/user_field_class.php");

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$g_current_user->editUser())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 5)
{
    $g_message->show("invalid");
}

if(isset($_GET['usf_id']))
{
    if(is_numeric($_GET['usf_id']) == false)
    {
        $g_message->show("invalid");
    }
}

if(isset($_GET['sequence']))
{
    if(is_numeric($_GET['sequence']) == false)
    {
        $g_message->show("invalid");
    }
}

// UserField-objekt anlegen
$user_field = new UserField($g_db);

if($_GET['usf_id'] > 0)
{
    $user_field->getUserField($_GET['usf_id']);
    
    // Pruefung, ob das Feld zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
    if($user_field->getValue("cat_org_id") >  0
    && $user_field->getValue("cat_org_id") != $g_current_organization->getValue("org_id"))
    {
        $g_message->show("norights");
    }
}

$err_code = "";

if($_GET['mode'] == 1)
{
   // Feld anlegen oder updaten

    $_SESSION['fields_request'] = $_REQUEST;
    
    // pruefen, ob Pflichtfelder gefuellt sind
    // (bei Systemfeldern sind diese disabled und werden nicht per POST uebertragen
    if(isset($_POST['usf_name']) && strlen($_POST['usf_name']) == 0)
    {
        $g_message->show("feld", "Name");
    }    

    if(isset($_POST['usf_name']) && strlen($_POST['usf_type']) == 0)
    {
        $g_message->show("Datentyp", "Name");
    }    

    if(isset($_POST['usf_name']) && $_POST['usf_cat_id'] == 0)
    {
        $g_message->show("Kategorie", "Name");
    }    
    
    if($_GET['usf_id'] == 0)
    {
        // Schauen, ob das Feld bereits existiert
        $sql    = "SELECT COUNT(*) as count 
                     FROM ". TBL_USER_FIELDS. "
                     JOIN ". TBL_CATEGORIES. "
                       ON usf_cat_id = cat_id
                      AND (  cat_org_id = ". $g_current_organization->getValue("org_id"). "
                          OR cat_org_id IS NULL )
                    WHERE usf_name LIKE '". $_POST['usf_name']. "'";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);
        $row = mysql_fetch_array($result);

        if($row['count'] > 0)
        {
            $g_message->show("field_exist");
        }      
    }

    // Eingabe verdrehen, da der Feldname anders als im Dialog ist
    if(isset($_POST['usf_hidden']))
    {
        $_POST['usf_hidden'] = 0;
    }
    else
    {
        $_POST['usf_hidden'] = 1;
    }
    if(isset($_POST['usf_disabled']) == false)
    {
        $_POST['usf_disabled'] = 0;
    }
    if(isset($_POST['usf_mandatory']) == false)
    {
        $_POST['usf_mandatory'] = 0;
    }

    // POST Variablen in das UserField-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, "usf_") === 0)
        {
            $user_field->setValue($key, $value);
        }
    }
    
    // Daten in Datenbank schreiben
    $return_code = $user_field->save();

    if($return_code < 0)
    {
        $g_message->show("norights");
    }    

    $_SESSION['navigation']->deleteLastUrl();
    unset($_SESSION['fields_request']);
    $g_current_session->renewUserObject();

    $err_code = "save";
}
elseif($_GET['mode'] == 2 || $_GET["mode"] == 3)
{
    if($user_field->getValue("usf_system") == 1)
    {
        // Systemfelder duerfen nicht geloescht werden
        $g_message->show("invalid");
    }
    
    if($_GET['mode'] == 2)
    {
        // Feld loeschen
        $user_field->delete();
        $g_current_session->renewUserObject();

        $err_code = "delete";
    }
    elseif($_GET["mode"] == 3)
    {
        // Frage, ob Kategorie geloescht werden soll

        $g_message->setForwardYesNo("$g_root_path/adm_program/administration/organization/fields_function.php?usf_id=". $_GET['usf_id']. "&mode=2");
        $g_message->show("delete_field", utf8_encode($user_field->getValue("usf_name")), "Löschen");
    }
}
elseif($_GET['mode'] == 4)
{
    // Feldreihenfolge aktualisieren
    $sequence_old = $user_field->getValue("usf_sequence");
    
    if($sequence_old != $_GET['sequence'])
    {
        $user_field->setValue("usf_sequence", $_GET['sequence']);
        $user_field->save();
        // Alle Userobjekte der Benutzer neu einlesen
        $g_current_session->renewUserObject();
    }
    exit();
}
         
// zu den Organisationseinstellungen zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($err_code);
?>