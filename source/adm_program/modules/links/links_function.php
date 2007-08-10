<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Links
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Daniel Dieckelmann
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
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
    $sql    = "SELECT * FROM ". TBL_LINKS. ", ". TBL_CATEGORIES ."
                WHERE lnk_id     = ". $_GET['lnk_id']. "
                  AND lnk_cat_id = cat_id
                  AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
                  AND cat_type = 'LNK' ";
    $result = $g_db->query($sql);

    if ($g_db->num_rows($result) == 0)
    {
        //Wenn keine Daten zu der ID gefunden worden bzw. die ID einer anderen Orga gehört ist Schluss mit lustig...
        $g_message->show("invalid");
    }

    $linkObject = $g_db->fetch_object($result);
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

if ($_GET["mode"] == 1 || ($_GET["mode"] == 3 && $_GET["lnk_id"] > 0) )
{
    $linkName = strStripTags($_POST['linkname']);
    $description  = strStripTags($_POST['description']);
    $linkUrl = strStripTags(trim($_POST['linkurl']));
    $category = $_POST['category'];
    
    if(strlen($linkName) == 0)
    {
        $g_message->show("feld", "Linkname");
    }
    if(strlen($linkUrl) == 0)
    {
        $g_message->show("feld", "Linkadresse");
    }
    if(strlen($category) == 0)
    {
        $g_message->show("feld", "Kategorie");
    }
    if(strlen($description) == 0)
    {
        $g_message->show("feld", "Beschreibung");
    }
    
    $act_date = date("Y.m.d G:i:s", time());

    //Die Webadresse wird jetzt falls sie nicht mit http:// oder https:// beginnt entsprechend aufbereitet
    if (substr($linkUrl, 0, 7) != 'http://' && substr($linkUrl, 0, 8) != 'https://' )
    {
        $linkUrl = "http://". $linkUrl;
    }

    //Link wird jetzt in der DB gespeichert
    if ($_GET["lnk_id"] == 0)
    {
        $sql = "INSERT INTO ". TBL_LINKS. " ( lnk_usr_id, lnk_timestamp,
                                              lnk_name, lnk_url, lnk_description, lnk_cat_id)
                                     VALUES (". $g_current_user->getValue("usr_id"). ", '$act_date',
                                              '$linkName', '$linkUrl', '$description', $category)";
        $result = $g_db->query($sql);
    }
    else
    {
        $sql = "UPDATE ". TBL_LINKS. " SET   lnk_name   = '$linkName',
                                             lnk_url    = '$linkUrl',
                                             lnk_description   = '$description',
                                             lnk_last_change   = '$act_date',
                                             lnk_usr_id_change = ". $g_current_user->getValue("usr_id"). ",
                                             lnk_cat_id        =  $category
                WHERE lnk_id = ". $_GET['lnk_id'];
        $result = $g_db->query($sql);
    }

    unset($_SESSION['links_request']);

    $location = "Location: $g_root_path/adm_program/modules/links/links.php?headline=". $_GET['headline'];
    header($location);
    exit();
}

elseif ($_GET["mode"] == 2 && $_GET["lnk_id"] > 0)
{
    // Loeschen von Weblinks...
    $sql = "DELETE FROM ". TBL_LINKS. " 
             WHERE lnk_id = ". $_GET["lnk_id"];
    $result = $g_db->query($sql, $g_adm_con);

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

?>