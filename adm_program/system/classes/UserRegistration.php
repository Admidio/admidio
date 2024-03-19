<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Creates, assign and update user registrations in database
 *
 * This class extends the User class with some special functions for new registrations.
 * If a new user is saved than there will be an additional table entry in the
 * registration table. This entry must be deleted if a registration is confirmed
 * or deleted. If a registration is confirmed or deleted then a notification SystemMail
 * will be sent to the user. If email couldn't be sent than an AdmException will be thrown.
 *
 * **Code example**
 * ```
 * // create a valid registration
 * $user = new UserRegistration($gDb, $gProfileFields);
 * $user->setValue('LAST_NAME', 'Schmidt');
 * $user->setValue('FIRST_NAME', 'Franka');
 * ...
 * // save user data and create registration
 * $user->save();
 * ```
 *
 * **Code example**
 * ```
 * // assign a registration
 * $userId = 4711;
 * $user = new UserRegistration($gDb, $gProfileFields, $userId);
 * // set user to valid and send notification email
 * $user->acceptRegistration();
 * ```
 */
class UserRegistration extends User
{
    /**
     * @var bool Flag if the object will send a SystemMail if registration is accepted or deleted.
     */
    private $sendEmail = true;
    /**
     * @var TableAccess
     */
    private $tableRegistration;

    /**
     * Constructor that will create an object of a recordset of the users table.
     * If the id is set than this recordset will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param ProfileFields $userFields An object of the ProfileFields class with the profile field structure
     *                                      of the current organization. This could be the default object .
     * @param int $userId The id of the user who should be loaded. If id isn't set than an empty object
     *                                      with no specific user is created.
     * @param int $organizationId The id of the organization for which the user should be registered.
     *                                      If no id is set than the user will be registered for the current organization.
     * @throws AdmException
     * @throws Exception
     */
    public function __construct(Database $database, ProfileFields $userFields, int $userId = 0, int $organizationId = 0)
    {
        parent::__construct($database, $userFields, $userId);

        // changes to a registration user should not be relevant for the change notifications
        $this->disableChangeNotification();

        if ($organizationId > 0) {
            $this->setOrganization($organizationId);
        }

        // create recordset for registration table
        $this->tableRegistration = new TableAccess($this->db, TBL_REGISTRATIONS, 'reg');
        $this->tableRegistration->readDataByColumns(array('reg_org_id' => $this->organizationId, 'reg_usr_id' => $userId));
    }

    /**
     * Deletes the registration record and set the user to valid. The user will also be assigned to all roles
     * that have the flag **rol_default_registration**. After that a notification email is send to the user.
     * If function returns **true** than the user can log in for the organization of this object.
     * @return true Returns **true** if the registration was successful
     * @throws AdmException|SmartyException
     * @throws Exception
     */
    public function acceptRegistration(): bool
    {
        global $gSettingsManager, $gMenu;

        $this->db->startTransaction();

        // set user active
        $this->saveChangesWithoutRights();
        $this->setValue('usr_valid', 1);
        $this->save();

        // delete registration record in registration table
        $this->tableRegistration->delete();

        // every user will get the default roles for registration
        $this->assignDefaultRoles();

        $this->db->endTransaction();

        // update registration count in menu
        $gMenu->initialize();

        // only send mail if systemmails are enabled
        if ($gSettingsManager->getBool('system_notifications_enabled')
        && $gSettingsManager->getBool('registration_manual_approval') && $this->sendEmail) {
            // send mail to user that his registration was accepted
            $sysMail = new SystemMail($this->db);
            $sysMail->addRecipientsByUser($this->getValue('usr_uuid'));
            $sysMail->sendSystemMail('SYSMAIL_REGISTRATION_APPROVED', $this); // TODO Exception handling
        }

        return true;
    }

