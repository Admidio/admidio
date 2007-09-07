<?php
/******************************************************************************
 * Passwort neu vergeben
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * user_id     -   Passwort der übergebenen user_id aendern
 *
 *****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");
 
// nur Webmaster d&uuml;rfen fremde Passwoerter aendern
if(!$g_current_user->isWebmaster() && $g_current_user->getValue("usr_id") != $_GET['user_id'])
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]) && is_numeric($_GET["user_id"]) == false)
{
    $g_message->show("invalid");
}

// Html-Kopf ausgeben
$g_layout['title']    = "Passwort &auml;ndern";
$g_layout['includes'] = false;
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<form action=\"$g_root_path/adm_program/modules/profile/password_save.php?user_id=". $_GET['user_id']. "\" method=\"post\">
<div class=\"formLayout\" id=\"password_form\" style=\"width: 300px\">
    <div class=\"formHead\">Passwort &auml;ndern</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt><label for=\"old_password\">Altes Passwort:</label></dt>
                    <dd><input type=\"password\" id=\"old_password\" name=\"old_password\" size=\"10\" maxlength=\"20\" /></dd>
                </dl>
            </li>
            <li><hr /></li>
            <li>
                <dl>
                    <dt><label for=\"new_password\">Neues Passwort:</label></dt>
                    <dd><input type=\"password\" id=\"new_password\" name=\"new_password\" size=\"10\" maxlength=\"20\" /></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"new_password2\">Wiederholen:</label></dt>
                    <dd><input type=\"password\" id=\"new_password2\" name=\"new_password2\" size=\"10\" maxlength=\"20\" /></dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"schliessen\" type=\"button\" value=\"schliessen\" onclick=\"window.close()\">
                <img src=\"$g_root_path/adm_program/images/door_in.png\" alt=\"Schließen\" />
                &nbsp;Schließen</button>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                <img src=\"$g_root_path/adm_program/images/disk.png\" alt=\"Speichern\" />
                &nbsp;Speichern</button>
        </div>
    </div>
</form>

<script type=\"text/javascript\"><!--
    document.getElementById('old_password').focus();
--></script>";
    
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>