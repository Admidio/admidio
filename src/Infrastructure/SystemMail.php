<?php
namespace Admidio\Infrastructure;

use Admidio\Organizations\Entity\Organization;
use Admidio\Infrastructure\Entity\Text;
use Admidio\Users\Entity\User;

/**
 * @brief This class is used to send system mails
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class SystemMail extends Email
{
    /**
     * @var Text
     */
    private Text $smTextObject;
    /**
     * @var Organization
     */
    private Organization $smOrganization;
    /**
     * @var Database An object of the class Database for communication with the database
     */
    private Database $db;
    /**
     * @var string
     */
    private string $smMailText;
    /**
     * @var string
     */
    private string $smMailHeader;
    /**
     * @var array<int,string> stores additional variables for the mail text
     */
    private array $smVariables = array();

    /**
     * Constructor that will create an object of a SystemMail to handle all system notifications.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @throws Exception|\PHPMailer\PHPMailer\Exception
     */
    public function __construct(Database $database)
    {
        $this->db =& $database;
        $this->smTextObject = new Text($this->db);
        parent::__construct();
    }

    /**
     * This method reads the mail text from the database. If there is a valid mail text than the method will
     * replace occurring placeholders with the desired content.
     * @param string $systemMailId Unique name of the corresponding system mail, corresponds to adm_texts.txt_name
     * @param User $user User object for which the data is then read and placed in the appropriate placeholders.
     * @return string Returns the text for the email with the replaced placeholders.
     * @throws Exception
     */
    public function getMailText(string $systemMailId, User $user): string
    {
        $this->smMailText = '';
        $this->smMailHeader = '';

        // create organization object of the organization the current user is assigned (at registration this can be every organization)
        if (!isset($this->smOrganization) || (int) $this->smOrganization->getValue('org_id') !== $user->getOrganization()) {
            $this->smOrganization = new Organization($this->db, $user->getOrganization());
        }

        // read email text from text table in database
        if ($this->smTextObject->getValue('txt_name') !== $systemMailId) {
            $this->smTextObject->readDataByColumns(array(
                'txt_name'   => $systemMailId,
                'txt_org_id' => (int) $this->smOrganization->getValue('org_id')
            ));
        }

        $mailSrcText = (string) $this->smTextObject->getValue('txt_text');

        if ($mailSrcText !== '') {
            // use unix line feeds in mail
            $mailSrcText = str_replace("\r\n", "\n", $mailSrcText);

            // now replace all parameters in email text
            $pregRepArray = array(
                '/#user_first_name#/' => $user->getValue('FIRST_NAME', 'database'),
                '/#user_last_name#/' => $user->getValue('LAST_NAME', 'database'),
                '/#user_login_name#/' => $user->getValue('usr_login_name'),
                '/#user_email#/' => $user->getValue('EMAIL'),
                '/#administrator_email#/' => $this->smOrganization->getValue('org_email_administrator'),
                '/#organization_short_name#/' => $this->smOrganization->getValue('org_shortname'),
                '/#organization_long_name#/' => $this->smOrganization->getValue('org_longname'),
                '/#organization_homepage#/' => $this->smOrganization->getValue('org_homepage')
            );

            $mailSrcText = preg_replace(array_keys($pregRepArray), array_values($pregRepArray), $mailSrcText);

            // replace additional variables
            foreach ($this->smVariables as $number => $value) {
                $mailSrcText = str_replace('#variable' . $number . '#', $value, $mailSrcText);
            }

            // Split subject and content based on labels or take default content if necessary
            if (str_contains($mailSrcText, '#subject#')) {
                $this->smMailHeader = trim(substr($mailSrcText, strpos($mailSrcText, '#subject#') + 9, strpos($mailSrcText, '#content#') - 9));
            } else {
                $this->smMailHeader = 'Systemmail von ' . $this->smOrganization->getValue('org_homepage');
            }

            if (str_contains($mailSrcText, '#content#')) {
                $this->smMailText = trim(substr($mailSrcText, strpos($mailSrcText, '#content#') + 9));
            } else {
                $this->smMailText = $mailSrcText;
            }
        }

        return $this->smMailText;
    }

    /**
     * This method sets the content for special variables.
     * @param int $number
     * @param string $value
     */
    public function setVariable(int $number, string $value): void
    {
        $this->smVariables[$number] = $value;
    }

    /**
     * This method sends a system mail after reading the mail text and replacing placeholders.
     * The system mail will only be sent if the preference for notifications is enabled and a valid
     * system mail text is set within the database.
     * @param string $systemMailId Unique name of the corresponding system mail, corresponds to adm_texts.txt_name
     * @param User $user User object for which the data is then read and placed in the appropriate placeholders.
     * @return true Return **true** if the mail was sent and false if it should not be sent because of preferences.
     * @throws Exception|\PHPMailer\PHPMailer\Exception
     */
    public function sendSystemMail(string $systemMailId, User $user): bool
    {
        global $gSettingsManager;

        if ($gSettingsManager->getBool('system_notifications_enabled')) {
            // only send system mail if there is a mail text available
            if ($this->getMailText($systemMailId, $user) !== '') {
                $this->setSender($gSettingsManager->getString('mail_sender_email'), $gSettingsManager->getString('mail_sender_name'));
                $this->setSubject($this->smMailHeader);
                $this->setText($this->smMailText);

                $returnMessage = $this->sendEmail();

                if ($returnMessage) {
                    return true;
                } else {
                    // if something went wrong then throw an exception with the error message
                    throw new Exception('SYS_EMAIL_NOT_SEND', array($user->getValue('EMAIL'), false));
                }
            }
        }

        return false;
    }
}
