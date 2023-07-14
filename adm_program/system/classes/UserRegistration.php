<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
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
 * will be send to the user. If email couldn't be send than an AdmException will be thrown.
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
     * @param Database      $database       Object of the class Database. This should be the default global object **$gDb**.
     * @param ProfileFields $userFields     An object of the ProfileFields class with the profile field structure
     *                                      of the current organization. This could be the default object .
     * @param int           $userId         The id of the user who should be loaded. If id isn't set than an empty object
     *                                      with no specific user is created.
     * @param int           $organizationId The id of the organization for which the user should be registered.
     *                                      If no id is set than the user will be registered for the current organization.
     */
    public function __construct(Database $database, ProfileFields $userFields, $userId = 0, $organizationId = 0)
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
     * If function returns true than the user can login for the organization of this object.
     * @return true Returns **true** if the registration was successful
     */
    public function acceptRegistration()
    {
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

        // only send mail if systemmails are enabled
        if ($GLOBALS['gSettingsManager']->getBool('system_notifications_enabled') && $this->sendEmail) {
            // send mail to user that his registration was accepted
            $sysmail = new SystemMail($this->db);
            $sysmail->addRecipientsByUserId((int) $this->getValue('usr_id'));
            $sysmail->sendSystemMail('SYSMAIL_REGISTRATION_USER', $this); // TODO Exception handling
        }

        return true;
    }

    /**
     * Method will adopt the profile fields data of this user object to the user object of the parameters.
     * If the system setting **registration_adopt_all_data** is set than all data of the registration process will
     * be adopted otherwise only username and password will be added to the existing user. The adopted data of the
     * user will not be saved. That must be done in the calling method because of duplicate **usr_login_name**.
     * @param User $user Object of the existing user that should be adopted.
     */
    public function adoptUser(User $user)
    {
        // always adopt loginname and password to the destination user
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
     * If the user is valid than only the registration will be deleted!
     * @return bool **true** if no error occurred
     */
    public function delete()
    {
        // only send mail if systemmails are enabled and user has email address
        // mail must be send before user data is removed from this object
        if ($GLOBALS['gSettingsManager']->getBool('system_notifications_enabled') && $this->sendEmail && $this->getValue('EMAIL') !== '') {
            // send mail to user that his registration was rejected
            $sysmail = new SystemMail($this->db);
            $sysmail->addRecipientsByUserId((int) $this->getValue('usr_id'));
            $sysmail->sendSystemMail('SYSMAIL_REFUSE_REGISTRATION', $this); // TODO Exception handling
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

        return $return;
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
     * Not every Admidio table has a uuid. Please check the database structure before you use this method.
     * @param int $uuid Unique uuid that should be searched.
     * @return bool Returns **true** if one record is found
     * @see TableAccess#readData
     * @see TableAccess#readDataByColumns
     */
    public function readDataByUuid($uuid)
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
     * notification mail will be send to all users of roles that have the right to confirm registrations
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset
     *                                if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool
     */
    public function save($updateFingerPrint = true)
    {
        // if new registration is saved then set user not valid
        if ($this->tableRegistration->isNewRecord()) {
            $this->setValue('usr_valid', 0);
        }

        $returnValue = parent::save($updateFingerPrint); // TODO Exception handling

        // if new registration is saved then save also record in registration table and send notification mail
        if ($this->tableRegistration->isNewRecord()) {
            // save registration record
            $this->tableRegistration->setValue('reg_org_id', $this->organizationId);
            $this->tableRegistration->setValue('reg_usr_id', (int) $this->getValue('usr_id'));
            $this->tableRegistration->setValue('reg_timestamp', DATETIME_NOW);
            $this->tableRegistration->save();

            // send a notification mail to all role members of roles that can approve registrations
            // therefore the flags system mails and notification mail for roles with approve registration must be activated
            if ($GLOBALS['gSettingsManager']->getBool('system_notifications_enabled')
                && $GLOBALS['gSettingsManager']->getBool('enable_registration_admin_mail') && $this->sendEmail) {
                $sql = 'SELECT DISTINCT first_name.usd_value AS first_name, last_name.usd_value AS last_name, email.usd_value AS email
                          FROM '.TBL_MEMBERS.'
                    INNER JOIN '.TBL_ROLES.'
                            ON rol_id = mem_rol_id
                    INNER JOIN '.TBL_CATEGORIES.'
                            ON cat_id = rol_cat_id
                    INNER JOIN '.TBL_USERS.'
                            ON usr_id = mem_usr_id
                    RIGHT JOIN '.TBL_USER_DATA.' AS email
                            ON email.usd_usr_id = usr_id
                           AND email.usd_usf_id = ? -- $this->mProfileFieldsData->getProperty(\'EMAIL\', \'usf_id\')
                           AND LENGTH(email.usd_value) > 0
                     LEFT JOIN '.TBL_USER_DATA.' AS first_name
                            ON first_name.usd_usr_id = usr_id
                           AND first_name.usd_usf_id = ? -- $this->mProfileFieldsData->getProperty(\'FIRST_NAME\', \'usf_id\')
                     LEFT JOIN '.TBL_USER_DATA.' AS last_name
                            ON last_name.usd_usr_id = usr_id
                           AND last_name.usd_usf_id = ? -- $this->mProfileFieldsData->getProperty(\'LAST_NAME\', \'usf_id\')
                         WHERE rol_approve_users = true
                           AND usr_valid  = true
                           AND cat_org_id = ? -- $this->organizationId
                           AND mem_begin <= ? -- DATE_NOW
                           AND mem_end    > ? -- DATE_NOW';
                $queryParams = array(
                    $this->mProfileFieldsData->getProperty('EMAIL', 'usf_id'),
                    $this->mProfileFieldsData->getProperty('FIRST_NAME', 'usf_id'),
                    $this->mProfileFieldsData->getProperty('LAST_NAME', 'usf_id'),
                    $this->organizationId,
                    DATE_NOW,
                    DATE_NOW
                );
                $emailStatement = $this->db->queryPrepared($sql, $queryParams);

                while ($row = $emailStatement->fetch()) {
                    // send mail that a new registration is available
                    $sysmail = new SystemMail($this->db);
                    $sysmail->addRecipient($row['email'], $row['first_name'], $row['last_name']);
                    $sysmail->sendSystemMail('SYSMAIL_REGISTRATION_WEBMASTER', $this); // TODO Exception handling
                }
            }
        }

        return $returnValue;
    }
}
