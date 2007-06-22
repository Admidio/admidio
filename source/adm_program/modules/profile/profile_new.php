<?php
/******************************************************************************
 * Profil bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id :  ID des Benutzers, dessen Profil bearbeitet werden soll
 * new_user : 0 - (Default) vorhandenen User bearbeiten
 *            1 - Dialog um neue Benutzer hinzuzufuegen.
 *            2 - Dialog um Registrierung entgegenzunehmen
 *            3 - Registrierung zuordnen/akzeptieren
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
// Registrierung muss ausgeloggt moeglich sein
if(isset($_GET['new_user']) && $_GET['new_user'] != 2)
{
    require("../../system/login_valid.php");
}

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]))
{
    if(is_numeric($_GET["user_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $usr_id = $_GET["user_id"];
}
else
{
    $usr_id = 0;
}

// pruefen, ob Modus neues Mitglied oder Registrierung erfassen
if(isset($_GET['new_user']))
{
    if(is_numeric($_GET['new_user']))
    {
        $new_user = $_GET['new_user'];
    }
    else
    {
        $new_user = 0;
    }
}
else
{
    $new_user = 0;
}

if($new_user == 1 || $new_user == 2)
{
    $usr_id = 0;
}

// pruefen, ob Modul aufgerufen werden darf
if($new_user == 2)
{
    // Registrierung deaktiviert, also auch diesen Modus sperren
    if($g_preferences['registration_mode'] == 0)
    {
        $g_message->show("module_disabled");
    }
}
else
{
    // prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
    if($g_current_user->editProfile($usr_id) == false)
    {
        $g_message->show("norights");
    }


}

// User auslesen
$user = new User($g_adm_con, $usr_id);

if($new_user == 0)
{
    // jetzt noch schauen, ob User ueberhaupt Mitglied in der Gliedgemeinschaft ist
    if(isMember($usr_id) == false)
    {
        // pruefen, ob der User noch in anderen Organisationen aktiv ist
        $sql    = "SELECT *
                     FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. "
                    WHERE rol_valid   = 1
                      AND rol_cat_id  = cat_id
                      AND cat_org_id <> $g_current_organization->id
                      AND mem_rol_id  = rol_id
                      AND mem_valid   = 1
                      AND mem_usr_id  = $usr_id ";
        $result      = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);
        $b_other_orga = false;

        if(mysql_num_rows($result) > 0)
        {
            // User, der woanders noch aktiv ist, darf in dieser Orga nicht bearbeitet werden
            // falls doch eine Registrierung vorliegt, dann darf Profil angezeigt werden
            if($user->getValue("usr_valid") != 0 
            || $user->getValue("usr_reg_org_shortname") != $g_current_organization->shortname)
            {
                $g_message->show("norights");
            }
        }

    }
}

$b_history = false;     // History-Funktion bereits aktiviert ja/nein
$_SESSION['navigation']->addUrl($g_current_url);

if(isset($_SESSION['profile_request']))
{
    $form_values = $_SESSION['profile_request'];
    
    foreach($user->db_user_fields as $key => $value)
    {
        $field_name = "usf-". $value['usf_id'];
        if(isset($form_values[$field_name]))
        {
            // Datum rest einmal wieder in MySQL-Format bringen
            if($value['usf_type'] == "DATE" && strlen($form_values[$field_name]) > 0)
            {
                $form_values[$field_name] = dtFormatDate($form_values[$field_name], "Y-m-d");
            }
            $user->setValue($value['usf_name'], $form_values[$field_name]);
        }
    }
    
    unset($_SESSION['profile_request']);
    $b_history = true;
}
elseif($usr_id > 0)
{
    // um die Zurueck-Funktion zu vereinfachen, deutsche Zeitangaben nutzen
    $user->birthday = mysqldate('d.m.y', $user->birthday);
}

// diese Funktion gibt den Html-Code fuer ein Feld mit Beschreibung wieder
// dabei wird der Inhalt richtig formatiert
function getFieldCode($field, $user, $new_user)
{
    global $g_preferences, $g_root_path, $g_current_user;
    $value    = "";
    $readonly = "";
    
    // Kennzeichen fuer readonly setzen
    $readonly = "";
    if($field['usf_disabled'] == 1 && $user->editUser() == false && $new_user == 0)
    {
        $readonly = " class=\"readonly\" readonly ";
    }

    // Code fuer die einzelnen Felder zusammensetzen    
    if($field['usf_name'] == "Geschlecht")
    {
        $checked_female = "";
        $checked_male   = "";
        if($new_user == 0 && $field['usd_value'] == 2)
        {
            $checked_female = " checked ";
        }
        elseif($new_user == 0 && $field['usd_value'] == 1)
        {
            $checked_male = " checked ";
        }
        $value = "<input type=\"radio\" id=\"female\" name=\"gender\" value=\"2\" $checked_female $readonly >
            <label for=\"female\"><img src=\"$g_root_path/adm_program/images/female.png\" title=\"weiblich\" alt=\"weiblich\"></label>
            &nbsp;
            <input type=\"radio\" id=\"male\" name=\"gender\" value=\"1\" $checked_male $readonly >
            <label for=\"male\"><img src=\"$g_root_path/adm_program/images/male.png\" title=\"m&auml;nnlich\" alt=\"m&auml;nnlich\"></label>";
    }
    elseif($field['usf_name'] == "Land")
    {
        //Laenderliste oeffnen
        $landlist = fopen(SERVER_PATH. "/adm_program/system/staaten.txt", "r");
        $value = "
        <select size=\"1\" name=\"usf-". $field['usf_id']. "\">
            <option value=\"\"";
                if(strlen($g_preferences['default_country']) == 0
                && strlen($field['usd_value']) == 0)
                {
                    $value = $value. " selected ";
                }
            $value = $value. "></option>";
            if(strlen($g_preferences['default_country']) > 0)
            {
                $value = $value. "<option value=\"". $g_preferences['default_country']. "\">". $g_preferences['default_country']. "</option>
                <option value=\"\">--------------------------------</option>\n";
            }

            $land = utf8_decode(trim(fgets($landlist)));
            while (!feof($landlist))
            {
                $value = $value. "<option value=\"$land\"";
                     if($new_user > 0 && $land == $g_preferences['default_country'])
                     {
                        $value = $value. " selected ";
                     }
                     if(!$new_user > 0 && $land == $field['usd_value'])
                     {
                        $value = $value. " selected ";
                     }
                $value = $value. ">$land</option>\n";
                $land = utf8_decode(trim(fgets($landlist)));
            }
        $value = $value. "</select>";
    }
    elseif($field['usf_type'] == "CHECKBOX")
    {
        $mode = "";
        if($field['usd_value'] == 1)
        {
            $mode = "checked";
        }
        $value = "<input type=\"checkbox\" id=\"usf-". $field['usf_id']. "\" name=\"usf-". $field['usf_id']. "\" $mode $readonly value=\"1\">";
    }
    else
    {
        if($field['usf_type'] == "DATE")
        {
            $width = "80px";
            $maxlength = "10";
            if(strlen($field['usd_value']) > 0)
            {
                $field['usd_value'] = mysqldate('d.m.y', $field['usd_value']);
            }
        }
        elseif($field['usf_type'] == "EMAIL" || $field['usf_type'] == "URL" || $field['usf_type'] == "BIG_TEXT")
        {
            $width     = "300px";
            if($field['usf_type'] == "BIG_TEXT")
            {
                $maxlength = "255";
            }
            else
            {
                $maxlength = "50";
            }
        }
        else
        {
            $width = "200px";
            $maxlength = "50";
        }
        
        $value = "<input type=\"text\" id=\"usf-". $field['usf_id']. "\" name=\"usf-". $field['usf_id']. "\" style=\"width: $width;\" maxlength=\"$maxlength\" $readonly value=\"". $field['usd_value']. "\" $readonly >";
    }
    
    // Icons der Messenger anzeigen
    $icon = "";
    if($field['cat_name'] == "Messenger")
    {
        if($field['usf_name'] == 'AIM')
        {
            $icon = "aim.png";
        }
        elseif($field['usf_name'] == 'Google Talk')
        {
            $icon = "google.gif";
        }
        elseif($field['usf_name'] == 'ICQ')
        {
            $icon = "icq.png";
        }
        elseif($field['usf_name'] == 'MSN')
        {
            $icon = "msn.png";
        }
        elseif($field['usf_name'] == 'Skype')
        {
            $icon = "skype.png";
        }
        elseif($field['usf_name'] == 'Yahoo')
        {
            $icon = "yahoo.png";
        }
        $icon = "<img src=\"$g_root_path/adm_program/images/$icon\" style=\"vertical-align: middle;\" alt=\"". $field['usf_name']. "\">&nbsp;";
    }
        
    // Kennzeichen fuer Pflichtfeld setzen
    $mandatory = "";
    if($field['usf_mandatory'] == 1)
    {
        $mandatory = "&nbsp;<span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>";
    }
    
    // Fragezeichen mit Feldbeschreibung anzeigen, wenn diese hinterlegt ist
    $description = "";
    if(strlen($field['usf_description']) > 0 && $field['cat_name'] != "Messenger")
    {
        $description = "&nbsp;<img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\"
        vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=user_field_description&amp;err_text=". urlencode($field['usf_name']). "','Message','width=400,height=400,left=310,top=200,scrollbars=yes')\">";
    }
    
    // nun den Html-Code fuer das Feld zusammensetzen
    $html = "
        <div style=\"margin-top: 5px;\">
            <div style=\"text-align: left; width: 25%; float: left;\">$icon". $field['usf_name']. ":&nbsp;</div>
            <div style=\"text-align: left; margin-left: 27%;\">$value$mandatory$description</div>
        </div>";
             
    return $html;
}

// Html-Kopf ausgeben
$g_layout['title'] = "Profil bearbeiten";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

echo "
<form action=\"$g_root_path/adm_program/modules/profile/profile_save.php?user_id=$usr_id&amp;new_user=$new_user\" method=\"post\" name=\"ProfilAnzeigen\">
    <div class=\"formHead\">";
        if($new_user == 1)
        {
            echo "Neuer Benutzer";
        }
        elseif($new_user == 2)
        {
            echo "Registrieren";
        }
        elseif($usr_id == $g_current_user->getValue("usr_id"))
        {
            echo "Mein Profil";
        }
        else
        {
            echo "Profil von ". $user->first_name. " ". $user->last_name;
        }
    echo "</div>
    <div class=\"formBody\">"; 
        // *******************************************************************************
        // Schleife ueber alle Kategorien und Felder ausser den Stammdaten
        // *******************************************************************************

        $category = "";
        $margintop = "0px";
        
        foreach($user->db_user_fields as $key => $value)
        {
            // Kategorienwechsel den Kategorienheader anzeigen
            // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
            if($category != $value['cat_name']
            && (  $new_user != 2
               || ( $new_user == 2 && $value['usf_mandatory'] == 1 )))
            {
                if(strlen($category) > 0)
                {
                    echo "</div>";
                    $margintop = "15px";
                }
                $category = $value['cat_name'];

                echo "<div class=\"groupBox\" style=\"margin-top: $margintop; text-align: left;\">
                    <div class=\"groupBoxHeadline\">". $value['cat_name']. "</div>";
            }

            // bei schneller Registrierung duerfen nur die Pflichtfelder ausgegeben werden
            if($new_user != 2
            || ( $new_user == 2 && $value['usf_mandatory'] == 1 ))
            {
                // Html des Feldes ausgeben
                echo getFieldCode($value, $user, $new_user);
            }

            if($value['usf_name'] == "Vorname")
            {
                // Nach dem Vornamen noch Benutzername und Passwort anzeigen
                if($usr_id > 0 || $new_user == 2)
                {
                    echo "<div style=\"margin-top: 5px;\">
                        <div style=\"text-align: left; width: 25%; float: left;\">Benutzername:&nbsp;</div>
                        <div style=\"text-align: left; margin-left: 27%;\">
                            <input type=\"text\" name=\"usr_login_name\" style=\"width: 200px;\" maxlength=\"20\" value=\"". $user->getValue("usr_login_name"). "\" ";
                            if($g_current_user->isWebmaster() == false && $new_user == 0)
                            {
                                echo " class=\"readonly\" readonly ";
                            }
                            echo " />";
                        if($new_user > 0)
                        {
                            echo "&nbsp;<span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>&nbsp;
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=nickname','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">";
                        }
                    echo "</div>
                    </div>";

                    if($new_user == 2)
                    {
                        echo "<div style=\"margin-top: 5px;\">
                            <div style=\"text-align: left; width: 25%; float: left;\">Passwort:&nbsp;</div>
                            <div style=\"text-align: left; margin-left: 27%;\">
                                <input type=\"password\" name=\"usr_password\" style=\"width: 130px;\" maxlength=\"20\" />
                                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>&nbsp;
                                <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=password','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                            </div>
                        </div>
                        <div style=\"margin-top: 5px;\">
                            <div style=\"text-align: left; width: 25%; float: left;\">Passwort (Wdh):&nbsp;</div>
                            <div style=\"text-align: left; margin-left: 27%;\">
                                <input type=\"password\" name=\"password2\" style=\"width: 130px;\" maxlength=\"20\" />
                                <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
                            </div>
                        </div>";
                    }
                    else
                    {
                        // eigenes Passwort aendern, nur Webmaster duerfen Passwoerter von anderen aendern
                        if($g_current_user->isWebmaster() || $g_current_user->getValue("usr_id") == $usr_id )
                        {
                            echo "<div style=\"margin-top: 5px;\">
                                <div style=\"text-align: left; width: 25%; float: left;\">Passwort:&nbsp;</div>
                                <div style=\"text-align: left; margin-left: 27%;\">
                                    <button name=\"password\" type=\"button\" value=\"Passwort &auml;ndern\" onclick=\"window.open('password.php?user_id=$usr_id','Titel','width=350,height=260,left=310,top=200')\">
                                    <img src=\"$g_root_path/adm_program/images/key.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Passwort &auml;ndern\">
                                    &nbsp;Passwort &auml;ndern</button>
                                </div>
                            </div>";
                        }
                    }
                    echo "<hr class=\"formLine\" width=\"85%\">";
                }
            }
        }
        
        // letzte Box noch schliesen
        echo "</div>";

        // User, die sich registrieren wollen, bekommen jetzt noch das Captcha praesentiert,
        // falls es in den Orgaeinstellungen aktiviert wurde...
        if ($new_user == 2 && $g_preferences['enable_registration_captcha'] == 1)
        {
            echo "
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: left; margin-left: 32%;\">
                    <img src=\"$g_root_path/adm_program/system/captcha_class.php?id=". time(). "\" border=\"0\" alt=\"Captcha\" />
                </div>
            </div>

            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Best&auml;tigungscode:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">
                    <input type=\"text\" id=\"captcha\" name=\"captcha\" style=\"width: 200px;\" maxlength=\"8\" value=\"\">&nbsp;<span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>&nbsp;
                    <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help','Message','width=400,height=320,left=310,top=200,scrollbars=yes')\">
                </div>
            </div>
            <hr class=\"formLine\" width=\"85%\">";
        }

        // Bild und Text fuer den Speichern-Button
        if($new_user == 2)
        {
            // Registrierung
            $btn_image = "email.png";
            $btn_text  = "Abschicken";
        }
        else
        {
            $btn_image = "disk.png";
            $btn_text  = "Speichern";
        }

        echo "
        <div style=\"margin-top: 6px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\">
            <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
            &nbsp;Zur&uuml;ck</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
            <img src=\"$g_root_path/adm_program/images/$btn_image\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"$btn_text\">
            &nbsp;$btn_text</button>
        </div>";

        if($new_user == 0 && $user->getValue("usr_usr_id_change") > 0)
        {
            // Angabe ueber die letzten Aenderungen
            if($user->getValue("usr_usr_id_change") == $g_current_user->getValue("usr_id"))
            {
                $user_last_change = $g_current_user;
            }
            else
            {
                $user_last_change = new User($g_adm_con, $user->getValue("usr_usr_id_change"));
            }

            echo "<div style=\"margin-top: 6px;\"><span style=\"font-size: 10pt\">
                Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $user->getValue("usr_last_change")).
                " durch ". $user_last_change->getValue("Vorname"). " ". $user_last_change->getValue("Nachname"). "</span>
            </div>";
        }
    echo "</div>
</form>

<script type=\"text/javascript\"><!--\n";
    if($g_current_user->editUser() || $new_user > 0)
    {
        echo "document.getElementById('last_name').focus();";
    }
    else
    {
        echo "document.getElementById('address').focus();";
    }
echo "\n--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>