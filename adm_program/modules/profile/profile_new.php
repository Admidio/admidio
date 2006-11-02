<?php
/******************************************************************************
 * Profil bearbeiten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id :  ID des Benutzers, dessen Profil bearbeitet werden soll
 * new_user : 0 - (Default) vorhandenen User bearbeiten
 *            1 - Dialog um neue Benutzer hinzuzufuegen.
 *            2 - Dialog um Registrierung entgegenzunehmen
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
// Registrierung muss ausgeloggt moeglich sein
if($_GET['new_user'] != 2)
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
if(array_key_exists("new_user", $_GET))
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


if($new_user != 2)
{
    // prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
    if(editUser() == false && $_GET['user_id'] != $g_current_user->id)
    {
        $g_message->show("norights");
    }
}

if($new_user == 0)
{
    // jetzt noch schauen, ob User ueberhaupt Mitglied in der Gliedgemeinschaft ist
    if(isMember($usr_id) == false)
    {
        $g_message->show("norights");
    }    
}

$b_history = false;     // History-Funktion bereits aktiviert ja/nein
$user      = new User($g_adm_con);
$_SESSION['navigation']->addUrl($g_current_url);

if(isset($_SESSION['profile_request']))
{
    $form_values = $_SESSION['profile_request'];
    $user->last_name  = $form_values['last_name'];
    $user->first_name = $form_values['first_name'];
    $user->login_name = $form_values['login_name'];
    $user->email      = $form_values['email'];
    // immer fuellen, ausser bei der schnellen Registrierung
    if($new_user != 2 || $g_preferences['registration_mode'] != 1)
    {
        $user->address    = $form_values['address'];
        $user->zip_code   = $form_values['zip_code'];
        $user->city       = $form_values['city'];
        $user->country    = $form_values['country'];
        $user->phone      = $form_values['phone'];
        $user->mobile     = $form_values['mobile'];
        $user->fax        = $form_values['fax'];
        $user->homepage   = $form_values['homepage'];
        $user->birthday   = $form_values['birthday'];
        $user->gender     = $form_values['gender'];
    }
    unset($_SESSION['profile_request']);
    $b_history = true;
}
elseif($usr_id > 0)
{ 
    // User auslesen
    $user->GetUser($usr_id);
    // um die Zurueck-Funktion zu vereinfachen, deutsche Zeitangaben nutzen
    $user->birthday = mysqldate('d.m.y', $user->birthday);
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_current_organization->longname - Profil bearbeiten</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if lt IE 7]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";

   require("../../../adm_config/header.php");
echo "</head>";
require("../../../adm_config/body_top.php");
    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <form action=\"profile_save.php?user_id=$usr_id&amp;new_user=$new_user\" method=\"post\" name=\"ProfilAnzeigen\">
            <div class=\"formHead\">";
                if($new_user == 1)
                {
                    echo strspace("Neuer Benutzer", 2);
                }
                elseif($new_user == 2)
                {
                    echo strspace("Registrieren", 2);
                }
                elseif($usr_id == $g_current_user->id)
                {
                    echo strspace("Mein Profil", 2);
                }
                else
                {
                    echo strspace("Profil von ". $user->first_name. " ". $user->last_name, 1);
                }
            echo "</div>
            <div class=\"formBody\">
                <div>
                    <div style=\"text-align: right; width: 30%; float: left;\">Nachname:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">
                        <input type=\"text\" id=\"last_name\" name=\"last_name\" style=\"width: 200px;\" maxlength=\"30\" value=\"$user->last_name\" ";
                        if(hasRole('Webmaster') == false && $new_user == 0)
                        {
                            echo " class=\"readonly\" readonly ";
                        }
                        echo " />";
                        if($new_user > 0)
                        {
                            echo "&nbsp;*";
                        }
                    echo "</div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Vorname:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">
                        <input type=\"text\" name=\"first_name\" style=\"width: 200px;\" maxlength=\"30\" value=\"$user->first_name\" ";
                        if(hasRole('Webmaster') == false && $new_user == 0)
                        {
                            echo " class=\"readonly\" readonly ";
                        }
                        echo " />";
                        if($new_user > 0)
                        {
                            echo "&nbsp;*";
                        }
                    echo "</div>
                </div>";
                if($usr_id > 0 || $new_user == 2)
                {
                    // bei der schnellen Registrierung hier schon das E-Mailfeld anzeigen, 
                    // da der untere Block nicht angezeigt wird
                    if($new_user == 2 && $g_preferences['registration_mode'] == 1)
                    {
                        echo "
                        <div style=\"margin-top: 6px;\">
                            <div style=\"text-align: right; width: 30%; float: left;\">E-Mail:</div>
                            <div style=\"text-align: left; margin-left: 32%;\">
                                <input type=\"text\" name=\"email\" style=\"width: 300px;\" maxlength=\"50\" value=\"$user->email\" />&nbsp;*&nbsp;
                                <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=email','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                            </div>
                        </div>
                        <hr width=\"85%\">";
                    }
                    echo "<div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Benutzername:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"text\" name=\"login_name\" style=\"width: 130px;\" maxlength=\"20\" value=\"$user->login_name\" ";
                            if(hasRole('Webmaster') == false && $new_user == 0)
                            {
                                echo " class=\"readonly\" readonly ";
                            }
                            echo " />";
                        if($new_user > 0)
                        {
                            echo "&nbsp;*&nbsp;
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=nickname','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">";
                        }
                    echo "</div>
                    </div>";

                    if($new_user == 2)
                    {
                        echo "<div style=\"margin-top: 6px;\">
                            <div style=\"text-align: right; width: 30%; float: left;\">Passwort:</div>
                            <div style=\"text-align: left; margin-left: 32%;\">
                                <input type=\"password\" name=\"password\" style=\"width: 130px;\" maxlength=\"20\" />&nbsp;*&nbsp;
                                <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=password','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                            </div>
                        </div>
                        <div style=\"margin-top: 6px;\">
                            <div style=\"text-align: right; width: 30%; float: left;\">Passwort (Wdh):</div>
                            <div style=\"text-align: left; margin-left: 32%;\">
                                <input type=\"password\" name=\"password2\" style=\"width: 130px;\" maxlength=\"20\" />&nbsp;*
                            </div>
                        </div>";
                    }
                    else
                    {
                        // eigenes Passwort aendern, nur Webmaster duerfen Passwoerter von anderen aendern
                        if(hasRole('Webmaster') || $g_current_user->id == $usr_id )
                        {
                            echo "<div style=\"margin-top: 6px;\">
                                <div style=\"text-align: right; width: 30%; float: left;\">Passwort:</div>
                                <div style=\"text-align: left; margin-left: 32%;\">
                                    <button name=\"password\" type=\"button\" value=\"Passwort &auml;ndern\" onclick=\"window.open('password.php?user_id=$usr_id','Titel','width=350,height=260,left=310,top=200')\">
                                    <img src=\"$g_root_path/adm_program/images/key.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Passwort &auml;ndern\">
                                    &nbsp;Passwort &auml;ndern</button>
                                </div>
                            </div>";
                        }
                    }
                }

                // immer anzeigen, ausser bei der schnellen Registrierung
                if($new_user != 2 || $g_preferences['registration_mode'] != 1)
                {
                    echo "<hr width=\"85%\">

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Adresse:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"text\" id=\"address\" name=\"address\" style=\"width: 300px;\" maxlength=\"50\" value=\"$user->address\" />
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Postleitzahl:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"text\" name=\"zip_code\" style=\"width: 80px;\" maxlength=\"10\" value=\"$user->zip_code\" />
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Ort:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"text\" name=\"city\" style=\"width: 200px;\" maxlength=\"30\" value=\"$user->city\" />
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Land:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">";
                            //Laenderliste oeffnen
                            $landlist = fopen("../../system/staaten.txt", "r");
                            echo "
                            <select size=\"1\" name=\"country\" />
                                <option value=\"\"";
                                    if(strlen($g_preferences['default_country']) == 0
                                    && strlen($user->country) == 0)
                                    {
                                        echo " selected ";
                                    }
                                echo "></option>";
                                if(strlen($g_preferences['default_country']) > 0)
                                {
                                    echo "<option value=\"". $g_preferences['default_country']. "\">". $g_preferences['default_country']. "</option>
                                    <option value=\"\">--------------------------------</option>\n";
                                }

                                $land = utf8_decode(trim(fgets($landlist)));
                                while (!feof($landlist))
                                {
                                    echo"<option value=\"$land\"";
                                         if($new_user > 0 && $land == $g_preferences['default_country'])
                                         {
                                            echo " selected ";
                                         }
                                         if(!$new_user > 0 && $land == $user->country)
                                         {
                                            echo " selected ";
                                         }
                                    echo">$land</option>\n";
                                    $land = utf8_decode(trim(fgets($landlist)));
                                }    

                            echo"
                            </select>";
                        echo "</div>
                    </div>

                    <hr width=\"85%\">

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Telefon:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"text\" name=\"phone\" style=\"width: 130px;\" maxlength=\"20\" value=\"$user->phone\" />
                            &nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Tel.Nr.)</span>
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Handy:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"text\" name=\"mobile\" style=\"width: 130px;\" maxlength=\"20\" value=\"$user->mobile\" />
                            &nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Handynr.)</span>
                         </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Fax:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"text\" name=\"fax\" style=\"width: 130px;\" maxlength=\"20\" value=\"$user->fax\" />
                            &nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Faxnr.)</span>
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">E-Mail:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"text\" name=\"email\" style=\"width: 300px;\" maxlength=\"50\" value=\"$user->email\" />";
                            if($new_user == 2)
                            {
                                // bei erweiterter Registrierung ist dies ein Pflichtfeld
                                echo "&nbsp;*&nbsp;
                                <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=email','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">";
                            }
                        echo "</div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Homepage:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"text\" name=\"homepage\" style=\"width: 300px;\" maxlength=\"50\" value=\"$user->homepage\" />
                        </div>
                    </div>

                    <hr width=\"85%\">

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Geburtstag:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"text\" name=\"birthday\" style=\"width: 80px;\" maxlength=\"10\" value=\"$user->birthday\" />
                        </div>
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Geschlecht:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"radio\" id=\"female\" name=\"gender\" value=\"2\"";
                                if($new_user == 0 && $user->gender == 2)
                                    echo " checked ";
                                echo "><label for=\"female\"><img src=\"$g_root_path/adm_program/images/female.png\" title=\"weiblich\" alt=\"weiblich\"></label>
                            &nbsp;
                            <input type=\"radio\" id=\"male\" name=\"gender\" value=\"1\"";
                                if($new_user == 0 && $user->gender == 1)
                                    echo " checked ";
                                echo "><label for=\"male\"><img src=\"$g_root_path/adm_program/images/male.png\" title=\"m&auml;nnlich\" alt=\"m&auml;nnlich\"></label>
                        </div>
                    </div>";

                    // organisationsspezifische Felder einlesen
                    if($new_user > 0)
                    {
                        $sql = "SELECT *
                                  FROM ". TBL_USER_FIELDS. "
                                 WHERE usf_org_shortname = '$g_organization'
                                 ORDER BY usf_name ASC ";
                    }
                    else
                    {
                        $sql = "SELECT *
                                  FROM ". TBL_USER_FIELDS. " LEFT JOIN ". TBL_USER_DATA. "
                                    ON usd_usf_id = usf_id
                                   AND usd_usr_id = $user->id
                                 WHERE usf_org_shortname = '$g_organization' ";
                        if(!isModerator())
                        {
                            $sql = $sql. " AND usf_locked = 0 ";
                        }
                        $sql = $sql. " ORDER BY usf_name ASC ";
                    }
                    $result_field = mysql_query($sql, $g_adm_con);
                    db_error($result_field);

                    if(mysql_num_rows($result_field) > 0)
                    {
                        echo "<hr width=\"85%\">";
                    }

                    while($row = mysql_fetch_object($result_field))
                    {
                        echo "<div style=\"margin-top: 6px;\">
                            <div style=\"text-align: right; width: 30%; float: left;\">
                                $row->usf_name:
                            </div>
                            <div style=\"text-align: left; margin-left: 32%;\">";                        
                                // in Abhaengigkeit des Feldtypes wird das Eingabefeld erstellt
                                echo "<input type=\"";
                                if($row->usf_type == "CHECKBOX")
                                {
                                    echo "checkbox";
                                }
                                else
                                {
                                    echo "text";
                                }
                                echo "\" id=\"". urlencode($row->usf_name). "\" name=\"". urlencode($row->usf_name). "\" ";

                                if($row->usf_type == "CHECKBOX")
                                {
                                    if($b_history == true && isset($form_values[urlencode($row->usf_name)]) 
                                    && $form_values[urlencode($row->usf_name)] == 1)
                                    {
                                        // Zurueck-Navigation und Haeckchen war bereits gesetzt
                                        echo " checked ";
                                    }
                                    elseif($new_user == 0 && $row->usd_value == 1)
                                    {
                                        echo " checked ";
                                    }
                                    echo " value=\"1\" ";
                                }
                                else
                                {
                                    if($row->usf_type == "NUMERIC")
                                    {
                                        echo " style=\"width: 80px;\" maxlength=\"15\" ";
                                    }
                                    elseif($row->usf_type == "TEXT")
                                    {
                                        echo " style=\"width: 200px;\" maxlength=\"30\" ";
                                    }
                                    elseif($row->usf_type == "TEXT_BIG")
                                    {
                                        echo " style=\"width: 300px;\" maxlength=\"255\" ";
                                    }

                                    if($b_history == true)
                                    {
                                        echo " value=\"". $form_values[urlencode($row->usf_name)]. "\" ";
                                    }
                                    elseif(strlen($row->usd_value) > 0)
                                    {
                                        echo " value=\"$row->usd_value\" ";
                                    }
                                }
                                echo ">";
                                // Fragezeichen mit Feldbeschreibung anzeigen, wenn diese hinterlegt ist
                                if(strlen($row->usf_description) > 0)
                                {
                                    echo "&nbsp;<img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" 
                                    vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=user_field_description&err_text=". urlencode($row->usf_name). "','Message','width=400,height=400,left=310,top=200,scrollbars=yes')\">";
                                }
                            echo "</div>
                        </div>";
                    }

                    echo "<hr width=\"85%\">";

                    // alle zugeordneten Messengerdaten einlesen
                    $sql = "SELECT usf_name, usd_value
                              FROM ". TBL_USER_FIELDS. " LEFT JOIN ". TBL_USER_DATA. "
                                ON usd_usf_id = usf_id
                               AND usd_usr_id = $user->id
                             WHERE usf_org_shortname IS NULL
                               AND usf_type   = 'MESSENGER'
                             ORDER BY usf_name ASC ";
                    $result_msg = mysql_query($sql, $g_adm_con);
                    db_error($result_msg);

                    while($row = mysql_fetch_object($result_msg))
                    {
                        echo "<div style=\"margin-top: 6px;\">
                            <div style=\"text-align: right; width: 30%; float: left;\">
                                $row->usf_name:
                                <img src=\"$g_root_path/adm_program/images/";
                                if($row->usf_name == 'AIM')
                                {
                                    echo "aim.png";
                                }
                                elseif($row->usf_name == 'Google Talk')
                                {
                                    echo "google.gif";
                                }
                                elseif($row->usf_name == 'ICQ')
                                {
                                    echo "icq.png";
                                }
                                elseif($row->usf_name == 'MSN')
                                {
                                    echo "msn.png";
                                }
                                elseif($row->usf_name == 'Skype')
                                {
                                    echo "skype.png";
                                }
                                elseif($row->usf_name == 'Yahoo')
                                {
                                    echo "yahoo.png";
                                }
                                echo "\" style=\"vertical-align: middle;\" />
                            </div>
                            <div style=\"text-align: left; margin-left: 32%;\">";
                                if($b_history == true)
                                {
                                    $messenger_id = $form_values[urlencode($row->usf_name)];
                                }
                                else
                                {
                                    $messenger_id = $row->usd_value;
                                }
                                echo "<input type=\"text\" name=\"". urlencode($row->usf_name). "\" style=\"width: 200px;\" maxlength=\"50\" value=\"$messenger_id\" />
                            </div>
                        </div>";
                    }
                } // end ohne schnelle Registrierung

                 // User, die sich registrieren wollen, bekommen jetzt noch das Captcha praesentiert,
                 // falls es in den Orgaeinstellungen aktiviert wurde...
                 if ($new_user == 2 && $g_preferences['enable_registration_captcha'] == 1)
                 {
                     echo "
        
                     <div style=\"margin-top: 6px;\">
                         <div style=\"text-align: left; margin-left: 32%;\">
                             <img src=\"$g_root_path/adm_program/system/captcha_class.php\" border=\"0\" alt=\"Captcha\" />
                         </div>
                     </div>
        
                     <div style=\"margin-top: 6px;\">
                            <div style=\"text-align: right; width: 30%; float: left;\">Best&auml;tigungscode:</div>
                            <div style=\"text-align: left; margin-left: 32%;\">
                                <input type=\"text\" id=\"captcha\" name=\"captcha\" style=\"width: 200px;\" maxlength=\"8\" value=\"\">&nbsp;*&nbsp;
                                <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help','Message','width=400,height=320,left=310,top=200,scrollbars=yes')\">
                            </div>
                     </div>";
                 }
         
                // Bild und Text fuer den Speichern-Button
                if($new_user == 2)
                {
                    // Registrierung
                    $btn_image = "mail.png";
                    $btn_text  = "Abschicken";
                    // bei der Registrierung einfach auf die letzte Seite im Browsercache,
                    // da Loginseite nicht im Stack liegt
                    $back_link = "history.back();";
                }
                else
                {
                    $btn_image = "disk.png";
                    $btn_text  = "Speichern";
                    $back_link = "self.location.href='$g_root_path/adm_program/system/back.php'";
                }

                echo "<hr width=\"85%\">

                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"$back_link\">
                    <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                    &nbsp;Zur&uuml;ck</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

                    <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                    <img src=\"$g_root_path/adm_program/images/$btn_image\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"$btn_text\">
                    &nbsp;$btn_text</button>
                </div>";

                if($new_user == 0 && $user->usr_id_change > 0)
                {
                    // Angabe ueber die letzten Aenderungen
                    $sql    = "SELECT usr_first_name, usr_last_name
                                 FROM ". TBL_USERS. "
                                WHERE usr_id = $user->usr_id_change ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result);
                    $row = mysql_fetch_array($result);

                    echo "<div style=\"margin-top: 6px;\"><span style=\"font-size: 10pt\">
                        Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $user->last_change).
                        " durch $row[0] $row[1]</span>
                    </div>";
                }
            echo "</div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--\n";
        if(hasRole('Webmaster') || $new_user > 0)
        {
            echo "document.getElementById('last_name').focus();";
        }
        else
        {
            echo "document.getElementById('address').focus();";
        }
    echo "\n--></script>";    

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";

?>