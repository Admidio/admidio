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

$user_id = $_GET['user_id'];

// nur Webmaster duerfen fremde Passwoerter aendern
if(!hasRole("Webmaster") && $g_current_user->id != $user_id)
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]) && is_numeric($_GET["user_id"]) == false)
{
    $g_message->show("invalid");
}

$err_code   = "";
$count_user = 0;

$user = new User($g_adm_con);
$user->getUser($user_id);

if( ($_POST["old_password"] != "" || hasRole('Webmaster') )
&& $_POST["new_password"] != ""
&& $_POST["new_password2"] != "")
{
    if ($_POST["new_password"] == $_POST["new_password2"])
    {
        // pruefen, ob altes Passwort korrekt eingegeben wurde
        $old_password_crypt = md5($_POST["old_password"]);

        // Webmaster duerfen Passwort so aendern
        if($user->password == $old_password_crypt || hasRole('Webmaster'))
        {
            $user->password = md5($_POST["new_password"]);
            $user->update($g_current_user->id);

            if($g_forum == 1)
            {
                forum_update_user($user->login_name, 1, $user->password, $user->email, $g_forum_db, $g_forum_con, $g_adm_db, $g_adm_con, $g_forum_praefix);
            }

            // wenn das PW des eingeloggten Users geaendert wird, dann Session-Variablen aktualisieren
            if($user->id == $g_current_user->id)
            {
                $g_current_user->password = $user->password;
                $_SESSION['g_current_user'] = $g_current_user;
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

echo "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?". ">
<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 TRANSITIONAL//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\">
<head>
    <!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->
    <title>Passwort &auml;ndern</title>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\" />
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\" />

    <!--[if lt IE 7]>
    <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->
</head>

<body>
    <div align=\"center\"><br />
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
                <img src=\"$g_root_path/adm_program/images/door_in.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\">
                &nbsp;Schlie&szlig;en</button>";
            }
            else
            {
                echo "<button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
                <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\">
                &nbsp;Zur&uuml;ck</button>";
            }
        echo "</div>
    </div>
</body>
</html>";
?>