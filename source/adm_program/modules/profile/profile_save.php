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

// Registrierung deaktiviert, also auch diesen Modus sperren
if(($new_user == 2 || $new_user == 3)
&& $g_preferences['registration_mode'] == 0)
{
    $g_message->show("module_disabled");
}

$_SESSION['profile_request'] = $_REQUEST;

if(!isset($_POST['login_name']))
{
    $_POST['login_name'] = "";
}

/*------------------------------------------------------------*/
// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
/*------------------------------------------------------------*/
if($new_user == 0 && $g_current_user->editProfile($usr_id) == false)
{
    $g_message->show("norights");
}

$user = new User($g_adm_con);

if($usr_id > 0)
{
    // Userdaten aus Datenbank holen
    $user->getUser($usr_id);

    if($user->valid == 1)
    {
        // keine Webanmeldung, dann schauen, ob User überhaupt Mitglied in der Gliedgemeinschaft ist
        if(isMember($usr_id) == false)
        {
            // pruefen, ob der User noch in anderen Organisationen aktiv ist
            $sql    = "SELECT *
                         FROM ". TBL_ROLES. ", ". TBL_MEMBERS. "
                        WHERE rol_org_shortname <> '$g_organization'
                          AND rol_valid          = 1
                          AND mem_rol_id         = rol_id
                          AND mem_valid          = 1
                          AND mem_usr_id         = $usr_id ";
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
// Feldinhalte saeubern und der User-Klasse zuordnen
/*------------------------------------------------------------*/

if($g_current_user->isWebmaster() || $new_user > 0)
{
    // Diese Daten duerfen nur vom Webmaster bzw. bei Neuanlage geaendert werden
    $user->last_name  = strStripTags($_POST['last_name']);
    $user->first_name = strStripTags($_POST['first_name']);
    $user->login_name = strStripTags($_POST['login_name']);
}

$user->email      = strStripTags($_POST['email']);

// immer speichern, ausser bei der schnellen Registrierung
if($new_user != 2 || $g_preferences['registration_mode'] != 1)
{
    $user->address    = strStripTags($_POST['address']);
    $user->zip_code   = strStripTags($_POST['zip_code']);
    $user->city       = strStripTags($_POST['city']);
    $user->country    = strStripTags($_POST['country']);
    $user->phone      = strStripTags($_POST['phone']);
    $user->mobile     = strStripTags($_POST['mobile']);
    $user->fax        = strStripTags($_POST['fax']);
    $user->homepage   = strStripTags($_POST['homepage']);
    $user->birthday   = strStripTags($_POST['birthday']);
    if(isset($_POST['gender']))
    {
        $user->gender = $_POST['gender'];
    }
    else
    {
        // falls das Geschlecht nicht angegeben wurde, dann neutralen Wert eintragen
        $user->gender = 0;
    }
}

// falls Registrierung, dann die entsprechenden Felder noch besetzen
if($new_user == 2)
{
    $user->valid = 0;
    $user->reg_org_shortname = $g_current_organization->shortname;
    $user->password = md5($_POST['password']);
}

/*------------------------------------------------------------*/
// Felder prüfen
/*------------------------------------------------------------*/
if(strlen($user->last_name) == 0)
{
    $g_message->show("feld", "Name");
}
if(strlen($user->first_name) == 0)
{
    $g_message->show("feld", "Vorname");
}
// E-Mail-Adresse auf Gueltigkeit pruefen, Pflichtfeld bei Registrierung
if(strlen($user->email) > 0 || $new_user == 2)
{
    if(!isValidEmailAddress($user->email))
    {
        $g_message->show("email_invalid");
    }
}
if(strlen($user->login_name) > 0)
{
    // pruefen, ob der Benutzername bereits vergeben ist
    $sql = "SELECT usr_id FROM ". TBL_USERS. "
             WHERE usr_login_name = {0} ";
    $sql    = prepareSQL($sql, array($user->login_name));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    if(mysql_num_rows($result) > 0)
    {
        $row = mysql_fetch_array($result);

        if(strcmp($row[0], $usr_id) != 0)
        {
            $g_message->show("login_name");
        }
    }
    
    // pruefen, ob der Benutzername bereits im Forum vergeben ist, 
    // Benutzernamenswechesel und diese Dinge
    if($g_forum_integriert)
    {
        //$g_forum->userRegister($user->login_name);
        
        // pruefen, ob der Benutzername bereits im Forum vergeben ist
        // Wenn die usr_id 0 ist, ist es eine Neuanmeldung, also nur den login_namen prüfen
        if($usr_id == 0)
        {
            if($g_forum->userCheck($user->login_name))
            {
                $g_message->show("login_name_forum");
            }
        }
        else
        // Wenn die usr_id > 0, dann ist es eine Aenderung eines bestehenden Users, 
        // also nachschauen, ob der neu gewählte login_name im Forum existiert.
        {
            // Erst mal den alten Usernamen holen
            $sql = "SELECT usr_login_name FROM ". TBL_USERS. "
                     WHERE usr_id =  $usr_id";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
            
            if(mysql_num_rows($result) > 0)
            {
                $row = mysql_fetch_array($result);
                
                // Wenn der alte Benutzername leer ist, ist es eine Neuanmeldung im Forum
                if(!$row[0])
                {
                    // Neuanmeldung im Forum
                    $forum_new = TRUE;
                    
                    // Schauen, ob der neue Benutzername schon im Forum vorhanden ist
                    if($g_forum->userCheck($user->login_name))
                    {
                        $g_message->show("login_name_forum");
                    }
                }
                else
                {
                    // Bestehender User im Forum
                    $forum_new = FALSE;
                    $forum_old_username = $row[0];
                    
                    // Schauen, ob der neue Benutzername schon im Forum vorhanden ist
                    if($g_forum->userCheck($user->login_name))
                    {
                        if($forum_old_username != $user->login_name)
                        {
                            $g_message->show("login_name_forum");
                        }
                    }
                }
            }
        }
    }
}
if(strlen($user->birthday) > 0)
{
    if(dtCheckDate($user->birthday) == false)
    {
        $g_message->show("date_invalid", "Geburtstag");
    }
}

// bei Registrierung muss Loginname und Pw geprueft werden
if($new_user == 2)
{
    if(strlen($user->login_name) == 0)
    {
        $g_message->show("feld", "Benutzername");
    }

    // beide Passwortfelder muessen identisch sein
    if ($_POST['password'] != $_POST['password2'])
    {
        $g_message->show("passwort");
    }

    if(strlen($_POST['password']) == 0)
    {
        $g_message->show("feld", "Passwort");
    }
}

// Feldinhalt der organisationsspezifischen Felder pruefen
$sql = "SELECT usf_id, usf_name, usf_type
          FROM ". TBL_USER_FIELDS. "
         WHERE usf_org_shortname  = '$g_organization' ";
if(!isModerator())
{
    $sql = $sql. " AND usf_locked = 0 ";
}
$result_msg = mysql_query($sql, $g_adm_con);
db_error($result_msg,__FILE__,__LINE__);

while($row = mysql_fetch_object($result_msg))
{
    // ein neuer Wert vorhanden
    if(isset($_POST["usf-$row->usf_id"])
    && strlen($_POST["usf-$row->usf_id"]) > 0)
    {
        if($row->usf_type == "NUMERIC"
        && is_numeric($_POST["usf-$row->usf_id"]) == false)
        {
            $g_message->show("field_numeric", $row->usf_name);
        }
        if($row->usf_type == "DATE"
        && dtCheckDate($_POST["usf-$row->usf_id"]) == false)
        {
            $g_message->show("date_invalid", $row->usf_name);
        }
    }
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

// Geburtstag fuer die DB formatieren
if(strlen($user->birthday) > 0)
{
    $user->birthday = dtFormatDate($user->birthday, "Y-m-d");
}

if($usr_id > 0)
{
    // Vorher schauen ob es den Username im Forum schon gibt.
    if($g_forum_integriert)
    {
        if($forum_new)
        {
            // Eine Neuanmeldung im Forum
            $g_forum->userInsert($user->login_name, 1, $user->password, $user->email);
        }
        else
        {
            // Ein Update eines bestehenden Forumusers
            $g_forum->usernameUpdate($user->login_name, $forum_old_username, 1, $user->password, $user->email);
        }
    }
    $ret_code = $user->update($g_current_user->id);        
}
else
{
    if($g_forum_integriert && $user->login_name)
    {
        $g_forum->userInsert($user->login_name, 0, $user->password, $user->email);
    }
    $ret_code = $user->insert($g_current_user->id);
}

if($ret_code != 0)
{
    $g_message->show("mysql", $ret_code);
}

// wenn Daten des eingeloggten Users geaendert werden, dann Session-Variablen aktualisieren
if($user->id == $g_current_user->id)
{
    $g_current_user = $user;
    $_SESSION['g_current_user'] = $g_current_user;
}

// immer speichern, ausser bei der schnellen Registrierung
if($new_user != 2 || $g_preferences['registration_mode'] != 1)
{
    /*------------------------------------------------------------*/
    // Messenger-Daten und gruppierungsspezifische Felder anlegen / updaten
    /*------------------------------------------------------------*/

    $sql = "SELECT usf_id, usf_name, usd_id, usd_value
              FROM ". TBL_USER_FIELDS. " LEFT JOIN ". TBL_USER_DATA. "
                ON usd_usf_id = usf_id
               AND usd_usr_id         = {0}
             WHERE (  usf_org_shortname IS NULL
                   OR usf_org_shortname  = '$g_organization' ) ";
    if(!isModerator())
    {
        $sql = $sql. " AND usf_locked = 0 ";
    }
    $sql = prepareSQL($sql, array($user->id));
    $result_msg = mysql_query($sql, $g_adm_con);
    db_error($result_msg,__FILE__,__LINE__);

    while($row = mysql_fetch_object($result_msg))
    {
        // Feldinhalt von Html & PHP-Code bereinigen
        $field_value = "";
        if(isset($_POST["usf-$row->usf_id"]))
        {
            $field_value = strStripTags($_POST["usf-$row->usf_id"]);
        }
        
        if(is_null($row->usd_value))
        {
            // noch kein Wert vorhanden -> neu einfuegen
            if(strlen($field_value) > 0)
            {
                $sql = "INSERT INTO ". TBL_USER_DATA. " (usd_usr_id, usd_usf_id, usd_value)
                                                 VALUES ({0}, $row->usf_id, {1}) ";
                $sql = prepareSQL($sql, array($user->id, $field_value));
                $result = mysql_query($sql, $g_adm_con);
                db_error($result,__FILE__,__LINE__);
            }
        }
        else
        {
            // auch ein neuer Wert vorhanden
            if(strlen($field_value) > 0)
            {
                if($field_value != $row->usd_value)
                {
                    $sql = "UPDATE ". TBL_USER_DATA. " SET usd_value = {0}
                             WHERE usd_id = $row->usd_id ";
                    $sql = prepareSQL($sql, array($field_value));
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result,__FILE__,__LINE__);
                }
            }
            else
            {
                $sql = "DELETE FROM ". TBL_USER_DATA. "
                         WHERE usd_id = $row->usd_id ";
                $result = mysql_query($sql, $g_adm_con);
                db_error($result,__FILE__,__LINE__);
            }
        }
    }
}

unset($_SESSION['profile_request']);
$_SESSION['navigation']->deleteLastUrl();

// hier auf Modus pruefen, damit kein Konflikt mit Editieren der Webanmeldung entsteht
//if($user->valid == 0 && $usr_id > 0)
if($new_user == 3)
{
    /*------------------------------------------------------------*/
    // neuer Benutzer wurde ueber Webanmeldung angelegt und soll nun zugeordnet werden
    /*------------------------------------------------------------*/

    // User auf aktiv setzen
    $user->valid = 1;
    $user->reg_org_shortname = "";
    $user->update($g_current_user->id);

    // Den User nun im Forum auch als Aktiv updaten, wenn g_forum gesetzt ist
    if($g_forum_integriert)
    {
        $g_forum->userUpdate($user->login_name, 1, $user->password, $user->email);
    }

    // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
    if($g_preferences['enable_system_mails'] == 1)
    {
        // Mail an den User schicken, um die Anmeldung zu bestaetigen
        $email = new Email();
        $email->setSender($g_preferences['email_administrator']);
        $email->addRecipient($user->email, "$user->first_name $user->last_name");
        $email->setSubject("Anmeldung auf $g_current_organization->homepage");
        $email->setText(utf8_decode("Hallo "). $user->first_name. utf8_decode(",\n\ndeine Anmeldung auf ").
            $g_current_organization->homepage. utf8_decode("wurde bestätigt.\n\nNun kannst du dich mit deinem Benutzernamen : ").
            $user->login_name. utf8_decode("\nund dem Passwort auf der Homepage einloggen.\n\n".
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
        $sql    = "SELECT usr_first_name, usr_last_name, usr_email
                     FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                    WHERE rol_org_shortname = '$g_organization'
                      AND rol_name          = 'Webmaster'
                      AND mem_rol_id        = rol_id
                      AND mem_valid         = 1
                      AND mem_usr_id        = usr_id
                      AND usr_valid         = 1
                      AND LENGTH(usr_email) > 0 ";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);

        while($row = mysql_fetch_object($result))
        {
            // Mail an die Webmaster schicken, dass sich ein neuer User angemeldet hat
            $email = new Email();
            $email->setSender($g_preferences['email_administrator']);
            $email->addRecipient($row->usr_email, "$row->usr_first_name $row->usr_last_name");
            $email->setSubject(utf8_decode("Neue Registrierung"));
            $email->setText(utf8_decode("Es hat sich ein neuer User auf "). $g_current_organization->homepage.
                utf8_decode(" registriert.\n\nNachname: "). $user->last_name. utf8_decode("\nVorname:  ").
                $user->first_name. utf8_decode("\nE-Mail:   "). $user->email.
                utf8_decode("\n\n\nDiese Nachricht wurde automatisch erzeugt."));
            if($email->sendEmail() == false)
            {
                $err_code = "mail_not_send";
                $err_text = $row->usr_email;
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
    header("Location: roles.php?user_id=$user->id&new_user=1");
    exit();
}
elseif($new_user == 0 && $user->valid == 0)
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

