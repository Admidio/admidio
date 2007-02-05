<?php
/******************************************************************************
 * Gaestebucheintraege anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
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

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_guestbook_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}


// Uebergabevariablen pruefen

if (array_key_exists("id", $_GET))
{
    if (is_numeric($_GET["id"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["id"] = 0;
}

if (array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "G&auml;stebuch";
}


// Falls ein Eintrag bearbeitet werden soll muss geprueft weden ob die Rechte gesetzt sind...
if ($_GET["id"] != 0)
{
    require("../../system/login_valid.php");

    if (!$g_current_user->editGuestbookRight())
    {
        $g_message->show("norights");
    }
}

if (isset($_SESSION['guestbook_entry_request']))
{
    $form_values = $_SESSION['guestbook_entry_request'];
    unset($_SESSION['guestbook_entry_request']);
}
else
{
    $form_values['name']     = "";
    $form_values['email']     = "";
    $form_values['homepage'] = "";
    $form_values['text']     = "";

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

            $form_values['name']     = $row_ba->gbo_name;
            $form_values['text']     = $row_ba->gbo_text;
            $form_values['email']    = $row_ba->gbo_email;
            $form_values['homepage'] = $row_ba->gbo_homepage;
        }
        elseif (mysql_num_rows($result) == 0)
        {
            //Wenn keine Daten zu der ID gefunden worden bzw. die ID einer anderen Orga gehört ist Schluss mit lustig...
            $g_message->show("invalid");
        }

    }

    // Wenn keine ID uebergeben wurde, der User aber eingeloggt ist koennen zumindest
    // Name, Emailadresse und Homepage vorbelegt werden...
    if ($_GET['id'] == 0 && $g_session_valid)
    {
        $form_values['name']     = $g_current_user->first_name. " ". $g_current_user->last_name;
        $form_values['email']    = $g_current_user->email;
        $form_values['homepage'] = $g_current_user->homepage;
    }

}

if (!$g_session_valid && $g_preferences['flooding_protection_time'] != 0)
{
    // Falls er nicht eingeloggt ist, wird vor dem Ausfuellen des Formulars noch geprueft ob der
    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
    // einen GB-Eintrag erzeugt hat...
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    $sql = "SELECT count(*) FROM ". TBL_GUESTBOOK. "
            where unix_timestamp(gbo_timestamp) > unix_timestamp()-". $g_preferences['flooding_protection_time']. "
              and gbo_org_id = $g_current_organization->id
              and gbo_ip_address = '$ipAddress' ";
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $row = mysql_fetch_array($result);
    if($row[0] > 0)
    {
          //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
          $g_message->show("flooding_protection", $g_preferences['flooding_protection_time']);
    }
}

echo "
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->\n
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
                    <div style=\"text-align: right; width: 25%; float: left;\">Name:</div>
                    <div style=\"text-align: left; margin-left: 27%;\">";
                    if ($g_current_user->id != 0)
                    {
                        // Eingeloggte User sollen ihren Namen nicht aendern duerfen
                        echo "<input class=\"readonly\" readonly type=\"text\" id=\"name\" name=\"name\" tabindex=\"1\" style=\"width: 350px;\" maxlength=\"60\" value=\"". htmlspecialchars($form_values['name'], ENT_QUOTES). "\">";
                    }
                    else
                    {
                        echo "<input type=\"text\" id=\"name\" name=\"name\" tabindex=\"1\" style=\"width: 350px;\" maxlength=\"60\" value=\"". htmlspecialchars($form_values['name'], ENT_QUOTES). "\">
                        <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>";
                    }
                    echo "</div>
                </div>

                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 25%; float: left;\">Emailadresse:</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <input type=\"text\" id=\"email\" name=\"email\" tabindex=\"2\" style=\"width: 350px;\" maxlength=\"50\" value=\"". htmlspecialchars($form_values['email'], ENT_QUOTES). "\">
                    </div>
                </div>

                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 25%; float: left;\">Homepage:</div>
                    <div style=\"text-align: left; margin-left: 27%;\">
                        <input type=\"text\" id=\"homepage\" name=\"homepage\" tabindex=\"3\" style=\"width: 350px;\" maxlength=\"50\" value=\"". htmlspecialchars($form_values['homepage'], ENT_QUOTES). "\">
                    </div>
                </div>

                <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 25%; float: left;\">Text:";
                    if ($g_preferences['enable_bbcode'] == 1)
                    {
                      echo "<br><br>
                      <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\" tabindex=\"4\">Text formatieren</a>";
                    }
                    echo "</div>
                    <div style=\"text-align: left; vertical-align: top; margin-left: 27%;\">
                        <textarea id=\"entry\" name=\"entry\" tabindex=\"4\" style=\"width: 350px;\" rows=\"10\" cols=\"40\">". htmlspecialchars($form_values['text'], ENT_QUOTES). "</textarea>&nbsp;<span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
                    </div>
                </div>";

                // Nicht eingeloggte User bekommen jetzt noch das Captcha praesentiert,
                // falls es in den Orgaeinstellungen aktiviert wurde...
                if (!$g_session_valid && $g_preferences['enable_guestbook_captcha'] == 1)
                {
                    echo "

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: left; margin-left: 27%;\">
                            <img src=\"$g_root_path/adm_program/system/captcha_class.php?id=". time(). "\" border=\"0\" alt=\"Captcha\" />
                        </div>
                    </div>

                    <div style=\"margin-top: 6px;\">
                           <div style=\"text-align: right; width: 25%; float: left;\">Best&auml;tigungscode:</div>
                           <div style=\"text-align: left; margin-left: 27%;\">
                               <input type=\"text\" id=\"captcha\" name=\"captcha\" tabindex=\"5\" style=\"width: 200px;\" maxlength=\"8\" value=\"\">
                               <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
                               <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help','Message','width=400,height=320,left=310,top=200,scrollbars=yes')\">
                           </div>
                    </div>";
                }


                echo "

                <hr width=\"85%\" />

                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\" tabindex=\"6\">
                        <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\"
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                        &nbsp;Zur&uuml;ck</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button name=\"speichern\" type=\"submit\" value=\"speichern\" tabindex=\"7\">
                        <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\"
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                        &nbsp;Speichern</button>
                </div>
            </div>
        </form>
    </div>";

    if ($g_current_user->id == 0)
    {
        $focusField = "name";
    }
    else
    {
        $focusField = "text";
    }

    echo"
    <script type=\"text/javascript\"><!--
        document.getElementById('$focusField').focus();
    --></script>";

   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>