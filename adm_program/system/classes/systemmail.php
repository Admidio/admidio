<?php
/**
 ***********************************************************************************************
 * Diese Klasse dient dazu Systemmails zu verschicken
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
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
class SystemMail extends Email
{
    private $smTextObject;
    private $smOrganization;
    private $db;                    ///< An object of the class Database for communication with the database
    private $smMailText;
    private $smMailHeader;
    private $smVariables = array(); // speichert zusaetzliche Variablen fuer den Mailtext

    /**
     * Constructor that will create an object of a SystemMail to handle all system notifications.
     * @param object $database Object of the class Database. This should be the default global object @b $gDb.
     */
    public function __construct(&$database)
    {
        $this->db          =& $database;
        $this->smTextObject = new TableText($this->db);
        parent::__construct();
    }

    /**
     * diese Methode liest den Mailtext aus der DB und ersetzt vorkommende Platzhalter durch den gewuenschten Inhalt
     * @param string $systemMailId eindeutige Bezeichnung der entsprechenden Systemmail, entspricht adm_texts.txt_name
     * @param object $user         Benutzerobjekt, zu dem die Daten dann ausgelesen und in die entsprechenden Platzhalter gesetzt werden
     * @return
     */
    public function getMailText($systemMailId, &$user)
    {
        global $gPreferences;

        // create organization object of the organization the current user is assigned (at registration this can be every organization)
        if(!is_object($this->smOrganization) || $this->smOrganization->getValue('org_id') != $user->getOrganization())
        {
            $this->smOrganization = new Organization($this->db, $user->getOrganization());
        }

        // read email text from text table in database
        if($this->smTextObject->getValue('txt_name') != $systemMailId)
        {
            $this->smTextObject->readDataByColumns(array('txt_name'   => $systemMailId,
                                                         'txt_org_id' => $this->smOrganization->getValue('org_id')));
        }

        $mailSrcText = $this->smTextObject->getValue('txt_text');

        // now replace all parameters in email text
        $mailSrcText = preg_replace('/#user_first_name#/', $user->getValue('FIRST_NAME', 'database'),  $mailSrcText);
        $mailSrcText = preg_replace('/#user_last_name#/',  $user->getValue('LAST_NAME', 'database'), $mailSrcText);
        $mailSrcText = preg_replace('/#user_login_name#/', $user->getValue('usr_login_name'), $mailSrcText);
        $mailSrcText = preg_replace('/#user_email#/', $user->getValue('EMAIL'),   $mailSrcText);
        $mailSrcText = preg_replace('/#webmaster_email#/', $gPreferences['email_administrator'],  $mailSrcText);
        $mailSrcText = preg_replace('/#organization_short_name#/', $this->smOrganization->getValue('org_shortname'), $mailSrcText);
        $mailSrcText = preg_replace('/%organization_long_name%/',  $this->smOrganization->getValue('org_longname'), $mailSrcText);
        $mailSrcText = preg_replace('/#organization_homepage#/',   $this->smOrganization->getValue('org_homepage'), $mailSrcText);

        // zusaetzliche Variablen ersetzen
        $iMax = count($this->smVariables);
        for($i = 1; $i <= $iMax; ++$i)
        {
            $mailSrcText = preg_replace('/#variable'.$i.'#/', $this->smVariables[$i], $mailSrcText);
        }

        // Betreff und Inhalt anhand von Kennzeichnungen splitten oder ggf. Default-Inhalte nehmen
        if(strpos($mailSrcText, '#subject#') !== false)
        {
            $this->smMailHeader = trim(substr($mailSrcText, strpos($mailSrcText, '#subject#') + 9, strpos($mailSrcText, '#content#') - 9));
        }
        else
        {
            $this->smMailHeader = 'Systemmail von '.$this->smOrganization->getValue('org_homepage');
        }

        if(strpos($mailSrcText, '#content#') !== false)
        {
            $this->smMailText = trim(substr($mailSrcText, strpos($mailSrcText, '#content#') + 9));
        }
        else
        {
            $this->smMailText = $mailSrcText;
        }

        return $this->smMailText;
    }

    /**
     * die Methode setzt den Inhalt fuer spezielle Variablen
     * @param int    $number
     * @param string $value
     */
    public function setVariable($number, $value)
    {
        $this->smVariables[$number] = $value;
    }

    /**
     * diese Methode sendet eine Systemmail nachdem der Mailtext ausgelesen und Platzhalter ersetzt wurden
     * @param  string       $systemMailId eindeutige Bezeichnung der entsprechenden Systemmail, entspricht adm_texts.txt_name
     * @param  object       $user         Benutzerobjekt, zu dem die Daten dann ausgelesen und in die entsprechenden Platzhalter gesetzt werden
     * @throws AdmException SYS_EMAIL_NOT_SEND
     * @return true
     */
    public function sendSystemMail($systemMailId, &$user)
    {
        global $gPreferences;

        $this->getMailText($systemMailId, $user);
        $this->setSender($gPreferences['email_administrator']);
        $this->setSubject($this->smMailHeader);
        $this->setText($this->smMailText);

        $returnMessage = $this->sendEmail();

        // if something went wrong then throw an exception with the error message
        if($returnMessage !== true)
        {
            throw new AdmException('SYS_EMAIL_NOT_SEND', $user->getValue('EMAIL'), $this->sendEmail());
        }

        return true;
    }
}