    /**
     * Method will adopt the profile fields data of this user object to the user object of the parameters.
     * If the system setting **registration_adopt_all_data** is set than all data of the registration process will
     * be adopted otherwise only username and password will be added to the existing user. The adopted data of the
     * user will not be saved. That must be done in the calling method because of duplicate **usr_login_name**.
     * @param User $user Object of the existing user that should be adopted.
     * @throws AdmException
     * @throws Exception
     */
    public function adoptUser(User $user)
    {
        // always adopt login name and password to the destination user
        $user->setValue('usr_login_name', $this->getValue('usr_login_name'));
        $user->setPassword($this->getValue('usr_password'), false);

        // adopt all registration fields to the user if this is enabled in the settings
        if ($GLOBALS['gSettingsManager']->getBool('registration_adopt_all_data')) {
            foreach ($this->mProfileFieldsData->getProfileFields() as $profileField) {
                if ($profileField->getValue('usf_registration') && $this->mProfileFieldsData->getValue($profileField->getValue('usf_name_intern')) !== '') {
                    $user->setValue($profileField->getValue('usf_name_intern'), $this->mProfileFieldsData->getValue($profileField->getValue('usf_name_intern'), 'database'));
                }
            }
        }
    }

    /**
     * Deletes the selected user registration. If user is not valid and has no other registrations than
     * delete user because he has no use for the system. After that a notification email is send to the user.
     * If the user is valider than only the registration will be deleted!
     * @return bool **true** if no error occurred
     * @throws AdmException
     * @throws Exception
     */
    public function delete(): bool
    {
        global $gMenu;

        // only send mail if systemmails are enabled and user has email address
        // mail must be sent before user data is removed from this object
        if ($GLOBALS['gSettingsManager']->getBool('system_notifications_enabled') && $this->sendEmail && $this->getValue('EMAIL') !== '') {
            // send mail to user that his registration was rejected
            $sysMail = new SystemMail($this->db);
            $sysMail->addRecipientsByUser($this->getValue('usr_uuid'));
            $sysMail->sendSystemMail('SYSMAIL_REGISTRATION_REFUSED', $this); // TODO Exception handling
        }

        $this->db->startTransaction();

        // delete registration record in registration table
        $return = $this->tableRegistration->delete();

        // if user is not valid and has no other registrations
        // than delete user because he has no use for the system
        if (!$this->getValue('usr_valid')) {
            $sql = 'SELECT reg_id
                      FROM '.TBL_REGISTRATIONS.'
                     WHERE reg_usr_id = ? -- $this->getValue(\'usr_id\')';
            $registrationsStatement = $this->db->queryPrepared($sql, array((int) $this->getValue('usr_id')));

            if ($registrationsStatement->rowCount() === 0) {
                $return = parent::delete();
            }
        }

        $this->db->endTransaction();

        // update registration count in menu
        $gMenu->initialize();

        return $return;
    }

    /**
     * Send a notification email to all role members of roles that can approve registrations
     * therefore the flags system mails and notification mail for roles with approve registration must be activated
     * @throws AdmException
     * @throws Exception
     */
    public function notifyAuthorizedMembers()
    {
        global $gSettingsManager;

        if ($gSettingsManager->getBool('system_notifications_enabled')
            && $gSettingsManager->getBool('registration_send_notification_email') && $this->sendEmail) {
            $sql = 'SELECT rol_uuid
                      FROM '.TBL_ROLES.'
                INNER JOIN '.TBL_CATEGORIES.'
                        ON cat_id = rol_cat_id
                     WHERE rol_approve_users = true
                       AND rol_valid = true
                       AND cat_org_id = ? -- $this->organizationId';
            $rolesStatement = $this->db->queryPrepared($sql, array($this->organizationId));

            while ($row = $rolesStatement->fetch()) {
                // send mail that a new registration is available
                $sysMail = new SystemMail($this->db);
                $sysMail->addRecipientsByRole($row['rol_uuid']);
                $sysMail->sendSystemMail('SYSMAIL_REGISTRATION_NEW', $this); // TODO Exception handling
            }
        }
    }

    /**
     * If called than the object will not send a SystemMail when registration was accepted or deleted.
     */
    public function notSendEmail()
    {
        $this->sendEmail = false;
    }

    /**
     * Reads a record out of the table in database selected by the unique uuid column in the table.
     * The name of the column must have the syntax table_prefix, underscore and uuid. E.g. usr_uuid.
     * Per default all columns of the default table will be read and stored in the object.
     * Not every Admidio table has an uuid. Please check the database structure before you use this method.
     * @param string $uuid Unique uuid that should be searched.
     * @return bool Returns **true** if one record is found
     * @throws AdmException
     * @throws Exception
     * @see TableAccess#readDataByColumns
     * @see TableAccess#readData
     */
    public function readDataByUuid(string $uuid): bool
    {
        $returnValue = parent::readDataByUuid($uuid);

        // create recordset for registration table
        $this->tableRegistration = new TableAccess($this->db, TBL_REGISTRATIONS, 'reg');
        $this->tableRegistration->readDataByColumns(array('reg_org_id' => $GLOBALS['gCurrentOrganization']->getValue('org_id'),
            'reg_usr_id' => $this->getValue('usr_id')));

        return $returnValue;
    }

    /**
     * Save all changed columns of the recordset in table of database. If it's a new user
     * than the registration table will also be filled with a new recordset and optional a
     * notification mail will be sent to all users of roles that have the right to confirm registrations
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset
     *                                if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool
     * @throws AdmException
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        global $gSettingsManager;

        // if new registration is saved then set user not valid
        if ($this->tableRegistration->isNewRecord()) {
            $this->setValue('usr_valid', 0);
        }

        $returnValue = parent::save($updateFingerPrint); // TODO Exception handling

        // if new registration is saved then save also record in registration table and send notification mail
        if ($this->tableRegistration->isNewRecord()) {
            // create a unique validation id
            $validationId = SecurityUtils::getRandomString(50);

            // save registration record
            $this->tableRegistration->setValue('reg_org_id', $this->organizationId);
            $this->tableRegistration->setValue('reg_usr_id', (int) $this->getValue('usr_id'));
            $this->tableRegistration->setValue('reg_timestamp', DATETIME_NOW);
            $this->tableRegistration->setValue('reg_validation_id', $validationId);
            $this->tableRegistration->save();

            // send a notification mail to the user to confirm his registration
            if ($gSettingsManager->getBool('system_notifications_enabled') && $this->sendEmail) {
                $sysMail = new SystemMail($this->db);
                $sysMail->addRecipientsByUser($this->getValue('usr_uuid'));
                $sysMail->setVariable(1, SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/registration/registration.php', array('user_uuid' => $this->getValue('usr_uuid'), 'id' => $validationId)));
                $sysMail->sendSystemMail('SYSMAIL_REGISTRATION_CONFIRMATION', $this); // TODO Exception handling
            }
        }

        return $returnValue;
    }

    /**
     * If email support is enabled and the current user is administrator or has the right to approve new
     * registrations than this method will create a new password and send this to the user. Also the current
     * registration record will be deleted because it has no use anymore.
     * @return void
     * @throws AdmException
     * @throws Exception
     */
    public function sendNewPassword()
    {
        // Resend access data
        parent::sendNewPassword();

        // delete registration record in registration table
        $this->tableRegistration->delete();
    }

    /**
     * Method will search in the registration table for a valid registration for this user in combination with the
     * validation ID. If a valid registration was found than reg_validation_id will be cleared and so the
     * registration is validated through the user. After that the registration must be manually approved by a member
     * of the organization.
     * @param string $validationId A validation ID of the registration that should be checked.
     * @return bool Return **true** if the given data could be joined to a valid registration
     * @throws AdmException
     * @throws Exception
     */
    public function validate(string $validationId): bool
    {
        $sql = 'SELECT reg.*
                  FROM ' . TBL_REGISTRATIONS . ' reg
                 WHERE reg_usr_id = ? -- $this->getValue(\'usr_id\')
                   AND reg_validation_id = ? -- $validationId ';
        $queryParams = array(
            $this->getValue('usr_id'),
            $validationId
        );
        $registrationStatement = $this->db->queryPrepared($sql, $queryParams);

        if ($registrationStatement->rowCount() === 1) {
            $row = $registrationStatement->fetch();
            $registration = new TableAccess($this->db, TBL_REGISTRATIONS, 'reg');
            $registration->setArray($row);

            // registration ID is only valid for 1 day
            $timeGap = time() - strtotime($row['reg_timestamp']);

            if ($timeGap < 60 * 60 * 24) {
                $registration->setValue('reg_validation_id', null);
                $registration->save();
                return true;
            }
        }

        return false;
    }
}
