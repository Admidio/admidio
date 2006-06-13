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
if (!editAnnouncements())
{
    $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
    header($location);
    exit();
}

if (!array_key_exists("headline", $_GET))
{
    $_GET["headline"] = "Links";
}

$err_code = "";
$err_text = "";

if ($_GET["mode"] == 1 || $_GET["mode"] == 3)
{
    $linkName = strStripTags($_POST['linkname']);
    $description  = strStripTags($_POST['beschreibung']);
    $linkUrl = $_POST['linkadresse'];

    if (strlen($linkName) > 0 && strlen($description)  > 0 && strlen($linkUrl) > 0)
    {
        $act_date = date("Y.m.d G:i:s", time());


        //Link wird jetzt in der DB gespeichert
        if ($_GET["lnk_id"] == 0)
        {
            $sql = "INSERT INTO ". TBL_LINKS. " ( lnk_org_id, lnk_usr_id, lnk_timestamp,
                                                  lnk_name, lnk_url, lnk_description)
                                     VALUES ('$g_current_organization->id', '$g_current_user->id', '$act_date',
                                             {0}, {1}, {2})";
            $sql    = prepareSQL($sql, array($linkName, $linkUrl, $description));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
        }
        else
        {
            $sql = "UPDATE ". TBL_LINKS. " SET   lnk_name   = {0},
                                                 lnk_url	= {1},
                                                 lnk_description  = {2},
                                                 lnk_timestamp    = '$act_date',
                                                 lnk_usr_id       = '$g_current_user->id'
                    WHERE lnk_id = {3}";
            $sql    = prepareSQL($sql, array($linkName, $linkUrl, $description, $_GET['lnk_id']));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
        }

        $location = "location: $g_root_path/adm_program/modules/links/links.php?headline=". $_GET['headline'];
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
    $sql = "DELETE FROM ". TBL_LINKS. " WHERE lnk_id = {0}";
    $sql    = prepareSQL($sql, array($_GET["lnk_id"]));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (!isset($_GET["url"]))
    {
        $_GET["url"] = "$g_root_path/$g_main_page";
    }

    $location = "location: $g_root_path/adm_program/system/err_msg.php?id=$id&err_code=delete&url=". urlencode($_GET["url"]);
    header($location);
    exit();
}

if ($err_code != "")
{
    $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
    header($location);
    exit();
}
?>