<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Rollen
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id  - E-Mail an den entsprechenden Benutzer schreiben
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/classes/email.php");
require("../../system/classes/table_roles.php");

if ($g_preferences['enable_mail_module'] != 1)
{
    // es duerfen oder koennen keine Mails ueber den Server verschickt werden
    $g_message->show("module_disabled");
} 

// Der Inhalt des Formulars wird nun in der Session gespeichert...
$_SESSION['mail_request'] = $_REQUEST;

// Uebergabevariablen pruefen

if (isset($_GET["usr_id"]) && is_numeric($_GET["usr_id"]) == false)
{
    $g_message->show("invalid");
}

if (isset($_POST["rol_id"]) && is_numeric($_POST["rol_id"]) == false)
{
    $g_message->show("invalid");
}


// Pruefungen, ob die Seite regulaer aufgerufen wurde

// in ausgeloggtem Zustand duerfen nie direkt usr_ids uebergeben werden...
if (array_key_exists("usr_id", $_GET) && !$g_valid_login)
{
    $g_message->show("invalid");
}

// aktuelle Seite im NaviObjekt speichern. Dann kann in der Vorgaengerseite geprueft werden, ob das
// Formular mit den in der Session gespeicherten Werten ausgefuellt werden soll...
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Falls eine Usr_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
// auf diese zugreifen darf oder ob die UsrId ueberhaupt eine gueltige Mailadresse hat...
if (array_key_exists("usr_id", $_GET))
{
    //usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($g_db, $_GET['usr_id']);

    // darf auf die User-Id zugegriffen werden    
    if((  $g_current_user->editUsers() == false
       && isMember($user->getValue("usr_id")) == false)
    || strlen($user->getValue("usr_id")) == 0 )
    {
        $g_message->show("usrid_not_found");
    }

    // besitzt der User eine gueltige E-Mail-Adresse
    if (!isValidEmailAddress($user->getValue("E-Mail")))
    {
        $g_message->show("usrmail_not_found");
    }
}

// Falls Attachmentgroesse die max_post_size aus der php.ini uebertrifft, ist $_POST komplett leer.
// Deswegen muss dies ueberprueft werden...
if (empty($_POST))
{
    $g_message->show("invalid");
}

//Erst mal ein neues Emailobjekt erstellen...
$email = new Email();

// und ein Dummy Rollenobjekt dazu
$role = new TableRoles($g_db);

//Nun der Mail die Absenderangaben,den Betreff und das Attachment hinzufuegen...
if(strlen($_POST['name']) == 0)
{
    $g_message->show("feld", "Name");
}

//Absenderangaben checken falls der User eingeloggt ist, damit ein paar schlaue User nicht einfach die Felder aendern koennen...
if ( $g_valid_login 
&& (  $_POST['mailfrom'] != $g_current_user->getValue("E-Mail") 
   || $_POST['name'] != $g_current_user->getValue("Vorname"). " ". $g_current_user->getValue("Nachname")) )
{
    $g_message->show("invalid");
}

//Absenderangaben setzen
if ($email->setSender($_POST['mailfrom'],$_POST['name']))
{
  //Betreff setzen
  if ($email->setSubject($_POST['subject']))
  {
        //Pruefen ob moeglicher Weise ein Attachment vorliegt
        if (isset($_FILES['userfile']))
        {
            //noch mal schnell pruefen ob der User wirklich eingelogt ist...
            if (!$g_valid_login)
            {
                $g_message->show("invalid");
            }
            $attachment_size = 0;
            // Nun jedes Attachment
            for($act_attachment_nr = 0; isset($_FILES['userfile']['name'][$act_attachment_nr]) == true; $act_attachment_nr++)
            {
                //Pruefen ob ein Fehler beim Upload vorliegt
                if (($_FILES['userfile']['error'][$act_attachment_nr] != 0) &&  ($_FILES['userfile']['error'][$act_attachment_nr] != 4))
                {
                    $g_message->show("attachment");
                }
                //Wenn ein Attachment vorliegt dieses der Mail hinzufuegen
                if ($_FILES['userfile']['error'][$act_attachment_nr] == 0)
                {
                    // pruefen, ob die Anhanggroesse groesser als die zulaessige Groesse ist
                    $attachment_size = $attachment_size + $_FILES['userfile']['size'][$act_attachment_nr];
                    if($attachment_size > $email->getMaxAttachementSize("b"))
                    {
                        $g_message->show("attachment");
                    }
                    
                    if (strlen($_FILES['userfile']['type'][$act_attachment_nr]) > 0)
                    {
                        $email->addAttachment($_FILES['userfile']['tmp_name'][$act_attachment_nr], $_FILES['userfile']['name'][$act_attachment_nr], $_FILES['userfile']['type'][$act_attachment_nr]);
                    }
                    // Falls kein ContentType vom Browser uebertragen wird,
                    // setzt die MailKlasse automatisch "application/octet-stream" als FileTyp
                    else
                    {
                        $email->addAttachment($_FILES['userfile']['tmp_name'][$act_attachment_nr], $_FILES['userfile']['name'][$act_attachment_nr]);
                    }
                }
            }
        }
    }
    else
    {
        $g_message->show("feld", "Betreff");
    }
}
else
{
    $g_message->show("email_invalid");
}

if (array_key_exists("rol_id", $_POST))
{    
    if (strlen($_POST['rol_id']) == 0)
    {
        $g_message->show("mail_rolle");
    }
    
    $role->readData($_POST['rol_id']);

    if ($g_valid_login == true && $role->getValue("rol_mail_this_role") == 2)
    {
        $g_message->show("invalid");
    }
    
    if ($g_valid_login == false && $role->getValue("rol_mail_this_role") == 3)
    {
        $g_message->show("invalid");
    }
}

// Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
// muss natuerlich der Code ueberprueft werden
if (!$g_valid_login && $g_preferences['enable_mail_captcha'] == 1)
{
    if ( !isset($_SESSION['captchacode']) || strtoupper($_SESSION['captchacode']) != strtoupper($_POST['captcha']) )
    {
        $g_message->show("captcha_code");
    }
}

//Nun die Empfaenger zusammensuchen und an das Mailobjekt uebergeben
if (array_key_exists("usr_id", $_GET))
{
    //den gefundenen User dem Mailobjekt hinzufuegen...
    $email->addRecipient($user->getValue("E-Mail"), $user->getValue("Vorname"). " ". $user->getValue("Nachname"));
}
else
{
    // Rolle wurde uebergeben, dann an alle Mitglieder aus der DB fischen (ausser dem Sender selber)
    $sql   = "SELECT first_name.usd_value as first_name, last_name.usd_value as last_name, 
                     email.usd_value as email, rol_name
                FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                JOIN ". TBL_USER_DATA. " as email
                  ON email.usd_usr_id = usr_id
                 AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id"). "
                 AND LENGTH(email.usd_value) > 0
                LEFT JOIN ". TBL_USER_DATA. " as last_name
                  ON last_name.usd_usr_id = usr_id
                 AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
                LEFT JOIN ". TBL_USER_DATA. " as first_name
                  ON first_name.usd_usr_id = usr_id
                 AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
               WHERE rol_id      = ". $_POST['rol_id']. "
                 AND rol_cat_id  = cat_id
                 AND cat_org_id  = ". $g_current_organization->getValue("org_id"). "
                 AND mem_rol_id  = rol_id
                 AND mem_begin  <= '".DATE_NOW."'
                 AND mem_end     > '".DATE_NOW."'
                 AND mem_usr_id  = usr_id
                 AND usr_valid   = 1 
                 AND usr_id     <> ". $g_current_user->getValue("usr_id");
    $result = $g_db->query($sql);

    if($g_db->num_rows($result) > 0)
    {
        // alle Mitglieder als BCC an die Mail haengen
        while ($row = $g_db->fetch_object($result))
        {
            $email->addBlindCopy($row->email, "$row->first_name $row->last_name");              
        }
    }
    else
    {
        // Falls in der Rolle kein User mit gueltiger Mailadresse oder die Rolle gar nicht in der Orga
        // existiert, muss zumindest eine brauchbare Fehlermeldung präsentiert werden...
        $g_message->show("role_empty");
    }

}

// Falls eine Kopie benoetigt wird, das entsprechende Flag im Mailobjekt setzen
if (isset($_POST['kopie']) && $_POST['kopie'] == true)
{
    $email->setCopyToSenderFlag();

    //Falls der User eingeloggt ist, werden die Empfaenger der Mail in der Kopie aufgelistet
    if ($g_valid_login)
    {
        $email->setListRecipientsFlag();
    }
}

//Den Text fuer die Mail aufbereiten
$mail_body = $_POST['name']. " hat ";
if ($role->getValue("rol_id") > 0)
{
    $mail_body = $mail_body. 'an die Rolle "'.$role->getValue("rol_name").'"';
}
else
{
    $mail_body = $mail_body. 'Dir';
}
$mail_body = $mail_body. " von ". $g_current_organization->getValue("org_homepage"). " folgende E-Mail geschickt:\n";
$mail_body = $mail_body. "Eine Antwort kannst Du an ". $_POST['mailfrom']. " schicken.";

if (!$g_valid_login)
{
    $mail_body = $mail_body. "\n(Der Absender war nicht eingeloggt. Deshalb könnten die Absenderangaben fehlerhaft sein.)";
}
$mail_body = $mail_body. "\n\n\n". $_POST['body'];

//Den Text an das Mailobjekt uebergeben
$email->setText($mail_body);


//Nun kann die Mail endgueltig versendet werden...
if ($email->sendEmail())
{
    // Der CaptchaCode wird bei erfolgreichem Mailversand aus der Session geloescht
    if (isset($_SESSION['captchacode']))
    {
        unset($_SESSION['captchacode']);
    }

    // Bei erfolgreichem Versenden wird aus dem NaviObjekt die am Anfang hinzugefuegte URL wieder geloescht...
    $_SESSION['navigation']->deleteLastUrl();
    // dann auch noch die mail.php entfernen
    $_SESSION['navigation']->deleteLastUrl();

    // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
    unset($_SESSION['mail_request']);

    // Meldung ueber erfolgreichen Versand und danach weiterleiten
    if($_SESSION['navigation']->count > 0)
    {
        $g_message->setForwardUrl($_SESSION['navigation']->getUrl());
    }
    else
    {
        $g_message->setForwardUrl($g_homepage);
    }
    
    if ($role->getValue("rol_id") > 0)
    {
        $g_message->show("mail_send", "die Rolle ". $role->getValue("rol_name"), "Hinweis");
    }
    else
    {
        $g_message->show("mail_send", $_POST['mailto'], "Hinweis");
    }
}
else
{
    if ($role->getValue("rol_id") > 0)
    {
        $g_message->show("mail_not_send", "die Rolle ". $role->getValue("rol_name"));
    }
    else
    {
        $g_message->show("mail_not_send", $_POST['mailto']);
    }
}



?>
