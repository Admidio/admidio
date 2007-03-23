<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Links
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Daniel Dieckelmann
 *
 * Uebergaben:
 *
 * lnk_id:   ID der Ankuendigung, die angezeigt werden soll
 * mode:     1 - Neuen Link anlegen
 *           2 - Link loeschen
 *           3 - Link editieren
 *           4 - Nachfrage ob Link geloescht werden soll
 * url:      kann beim Loeschen mit uebergeben werden
 * headline: Ueberschrift, die ueber den Links steht
 *           (Default) Links
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

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_weblinks_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// erst pruefen, ob der User auch die entsprechenden Rechte hat
if (!$g_current_user->editWeblinksRight())
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
    db_error($result,__FILE__,__LINE__);

    if (mysql_num_rows($result) == 0)
    {
        //Wenn keine Daten zu der ID gefunden worden bzw. die ID einer anderen Orga gehört ist Schluss mit lustig...
        $g_message->show("invalid");
    }

    $linkObject = mysql_fetch_object($result);
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

if ($_GET["mode"] == 1 || ($_GET["mode"] == 3 && $_GET["lnk_id"] > 0) )
{
    $linkName = strStripTags($_POST['linkname']);
    $description  = strStripTags($_POST['description']);
    $linkUrl = strStripTags(trim($_POST['linkurl']));
    $category = $_POST['category'];

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
                                                  lnk_name, lnk_url, lnk_description, lnk_cat_id)
                                     VALUES ($g_current_organization->id, $g_current_user->id, '$act_date',
                                             {0}, {1}, {2}, {3})";
            $sql    = prepareSQL($sql, array($linkName, $linkUrl, $description, $category));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
        }
        else
        {
            $sql = "UPDATE ". TBL_LINKS. " SET   lnk_name   = {0},
                                                 lnk_url    = {1},
                                                 lnk_description   = {2},
                                                 lnk_last_change   = '$act_date',
                                                 lnk_usr_id_change = $g_current_user->id,
                                                 lnk_cat_id        =  {3}
                    WHERE lnk_id = {4}";
            $sql    = prepareSQL($sql, array($linkName, $linkUrl, $description, $category, $_GET['lnk_id']));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
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

elseif ($_GET["mode"] == 2 && $_GET["lnk_id"] > 0)
{
    // Loeschen von Weblinks...
    $sql = "DELETE FROM ". TBL_LINKS. " WHERE lnk_id = {0}";
    $sql    = prepareSQL($sql, array($_GET["lnk_id"]));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    if (!isset($_GET["url"]))
    {
        $_GET["url"] = "$g_root_path/$g_main_page";
    }

    $g_message->setForwardUrl($_GET["url"]);
    $g_message->show("delete");
}

elseif ($_GET["mode"] == 4 && $_GET["lnk_id"] > 0)
{
    //Nachfrage ob Weblinkeintrag geloescht werden soll
    $g_message->setForwardYesNo("$g_root_path/adm_program/modules/links/links_function.php?lnk_id=$_GET[lnk_id]&mode=2&url=$g_root_path/adm_program/modules/links/links.php");
    $g_message->show("delete_link", utf8_encode($linkObject->lnk_name));
}

else
{
    // Falls der mode unbekannt ist, ist natürlich Ende...
    $g_message->show("invalid");
}

$g_message->show($err_code, $err_text);
?>