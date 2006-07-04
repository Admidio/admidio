<?php
/******************************************************************************
 * Gaestebucheintraege anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Uebergaben:
 *
 * id            - ID des Eintrages, der bearbeitet werden soll
 * headline      - Ueberschrift, die ueber den Einraegen steht
 *                 (Default) Gaestebuch
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

if (!array_key_exists("id", $_GET))
{
    $_GET["id"] = 0;
}

if (!array_key_exists("headline", $_GET))
{
    $_GET["headline"] = "G&auml;stebuch";
}


// Falls ein Eintrag bearbeitet werden soll muss geprueft weden ob die Rechte gesetzt sind...
if ($_GET["id"] != 0)
{
    require("../../system/login_valid.php");

    if (!editGuestbook())
    {
        $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
        header($location);
        exit();
    }
}

$name      = "";
$text      = "";
$email     = "";
$homepage  = "";


// Wenn eine ID uebergeben wurde, soll der Eintrag geaendert werden
// -> Felder mit Daten des Eintrages vorbelegen

if ($_GET['id'] != 0)
{
    $sql    = "SELECT * FROM ". TBL_GUESTBOOK. " WHERE gbo_id = {0} and gbo_org_id = $g_current_organization->id";
    $sql    = prepareSQL($sql, array($_GET['id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (mysql_num_rows($result) > 0)
    {
        $row_ba = mysql_fetch_object($result);

        $name     = $row_ba->gbo_name;
        $text     = $row_ba->gbo_text;
        $email    = $row_ba->gbo_email;
        $homepage = $row_ba->gbo_homepage;
    }
    elseif (mysql_num_rows($result) == 0)
    {
        //Wenn keine Daten zu der ID gefunden worden bzw. die ID einer anderen Orga gehört ist Schluss mit lustig...
        $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid";
        header($location);
        exit();
    }

}

// Wenn keine ID uebergeben wurde, der User aber eingeloggt ist koennen zumindest
// Name, Emailadresse und Homepgae vorbelegt werden...
if ($_GET['id'] == 0 && $g_session_valid)
{
    $name  = $g_current_user->first_name. " ". $g_current_user->last_name;
    $email = $g_current_user->email;
    $homepage = $g_current_user->homepage;
}


echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - ". $_GET["headline"]. "</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <form action=\"guestbook_function.php?id=". $_GET["id"]. "&amp;headline=". $_GET['headline']. "&amp;mode=";
            if ($_GET['id'] > 0)
            {
                echo "3";
            }
            else
            {
                echo "1";
            }
            echo "\" method=\"post\" name=\"Gaestebucheintrag\">

            <div class=\"formHead\">";
                if ($_GET['id'] > 0)
                {
                    $formHeadline = "G&auml;stebucheintrag &auml;ndern";
                }
                else
                {
                    $formHeadline = "G&auml;stebucheintrag anlegen";
                }
                echo strspace($formHeadline, 2);
            echo "</div>
            <div class=\"formBody\">
                <div>
                    <div style=\"text-align: right; width: 25%; float: left;\">Name:*</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <input type=\"text\" id=\"Name\" name=\"Name\" tabindex=\"1\" size=\"53\" maxlength=\"60\" value=\"". htmlspecialchars($name, ENT_QUOTES). "\">
                    </div>
                </div>

                <div>
                    <div style=\"text-align: right; width: 25%; float: left;\">Emailadresse:</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <input type=\"text\" id=\"Email\" name=\"Email\" tabindex=\"1\" size=\"53\" maxlength=\"50\" value=\"". htmlspecialchars($email, ENT_QUOTES). "\">
                    </div>
                </div>

                <div>
                    <div style=\"text-align: right; width: 25%; float: left;\">Homepage:</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <input type=\"text\" id=\"Homepage\" name=\"Homepage\" tabindex=\"1\" size=\"53\" maxlength=\"50\" value=\"". htmlspecialchars($homepage, ENT_QUOTES). "\">
                    </div>
                </div>

                <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 25%; float: left;\">Text:*";
                    if($g_preferences['enable_bbcode'] == 1)
                    {
                      echo "<br><br>
                      <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=400,left=310,top=200,scrollbars=yes')\" tabindex=\"6\">Text formatieren</a>";
                    }
                    echo "</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <textarea  name=\"Text\" tabindex=\"2\" rows=\"10\" cols=\"40\">". htmlspecialchars($text, ENT_QUOTES). "</textarea>
                    </div>
                </div>";


                echo "<hr width=\"85%\" />

                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\" tabindex=\"5\">
                        <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\"
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                        &nbsp;Zur&uuml;ck</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button name=\"speichern\" type=\"submit\" value=\"speichern\" tabindex=\"4\">
                        <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\"
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                        &nbsp;Speichern</button>
                </div>
            </div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--
        document.getElementById('headline').focus();
    --></script>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>