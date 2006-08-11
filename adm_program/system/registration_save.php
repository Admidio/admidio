<?php
/******************************************************************************
 * Registrieren - Funktionen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
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

require("common.php");
require("email_class.php");

$_SESSION['registration_request'] = $_REQUEST;

$err_code   = "";
$err_text   = "";
$count_user = 0;

$_POST['login_name'] = strStripTags($_POST['login_name']);
$_POST['last_name']  = strStripTags($_POST['last_name']);
$_POST['first_name'] = strStripTags($_POST['first_name']);
$_POST['email']      = strStripTags($_POST['email']);

// Felder prüfen
if ($_POST['password'] != $_POST['password2'])
{
    $err_code = "passwort";
}

if(strlen($err_code) == 0)
{
    if(!isValidEmailAddress($_POST['email']))
    {
        $err_code = "email_invalid";
    }
}
 
if(strlen($err_code) == 0)
{
    if(strlen($_POST['login_name']) == 0)
    {
        $err_text = "Benutzername";
    }

    if(strlen($_POST['password']) == 0)
    {
        $err_text = "Passwort";
    }

    if(strlen($_POST['last_name']) == 0)
    {
        $err_text = "Nachname";
    }

    if(strlen($_POST['first_name']) == 0)
    {
        $err_text = "Vorname";
    }

    if(strlen($err_text) != 0)
    {
        $err_code = "feld";
    }
}

if(strlen($err_code) != 0)
{
    $g_message->show($err_code, $err_text);
}


// pruefen, ob der Username bereits vergeben ist
$sql    = "SELECT usr_id FROM ". TBL_USERS. " 
            WHERE usr_login_name LIKE {0}";
$sql    = prepareSQL($sql, array($_POST['login_name']));
$result = mysql_query($sql, $g_adm_con);
db_error($result);      

$count_user = mysql_num_rows($result);

if ($count_user == 0)
{
    $password_crypt = md5($_POST['password']);

    $sql    = "INSERT INTO ". TBL_USERS. " (usr_reg_org_shortname, usr_last_name, usr_first_name, usr_email, usr_login_name, usr_password, usr_valid) ".
    "                               VALUES ('$g_organization', {0}, {1}, {2}, {3}, '$password_crypt', 0)";
    $sql    = prepareSQL($sql, array($_POST['last_name'], $_POST['first_name'], $_POST['email'], $_POST['login_name']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    
    unset($_SESSION['registration_request']);

    // E-Mail an alle Webmaster schreiben
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
    db_error($result);

    while($row = mysql_fetch_object($result))
    {
        // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
        if($g_preferences['send_email_extern'] != 1)
        {
            // Mail an den User mit den Loginaten schicken
            $email = new Email();
            $email->setSender($g_preferences['email_administrator']);
            $email->addRecipient($row->usr_email, "$row->first_name $row->last_name");
            $email->setSubject(utf8_decode("Neue Registrierung"));
            $email->setText(utf8_decode("Es hat sich ein neuer User auf "). $g_current_organization->homepage. 
                utf8_decode(" registriert.\n\nNachname: "). $_POST['last_name']. utf8_decode("\nVorname:  "). 
                $_POST['first_name']. utf8_decode("\nE-Mail:   "). $_POST['email']. 
                utf8_decode("\n\n\nDiese Nachricht wurde automatisch erzeugt."));
            if($email->sendEmail() == true)
            {
                $err_code = "anmeldung";
            }      
            else
            {
                $err_code = "mail_not_send";    
                $err_text = $row->usr_email;
            }
        }
    }

    $g_message->setForwardUrl("home");
    $g_message->show($err_code, $err_text);
}
else
{
    $g_message->show("login_name");
}
?>