<?php
/******************************************************************************
 * Zeigt eine Liste mit moeglichen Zuordnungen an
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * new_user_id: ID des Users, der angezeigt werden soll
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

// nur Webmaster duerfen User zuordnen, ansonsten Seite verlassen
if($g_current_user->approveUsers() == false)
{
   $g_message->show("norights");
}

// pruefen, ob Modul aufgerufen werden darf
if($g_preferences['registration_mode'] == 0)
{
    $g_message->show("module_disabled");
}

// Uebergabevariablen pruefen und initialisieren
$req_new_user_id = 0;

if(isset($_GET['new_user_id']) && is_numeric($_GET['new_user_id']))
{
    $req_new_user_id = $_GET['new_user_id'];
}
else
{
    $g_message->show("invalid");
}

// neuen User erst einmal als Objekt erzeugen
$new_user = new User($g_adm_con, $req_new_user_id);

// alle User aus der DB selektieren, die denselben Vor- und Nachnamen haben
$sql = "SELECT usr_id, usr_login_name, last_name.usd_value as last_name, 
               first_name.usd_value as first_name, address.usd_value as address,
               zip_code.usd_value as zip_code, city.usd_value as city,
               email.usd_value as email
          FROM ". TBL_USERS. "
         RIGHT JOIN ". TBL_USER_DATA. " as last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
         RIGHT JOIN ". TBL_USER_DATA. " as first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
          LEFT JOIN ". TBL_USER_DATA. " as address
            ON address.usd_usr_id = usr_id
           AND address.usd_usf_id = ". $g_current_user->getProperty("Adresse", "usf_id"). "
          LEFT JOIN ". TBL_USER_DATA. " as zip_code
            ON zip_code.usd_usr_id = usr_id
           AND zip_code.usd_usf_id = ". $g_current_user->getProperty("PLZ", "usf_id"). "
          LEFT JOIN ". TBL_USER_DATA. " as city
            ON city.usd_usr_id = usr_id
           AND city.usd_usf_id = ". $g_current_user->getProperty("Ort", "usf_id"). "
          LEFT JOIN ". TBL_USER_DATA. " as email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id"). "
         WHERE usr_valid = 1 
           AND (  (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4)  LIKE SUBSTRING(SOUNDEX('". $new_user->getValue("Nachname")."'), 1, 4)
                  AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4)  LIKE SUBSTRING(SOUNDEX('". $new_user->getValue("Vorname"). "'), 1, 4) )
               OR (   SUBSTRING(SOUNDEX(last_name.usd_value),  1, 4)  LIKE SUBSTRING(SOUNDEX('". $new_user->getValue("Vorname"). "'), 1, 4)
                  AND SUBSTRING(SOUNDEX(first_name.usd_value), 1, 4)  LIKE SUBSTRING(SOUNDEX('". $new_user->getValue("Nachname")."'), 1, 4) ) )";
$result_usr = mysql_query($sql, $g_adm_con);
$member_found = mysql_num_rows($result_usr);

if($member_found == 0)
{
    // kein User mit dem Namen gefunden, dann direkt neuen User erzeugen und dieses Script verlassen
    header("Location: $g_root_path/adm_program/modules/profile/profile_new.php?user_id=$req_new_user_id&new_user=3");
    exit();
}

$_SESSION['navigation']->addUrl($g_current_url);

// Html-Kopf ausgeben
$g_layout['title'] = "Neuen Benutzer zuordnen";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<div class=\"formHead\" style=\"width: 400px;\">Anmeldung zuordnen</div>
<div class=\"formBody\" style=\"width: 400px;\">
    Es wurde bereits ein Benutzer mit &auml;hnlichem Namen wie 
    <b>". $new_user->getValue("Vorname"). " ". $new_user->getValue("Nachname"). "</b> 
    in der Datenbank gefunden.<br>
    <div class=\"groupBox\" style=\"margin-top: 10px; text-align: left;\">
        <div class=\"groupBoxHeadline\">Gefundene Benutzer</div>
        <div class=\"groupBoxBody\">";
            // Alle gefundenen Benutzer mit Adresse ausgeben und einem Link zur weiteren moeglichen Verarbeitung
            $i = 0;
            while($row = mysql_fetch_object($result_usr))
            {
                if($i > 0)
                {
                    echo "<hr class=\"formLine\" width=\"90%\">";
                }
                echo "<div style=\"margin-left: 20px;\">
                    <i>$row->first_name $row->last_name</i><br>";
                    if(strlen($row->address) > 0)
                    {
                        echo "$row->address<br>";
                    }
                    if(strlen($row->zip_code) > 0 || strlen($row->city) > 0)
                    {
                        echo "$row->zip_code $row->city<br>";
                    }
                    if(strlen($row->email) > 0)
                    {
                        if($g_preferences['enable_mail_module'] == 1)
                        {
                            echo "<a href=\"$g_root_path/adm_program/modules/mail/mail.php?usr_id=$row->usr_id\">$row->email</a><br>";
                        }
                        else
                        {
                            echo "<a href=\"mailto:$row->usr_email\">$row->email</a><br>";
                        }
                    }

                    if(isMember($row->usr_id))
                    {
                        // gefundene User ist bereits Mitglied dieser Organisation
                        if(strlen($row->usr_login_name) > 0)
                        {
                            // Logindaten sind bereits vorhanden -> Logindaten neu zuschicken                    
                            echo "<br>Dieser Benutzer besitzt schon ein g&uuml;ltiges Login.";
                            if($g_preferences['enable_system_mails'] == 1)
                            {
                                echo "<br>M&ouml;chtest du ihm seinen Loginnamen mit Passwort als Erinnerung zuschicken ?<br>
                                <div style=\"margin-top: 5px;\">
                                    <span class=\"iconLink\">
                                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$req_new_user_id&amp;user_id=$row->usr_id&amp;mode=6\"><img
                                        class=\"iconLink\" src=\"$g_root_path/adm_program/images/key.png\" alt=\"E-Mail mit Benutzernamen und neuem Passwort zuschicken\"></a>
                                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$req_new_user_id&amp;user_id=$row->usr_id&amp;mode=6\">Zugangsdaten zuschicken</a>
                                    </span>
                                </div>";
                            }
                        }
                        else
                        {
                            // Logindaten sind NICHT vorhanden -> diese nun zuordnen
                            echo "<br>Dieser Benutzer besitzt noch kein Login.<br>
                                M&ouml;chtest du ihm die Daten dieser Registrierung zuordnen ?<br>
                            <div style=\"margin-top: 5px;\">
                                <span class=\"iconLink\">
                                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$req_new_user_id&amp;user_id=$row->usr_id&amp;mode=1\"><img
                                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/properties.png\" alt=\"Zugangsdaten zuordnen\"></a>
                                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$req_new_user_id&amp;user_id=$row->usr_id&amp;mode=1\">Zugangsdaten zuordnen</a>
                                </span>
                            </div>";
                        }
                    }
                    else
                    {
                        // gefundene User ist noch KEIN Mitglied dieser Organisation
                        $link = "$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$req_new_user_id&amp;user_id=$row->usr_id&amp;mode=2";

                        if(strlen($row->usr_login_name) > 0)
                        {
                            // Logindaten sind bereits vorhanden
                            echo "<br>Dieser Benutzer ist noch kein Mitglied der Organisation $g_organization, 
                            besitzt aber bereits Logindaten.<br>
                            <div style=\"margin-top: 5px;\">
                                <span class=\"iconLink\">
                                    <a class=\"iconLink\" href=\"$link\"><img class=\"iconLink\" 
                                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/properties.png\" alt=\"Mitgliedschaft zuweisen\"></a>
                                    <a class=\"iconLink\" href=\"$link\">Mitgliedschaft zuweisen</a>
                                </span>
                            </div>";
                        }               
                        else
                        {
                            // KEINE Logindaten vorhanden
                            echo "<br>Dieser Benutzer ist noch kein Mitglied der Organisation $g_organization und 
                            besitzt auch keine Logindaten.<br>
                            <div style=\"margin-top: 5px;\">                        
                                <span class=\"iconLink\">
                                    <a class=\"iconLink\" href=\"$link\"><img class=\"iconLink\"
                                    class=\"iconLink\" src=\"$g_root_path/adm_program/images/properties.png\" alt=\"Rollen und Logindaten diesem Benutzer zuordnen\"></a>
                                    <a class=\"iconLink\" href=\"$link\">Mitgliedschaft und Logindaten diesem Benutzer zuordnen</a>
                                </span>
                            </div>";
                        }               
                    }
                echo "</div>";
                $i++;
            }
        echo "</div>
    </div>
    <div class=\"groupBox\" style=\"margin-top: 10px; text-align: left;\">
        <div class=\"groupBoxHeadline\">Neuen Benutzer anlegen</div>
        <div class=\"groupBoxBody\">
            <div style=\"margin-left: 20px;\">
                Falls der neue Benutzer nicht bei den oben aufgelisteten Benutzern dabei ist, 
                kannst du auch einen neuen Benutzer anlegen.<br>
                <div style=\"margin-top: 5px;\">
                    <span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=$req_new_user_id&new_user=3\"><img
                        class=\"iconLink\" src=\"$g_root_path/adm_program/images/add.png\" alt=\"Neuen Benutzer anlegen\"></a>
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=$req_new_user_id&new_user=3\">Benutzer anlegen</a>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div style=\"margin-top: 20px;\">
        <span class=\"iconLink\">
            <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\"><img
            class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
            <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
        </span>
    </div>
</div>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>