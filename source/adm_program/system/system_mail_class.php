<?php
/******************************************************************************
 * Diese Klasse dient dazu ein Systemmails zu verschicken
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Methoden:
 *
 * getMailText($sysmail_id, &$user)
 *                  - diese Methode liest den Mailtext aus der DB und ersetzt 
 *                    vorkommende Platzhalter durch den gewuenschten Inhalt
 *
 * sendSystemMail($sysmail_id, &$user)
 *                  - diese Methode sendet eine Systemmail nachdem der Mailtext 
 *                    ausgelesen und Platzhalter ersetzt wurden
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/email_class.php");
require_once(SERVER_PATH. "/adm_program/system/text_class.php");

class SystemMail extends Email
{
    var $textObject;
    var $db;
    var $mailText;
    var $mailHeader;

    // Konstruktor
    function SystemMail(&$db)
    {
        $this->textObject = new Text($db);
        $this->Email();
    }
    
    // diese Methode liest den Mailtext aus der DB und ersetzt vorkommende Platzhalter durch den gewuenschten Inhalt
    // sysmail_id : eindeutige Bezeichnung der entsprechenden Systemmail, entspricht adm_texts.txt_name
    // user       : Benutzerobjekt, zu dem die Daten dann ausgelesen und in die entsprechenden Platzhalter gesetzt werden
    function getMailText($sysmail_id, &$user)
    {
        global $g_current_organization, $g_preferences;
    
        if($this->textObject->getValue("txt_name") != $sysmail_id)
        {
            $this->textObject->getText($sysmail_id);
        }
        
        $mailSrcText = $this->textObject->getValue("txt_text");
        
        // jetzt alle Variablen ersetzen
        $mailSrcText = preg_replace ("/%first_name%/", $user->getValue("Vorname"),  $mailSrcText);
        $mailSrcText = preg_replace ("/%last_name%/",  $user->getValue("Nachname"), $mailSrcText);
        $mailSrcText = preg_replace ("/%login_name%/", $user->getValue("usr_login_name"), $mailSrcText);
        $mailSrcText = preg_replace ("/%email_user%/", $user->getValue("E-Mail"),   $mailSrcText);
        $mailSrcText = preg_replace ("/%email_webmaster%/", $g_preferences['email_administrator'],  $mailSrcText);
        $mailSrcText = preg_replace ("/%homepage%/",   $g_current_organization->getValue("org_homepage"), $mailSrcText);
        
        // Betreff und Inhalt anhand von Kennzeichnungen splitten oder ggf. Default-Inhalte nehmen
        if(strpos($mailSrcText, "#Betreff#") !== false)
        {
            $this->mailHeader = trim(substr($mailSrcText, strpos($mailSrcText, "#Betreff#") + 9, strpos($mailSrcText, "#Inhalt#") - 9));
        }
        else
        {
            $this->mailHeader = "Systemmail von ". $g_current_organization->getValue("org_homepage");
        }
        
        if(strpos($mailSrcText, "#Inhalt#") !== false)
        {
            $this->mailText   = trim(substr($mailSrcText, strpos($mailSrcText, "#Inhalt#") + 8));
        }
        else
        {
            $this->mailText   = $mailSrcText;
        }

        return $this->mailText;
    }
    
    // diese Methode sendet eine Systemmail nachdem der Mailtext ausgelesen und Platzhalter ersetzt wurden
    // sysmail_id : eindeutige Bezeichnung der entsprechenden Systemmail, entspricht adm_texts.txt_name
    // user       : Benutzerobjekt, zu dem die Daten dann ausgelesen und in die entsprechenden Platzhalter gesetzt werden    
    function sendSystemMail($sysmail_id, &$user)
    {
        global $g_preferences;
        
        $this->getMailText($sysmail_id, $user);
        $this->setSender($g_preferences['email_administrator']);
        $this->setSubject($this->mailHeader);
        $this->setText($this->mailText);

        return $email->sendEmail();
    }
}
?>