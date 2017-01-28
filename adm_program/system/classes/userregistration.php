<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class UserRegistration
 * @brief Creates, assign and update user registrations in database
 *
 * This class extends the User class with some special functions for new registrations.
 * If a new user is saved than there will be an additional table entry in the
 * registration table. This entry must be deleted if a registration is confirmed
 * or deleted. If a registration is confirmed or deleted then a notification SystemMail
 * will be send to the user. If email couldn't be send than an AdmException will be thrown.
 * @par Example 1
 * @code // create a valid registration
 * $user = new UserRegistration($gDb, $gProfileFields);
 * $user->setValue('LAST_NAME', 'Schmidt');
 * $user->setValue('FIRST_NAME', 'Franka');
 * ...
 * // save user data and create registration
 * $user->save(); @endcode
 * @par Example 2
 * @code // assign a registration
 * $userId = 4711;
 * $user = new UserRegistration($gDb, $gProfileFields, $userId);
 * // set user to valid and send notification email
 * $user->acceptRegistration(); @endcode
 */
class UserRegistration extends User
{
    private $sendEmail; ///< Flag if the object will send a SystemMail if registration is accepted or deleted.
    private $tableRegistration;

    /**
     * Constructor that will create an object of a recordset of the users table.
     * If the id is set than this recordset will be loaded.
     * @param \Database      $database       Object of the class Database. This should be the default global object @b $gDb.
     * @param \ProfileFields $userFields     An object of the ProfileFields class with the profile field structure
     *                                       of the current organization. This could be the default object @b $gProfileFields.
     * @param int            $userId         The id of the user who should be loaded. If id isn't set than an empty object
     *                                       with no specific user is created.
     * @param int            $organizationId The id of the organization for which the user should be registered.
     *                                       If no id is set than the user will be registered for the current organization.
     */
    public function __construct(&$database, ProfileFields $userFields, $userId = 0, $organizationId = 0)
    {
        global $gCurrentOrganization;

        $this->sendEmail = true;

        parent::__construct($database, $userFields, $userId);

        if($organizationId > 0)
        {
            $this->setOrganization($organizationId);
        }

        // create recordset for registration table
        $this->tableRegistration = new TableAccess($this->db, TBL_REGISTRATIONS, 'reg');
        $this->tableRegistration->readDataByColumns(array('reg_org_id' => $this->organizationId, 'reg_usr_id' => $userId));
    }

    /**
     * Deletes the registration record and set the user to valid. The user will also be assigned to all roles
     * that have the flag @b rol_default_registration. After that a notification email is send to the user.
     * If function returns true than the user can login for the organization of this object.
     * @return true Returns @b true if the registration was successful
     */
    public function acceptRegistration()
    {
        global $gMessage, $gL10n, $gPreferences;

        $this->db->startTransaction();

        // set user active
        $this->setValue('usr_valid', 1);
        $this->save();

        // delete registration record in registration table
        $this->tableRegistration->delete();

        // every user will get the default roles for registration
        $this->assignDefaultRoles();

        $this->db->endTransaction();

        // only send mail if systemmails are enabled
        if($gPreferences['enable_system_mails'] == 1 && $this->sendEmail)
        {
            // send mail to user that his registration was accepted
            $sysmail = new SystemMail($this->db);
            $sysmail->addRecipient($this->getValue('EMAIL'), $this->getValue('FIRST_NAME', 'database').' '.$this->getValue('LAST_NAME', 'database'));
            $sysmail->sendSystemMail('SYSMAIL_REGISTRATION_USER', $this); // TODO Exception handling
        }

        return true;
    }

