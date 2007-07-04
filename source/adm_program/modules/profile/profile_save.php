<?php
/******************************************************************************
 * Verschiedene Funktionen fuer das Profil
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id :  ID des Benutzers, dessen Profil bearbeitet werden soll
 * new_user : 0 - (Default) vorhandenen User bearbeiten
 *            1 - Neuen Benutzer hinzufuegen.
 *            2 - Registrierung entgegennehmen
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
require("../../system/email_class.php");
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
    $usr_id  = $_GET['user_id'];
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

if($new_user == 1 || $new_user == 2)
{
    $usr_id = 0;
}

// Registrierung deaktiviert, also auch diesen Modus sperren
if(($new_user == 2 || $new_user == 3)
&& $g_preferences['registration_mode'] == 0)
{
    $g_message->show("module_disabled");
}

$_SESSION['profile_request'] = $_REQUEST;

if(!isset($_POST['usr_login_name']))
{
    $_POST['usr_login_name'] = "";
}

/*------------------------------------------------------------*/
// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
/*------------------------------------------------------------*/
if($new_user == 0 && $g_current_user->editProfile($usr_id) == false)
{
    $g_message->show("norights");
}

$user = new User($g_adm_con, $usr_id);

if($usr_id > 0)
{
    if($user->getValue("usr_valid") == 1)
    {
        // keine Webanmeldung, dann schauen, ob User überhaupt Mitglied in der Gliedgemeinschaft ist
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
                $g_message->show("norights");
            }
        }
    }
}

/*------------------------------------------------------------*/
// Feldinhalte pruefen der User-Klasse zuordnen
/*------------------------------------------------------------*/

// bei Registrierung muss Loginname und Pw geprueft werden
if($new_user == 2)
{
    if(strlen($_POST['usr_login_name']) == 0)
    {
        $g_message->show("feld", "Benutzername");
    }

    // beide Passwortfelder muessen identisch sein
    if ($_POST['usr_password'] != $_POST['password2'])
    {
        $g_message->show("passwort");
    }

    if(strlen($_POST['usr_password']) == 0)
    {
        $g_message->show("feld", "Passwort");
    }
}

// nun alle Profilfelder pruefen
foreach($user->db_user_fields as $key => $value)
{
    $post_id = "usf-". $value['usf_id'];    
    
    if(isset($_POST[$post_id])
    && $_POST[$post_id] != $user->getValue($value['usf_name']))
    {
        // Pflichtfelder muessen gefuellt sein
        if($value['usf_mandatory'] == 1 && strlen($_POST[$post_id]) == 0)
        {
            $g_message->show("feld", $value['usf_name']);
        }

        // gesperrte Felder duerfen nur von berechtigten Benutzern geaendert werden 
        // Ausnahme bei der Registrierung
        if($value['usf_disabled'] == 1 && $g_current_user->editUser() == false && $new_user != 2)
        {
            $g_message->show("norights");
        }
        
        if(strlen($_POST[$post_id]) > 0)
        {
            // Pruefungen fuer die entsprechenden Datentypen
            if($value['usf_type'] == "CHECKBOX")
            {
                // Checkbox darf nur 1 oder 0 haben
                if($_POST[$post_id] != 0 && $_POST[$post_id] != 1)
                {
                    $g_message->show("invalid");
                }
            }
            elseif($value['usf_type'] == "DATE")
            {
                // Datum muss gueltig sein und formatiert werden
                if(dtCheckDate($_POST[$post_id]) == false)
                {
                    $g_message->show("date_invalid", $value['usf_name']);
                }
                $_POST[$post_id] = dtFormatDate($_POST[$post_id], "Y-m-d");
            }
            elseif($value['usf_type'] == "EMAIL")
            {
                // Pruefung auf gueltige E-Mail-Adresse
                if(!isValidEmailAddress($_POST[$post_id]))
                {
                    $g_message->show("email_invalid");
                }        
            }
            elseif($value['usf_type'] == "NUMERIC")
            {
                // Zahl muss numerisch sein
                if(is_numeric($_POST[$post_id]) == false)
                {
                    $g_message->show("field_numeric", $value['usf_name']);
                }
            }
        }

        $user->setValue($value['usf_name'], $_POST[$post_id]);
    }
    else
    {
        // Checkboxen uebergeben bei 0 keinen Wert, deshalb diesen hier setzen
        if($value['usf_type'] == "CHECKBOX")
        {
            $user->setValue($value['usf_name'], "0");
        }
    }
}

