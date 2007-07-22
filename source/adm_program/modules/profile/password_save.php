<?php
/******************************************************************************
 * Password des Benutzers speichern
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id - Password der uebergebenen ID aendern
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

// nur Webmaster duerfen fremde Passwoerter aendern
if($g_current_user->isWebmaster()      == false 
&& $g_current_user->getValue("usr_id") != $_GET['user_id'])
{
    $g_message->show("norights");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_user_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET['user_id']))
{
    if(is_numeric($_GET['user_id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_user_id = $_GET['user_id'];
}

$err_code   = "";
$count_user = 0;

$user = new User($g_adm_con, $req_user_id);

if( ($_POST["old_password"] != "" || $g_current_user->isWebmaster() )
&& $_POST["new_password"] != ""
&& $_POST["new_password2"] != "")
{
    if ($_POST["new_password"] == $_POST["new_password2"])
    {
        // pruefen, ob altes Passwort korrekt eingegeben wurde
        $old_password_crypt = md5($_POST["old_password"]);

        // Webmaster duerfen Passwort so aendern
        if($user->getValue("usr_password") == $old_password_crypt || $g_current_user->isWebmaster())
        {
            $user->setValue("usr_password", md5($_POST["new_password"]));
            $user->save();

            // Paralell im Forum aendern, wenn g_forum gesetzt ist
            if($g_forum_integriert)
            {
                $g_forum->userUpdate($user->getValue("usr_login_name"), 1, $user->getValue("usr_password"), $user->getValue("E-Mail"));
            }

            // wenn das PW des eingeloggten Users geaendert wird, dann Session-Variablen aktualisieren
            if($user->getValue("usr_id") == $g_current_user->getValue("usr_id"))
            {
                $g_current_user->setValue("usr_password", $user->getValue("usr_password"));
            }
        }
        else
        {
            $err_code = "altes_passwort";
        }
    }
    else
    {
        $err_code = "passwort";
    }
}
else
{
    $err_code = "felder";
}

// Html-Kopf ausgeben
$g_layout['title'] = "Passwort &auml;ndern";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

echo "<br />
<div class=\"groupBox\" align=\"left\" style=\"padding: 10px\">";
    switch ($err_code)
    {
        case "felder":
            echo "Es sind nicht alle Felder aufgef&uuml;llt worden.";
            break;

        case "passwort":
            echo "Das Passwort stimmt nicht mit der Wiederholung &uuml;berein.";
            break;

        case "altes_passwort":
            echo "Das alte Passwort ist falsch.";
            break;

        default:
            echo "Das Passwort wurde erfolgreich ge&auml;ndert.";
            break;
    }
echo "</div>
<div style=\"padding-top: 10px;\" align=\"center\">";
    if($err_code == "")
    {
        echo "<button name=\"schliessen\" type=\"button\" value=\"schliessen\" onclick=\"window.close()\">
        <img src=\"$g_root_path/adm_program/images/door_in.png\" alt=\"Schlie&szlig;en\">
        &nbsp;Schlie&szlig;en</button>";
    }
    else
    {
        echo "<button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
        <img src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\">
        &nbsp;Zur&uuml;ck</button>";
    }
echo "</div>";
        
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>