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
$user      = new User($g_adm_con);
$user->GetUser($usr_id);

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
            if($user->valid != 0 || $user->reg_org_shortname != $g_organization)
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
        if(isset($form_values['gender']))
        {
            $user->gender = $form_values['gender'];
        }
        else
        {
            $user->gender = 0;
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
        elseif($usr_id == $g_current_user->id)
        {
            echo "Mein Profil";
        }
        else
        {
            echo "Profil von ". $user->first_name. " ". $user->last_name;
        }
    echo "</div>
    <div class=\"formBody\">
        <div>
            <div style=\"text-align: right; width: 30%; float: left;\">Nachname:&nbsp;</div>
            <div style=\"text-align: left; margin-left: 32%;\">
                <input type=\"text\" id=\"last_name\" name=\"last_name\" style=\"width: 200px;\" maxlength=\"30\" value=\"$user->last_name\" ";
                if($g_current_user->isWebmaster() == false && $new_user == 0)
                {
                    echo " class=\"readonly\" readonly ";
                }
                echo " />";
                if($new_user > 0 || $g_current_user->isWebmaster() == true)
                {
                    echo "&nbsp;<span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>";
                }
            echo "</div>
        </div>
        <div style=\"margin-top: 6px;\">
            <div style=\"text-align: right; width: 30%; float: left;\">Vorname:&nbsp;</div>
            <div style=\"text-align: left; margin-left: 32%;\">
                <input type=\"text\" name=\"first_name\" style=\"width: 200px;\" maxlength=\"30\" value=\"$user->first_name\" ";
                if($g_current_user->isWebmaster() == false && $new_user == 0)
                {
                    echo " class=\"readonly\" readonly ";
                }
                echo " />";
                if($new_user > 0 || $g_current_user->isWebmaster() == true)
                {
                    echo "&nbsp;<span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>";
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
                    <div style=\"text-align: right; width: 30%; float: left;\">E-Mail:&nbsp;</div>
                    <div style=\"text-align: left; margin-left: 32%;\">
                        <input type=\"text\" name=\"email\" style=\"width: 300px;\" maxlength=\"50\" value=\"$user->email\" />&nbsp;<span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>&nbsp;
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=email','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>
                <hr class=\"formLine\" width=\"85%\">";
            }
            echo "<div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Benutzername:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">
                    <input type=\"text\" name=\"login_name\" style=\"width: 130px;\" maxlength=\"20\" value=\"$user->login_name\" ";
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
                echo "<div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Passwort:&nbsp;</div>
                    <div style=\"text-align: left; margin-left: 32%;\">
                        <input type=\"password\" name=\"password\" style=\"width: 130px;\" maxlength=\"20\" />
                        <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>&nbsp;
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=password','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Passwort (Wdh):&nbsp;</div>
                    <div style=\"text-align: left; margin-left: 32%;\">
                        <input type=\"password\" name=\"password2\" style=\"width: 130px;\" maxlength=\"20\" />
                        <span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
                    </div>
                </div>";
            }
            else
            {
                // eigenes Passwort aendern, nur Webmaster duerfen Passwoerter von anderen aendern
                if($g_current_user->isWebmaster() || $g_current_user->id == $usr_id )
                {
                    echo "<div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Passwort:&nbsp;</div>
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
            echo "<hr class=\"formLine\" width=\"85%\">

            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Adresse:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">
                    <input type=\"text\" id=\"address\" name=\"address\" style=\"width: 300px;\" maxlength=\"50\" value=\"$user->address\" />
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Postleitzahl:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">
                    <input type=\"text\" name=\"zip_code\" style=\"width: 80px;\" maxlength=\"10\" value=\"$user->zip_code\" />
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Ort:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">
                    <input type=\"text\" name=\"city\" style=\"width: 200px;\" maxlength=\"30\" value=\"$user->city\" />
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Land:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">";
                    //Laenderliste oeffnen
                    $landlist = fopen("../../system/staaten.txt", "r");
                    echo "
                    <select size=\"1\" name=\"country\">
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

            <hr class=\"formLine\" width=\"85%\">

            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Telefon:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">
                    <input type=\"text\" name=\"phone\" style=\"width: 130px;\" maxlength=\"20\" value=\"$user->phone\" />
                    &nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Tel.Nr.)</span>
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Handy:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">
                    <input type=\"text\" name=\"mobile\" style=\"width: 130px;\" maxlength=\"20\" value=\"$user->mobile\" />
                    &nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Handynr.)</span>
                 </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Fax:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">
                    <input type=\"text\" name=\"fax\" style=\"width: 130px;\" maxlength=\"20\" value=\"$user->fax\" />
                    &nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Faxnr.)</span>
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">E-Mail:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">
                    <input type=\"text\" name=\"email\" style=\"width: 300px;\" maxlength=\"50\" value=\"$user->email\" />";
                    if($new_user == 2)
                    {
                        // bei erweiterter Registrierung ist dies ein Pflichtfeld
                        echo "&nbsp;<span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>&nbsp;
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=email','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">";
                    }
                echo "</div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Homepage:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">
                    <input type=\"text\" name=\"homepage\" style=\"width: 300px;\" maxlength=\"50\" value=\"$user->homepage\" />
                </div>
            </div>

            <hr class=\"formLine\" width=\"85%\">

            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Geburtstag:&nbsp;</div>
                <div style=\"text-align: left; margin-left: 32%;\">
                    <input type=\"text\" name=\"birthday\" style=\"width: 80px;\" maxlength=\"10\" value=\"$user->birthday\" />
                </div>
            </div>
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 30%; float: left;\">Geschlecht:&nbsp;</div>
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
            if($new_user == 1 || $new_user == 2)
            {
                // Neuer User anlegen bzw. Registrierung anlegen
                $sql = "SELECT *
                          FROM ". TBL_USER_FIELDS. ", ". TBL_CATEGORIES. "
                         WHERE usf_cat_id = cat_id
                           AND cat_org_id = $g_current_organization->id ";
            }
            else
            {
                // vorhandender User editieren bzw. Registrierung akzeptieren
                $sql = "SELECT *
                          FROM ". TBL_USER_FIELDS. " 
                          LEFT JOIN ". TBL_USER_DATA. "
                            ON usd_usf_id = usf_id
                           AND usd_usr_id = $user->id
                          JOIN ". TBL_CATEGORIES. "
                            ON usf_cat_id = cat_id
                           AND cat_org_id = $g_current_organization->id ";
            }
            // wenn nicht Moderator, dann nur die freigegebenen Felder anzeigen
            if(!$g_current_user->assignRoles())
            {
                $sql = $sql. " AND usf_hidden = 0 ";
            }
            $sql = $sql. " ORDER BY usf_sequence ASC ";

            $result_field = mysql_query($sql, $g_adm_con);
            db_error($result_field,__FILE__,__LINE__);

            if(mysql_num_rows($result_field) > 0)
            {
                echo "<hr class=\"formLine\" width=\"85%\">";
            }

            while($row = mysql_fetch_object($result_field))
            {
                echo "<div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">
                        $row->usf_name:&nbsp;
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
                        echo "\" id=\"usf-$row->usf_id\" name=\"usf-$row->usf_id\" ";

                        if($row->usf_type == "CHECKBOX")
                        {
                            if($b_history == true && isset($form_values[$row->usf_id])
                            && $form_values[$row->usf_id] == 1)
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
                            if($row->usf_type == "NUMERIC" || $row->usf_type == "DATE")
                            {
                                echo " style=\"width: 80px;\" maxlength=\"15\" ";
                            }
                            elseif($row->usf_type == "TEXT")
                            {
                                echo " style=\"width: 200px;\" maxlength=\"50\" ";
                            }
                            elseif($row->usf_type == "EMAIL" || $row->usf_type == "TEXT_BIG" || $row->usf_type == "URL")
                            {
                                echo " style=\"width: 300px;\" maxlength=\"255\" ";
                            }

                            if($b_history == true)
                            {
                                echo " value=\"". $form_values["usf-$row->usf_id"]. "\" ";
                            }
                            elseif(isset($row->usd_value) && strlen($row->usd_value) > 0)
                            {
                                if($row->usf_type == "DATE")
                                {
                                    // Datum muss noch formatiert werden
                                    $row->usd_value = mysqldate('d.m.y', $row->usd_value);
                                }
                                echo " value=\"$row->usd_value\" ";
                            }
                        }
                        echo ">";
                        // Fragezeichen mit Feldbeschreibung anzeigen, wenn diese hinterlegt ist
                        if(strlen($row->usf_description) > 0)
                        {
                            echo "&nbsp;<img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\"
                            vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=user_field_description&amp;err_text=". urlencode($row->usf_name). "','Message','width=400,height=400,left=310,top=200,scrollbars=yes')\">";
                        }
                    echo "</div>
                </div>";
            }

            echo "<hr class=\"formLine\" width=\"85%\">";

            // alle zugeordneten Messengerdaten einlesen
            $sql = "SELECT usf_id, usf_name, usd_value
                      FROM ". TBL_USER_FIELDS. "
                      LEFT JOIN ". TBL_USER_DATA. "
                        ON usd_usf_id  = usf_id
                       AND usd_usr_id  = $user->id
                      JOIN ". TBL_CATEGORIES. "
                        ON usf_cat_id = cat_id
                       AND cat_name   = 'Messenger'
                     ORDER BY usf_sequence ASC ";
            $result_msg = mysql_query($sql, $g_adm_con);
            db_error($result_msg,__FILE__,__LINE__);

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
                        echo "\" style=\"vertical-align: middle;\" alt=\"$row->usf_name\">&nbsp;
                    </div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($b_history == true)
                        {
                            $messenger_id = $form_values["usf-$row->usf_id"];
                        }
                        else
                        {
                            $messenger_id = $row->usd_value;
                        }
                        echo "<input type=\"text\" id=\"usf-$row->usf_id\" name=\"usf-$row->usf_id\" style=\"width: 200px;\" maxlength=\"50\" value=\"$messenger_id\">
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
             </div>";
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

        echo "<hr class=\"formLine\" width=\"85%\">

        <div style=\"margin-top: 6px;\">
            <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/system/back.php'\">
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
            db_error($result,__FILE__,__LINE__);
            $row = mysql_fetch_array($result);

            echo "<div style=\"margin-top: 6px;\"><span style=\"font-size: 10pt\">
                Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $user->last_change).
                " durch $row[0] $row[1]</span>
            </div>";
        }
    echo "</div>
</form>

<script type=\"text/javascript\"><!--\n";
    if($g_current_user->isWebmaster() || $new_user > 0)
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