$login_name_changed = false;

if($g_current_user->isWebmaster() || $new_user > 0)
{
    // Loginname darf nur vom Webmaster bzw. bei Neuanlage geaendert werden    
    if(strlen($_POST['usr_login_name']) > 0
    && $_POST['usr_login_name'] != $user->getValue("usr_login_name"))
    {
        // pruefen, ob der Benutzername bereits vergeben ist
        $sql = "SELECT usr_id FROM ". TBL_USERS. "
                 WHERE usr_login_name = '". $_POST['usr_login_name']. "'";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);

        if(mysql_num_rows($result) > 0)
        {
            $row = mysql_fetch_array($result);

            if(strcmp($row['usr_id'], $usr_id) != 0)
            {
                $g_message->show("login_name");
            }
        }
        
        $login_name_changed = true;

        // pruefen, ob der Benutzername bereits im Forum vergeben ist, 
        // Benutzernamenswechesel und diese Dinge
        if($g_forum_integriert)
        {
            // pruefen, ob der Benutzername bereits im Forum vergeben ist
            if($g_forum->userCheck($user->login_name))
            {
                $g_message->show("login_name_forum");
            }
            
            // bisherigen Loginnamen merken, damit dieser spaeter im Forum geaendert werden kann
            $forum_old_username = "";
            if(strlen($user->getValue("usr_login_name")) > 0)
            {
                $forum_old_username = $user->getValue("usr_login_name");
            }
        }

        $user->setValue("usr_login_name", $_POST['usr_login_name']);
    }    
}

// falls Registrierung, dann die entsprechenden Felder noch besetzen
if($new_user == 2)
{
    $user->setValue("usr_valid", 0);
    $user->setValue("usr_reg_org_shortname", $g_current_organization->shortname);
    $user->setValue("usr_password", md5($_POST['usr_password']));
}


// Falls der User sich registrieren wollte, aber ein Captcha geschaltet ist,
// muss natuerlich der Code ueberprueft werden
if ($new_user == 2 && $g_preferences['enable_registration_captcha'] == 1)
{
    if ( !isset($_SESSION['captchacode']) || strtoupper($_SESSION['captchacode']) != strtoupper($_POST['captcha']) )
    {
        $g_message->show("captcha_code");
    }
}

/*------------------------------------------------------------*/
// Benutzerdaten in Datenbank schreiben
/*------------------------------------------------------------*/

$ret_code = $user->save($g_current_user->getValue("usr_id"));        

if($ret_code != 0)
{
    $g_message->show("mysql", $ret_code);
}

// Nachdem der User erfolgreich aktualisiert den Usernamen im Forum aktualisieren
if($g_forum_integriert && $login_name_changed)
{
    if(strlen($forum_old_username) > 0)
    {
        // Ein Update eines bestehenden Forumusers
        $g_forum->usernameUpdate($user->getValue("usr_login_name"), $forum_old_username, 1, $user->getValue("usr_password"), $user->getValue("E-Mail"));
    }
    else
    {
        // Eine Neuanmeldung im Forum
        $g_forum->userInsert($user->getValue("usr_login_name"), 1, $user->getValue("usr_password"), $user->getValue("E-Mail"));
    }
}

// wenn Daten des eingeloggten Users geaendert werden, dann Session-Variablen aktualisieren
if($user->getValue("usr_id") == $g_current_user->getValue("usr_id"))
{
    $g_current_user = $user;
}

unset($_SESSION['profile_request']);
$_SESSION['navigation']->deleteLastUrl();

