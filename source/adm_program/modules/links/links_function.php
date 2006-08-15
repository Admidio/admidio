<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Links
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Uebergaben:
 *
 * lnk_id:   ID der Ankuendigung, die angezeigt werden soll
 * mode:     1 - Neuen Link anlegen
 *           2 - Link loeschen
 *           3 - Link editieren
 * url:      kann beim Loeschen mit uebergeben werden
 * headline: Ueberschrift, die ueber den Links steht
 *           (Default) Links
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

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!editWeblinks())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen
if (array_key_exists("lnk_id", $_GET))
{
    if (is_numeric($_GET["lnk_id"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["lnk_id"] = 0;
}


if (array_key_exists("mode", $_GET))
{
    if (is_numeric($_GET["mode"]) == false)
    {
        $g_message->show("invalid");
    }
}

// jetzt wird noch geprueft ob die eventuell uebergebene lnk_id uberhaupt zur Orga gehoert oder existiert...
if ($_GET["lnk_id"] > 0)
{
    $sql    = "SELECT * FROM ". TBL_LINKS. " WHERE lnk_id = {0} and lnk_org_id = $g_current_organization->id";
    $sql    = prepareSQL($sql, array($_GET['lnk_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (mysql_num_rows($result) == 0)
    {
        //Wenn keine Daten zu der ID gefunden worden bzw. die ID einer anderen Orga gehört ist Schluss mit lustig...
        $g_message->show("invalid");
    }
}

if (array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "Links";
}

$_SESSION['links_request'] = $_REQUEST;


$err_code = "";
$err_text = "";

if ($_GET["mode"] == 1 || $_GET["mode"] == 3)
{
    $linkName = strStripTags($_POST['linkname']);
    $description  = strStripTags($_POST['description']);
    $linkUrl = trim($_POST['linkurl']);

    if (strlen($linkName) > 0 && strlen($description)  > 0 && strlen($linkUrl) > 0)
    {
        $act_date = date("Y.m.d G:i:s", time());

        //Die Webadresse wird jetzt falls sie nicht mit http:// oder https:// beginnt entsprechend aufbereitet
        if (substr($linkUrl, 0, 7) != 'http://' && substr($linkUrl, 0, 8) != 'https://' )
        {
            $linkUrl = "http://". $linkUrl;
        }

        //Link wird jetzt in der DB gespeichert
        if ($_GET["lnk_id"] == 0)
        {
            $sql = "INSERT INTO ". TBL_LINKS. " ( lnk_org_id, lnk_usr_id, lnk_timestamp,
                                                  lnk_name, lnk_url, lnk_description)
                                     VALUES ($g_current_organization->id, $g_current_user->id, '$act_date',
                                             {0}, {1}, {2})";
            $sql    = prepareSQL($sql, array($linkName, $linkUrl, $description));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
        }
        else
        {
            $sql = "UPDATE ". TBL_LINKS. " SET   lnk_name   = {0},
                                                 lnk_url    = {1},
                                                 lnk_description   = {2},
                                                 lnk_last_change   = '$act_date',
                                                 lnk_usr_id_change = $g_current_user->id
                    WHERE lnk_id = {3}";
            $sql    = prepareSQL($sql, array($linkName, $linkUrl, $description, $_GET['lnk_id']));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
        }

        unset($_SESSION['links_request']);

        $location = "Location: $g_root_path/adm_program/modules/links/links.php?headline=". $_GET['headline'];
        header($location);
        exit();
    }
    else
    {
        if (strlen($linkName) == 0)
        {
            $err_text = "Linkname";
        }
        elseif (strlen($linkUrl) == 0)
        {
            $err_text = "Linkadresse";
        }
        elseif (strlen($description) == 0)
        {
            $err_text = "Beschreibung";
        }

        $err_code = "feld";
    }
}

elseif ($_GET["mode"] == 2)
{
    // Loeschen von Weblinks...
    $sql = "DELETE FROM ". TBL_LINKS. " WHERE lnk_id = {0}";
    $sql    = prepareSQL($sql, array($_GET["lnk_id"]));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (!isset($_GET["url"]))
    {
        $_GET["url"] = "$g_root_path/$g_main_page";
    }

    $g_message->setForwardUrl($_GET["url"]);
    $g_message->show("delete");
}

else
{
    // Falls der mode unbekannt ist, ist natürlich Ende...
    $g_message->show("invalid");
}

$g_message->show($err_code, $err_text);
?>