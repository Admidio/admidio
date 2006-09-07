<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Rollen-Kategorien
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * rlc_id: ID der Rollen-Kategorien
 * mode:   1 - Kategorie anlegen oder updaten
 *         2 - Kategorie loeschen
 *         3 - Frage, ob Kategorie geloescht werden soll
 * url :   URL von der die aufrufende Seite aufgerufen wurde
 *         (muss uebergeben werden, damit der Zurueck-Button funktioniert)
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

if(isset($_GET["rlc_id"]) && is_numeric($_GET["rlc_id"]) == false)
{
    $g_message->show("invalid");
}

// wenn URL uebergeben wurde zu dieser gehen, ansonsten zurueck
if(array_key_exists('url', $_GET) && strlen($_GET['url']) > 0)
{
    $url = urlencode($_GET['url']);
}
else
{
    $url = urlencode(getHttpReferer());
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
        if(!($_GET['rlc_id'] > 0))
        {
            // Schauen, ob die Kategorie bereits existiert
            $sql    = "SELECT COUNT(*) FROM ". TBL_ROLE_CATEGORIES. "
                        WHERE rlc_org_shortname LIKE '$g_organization'
                          AND rlc_name          LIKE {0}";
            $sql    = prepareSQL($sql, array($category_name));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            $row = mysql_fetch_array($result);

            if($row[0] > 0)
            {
                $g_message->show("category_exist");
            }      
        }

        if(array_key_exists("locked", $_POST))
        {
            $locked = 1;
        }
        else
        {
            $locked = 0;
        }

        if($_GET['rlc_id'] > 0)
        {
            $sql = "UPDATE ". TBL_ROLE_CATEGORIES. "
                       SET rlc_name   = {0}
                         , rlc_locked = $locked
                     WHERE rlc_id     = {1}";
        }
        else
        {
            // Feld in Datenbank hinzufuegen
            $sql    = "INSERT INTO ". TBL_ROLE_CATEGORIES. " (rlc_org_shortname, rlc_name, rlc_locked)
                                                      VALUES ('$g_organization', {0}, $locked) ";
        }
        $sql    = prepareSQL($sql, array(trim($category_name), $_GET['rlc_id']));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
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
                WHERE rol_rlc_id = {0} ";
    $sql    = prepareSQL($sql, array($_GET['rlc_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);              
    $row_num = mysql_num_rows($result);

    if($row_num == 0)
    {
        // Feld loeschen
        $sql    = "DELETE FROM ". TBL_ROLE_CATEGORIES. "
                    WHERE rlc_id = {0}";
        $sql    = prepareSQL($sql, array($_GET['rlc_id']));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        $err_code = "delete";
    }
}
elseif($_GET["mode"] == 3)
{
    // Frage, ob Kategorie geloescht werden soll
    $sql = "SELECT rlc_name FROM ". TBL_ROLE_CATEGORIES. "
             WHERE rlc_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['rlc_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $row = mysql_fetch_array($result);
    
    $g_message->setForwardYesNo("$g_root_path/adm_program/administration/roles/categories_function.php?rlc_id=". $_GET['rlc_id']. "&mode=2&url=$url");
    $g_message->show("delete_category", utf8_encode($row[0]), "Löschen");
}
         
// zur Kategorienuebersicht zurueck
$g_message->setForwardUrl("$g_root_path/adm_program/administration/roles/categories.php?url=$url", 2000);
$g_message->show($err_code);
?>