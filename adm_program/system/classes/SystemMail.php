<?php
/**
 ***********************************************************************************************
 * This class is used to send system mails
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getMailText($systemMailId, $user)
 *                  - diese Methode liest den Mailtext aus der DB und ersetzt
 *                    vorkommende Platzhalter durch den gewuenschten Inhalt
 *
 * setVariable($number, $value)
 *                  - hier kann der Inhalt fuer zusaetzliche Variablen gesetzt werden
 *
 * sendSystemMail($systemMailId, $user)
 *                  - diese Methode sendet eine Systemmail nachdem der Mailtext
 *                    ausgelesen und Platzhalter ersetzt wurden
 */
class SystemMail extends Email
{
    /**
     * @var TableText
     */
    private $smTextObject;
    /**
     * @var Organization
     */
    private $smOrganization;
    /**
     * @var Database An object of the class Database for communication with the database
     */
    private $db;
    /**
     * @var string
     */
    private $smMailText;
    /**
     * @var string
     */
    private $smMailHeader;
    /**
     * @var array<int,string> speichert zusaetzliche Variablen fuer den Mailtext
     */
    private $smVariables = array();

    /**
     * Constructor that will create an object of a SystemMail to handle all system notifications.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(Database $database)
    {
        $this->db =& $database;
        $this->smTextObject = new TableText($this->db);
        parent::__construct();
    }

    /**
     * diese Methode liest den Mailtext aus der DB und ersetzt vorkommende Platzhalter durch den gewuenschten Inhalt
     * @param string $systemMailId eindeutige Bezeichnung der entsprechenden Systemmail, entspricht adm_texts.txt_name
     * @param User   $user         Benutzerobjekt, zu dem die Daten dann ausgelesen und in die entsprechenden Platzhalter gesetzt werden
     * @return string
     */
    public function getMailText($systemMailId, User $user)
    {
        global $gSettingsManager;

        // create organization object of the organization the current user is assigned (at registration this can be every organization)
        if (!$this->smOrganization instanceof Organization || (int) $this->smOrganization->getValue('org_id') !== $user->getOrganization()) {
            $this->smOrganization = new Organization($this->db, $user->getOrganization());
        }

        // read email text from text table in database
        if ($this->smTextObject->getValue('txt_name') !== $systemMailId) {
            $this->smTextObject->readDataByColumns(array(
                'txt_name'   => $systemMailId,
                'txt_org_id' => (int) $this->smOrganization->getValue('org_id')
            ));
        }

        $mailSrcText = $this->smTextObject->getValue('txt_text');

        // use unix linefeeds in mail
        $mailSrcText = str_replace("\r\n", "\n", $mailSrcText);

        // now replace all parameters in email text
        $pregRepArray = array(
            '/#user_first_name#/'         => $user->getValue('FIRST_NAME', 'database'),
            '/#user_last_name#/'          => $user->getValue('LAST_NAME', 'database'),
            '/#user_login_name#/'         => $user->getValue('usr_login_name'),
            '/#user_email#/'              => $user->getValue('EMAIL'),
            '/#administrator_email#/'     => $gSettingsManager->getString('email_administrator'),
            '/#organization_short_name#/' => $this->smOrganization->getValue('org_shortname'),
            '/#organization_long_name#/'  => $this->smOrganization->getValue('org_longname'),
            '/#organization_homepage#/'   => $this->smOrganization->getValue('org_homepage')
        );

        $mailSrcText = preg_replace(array_keys($pregRepArray), array_values($pregRepArray), $mailSrcText);

        // zusaetzliche Variablen ersetzen
        foreach ($this->smVariables as $number => $value) {
            $mailSrcText = str_replace('#variable'.$number.'#', $value, $mailSrcText);
        }

        // Betreff und Inhalt anhand von Kennzeichnungen splitten oder ggf. Default-Inhalte nehmen
        if (str_contains($mailSrcText, '#subject#')) {
            $this->smMailHeader = trim(substr($mailSrcText, strpos($mailSrcText, '#subject#') + 9, strpos($mailSrcText, '#content#') - 9));
        } else {
            $this->smMailHeader = 'Systemmail von '.$this->smOrganization->getValue('org_homepage');
        }

        if (str_contains($mailSrcText, '#content#')) {
            $this->smMailText = trim(substr($mailSrcText, strpos($mailSrcText, '#content#') + 9));
        } else {
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
     * @param string $systemMailId eindeutige Bezeichnung der entsprechenden Systemmail, entspricht adm_texts.txt_name
     * @param User   $user         Benutzerobjekt, zu dem die Daten dann ausgelesen und in die entsprechenden Platzhalter gesetzt werden
     * @throws AdmException SYS_EMAIL_NOT_SEND
     * @return true
     */
    public function sendSystemMail($systemMailId, User $user)
    {
        global $gSettingsManager;

        $this->getMailText($systemMailId, $user);
        $this->setSender($gSettingsManager->getString('email_administrator'));
        $this->setSubject($this->smMailHeader);
        $this->setText($this->smMailText);

        $returnMessage = $this->sendEmail();

        // if something went wrong then throw an exception with the error message
        if ($returnMessage !== true) {
            throw new AdmException('SYS_EMAIL_NOT_SEND', array($user->getValue('EMAIL'), $returnMessage));
        }

        return true;
    }
}
