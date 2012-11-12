 <?php
/******************************************************************************
 * Diese Klasse dient dazu Systemmails zu verschicken
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getMailText($systemMailId, &$user)
 *                  - diese Methode liest den Mailtext aus der DB und ersetzt 
 *                    vorkommende Platzhalter durch den gewuenschten Inhalt
 *
 * setVariable($number, $value)
 *                  - hier kann der Inhalt fuer zusaetzliche Variablen gesetzt werden
 *
 * sendSystemMail($systemMailId, &$user)
 *                  - diese Methode sendet eine Systemmail nachdem der Mailtext 
 *                    ausgelesen und Platzhalter ersetzt wurden
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/email.php');
require_once(SERVER_PATH. '/adm_program/system/classes/table_text.php');

class SystemMail extends Email
{
    private $smTextObject;
    private $db;
    private $smMailText;
    private $smMailHeader;
    private $smVariables = array();   // speichert zusaetzliche Variablen fuer den Mailtext

    // Konstruktor
    public function __construct(&$db)
    {
        $this->smTextObject = new TableText($db);
        parent::__construct();
    }
    
    // diese Methode liest den Mailtext aus der DB und ersetzt vorkommende Platzhalter durch den gewuenschten Inhalt
    // sysmail_id : eindeutige Bezeichnung der entsprechenden Systemmail, entspricht adm_texts.txt_name
    // user       : Benutzerobjekt, zu dem die Daten dann ausgelesen und in die entsprechenden Platzhalter gesetzt werden
    public function getMailText($systemMailId, &$user)
    {
        global $gCurrentOrganization, $gPreferences;
    
        if($this->smTextObject->getValue('txt_name') != $systemMailId)
        {
			$this->smTextObject->readDataByColumns(array('txt_name' => $systemMailId, 'txt_org_id' => $gCurrentOrganization->getValue('org_id')));
        }
        
        $mailSrcText = $this->smTextObject->getValue('txt_text');
        
        // jetzt alle Variablen ersetzen
        $mailSrcText = preg_replace ('/%user_first_name%/', $user->getValue('FIRST_NAME'),  $mailSrcText);
        $mailSrcText = preg_replace ('/%user_last_name%/',  $user->getValue('LAST_NAME'), $mailSrcText);
        $mailSrcText = preg_replace ('/%user_login_name%/', $user->getValue('usr_login_name'), $mailSrcText);
        $mailSrcText = preg_replace ('/%user_email%/', $user->getValue('EMAIL'),   $mailSrcText);
        $mailSrcText = preg_replace ('/%webmaster_email%/', $gPreferences['email_administrator'],  $mailSrcText);
        $mailSrcText = preg_replace ('/%organization_short_name%/', $gCurrentOrganization->getValue('org_shortname'), $mailSrcText);
        $mailSrcText = preg_replace ('/%organization_long_name%/',  $gCurrentOrganization->getValue('org_longname'), $mailSrcText);
        $mailSrcText = preg_replace ('/%organization_homepage%/',   $gCurrentOrganization->getValue('org_homepage'), $mailSrcText);
        
        // zusaetzliche Variablen ersetzen
        for($i = 1; $i <= count($this->smVariables); $i++)
        {
            $mailSrcText = preg_replace ('/%variable'.$i.'%/', $this->smVariables[$i],  $mailSrcText);
        }
        
        // Betreff und Inhalt anhand von Kennzeichnungen splitten oder ggf. Default-Inhalte nehmen
        if(strpos($mailSrcText, '#subject#') !== false)
        {
            $this->smMailHeader = trim(substr($mailSrcText, strpos($mailSrcText, '#subject#') + 9, strpos($mailSrcText, '#content#') - 9));
        }
        else
        {
            $this->smMailHeader = 'Systemmail von '. $gCurrentOrganization->getValue('org_homepage');
        }
        
        if(strpos($mailSrcText, '#content#') !== false)
        {
            $this->smMailText   = trim(substr($mailSrcText, strpos($mailSrcText, '#content#') + 9));
        }
        else
        {
            $this->smMailText   = $mailSrcText;
        }

        return $this->smMailText;
    }
    
    // die Methode setzt den Inhalt fuer spezielle Variablen
    public function setVariable($number, $value)
    {
        $this->smVariables[$number] = $value;
    }
    
    // diese Methode sendet eine Systemmail nachdem der Mailtext ausgelesen und Platzhalter ersetzt wurden
    // sysmail_id : eindeutige Bezeichnung der entsprechenden Systemmail, entspricht adm_texts.txt_name
    // user       : Benutzerobjekt, zu dem die Daten dann ausgelesen und in die entsprechenden Platzhalter gesetzt werden    
    public function sendSystemMail($systemMailId, &$user)
    {
        global $gPreferences;
        
        $this->getMailText($systemMailId, $user);
        $this->setSender($gPreferences['email_administrator']);
        $this->setSubject($this->smMailHeader);
        $this->setText($this->smMailText);

        return $this->sendEmail();
    }
}
?>