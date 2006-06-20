<?php
/******************************************************************************
 * Rollen-Kategorien anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * rlc_id: ID der Rollen-Kategorien, die bearbeitet werden soll
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
 ****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");

// nur Moderatoren duerfen Kategorien erfassen & verwalten
if(!isModerator())
{
    $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
    header($location);
    exit();
}

// wenn URL uebergeben wurde zu dieser gehen, ansonsten zurueck
if(array_key_exists('url', $_GET))
{
    $url = $_GET['url'];
}
else
{
    $url = urlencode(getHttpReferer());
}

$category_name   = "";
$category_locked = 0;

// Wenn eine Feld-ID uebergeben wurde, soll das Feld geaendert werden
// -> Felder mit Daten des Feldes vorbelegen

if ($_GET["rlc_id"] != 0)
{
    $sql    = "SELECT * FROM ". TBL_ROLE_CATEGORIES. " WHERE rlc_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['rlc_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (mysql_num_rows($result) > 0)
    {
        $row_rlc = mysql_fetch_object($result);
        $category_name   = $row_rlc->rlc_name;
        $category_locked = $row_rlc->rlc_locked;
    }
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Kategorie</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <form action=\"categories_function.php?rlc_id=". $_GET['rlc_id']. "&amp;mode=1&amp;url=$url\" method=\"post\" id=\"edit_category\">
            <div class=\"formHead\">";
                if($_GET['rlc_id'] > 0)
                {
                    echo strspace("Kategorie ändern");
                }
                else
                {
                    echo strspace("Kategorie anlegen");
                }
            echo "</div>
            <div class=\"formBody\">
                <div>
                    <div style=\"text-align: right; width: 23%; float: left;\">Name:</div>
                    <div style=\"text-align: left; margin-left: 24%;\">
                        <input type=\"text\" id=\"name\" name=\"name\" size=\"30\" maxlength=\"100\" value=\"". htmlspecialchars($category_name, ENT_QUOTES). "\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 23%; float: left;\">
                        <label for=\"locked\"><img src=\"$g_root_path/adm_program/images/lock.png\" alt=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\"></label>
                    </div>
                    <div style=\"text-align: left; margin-left: 24%;\">
                        <input type=\"checkbox\" id=\"locked\" name=\"locked\" ";
                            if($category_locked == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                        <label for=\"locked\">Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar&nbsp;</label>
                    </div>
                </div>

                <hr width=\"85%\" />

                <div style=\"margin-top: 6px;\">
                    <button id=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
                    <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                    &nbsp;Zur&uuml;ck</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button id=\"speichern\" type=\"submit\" value=\"speichern\">
                    <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                    &nbsp;Speichern</button>
                </div>";
            echo "</div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--
        document.getElementById('name').focus();
    --></script>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>