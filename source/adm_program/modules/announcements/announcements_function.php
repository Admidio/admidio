<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Ankuendigungen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * ann_id:   ID der Ankuendigung, die angezeigt werden soll
 * mode:     1 - Neue Ankuendigung anlegen
 *           2 - Ankuendigung loeschen
 *           3 - Ankuendigung aendern
 *           4 - Frage, ob Ankuendigung geloescht werden soll
 * url:      kann beim Loeschen mit uebergeben werden
 * headline: Ueberschrift, die ueber den Ankuendigungen steht
 *           (Default) Ankuendigungen
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

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!editAnnouncements())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["ann_id"]) && is_numeric($_GET["ann_id"]) == false && $_GET["ann_id"]!=NULL)
{
    $g_message->show("invalid");
}

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 4)
{
    $g_message->show("invalid");
}

if($_GET["mode"] == 2 || $_GET["mode"] == 3 || $_GET["mode"] == 4)
{
    // pruefen, ob man die Ankuendigung bearbeiten darf
    $sql = "SELECT * FROM ". TBL_ANNOUNCEMENTS. " 
             WHERE ann_id = {0}
               AND (  ann_org_shortname = '$g_organization'
                   OR ann_global = 1 ) ";
    $sql = prepareSQL($sql, array($_GET['ann_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if(!$row_ann = mysql_fetch_object($result))
    {
        $g_message->show("norights");
    }
}

if(array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "Ank&uuml;ndigungen";
}

$_SESSION['announcements_request'] = $_REQUEST;

$err_code = "";
$err_text = "";

if($_GET["mode"] == 1 || $_GET["mode"] == 3)
{
    $headline = strStripTags($_POST['headline']);
    $content  = strStripTags($_POST['description']);

    if(strlen($headline) > 0
    && strlen($content)  > 0)
    {
        $act_date = date("Y.m.d G:i:s", time());

        if(array_key_exists("global", $_POST))
        {
            $global = 1;
        }
        else
        {
            $global = 0;
        }

        // Termin speichern

        if ($_GET["ann_id"] == 0)
        {
            $sql = "INSERT INTO ". TBL_ANNOUNCEMENTS. " (ann_global, ann_org_shortname, ann_usr_id, ann_timestamp,
                                                         ann_headline, ann_description)
                                                 VALUES ($global, '$g_organization', '$g_current_user->id', '$act_date',
                                                         {0}, {1})";
            $sql    = prepareSQL($sql, array($headline, $content));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
        }
        else
        {
            $sql = "UPDATE ". TBL_ANNOUNCEMENTS. " SET ann_global         = $global
                                                     , ann_headline   = {0}
                                                     , ann_description   = {1}
                                                     , ann_last_change    = '$act_date'
                                                     , ann_usr_id_change = $g_current_user->id
                     WHERE ann_id = {2}";
            $sql    = prepareSQL($sql, array($headline, $content, $_GET['ann_id']));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
        }
        unset($_SESSION['announcements_request']);
        
        $location = "Location: $g_root_path/adm_program/modules/announcements/announcements.php?headline=". $_GET['headline'];
        header($location);
        exit();
    }
    else
    {
        if(strlen($headline) > 0)
        {
            $err_text = "Beschreibung";
        }
        else
        {
            $err_text = "&Uuml;berschrift";
        }
        $err_code = "feld";
    }
}
elseif($_GET["mode"] == 2)
{
    $sql = "DELETE FROM ". TBL_ANNOUNCEMENTS. " WHERE ann_id = {0}";
    $sql    = prepareSQL($sql, array($_GET["ann_id"]));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if(!isset($_GET["url"]))
    {
        $_GET["url"] = "$g_root_path/$g_main_page";
    }

    $g_message->setForwardUrl($_GET["url"]);
    $g_message->show("delete");
}
elseif($_GET["mode"] == 4)
{
    $g_message->setForwardYesNo("$g_root_path/adm_program/modules/announcements/announcements_function.php?ann_id=". $_GET["ann_id"]. "&amp;mode=2&amp;url=$g_root_path/adm_program/modules/announcements/announcements.php");
    $g_message->show("delete_announcement", utf8_encode($row_ann->ann_headline), "Löschen");
}

$g_message->show($err_code, $err_text);
?>