<?php
/******************************************************************************
 * Links auflisten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * start     - Angabe, ab welchem Datensatz Links angezeigt werden sollen
 * headline  - Ueberschrift, die ueber den Links steht
 *             (Default) Links
 * id        - Nur einen einzigen Link anzeigen lassen.
 *
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
require("../../system/bbcode.php");

if (!array_key_exists("start", $_GET))
{
    $_GET["start"] = 0;
}

if (!array_key_exists("headline", $_GET))
{
    $_GET["headline"] = "Links";
}

if (!array_key_exists("id", $_GET))
{
    $_GET["id"] = 0;
}

if ($g_preferences['enable_bbcode'] == 1)
{
    // Klasse fuer BBCode initialisieren
    $bbcode = new ubbParser();
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - ". $_GET["headline"]. "</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">";

    if ($g_preferences['enable_rss'] == 1)
    {
        echo "<link type=\"application/rss+xml\" rel=\"alternate\" title=\"$g_current_organization->longname - Links\"
        href=\"$g_root_path/adm_program/modules/links/rss_links.php\">";
    }

    echo "
    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <h1>". strspace($_GET["headline"]). "</h1>";


        // falls eine id fuer einen bestimmten Link uebergeben worden ist...
        if ($_GET['id'] > 0)
        {
            $sql    = "SELECT * FROM ". TBL_LINKS. "
                       WHERE lnk_id = '$_GET[id]' and lnk_org_id = '$g_current_organization->id'";
        }
        //...ansonsten alle fuer die Gruppierung passenden Links aus der DB holen.
        else
        {
            $sql    = "SELECT * FROM ". TBL_LINKS. "
                       WHERE lnk_org_id = '$g_current_organization->id'
                       ORDER BY lnk_timestamp DESC
                       LIMIT ". $_GET["start"]. ", 10 ";
        }

        $links_result = mysql_query($sql, $g_adm_con);
        db_error($links_result);

        // Gucken wieviele Linkdatensaetze insgesamt fuer die Gruppierung vorliegen...
        // Das wird naemlich noch fuer die Seitenanzeige benoetigt...
        $sql    = "SELECT COUNT(*) FROM ". TBL_LINKS. "
                   WHERE lnk_org_id = '$g_current_organization->id'";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
        $row = mysql_fetch_array($result);
        $numLinks = $row[0];

        // Icon-Links und Navigation anzeigen

        if ($_GET['id'] == 0 && (editWeblinks() || $g_preferences['enable_rss'] == true))
        {
            echo "<p>";

            // Neuen Link anlegen
            if (editWeblinks())
            {
                echo "<span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"links_new.php?headline=". $_GET["headline"]. "\"><img
                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Neu anlegen\"></a>
                    <a class=\"iconLink\" href=\"links_new.php?headline=". $_GET["headline"]. "\">Neu anlegen</a>
                </span>";
            }

            if (editWeblinks() && $g_preferences['enable_rss'] == true)
            {
                echo "&nbsp;&nbsp;&nbsp;&nbsp;";
            }

            // Feed abonnieren
            if ($g_preferences['enable_rss'] == true)
            {
                echo "<span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/links/rss_links.php\"><img
                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/feed.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"". $_GET["headline"]. "-Feed abonnieren\"></a>
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/links/rss_links.php\">". $_GET["headline"]. "-Feed abonnieren</a>
                </span>";
            }

            echo "</p>";

            // Navigation mit Vor- und Zurueck-Buttons
            $baseUrl = "$g_root_path/adm_program/modules/links/links.php?headline=". $_GET["headline"];
            echo generatePagination($baseUrl, $numLinks, 10, $_GET["start"], TRUE);
        }

        if (mysql_num_rows($links_result) == 0)
        {
            // Keine Links gefunden
            if ($_GET['id'] > 0)
            {
                echo "<p>Der angeforderte Eintrag exisitiert nicht (mehr) in der Datenbank.</p>";
            }
            else
            {
                echo "<p>Es sind keine Eintr&auml;ge vorhanden.</p>";
            }
        }
        else
        {

            // Links auflisten
            while ($row = mysql_fetch_object($links_result))
            {

                echo "
                <div class=\"boxBody\" style=\"overflow: hidden;\">
                    <div class=\"boxHead\">
                        <div style=\"text-align: left; float: left;\">
                            <a href=\"$row->lnk_url\" target=\"_blank\">
                            <img src=\"$g_root_path/adm_program/images/globe.png\" style=\"vertical-align: top;\" alt=\"Gehe zu $row->lnk_name\"
                            title=\"Gehe zu $row->lnk_name\" border=\"0\"></a>
                            <a href=\"$row->lnk_url\" target=\"_blank\">";
                            if (strlen($row->lnk_name) > 25)
                            {
                                echo "<span style=\"font-size: 8pt;\">$row->lnk_name</span>";
                            }
                            else
                            {
                                echo "$row->lnk_name";
                            }
                            echo "</a>
                        </div>";

                        // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                        if (editWeblinks())
                        {
                            echo "<div style=\"text-align: right;\">" .
                                mysqldatetime("d.m.y", $row->lnk_timestamp). "&nbsp;
                                <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"cursor: pointer;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"
                                onclick=\"self.location.href='links_new.php?lnk_id=$row->lnk_id&amp;headline=". $_GET['headline']. "'\">";


                                echo "
                                <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"cursor: pointer;\" width=\"16\" height=\"16\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\" ";
                                $load_url = urlencode("$g_root_path/adm_program/modules/links/links_function.php?lnk_id=$row->lnk_id&amp;mode=2&amp;url=$g_root_path/adm_program/modules/links/links.php");
                                echo " onclick=\"self.location.href='$g_root_path/adm_program/system/err_msg.php?err_code=delete_link&amp;err_text=". urlencode($row->lnk_name). "&amp;err_head=L&ouml;schen&amp;button=2&amp;url=$load_url'\">";

                            echo "&nbsp;</div>";
                        }
                        else
                        {
                            echo "<div style=\"text-align: right;\">". mysqldatetime("d.m.y", $row->lnk_timestamp). "&nbsp;</div>";
                        }
                    echo "</div>

                    <div style=\"margin: 8px 4px 4px 4px; text-align: left;\">";
                        // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
                        if ($g_preferences['enable_bbcode'] == 1)
                        {
                            echo strSpecialChars2Html($bbcode->parse($row->lnk_description));
                        }
                        else
                        {
                            echo nl2br(strSpecialChars2Html($row->lnk_description));
                        }
                    echo "</div>
                    <div style=\"margin: 8px 4px 4px 4px; font-size: 8pt; text-align: left;\">";
                        $user_create = new TblUsers($g_adm_con);
                        $user_create->getUser($row->lnk_usr_id);
                        echo "Angelegt von ". strSpecialChars2Html($user_create->first_name). " ". strSpecialChars2Html($user_create->last_name).
                        " am ". mysqldatetime("d.m.y h:i", $row->lnk_timestamp). "
                    </div>
                </div>

                <br />";
             }  // Ende While-Schleife
        }

        // Die untere Navigationsleiste wird nur angezeigt wenn die Seite mehr als 2 Elemente enthaelt...
        if ($_GET['id'] == 0 && mysql_num_rows($links_result) > 2)
        {
            // Navigation mit Vor- und Zurueck-Buttons
            $baseUrl = "$g_root_path/adm_program/modules/links/links.php?headline=". $_GET["headline"];
            echo generatePagination($baseUrl, $numLinks, 10, $_GET["start"], TRUE);
        }
    echo "</div>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>