// hier auf Modus pruefen, damit kein Konflikt mit Editieren der Webanmeldung entsteht
if($new_user == 3)
{
    /*------------------------------------------------------------*/
    // neuer Benutzer wurde ueber Webanmeldung angelegt und soll nun zugeordnet werden
    /*------------------------------------------------------------*/

    // User auf aktiv setzen
    $user->setValue("usr_valid", 1);
    $user->setValue("usr_reg_org_shortname", "");
    $user->save($g_current_user->getValue("usr_id"));

    // Den User nun im Forum auch als Aktiv updaten, wenn g_forum gesetzt ist
    if($g_forum_integriert)
    {
        $g_forum->userUpdate($user->getValue("usr_login_name"), 1, $user->getValue("usr_password"), $user->getValue("E-Mail"));
    }

    // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
    if($g_preferences['enable_system_mails'] == 1)
    {
        // Mail an den User schicken, um die Anmeldung zu bestaetigen
        $email = new Email();
        $email->setSender($g_preferences['email_administrator']);
        $email->addRecipient($user->getValue("E-Mail"), $user->getValue("Vorname"). " ". $user->getValue("Nachname"));
        $email->setSubject("Anmeldung auf $g_current_organization->homepage");
        $email->setText(utf8_decode("Hallo "). $user->getValue("Vorname"). utf8_decode(",\n\ndeine Anmeldung auf ").
            $g_current_organization->homepage. utf8_decode("wurde bestätigt.\n\nNun kannst du dich mit deinem Benutzernamen : ").
            $user->getValue("usr_login_name"). utf8_decode("\nund dem Passwort auf der Homepage einloggen.\n\n".
            "Sollten noch Fragen bestehen, schreib eine E-Mail an "). $g_preferences['email_administrator'].
            utf8_decode(" .\n\nViele Grüße\nDie Webmaster"));
        $email->sendEmail();
    }

    // neuer User -> Rollen zuordnen
    $location = "Location: roles.php?user_id=$user->id&new_user=1";
    header($location);
    exit();
}
elseif($new_user == 2)
{
    /*------------------------------------------------------------*/
    // Registrierung eines neuen Benutzers
    // -> E-Mail an alle Webmaster schreiben
    /*------------------------------------------------------------*/
    $err_code = "anmeldung";
    $err_text = "";

    // nur ausfuehren, wenn E-Mails auch unterstuetzt werden und die Webmasterbenachrichtung aktiviert ist
    if($g_preferences['enable_system_mails'] == 1 && $g_preferences['enable_registration_admin_mail'] == 1)
    {
        $sql = "SELECT first_name.usd_value as first_name, last_name.usd_value as last_name, email.usd_value as email
                  FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                  LEFT JOIN ". TBL_USER_DATA. " first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
                  LEFT JOIN ". TBL_USER_DATA. " last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
                 RIGHT JOIN ". TBL_USER_DATA. " email
                    ON email.usd_usr_id = usr_id
                   AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id"). "
                   AND LENGTH(email.usd_value) > 0
                 WHERE rol_name          = 'Webmaster'
                   AND rol_cat_id        = cat_id
                   AND cat_org_id        = $g_current_organization->id
                   AND mem_rol_id        = rol_id
                   AND mem_valid         = 1
                   AND mem_usr_id        = usr_id
                   AND usr_valid         = 1 ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);

        while($row = mysql_fetch_array($result))
        {
            // Mail an die Webmaster schicken, dass sich ein neuer User angemeldet hat
            $email = new Email();
            $email->setSender($g_preferences['email_administrator']);
            $email->addRecipient($row['email'], $row['first_name']. " ". $row['last_name']);
            $email->setSubject(utf8_decode("Neue Registrierung"));
            $email->setText(utf8_decode("Es hat sich ein neuer User auf "). $g_current_organization->homepage.
                utf8_decode(" registriert.\n\nNachname: "). $user->getValue("Nachname"). utf8_decode("\nVorname:  ").
                $user->getValue("Vorname"). utf8_decode("\nE-Mail:   "). $user->getValue("E-Mail").
                utf8_decode("\n\n\nDiese Nachricht wurde automatisch erzeugt."));
            if($email->sendEmail() == false)
            {
                $err_code = "mail_not_send";
                $err_text = $row['email'];
            }
        }
    }

    // nach Registrierung auf die Startseite verweisen
    $g_message->setForwardUrl("home");
    $g_message->show($err_code, $err_text);
}

/*------------------------------------------------------------*/
// auf die richtige Seite weiterleiten
/*------------------------------------------------------------*/

if($usr_id == 0)
{
    // neuer User -> Rollen zuordnen
    header("Location: $g_root_path/adm_program/modules/profile/roles.php?user_id=". $user->getValue("usr_id"). "&new_user=1");
    exit();
}
elseif($new_user == 0 && $user->getValue("usr_valid") == 0)
{
    // neue Registrierung bearbeitet
    $g_message->setForwardUrl($_SESSION['navigation']->getPreviousUrl(), 2000);
    $g_message->show("save");
}
else
{
    // zur Profilseite zurueckkehren
    $g_message->setForwardUrl("$g_root_path/adm_program/modules/profile/profile.php?user_id=$usr_id", 2000);
    $g_message->show("save");
}
?>

