<?php
/******************************************************************************
 * Links anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Daniel Dieckelmann
 *
 * Uebergaben:
 *
 * lnk_id        - ID der Ankuendigung, die bearbeitet werden soll
 * headline      - Ueberschrift, die ueber den Links steht
 *                 (Default) Links
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


// Ist ueberhaupt das Recht vorhanden?
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

if (array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "Links";
}

$_SESSION['navigation']->addUrl($g_current_url);

if (isset($_SESSION['links_request']))
{
    $form_values = $_SESSION['links_request'];
    unset($_SESSION['links_request']);
}
else
{
    $form_values['linkname']    = "";
    $form_values['description'] = "";
    $form_values['linkurl']     = "";
    $form_values['category']    = 0;

    // Wenn eine Link-ID uebergeben wurde, soll der Link geaendert werden
    // -> Felder mit Daten des Links vorbelegen
    if ($_GET["lnk_id"] != 0)
    {
        $sql    = "SELECT * FROM ". TBL_LINKS. " WHERE lnk_id = {0} and lnk_org_id = $g_current_organization->id";
        $sql    = prepareSQL($sql, array($_GET['lnk_id']));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);

        if (mysql_num_rows($result) > 0)
        {
            $row_ba = mysql_fetch_object($result);

            $form_values['linkname']    = $row_ba->lnk_name;
            $form_values['description'] = $row_ba->lnk_description;
            $form_values['linkurl']     = $row_ba->lnk_url;
            $form_values['category']    = $row_ba->lnk_cat_id;
        }
        elseif (mysql_num_rows($result) == 0)
        {
            //Wenn keine Daten zu der ID gefunden worden bzw. die ID einer anderen Orga gehört ist Schluss mit lustig...
            $g_message->show("invalid");
        }
    }
}

// Html-Kopf ausgeben
$g_layout['title'] = $_GET["headline"];
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<form action=\"links_function.php?lnk_id=". $_GET["lnk_id"]. "&amp;headline=". $_GET['headline']. "&amp;mode=";
    if($_GET["lnk_id"] > 0)
    {
        echo "3";
    }
    else
    {
        echo "1";
    }
    echo "\" method=\"post\" name=\"LinkAnlegen\">

    <div class=\"formHead\">";
        if($_GET["lnk_id"] > 0)
        {
            echo $_GET["headline"]. " &auml;ndern";
        }
        else
        {
            echo $_GET["headline"]. " anlegen";
        }
    echo "</div>
    <div class=\"formBody\">
        <div>
            <div style=\"text-align: right; width: 25%; float: left;\">Linkname:</div>
            <div style=\"text-align: left; margin-left: 27%;\">
                <input type=\"text\" id=\"linkname\" name=\"linkname\" tabindex=\"1\" style=\"width: 350px;\" maxlength=\"250\" value=\"". htmlspecialchars($form_values['linkname'], ENT_QUOTES). "\">
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>

        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Linkadresse:</div>
            <div style=\"text-align: left; margin-left: 27%;\">
                <input type=\"text\" id=\"linkurl\" name=\"linkurl\" tabindex=\"2\" style=\"width: 350px;\" maxlength=\"250\" value=\"". htmlspecialchars($form_values['linkurl'], ENT_QUOTES). "\">
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>

        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Kategorie:</div>
            <div style=\"text-align: left; margin-left: 27%;\">
                <select size=\"1\" name=\"category\" tabindex=\"3\">";
                    $sql = "SELECT * FROM ". TBL_CATEGORIES. "
                             WHERE cat_org_id = $g_current_organization->id
                               AND cat_type   = 'LNK'
                             ORDER BY cat_name ASC ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result,__FILE__,__LINE__);

                    while($row = mysql_fetch_object($result))
                    {
                        echo "<option value=\"$row->cat_id\"";
                            if($form_values['category'] == $row->cat_id
                            || ($form_values['category'] == 0 && $row->cat_name == 'Allgemein'))
                                echo " selected ";
                        echo ">$row->cat_name</option>";
                    }
                echo "</select>
            </div>
        </div>

        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 25%; float: left;\">Beschreibung:";
                if($g_preferences['enable_bbcode'] == 1)
                {
                  echo "<br><br>
                  <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\" tabindex=\"7\">Text formatieren</a>";
                }
            echo "</div>
            <div style=\"text-align: left; margin-left: 27%;\">
                <textarea  name=\"description\" tabindex=\"4\" style=\"width: 350px;\" rows=\"10\" cols=\"40\">". htmlspecialchars($form_values['description'], ENT_QUOTES). "</textarea>
                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
            </div>
        </div>";


        echo "<hr class=\"formLine\" width=\"85%\" />

        <div style=\"margin-top: 6px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\" tabindex=\"6\">
                <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\"
                width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                &nbsp;Zur&uuml;ck</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"speichern\" type=\"submit\" value=\"speichern\" tabindex=\"5\">
                <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\"
                width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                &nbsp;Speichern</button>
        </div>";

    echo "</div>
</form>

<script type=\"text/javascript\"><!--
    document.getElementById('linkname').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>