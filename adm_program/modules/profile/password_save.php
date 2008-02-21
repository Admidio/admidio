<?php
/******************************************************************************
 * Password des Benutzers speichern
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * user_id - Password der uebergebenen ID aendern
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

$user = new User($g_db, $req_user_id);

if( ($_POST["old_password"] != "" || $g_current_user->isWebmaster() )
&& $_POST["new_password"] != ""
&& $_POST["new_password2"] != "")
{
    if(strlen($_POST["new_password"]) > 5)
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
                    $g_forum->userUpdate($user->getValue("usr_login_name"), $user->getValue("usr_password"), $user->getValue("E-Mail"));
                }

                // wenn das PW des eingeloggten Users geaendert wird, dann Session-Variablen aktualisieren
                if($user->getValue("usr_id") == $g_current_user->getValue("usr_id"))
                {
                    $g_current_user->setValue("usr_password", $user->getValue("usr_password"));
                }
            }
            else
            {
                $err_code = "old_password_wrong";
            }
        }
        else
        {
            $err_code = "passwords_not_equal";
        }
    }
    else
    {
        $err_code = "password_length";
    }
}
else
{
    $err_code = "fields";
}

// Html-Kopf ausgeben
$g_layout['title']    = "Passwort &auml;ndern";
$g_layout['includes'] = false;
require(THEME_SERVER_PATH. "/overall_header.php");

echo "<br />
<div class=\"groupBox\">
    <div class=\"groupBoxHeadline\">Hinweis</div>
    <div class=\"groupBoxBody\">";
        switch ($err_code)
        {
            case "fields":
                echo "Es sind nicht alle Felder aufgef&uuml;llt worden.";
                break;

            case "passwords_not_equal":
                echo "Das Passwort stimmt nicht mit der Wiederholung &uuml;berein.";
                break;

            case "old_password_wrong":
                echo "Das alte Passwort ist falsch.";
                break;

            case "password_length":
                echo "Das neue Passwort muss aus mindestens 6 Zeichen bestehen.";
                break;

            default:
                echo "Das Passwort wurde erfolgreich ge&auml;ndert.";
                break;
        }
    echo "</div>
</div>
<div style=\"padding-top: 10px;\" align=\"center\">";
    if($err_code == "")
    {
        echo "<button name=\"schliessen\" type=\"button\" value=\"schliessen\" onclick=\"window.close()\"><img src=\"". THEME_PATH. "/icons/door_in.png\" alt=\"Schließen\" />&nbsp;Schließen</button>";
    }
    else
    {
        echo "<button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\"><img src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" />&nbsp;Zurück</button>";
    }
echo "</div>";
        
require(THEME_SERVER_PATH. "/overall_footer.php");

?>