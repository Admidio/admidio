<?php
/******************************************************************************
 * Zeigt eine Liste mit moeglichen Zuordnungen an
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * new_user_id: ID des Users, der angezeigt werden soll
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
$new_user = new User($g_db, $req_new_user_id);

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
$result_usr   = $g_db->query($sql);
$member_found = $g_db->num_rows($result_usr);

if($member_found == 0)
{
    // kein User mit dem Namen gefunden, dann direkt neuen User erzeugen und dieses Script verlassen
    header("Location: $g_root_path/adm_program/modules/profile/profile_new.php?user_id=$req_new_user_id&new_user=3");
    exit();
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$g_layout['title'] = "Neuen Benutzer zuordnen";
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<div class=\"formLayout\" id=\"assign_users_form\" style=\"width: 400px;\">
    <div class=\"formHead\">Anmeldung zuordnen</div>
    <div class=\"formBody\">
        Es wurde bereits ein Benutzer mit &auml;hnlichem Namen wie 
        <strong>". $new_user->getValue("Vorname"). " ". $new_user->getValue("Nachname"). "</strong> 
        in der Datenbank gefunden.<br />
        <div class=\"groupBox\">
            <div class=\"groupBoxHeadline\">Gefundene Benutzer</div>
            <div class=\"groupBoxBody\">";
                // Alle gefundenen Benutzer mit Adresse ausgeben und einem Link zur weiteren moeglichen Verarbeitung
                $i = 0;
                while($row = $g_db->fetch_object($result_usr))
                {
                    if($i > 0)
                    {
                        echo "<hr />";
                    }
                    echo "<div style=\"margin-left: 20px;\">
                        <i>$row->first_name $row->last_name</i><br />";
                        if(strlen($row->address) > 0)
                        {
                            echo "$row->address<br />";
                        }
                        if(strlen($row->zip_code) > 0 || strlen($row->city) > 0)
                        {
                            echo "$row->zip_code $row->city<br />";
                        }
                        if(strlen($row->email) > 0)
                        {
                            if($g_preferences['enable_mail_module'] == 1)
                            {
                                echo "<a href=\"$g_root_path/adm_program/modules/mail/mail.php?usr_id=$row->usr_id\">$row->email</a><br />";
                            }
                            else
                            {
                                echo "<a href=\"mailto:$row->email\">$row->email</a><br />";
                            }
                        }

                        if(isMember($row->usr_id))
                        {
                            // gefundene User ist bereits Mitglied dieser Organisation
                            if(strlen($row->usr_login_name) > 0)
                            {
                                // Logindaten sind bereits vorhanden -> Logindaten neu zuschicken                    
                                echo "<br />Dieser Benutzer besitzt schon ein g&uuml;ltiges Login.";
                                if($g_preferences['enable_system_mails'] == 1)
                                {
                                    echo "<br />M&ouml;chtest du ihm seinen Loginnamen mit Passwort als Erinnerung zuschicken ?<br />

                                    <span class=\"iconTextLink\">
                                        <a href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$req_new_user_id&amp;user_id=$row->usr_id&amp;mode=6\"><img
                                        src=\"". THEME_PATH. "/icons/key.png\" alt=\"E-Mail mit Benutzernamen und neuem Passwort zuschicken\" /></a>
                                        <a href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$req_new_user_id&amp;user_id=$row->usr_id&amp;mode=6\">Zugangsdaten zuschicken</a>
                                    </span>";
                                }
                            }
                            else
                            {
                                // Logindaten sind NICHT vorhanden -> diese nun zuordnen
                                echo "<br />Dieser Benutzer besitzt noch kein Login.<br />
                                    M&ouml;chtest du ihm die Daten dieser Registrierung zuordnen ?<br />

                                <span class=\"iconTextLink\">
                                    <a href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$req_new_user_id&amp;user_id=$row->usr_id&amp;mode=1\"><img
                                    src=\"". THEME_PATH. "/icons/new_registrations.png\" alt=\"Zugangsdaten zuordnen\" /></a>
                                    <a href=\"$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$req_new_user_id&amp;user_id=$row->usr_id&amp;mode=1\">Zugangsdaten zuordnen</a>
                                </span>";
                            }
                        }
                        else
                        {
                            // gefundene User ist noch KEIN Mitglied dieser Organisation
                            $link = "$g_root_path/adm_program/administration/new_user/new_user_function.php?new_user_id=$req_new_user_id&amp;user_id=$row->usr_id&amp;mode=2";

                            if(strlen($row->usr_login_name) > 0)
                            {
                                // Logindaten sind bereits vorhanden
                                echo "<br />Dieser Benutzer ist noch kein Mitglied der Organisation $g_organization, 
                                besitzt aber bereits Logindaten.<br />

                                <span class=\"iconTextLink\">
                                    <a href=\"$link\"><img src=\"". THEME_PATH. "/icons/new_registrations.png\" 
                                        alt=\"Mitgliedschaft zuweisen\" /></a>
                                    <a href=\"$link\">Mitgliedschaft zuweisen</a>
                                </span>";
                            }               
                            else
                            {
                                // KEINE Logindaten vorhanden
                                echo "<br />Dieser Benutzer ist noch kein Mitglied der Organisation $g_organization und 
                                besitzt auch keine Logindaten.<br />
                                
                                <span class=\"iconTextLink\">
                                    <a href=\"$link\"><img src=\"". THEME_PATH. "/icons/new_registrations.png\" 
                                        alt=\"Rollen und Logindaten diesem Benutzer zuordnen\" /></a>
                                    <a href=\"$link\">Mitgliedschaft und Logindaten diesem Benutzer zuordnen</a>
                                </span>";
                            }               
                        }
                    echo "</div>";
                    $i++;
                }
            echo "</div>
        </div>

        <div class=\"groupBox\">
            <div class=\"groupBoxHeadline\">Neuen Benutzer anlegen</div>
            <div class=\"groupBoxBody\">
                <div style=\"margin-left: 20px;\">
                    Falls der neue Benutzer nicht bei den oben aufgelisteten Benutzern dabei ist, 
                    kannst du auch einen neuen Benutzer anlegen.<br />
                    
                    <span class=\"iconTextLink\">
                        <a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=$req_new_user_id&amp;new_user=3\"><img
                        src=\"". THEME_PATH. "/icons/add.png\" alt=\"Neuen Benutzer anlegen\" /></a>
                        <a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=$req_new_user_id&amp;new_user=3\">Benutzer anlegen</a>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
        </span>
    </li>
</ul>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>