    /**
     * Deletes the selected user registration. If user is not valid and has no other registrations than
     * delete user because he has no use for the system. After that a notification email is send to the user.
     * If the user is valid than only the registration will be deleted!
     * @return bool @b true if no error occurred
     */
    public function delete()
    {
        global $gMessage, $gL10n, $gPreferences;

        $userEmail     = $this->getValue('EMAIL');
        $userFirstName = $this->getValue('FIRST_NAME');
        $userLastName  = $this->getValue('LAST_NAME');

        $this->db->startTransaction();

        // delete registration record in registration table
        $return = $this->tableRegistration->delete();

        // if user is not valid and has no other registrations
        // than delete user because he has no use for the system
        if($this->getValue('usr_valid') == 0)
        {
            $sql = 'SELECT reg_id
                      FROM '.TBL_REGISTRATIONS.'
                     WHERE reg_usr_id = '.$this->getValue('usr_id');
            $registrationsStatement = $this->db->query($sql);

            if($registrationsStatement->rowCount() === 0)
            {
                $return = parent::delete();
            }
        }

        $this->db->endTransaction();

        // only send mail if systemmails are enabled and user has email address
        if($gPreferences['enable_system_mails'] == 1 && $this->sendEmail && $userEmail !== '')
        {
            // send mail to user that his registration was accepted
            $sysmail = new SystemMail($this->db);
            $sysmail->addRecipient($userEmail, $userFirstName. ' '. $userLastName);
            $sysmail->sendSystemMail('SYSMAIL_REFUSE_REGISTRATION', $this); // TODO Exception handling
        }

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
     * Save all changed columns of the recordset in table of database. If it's a new user
     * than the registration table will also be filled with a new recordset and optional a
     * notification mail will be send to all users of roles that have the right to confirm registrations
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset
     *                                if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool
     */
    public function save($updateFingerPrint = true)
    {
        global $gMessage, $gL10n, $gPreferences;

        // if new registration is saved then set user not valid
        if($this->tableRegistration->isNewRecord())
        {
            $this->setValue('usr_valid', 0);
        }

        $returnValue = parent::save($updateFingerPrint); // TODO Exception handling

        // if new registration is saved then save also record in registration table and send notification mail
        if($this->tableRegistration->isNewRecord())
        {
            // save registration record
            $this->tableRegistration->setValue('reg_org_id', $this->organizationId);
            $this->tableRegistration->setValue('reg_usr_id', $this->getValue('usr_id'));
            $this->tableRegistration->setValue('reg_timestamp', DATETIME_NOW);
            $this->tableRegistration->save();

            // send a notification mail to all role members of roles that can approve registrations
            // therefore the flags system mails and notification mail for roles with approve registration must be activated
            if($gPreferences['enable_system_mails'] == 1 && $gPreferences['enable_registration_admin_mail'] == 1 && $this->sendEmail)
            {
                $sql = 'SELECT DISTINCT first_name.usd_value AS first_name, last_name.usd_value AS last_name, email.usd_value AS email
                          FROM '.TBL_MEMBERS.'
                    INNER JOIN '.TBL_ROLES.'
                            ON rol_id = mem_rol_id
                    INNER JOIN '.TBL_CATEGORIES.'
                            ON cat_id = rol_cat_id
                    INNER JOIN '.TBL_USERS.'
                            ON usr_id = mem_usr_id
                    RIGHT JOIN '.TBL_USER_DATA.' email
                            ON email.usd_usr_id = usr_id
                           AND email.usd_usf_id = '. $this->mProfileFieldsData->getProperty('EMAIL', 'usf_id'). '
                           AND LENGTH(email.usd_value) > 0
                     LEFT JOIN '.TBL_USER_DATA.' first_name
                            ON first_name.usd_usr_id = usr_id
                           AND first_name.usd_usf_id = '. $this->mProfileFieldsData->getProperty('FIRST_NAME', 'usf_id'). '
                     LEFT JOIN '.TBL_USER_DATA.' last_name
                            ON last_name.usd_usr_id = usr_id
                           AND last_name.usd_usf_id = '. $this->mProfileFieldsData->getProperty('LAST_NAME', 'usf_id'). '
                         WHERE rol_approve_users = 1
                           AND usr_valid         = 1
                           AND cat_org_id        = '.$this->organizationId.'
                           AND mem_begin        <= \''.DATE_NOW.'\'
                           AND mem_end           > \''.DATE_NOW.'\'';
                $emailStatement = $this->db->query($sql);

                while($row = $emailStatement->fetch())
                {
                    // send mail that a new registration is available
                    $sysmail = new SystemMail($this->db);
                    $sysmail->addRecipient($row['email'], $row['first_name']. ' '. $row['last_name']);
                    $sysmail->sendSystemMail('SYSMAIL_REGISTRATION_WEBMASTER', $this); // TODO Exception handling
                }
            }
        }

        return $returnValue;
    }
}
