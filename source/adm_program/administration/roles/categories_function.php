<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Kategorien
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * cat_id: ID der Rollen-Kategorien
 * type :  Typ der Kategorie, die angelegt werden sollen
 *         ROL = Rollenkategorien
 *         LNK = Linkkategorien
 * mode:   1 - Kategorie anlegen oder updaten
 *         2 - Kategorie loeschen
 *         3 - Frage, ob Kategorie geloescht werden soll
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

// nur Moderatoren duerfen Kategorien erfassen & verwalten
if(!isModerator())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 3)
{
    $g_message->show("invalid");
}

if(isset($_GET["cat_id"]) && is_numeric($_GET["cat_id"]) == false)
{
    $g_message->show("invalid");
}

if(isset($_GET["cat_id"]) == false)
{
    $_GET["cat_id"] = 0;
}

if($_GET["cat_id"] == 0)
{
    if(isset($_GET["type"]))
    {
        if($_GET["type"] != "ROL" && $_GET["type"] != "LNK")
        {
            $g_message->show("invalid");
        }
    }
    else
    {
        $g_message->show("invalid");
    }  
}

$err_code = "";
$err_text = "";

if($_GET['mode'] == 1)
{
    // Feld anlegen oder updaten

    $_SESSION['categories_request'] = $_REQUEST;
    $category_name = strStripTags($_POST['name']);

    if(strlen($category_name) > 0)
    {
        if($_GET['cat_id'] == 0)
        {
            // Schauen, ob die Kategorie bereits existiert
            $sql    = "SELECT COUNT(*) FROM ". TBL_CATEGORIES. "
                        WHERE cat_org_id = $g_current_organization->id
                          AND cat_type   = {0}
                          AND cat_name   LIKE {1} ";
            $sql    = prepareSQL($sql, array($_GET['type'], $category_name));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            $row = mysql_fetch_array($result);

            if($row[0] > 0)
            {
                $g_message->show("category_exist");
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

        if($_GET['cat_id'] > 0)
        {
            $sql = "UPDATE ". TBL_CATEGORIES. "
                       SET cat_name   = {0}
                         , cat_hidden = $hidden
                     WHERE cat_id     = {1}";
        }
        else
        {
            // Feld in Datenbank hinzufuegen
            $sql    = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden)
                                                 VALUES ($g_current_organization->id, {2}, {0}, $hidden) ";
        }
        $sql    = prepareSQL($sql, array(trim($category_name), $_GET['cat_id'], $_GET['type']));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
       
        $_SESSION['navigation']->deleteLastUrl();
        unset($_SESSION['categories_request']);
    }
    else
    {
        // es sind nicht alle Felder gefuellt
        $err_text = "Name";
        $err_code = "feld";
    }

    if(strlen($err_code) > 0)
    {
        $g_message->show($err_code, $err_text);
    }

    $err_code = "save";
}
elseif($_GET['mode'] == 2)  // Feld loeschen
{
    // schauen, ob Rollen dieser Kategorie zugeordnet sind
    $sql    = "SELECT * FROM ". TBL_ROLES. "
                WHERE rol_cat_id = {0} ";
    $sql    = prepareSQL($sql, array($_GET['cat_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);              
    $row_num = mysql_num_rows($result);

    if($row_num == 0)
    {
        // Feld loeschen
        $sql    = "DELETE FROM ". TBL_CATEGORIES. "
                    WHERE cat_id = {0}";
        $sql    = prepareSQL($sql, array($_GET['cat_id']));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        $err_code = "delete";
    }
}
elseif($_GET["mode"] == 3)
{
    // Frage, ob Kategorie geloescht werden soll
    $sql = "SELECT cat_name FROM ". TBL_CATEGORIES. "
             WHERE cat_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['cat_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $row = mysql_fetch_array($result);
    
    $g_message->setForwardYesNo("$g_root_path/adm_program/administration/roles/categories_function.php?cat_id=". $_GET['cat_id']. "&mode=2");
    $g_message->show("delete_category", utf8_encode($row[0]), "Löschen");
}
         
// zur Kategorienuebersicht zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($err_code);
?>