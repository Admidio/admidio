<?php
/******************************************************************************
 * Passwort neu vergeben
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id     - Passwort der übergebenen User-Id aendern
 * mode   : 0 - (Default) Anzeige des Passwordaenderungsformulars
 *          1 - Passwortaenderung wird verarbeitet
 *
 *****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");
 
// nur Webmaster duerfen fremde Passwoerter aendern
if(!$g_current_user->isWebmaster() && $g_current_user->getValue("usr_id") != $_GET['usr_id'])
{
    $g_message->show("norights", "", "", false);
}

// Uebergabevariablen pruefen

if(isset($_GET["usr_id"]) && is_numeric($_GET["usr_id"]) == false)
{
    $g_message->show("invalid", "", "", false);
}

if(isset($_GET["mode"]) && is_numeric($_GET["mode"]) && $_GET["mode"] == 1)
{
    /***********************************************************************/
    /* Formular verarbeiten */
    /***********************************************************************/
    
    if( (strlen($_POST["old_password"]) > 0 || $g_current_user->isWebmaster() )
    && strlen($_POST["new_password"]) > 0
    && strlen($_POST["new_password2"]) > 0)
    {
        if(strlen($_POST["new_password"]) > 5)
        {
            if ($_POST["new_password"] == $_POST["new_password2"])
            {
                // pruefen, ob altes Passwort korrekt eingegeben wurde              
                $user = new User($g_db, $_GET["usr_id"]);
                $old_password_crypt = md5($_POST["old_password"]);

                // Webmaster duerfen Passwort so aendern
                if($user->getValue("usr_password") == $old_password_crypt || $g_current_user->isWebmaster())
                {
                    $user->setValue("usr_password", md5($_POST["new_password"]));
                    $user->save();

                    // Paralell im Forum aendern, wenn g_forum gesetzt ist
                    if($g_preferences['enable_forum_interface'])
                    {
                        $g_forum->userUpdate($user->getValue("usr_login_name"), $user->getValue("usr_password"), $user->getValue("E-Mail"));
                    }

                    // wenn das PW des eingeloggten Users geaendert wird, dann Session-Variablen aktualisieren
                    if($user->getValue("usr_id") == $g_current_user->getValue("usr_id"))
                    {
                        $g_current_user->setValue("usr_password", $user->getValue("usr_password"));
                    }

                    $g_message->setCloseButton();
                    $g_message->show("password_changed", "", "Hinweis", false);
                }
                else
                {
                    $g_message->show("password_old_wrong", "", "", false);
                }
            }
            else
            {
                $g_message->show("passwords_not_equal", "", "", false);
            }
        }
        else
        {
            $g_message->show("password_length", "", "", false);
        }
    }
    else
    {
        $g_message->show("felder", "", "", false);
    }
}
else
{
    /***********************************************************************/
    /* Passwortformular anzeigen */
    /***********************************************************************/

    // Html-Kopf ausgeben
    $g_layout['title']    = "Passwort ändern";
    $g_layout['includes'] = false;
    require(THEME_SERVER_PATH. "/overall_header.php");

    // Html des Modules ausgeben
    echo '
    <form action="'. $g_root_path. '/adm_program/modules/profile/password.php?usr_id='. $_GET['usr_id']. '&mode=1" method="post">
    <div class="formLayout" id="password_form" style="width: 300px">
        <div class="formHead">'. $g_layout['title']. '</div>
        <div class="formBody">
            <ul class="formFieldList">
                <li>
                    <dl>
                        <dt><label for="old_password">Altes Passwort:</label></dt>
                        <dd><input type="password" id="old_password" name="old_password" size="10" maxlength="20" /></dd>
                    </dl>
                </li>
                <li><hr /></li>
                <li>
                    <dl>
                        <dt><label for="new_password">Neues Passwort:</label></dt>
                        <dd><input type="password" id="new_password" name="new_password" size="10" maxlength="20" /></dd>
                    </dl>
                </li>
                <li>
                    <dl>
                        <dt><label for="new_password2">Wiederholen:</label></dt>
                        <dd><input type="password" id="new_password2" name="new_password2" size="10" maxlength="20" /></dd>
                    </dl>
                </li>
            </ul>

            <hr />

            <div class="formSubmit">
                <button name="close" type="button" value="Schließen" onclick="window.close()"><img src="'. THEME_PATH. '/icons/door_in.png" alt="Schließen" />&nbsp;Schließen</button>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <button name="save" type="submit" value="Speichern"><img src="'. THEME_PATH. '/icons/disk.png" alt="Speichern" />&nbsp;Speichern</button>
            </div>
        </div>
    </form>

    <script type="text/javascript"><!--
        document.getElementById(\'old_password\').focus();
    --></script>';
        
    require(THEME_SERVER_PATH. "/overall_footer.php");
}

?>