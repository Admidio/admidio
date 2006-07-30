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

// Uebergabevariablen pruefen

if(array_key_exists("start", $_GET))
{
	if(is_numeric($_GET["start"]) == false)
	{
	    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_variable&err_text=ann_id";
	    header($location);
	    exit();
	}
}
else
{
    $_GET["start"] = 0;
}

if(array_key_exists("id", $_GET))
{
	if(is_numeric($_GET["id"]) == false)
	{
	    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_variable&err_text=id";
	    header($location);
	    exit();
	}	
}
else
{
    $_GET["id"] = 0;
}

if(array_key_exists("headline", $_GET))
{
	$_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "Links";
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
            // Neuen Link anlegen
            if (editWeblinks())
            {
                echo "<p>
					<span class=\"iconLink\">
	                    <a class=\"iconLink\" href=\"links_new.php?headline=". $_GET["headline"]. "\"><img
	                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Neu anlegen\"></a>
	                    <a class=\"iconLink\" href=\"links_new.php?headline=". $_GET["headline"]. "\">Neu anlegen</a>
	                </span>
				</p>";
            }

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
            echo "<div class=\"formHead\">Weblinks</div>
            <div class=\"formBody\" style=\"overflow: hidden;\">";
                
                $i = 0;
                while ($row = mysql_fetch_object($links_result))
                {
                    if($i > 0)
                    {
                        echo "<hr width=\"98%\" />";
                    }
                    echo "
                    <div style=\"text-align: left;\">
                        <div style=\"text-align: left;\">
                            <a href=\"$row->lnk_url\" target=\"_blank\">
                                <img src=\"$g_root_path/adm_program/images/globe.png\" style=\"vertical-align: top;\" 
                                    alt=\"Gehe zu $row->lnk_name\" title=\"Gehe zu $row->lnk_name\" border=\"0\"></a>
                            <a href=\"$row->lnk_url\" target=\"_blank\">$row->lnk_name</a>
                        </div>
                        <div style=\"margin-top: 10px; text-align: left;\">";

                            // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
                            if ($g_preferences['enable_bbcode'] == 1)
                            {
                                echo strSpecialChars2Html($bbcode->parse($row->lnk_description));
                            }
                            else
                            {
                                echo nl2br(strSpecialChars2Html($row->lnk_description));
                            }
                        echo "</div>";
                        if(editWeblinks())
                        {
                            echo "
                            <div style=\"margin-top: 10px; font-size: 8pt; text-align: left;\">";
                                // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                                if (editWeblinks())
                                {
                                    echo "<img src=\"$g_root_path/adm_program/images/edit.png\" style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"
                                        onclick=\"self.location.href='links_new.php?lnk_id=$row->lnk_id&amp;headline=". $_GET['headline']. "'\">

                                        <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"cursor: pointer; vertical-align: middle;\" width=\"16\" height=\"16\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\" ";
                                        $load_url = urlencode("$g_root_path/adm_program/modules/links/links_function.php?lnk_id=$row->lnk_id&amp;mode=2&amp;url=$g_root_path/adm_program/modules/links/links.php");
                                        echo " onclick=\"self.location.href='$g_root_path/adm_program/system/err_msg.php?err_code=delete_link&amp;err_text=". urlencode($row->lnk_name). "&amp;err_head=L&ouml;schen&amp;button=2&amp;url=$load_url'\">
                                        &nbsp;";
                                }
                                $user_create = new User($g_adm_con);
                                $user_create->getUser($row->lnk_usr_id);
                                echo "Angelegt von ". strSpecialChars2Html($user_create->first_name). " ". strSpecialChars2Html($user_create->last_name).
                                " am ". mysqldatetime("d.m.y h:i", $row->lnk_timestamp);
                                
		                        if($row->lnk_usr_id_change > 0)
		                        {
		                            $user_change = new User($g_adm_con);
		                            $user_change->getUser($row->lnk_usr_id_change);
		                            echo "<br>Zuletzt bearbeitet von ". strSpecialChars2Html($user_change->first_name). " ". strSpecialChars2Html($user_change->last_name).
		                            " am ". mysqldatetime("d.m.y h:i", $row->lnk_last_change);
		                        }                                
                            echo "</div>";
                        }
                    echo "</div>";                    
                    $i++;
                 }  // Ende While-Schleife
             echo "</div>";
        }

		if(mysql_num_rows($links_result) > 2)
		{
	        // Navigation mit Vor- und Zurueck-Buttons
	        // erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
	        $baseUrl = "$g_root_path/adm_program/modules/links/links.php?headline=". $_GET["headline"];
	        echo generatePagination($baseUrl, $numLinks, 10, $_GET["start"], TRUE);
		}
    echo "</div>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>