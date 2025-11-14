<?php
namespace Admidio\Infrastructure\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\SystemMail;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserRegistration;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the registration module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class RegistrationService
{
    /**
     * @var Database $db An object of the class Database for communication with the database
     */
    protected Database $db;
    /**
     * @var string $registrationUserUUID UUID of the user who wants to confirm his registration.
     */
    protected string $registrationUserUUID = '';

    /**
     * Constructor that will create an object of a recordset of the table adm_lists.
     * If the id is set than the specific list will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $registrationUserUUID UUID of the user who wants to confirm his registration.
     */
    public function __construct(Database $database, string $registrationUserUUID = '')
    {
        $this->db = $database;
        $this->registrationUserUUID = $registrationUserUUID;
    }

    /**
     * User has clicked the link in his registration email, and now check if it's a valid request
     * and then confirm his registration. If manual approval is enabled, notify all authorized members
     * otherwise accept the registration.
     * @param string $assignUserUUID The UUID of the user to whom the new login should be assigned
     * @param bool $memberOfOrganization True if the user is already a member of the organization, otherwise false.
     * @return array{message: string, forwardUrl: string} Array with message and forward url.
     * @throws Exception
     */
    public function assignRegistration(string $assignUserUUID, bool $memberOfOrganization): array
    {
        global $gSettingsManager, $gProfileFields, $gCurrentUser, $gL10n, $gNavigation;

        $user = new User($this->db, $gProfileFields);
        $user->readDataByUuid($assignUserUUID);

        try {
            $registrationUser = new UserRegistration($this->db, $gProfileFields);
            $registrationUser->readDataByUuid($this->registrationUserUUID);

            $this->db->startTransaction();

            // adopt the data of the registration user to the existing user account
            $registrationUser->adoptUser($user);

            // first delete the new user set, then update the old one to avoid a
            // duplicate key because of the login name
            $registrationUser->notSendEmail();
            $registrationUser->delete();
            $user->save();

            // every new user to the organization will get the default roles for registration
            if (!$memberOfOrganization) {
                $user->assignDefaultRoles();
            }
            $this->db->endTransaction();

            if ($gSettingsManager->getBool('system_notifications_enabled')) {
                // Send mail to the user to confirm the registration or the assignment to the new organization
                $systemMail = new SystemMail($this->db);
                $systemMail->addRecipientsByUser($assignUserUUID);
                $systemMail->sendSystemMail('SYSMAIL_REGISTRATION_APPROVED', $user);
                $message = $gL10n->get('SYS_ASSIGN_LOGIN_EMAIL', array($user->getValue('EMAIL')));
            } else {
                $message = $gL10n->get('SYS_ASSIGN_LOGIN_SUCCESSFUL');
            }

            // if current user has the right to assign roles then show roles dialog
            // otherwise go to previous url (default roles are assigned automatically)
            if ($gCurrentUser->isAdministratorRoles()) {
                // User already exists, but is not yet a member of the current organization, so first assign roles and then send mail later
                admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/profile/roles.php', array('user_uuid' => $assignUserUUID, 'accept_registration' => true)));
                // => EXIT
            }
        } catch (Exception $e) {
            // exception is thrown when email couldn't be sent
            // so save user data and then show error
            $user->save();
            $this->db->endTransaction();
            return array('message' => $e->getMessage(), 'forwardUrl' => $gNavigation->getPreviousUrl());
        }

        return array('message' => $message, 'forwardUrl' => ADMIDIO_URL.FOLDER_MODULES.'/registration.php');
    }

    /**
     * User has clicked the link in his registration email, and now check if it's a valid request
     * and then confirm his registration. If manual approval is enabled, notify all authorized members
     * otherwise accept the registration.
     * @param string $registrationId The validation id to confirm the registration by the user.
     * @return array{message: string, forwardUrl: string} Array with message and forward url.
     * @throws Exception
     */
    public function confirmRegistration(string $registrationId): array
    {
        global $gSettingsManager, $gProfileFields, $gCurrentOrganization, $gL10n;

        $userRegistration = new UserRegistration($this->db, $gProfileFields);
        $userRegistration->readDataByUuid($this->registrationUserUUID);

        if ($userRegistration->validate($registrationId)) {
            if ($gSettingsManager->getBool('registration_manual_approval')) {
                // notify all authorized members about the new registration to approve it
                $userRegistration->notifyAuthorizedMembers();
                $forwardUrl = $gCurrentOrganization->getValue('org_homepage');
                $message = $gL10n->get('SYS_REGISTRATION_VALIDATION_OK', array($gCurrentOrganization->getValue('org_longname')));
            } else {
                // user has done a successful registration, so the account could be activated
                $userRegistration->acceptRegistration();
                $forwardUrl = ADMIDIO_URL . FOLDER_SYSTEM . '/login.php';
                $message = $gL10n->get('SYS_REGISTRATION_VALIDATION_OK_SELF');
            }
        } else {
            throw new Exception('SYS_REGISTRATION_VALIDATION_FAILED');
        }
        return array('message' => $message, 'forwardUrl' => $forwardUrl);
    }

    /**
     * Creates an array with all available registrations.
     * The returned array contains the following information:
     * usr_id, usr_uuid, usr_login_name, reg_timestamp, reg_validation_id
     * @return array Returns an array with all available registrations.
     * @throws Exception
     */
    public function findAll(): array
    {
        global $gCurrentOrgId;

        // Select new Members of the group
        $sql = 'SELECT usr_id, usr_uuid, usr_login_name, reg_timestamp, reg_validation_id
                  FROM '.TBL_REGISTRATIONS.'
            INNER JOIN '.TBL_USERS.'
                    ON usr_id = reg_usr_id
                 WHERE usr_valid = false
                   AND reg_org_id = ? -- $gCurrentOrgId
              ORDER BY reg_validation_id DESC, reg_timestamp DESC';
        $queryParameters = array($gCurrentOrgId);

        return $this->db->getArrayFromSql($sql, $queryParameters);
    }
}
