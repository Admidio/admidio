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

// nur Webmaster duerfen organisationsspezifischen Profilfelder verwalten
if(!$g_current_user->isWebmaster())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 3)
{
    $g_message->show("invalid");
}

if(isset($_GET["usf_id"]) && is_numeric($_GET["usf_id"]) == false)
{
    $g_message->show("invalid");
}

$err_code = "";
$err_text = "";

if($_GET['mode'] == 1)
{
   // Feld anlegen oder updaten

    $_SESSION['fields_request'] = $_REQUEST;
    
    if(strlen(trim($_POST['name'])) > 0
    && strlen(trim($_POST['type'])) > 0)
    {
        if(!($_GET['usf_id'] > 0))
        {
            // Schauen, ob das Feld bereits existiert
            $sql    = "SELECT COUNT(*) FROM ". TBL_USER_FIELDS. "
                        WHERE usf_org_id =    $g_current_organization->id
                          AND usf_name   LIKE {0}";
            $sql    = prepareSQL($sql, array($_POST['name']));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
            $row = mysql_fetch_array($result);

            if($row[0] > 0)
            {
                $g_message->show("field_exist");
            }      
        }

        if(array_key_exists("hidden", $_POST))
        {
            $hidden = 1;
        }
        else
        {
            $hidden = 0;
        }

        if($_GET['usf_id'] > 0)
        {
            $sql = "UPDATE ". TBL_USER_FIELDS. "
                       SET usf_name        = {0}
                         , usf_description = {1}
                         , usf_type        = {2}
                         , usf_hidden      = $hidden
                     WHERE usf_id = {3}";
        }
        else
        {
            // Feld in Datenbank hinzufuegen
            $sql    = "INSERT INTO ". TBL_USER_FIELDS. " (usf_org_id, usf_name, usf_description,
                                                          usf_type, usf_hidden)
                            VALUES ($g_current_organization->id, {0}, {1}, {2}, $hidden) ";
        }
        $sql    = prepareSQL($sql, array(trim($_POST['name']), trim($_POST['description']),
                                         trim($_POST['type']), $_GET['usf_id']));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);
        
        $_SESSION['navigation']->deleteLastUrl();
        unset($_SESSION['fields_request']);
    }
    else
    {
        // es sind nicht alle Felder gefuellt
        if(strlen(trim($_POST['name'])) == 0)
        {
            $err_text = "Name";
        }
        else
        {
            $err_text = "Datentyp";
        }
        $err_code = "feld";
    }

    if(strlen($err_code) > 0)
    {
        $g_message->show($err_code, $err_text);
    }

    $err_code = "save";
}
elseif($_GET['mode'] == 2)
{
    // Feld loeschen

    // erst die Userdaten zum Feld loeschen
    $sql    = "DELETE FROM ". TBL_USER_DATA. "
                WHERE usd_usf_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['usf_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    $sql    = "DELETE FROM ". TBL_USER_FIELDS. "
                WHERE usf_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['usf_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    $err_code = "delete";
}
elseif($_GET["mode"] == 3)
{
    // Frage, ob Kategorie geloescht werden soll
    $sql = "SELECT usf_name FROM ". TBL_USER_FIELDS. "
             WHERE usf_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['usf_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);
    $row = mysql_fetch_array($result);
    
    $g_message->setForwardYesNo("$g_root_path/adm_program/administration/organization/fields_function.php?usf_id=". $_GET['usf_id']. "&mode=2");
    $g_message->show("delete_field", utf8_encode($row[0]), "Löschen");
}
         
// zu den Organisationseinstellungen zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($err_code);
?>