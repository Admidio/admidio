<?php
/*****************************************************************************/
/** @class UserRegistration
 *  @brief Creates, assign and update user registrations in database
 *
 *  This class extends the user class with some special functions for new registrations.
 *  If a new user is saved than there will be an additional table entry in the 
 *  registration table. This entry must be deleted if a registration is confirmed
 *  or deleted. If a registration is confirmed or deleted then a notification email
 *  will be send to the user.
 *  @par Examples
 *  @code // create a valid registration
 *  $user = new UserRegistration($gDb, $gProfileFields);
 *  $user->setValue('LAST_NAME', 'Schmidt');
 *  $user->setValue('FIRST_NAME', 'Franka');
 *  ...
 *  // save user data and create registration
 *  $user->save(); @endcode
 *  @code // assign a registration
 *  $userId = 4711;
 *  $user = new UserRegistration($gDb, $gProfileFields, $userId);
 *  // set user to valid and send notification email
 *  $user->acceptRegistration(); @endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2012 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/user.php');

class UserRegistration extends User
{
	private $TableRegistration;

    // Constructor
    public function __construct(&$db, $userFields, $userId, $organizationId = 0)
    {
		global $gCurrentOrganization;
		
        parent::__construct($db, $userFields, $userId);

		if($organizationId > 0)
		{
			$this->setOrganization($organizationId);
		}
		
		// create recordset for registration table
		$this->TableRegistration = new TableAccess($this->db, TBL_REGISTRATIONS, 'reg');
		$this->TableRegistration->readDataByColumns(array('reg_org_id' => $this->organizationId, 'reg_usr_id' => $userId));
    }
	
	/** Deletes the registration record and set the user to valid. The user will also be
     *  assigned to all roles that have the flag @b rol_default_registration. After that 
	 *  a notification email is send to the user. If function returns true than the user
	 *  can login for the organization of this object.
	 *  @return Returns @b true if the registration was succesful
	 */
	public function acceptRegistration()
	{
		global $gMessage, $gL10n, $gPreferences;

		$this->db->startTransaction();
		
		// set user active
		$this->setValue('usr_valid', 1);
		$this->save();
		
		// delete registration record in registration table
		$this->TableRegistration->delete();

		// every user will get the default roles for registration
		$this->assignDefaultRoles();
		
		$this->db->endTransaction();
		
        // only send mail if systemmails are enabled
        if($gPreferences['enable_system_mails'] == 1)
        {
            // send mail to user that his registration was accepted
            $sysmail = new SystemMail($this->db);
            $sysmail->addRecipient($this->getValue('EMAIL'), $this->getValue('FIRST_NAME'). ' '. $this->getValue('LAST_NAME'));
            if($sysmail->sendSystemMail('SYSMAIL_REGISTRATION_USER', $this) == false)
            {
                $gMessage->show($gL10n->get('SYS_EMAIL_NOT_SEND', $this->getValue('EMAIL')));
            }
        }

		return true;
	}
	
	/** Deletes the selected user registration. If user is not valid and has no other registrations 
	 *  than delete user because he has no use for the system. After that 
	 *  a notification email is send to the user. If the user is valid than only
	 *  the registration will be deleted! 
	 *  @return @b true if no error occured
	 */
    public function delete()
    {
		global $gMessage, $gL10n, $gPreferences;

        // only send mail if systemmails are enabled
		// send email before user will be deleted
        if($gPreferences['enable_system_mails'] == 1)
        {
            // send mail to user that his registration was accepted
            $sysmail = new SystemMail($this->db);
            $sysmail->addRecipient($this->getValue('EMAIL'), $this->getValue('FIRST_NAME'). ' '. $this->getValue('LAST_NAME'));
            if($sysmail->sendSystemMail('SYSMAIL_REFUSE_REGISTRATION', $this) == false)
            {
                $gMessage->show($gL10n->get('SYS_EMAIL_NOT_SEND', $this->getValue('EMAIL')));
            }
        }

		$this->db->startTransaction();
		
		// delete registration record in registration table
		$return = $this->TableRegistration->delete();
		
		// if user is not valid and has no other registrations 
		// than delete user because he has no use for the system
		if($this->getValue('usr_valid') == 0)
		{
			$sql = 'SELECT reg_id FROM '.TBL_REGISTRATIONS.' WHERE reg_usr_id = '.$this->getValue('usr_id');
			$this->db->query($sql);

			if($this->db->num_rows() == 0)
			{
				$return = parent::delete();
			}
		}
		
        $this->db->endTransaction();		
        return $return;	
	}

	/** Save all changed columns of the recordset in table of database. If it's a new user than the registration table
	 *  will also be filled with a new recordset.
	 *  @param $updateFingerPrint Default @b true. Will update the creator or editor of the recordset 
	 *                            if table has columns like @b usr_id_create or @b usr_id_changed
	 */
    public function save($updateFingerPrint = true)
    {
		// if new registration is saved then set user not valid
		if($this->TableRegistration->isNewRecord())
		{
			$this->setValue('usr_valid', 0);
		}
		
        parent::save($updateFingerPrint);
		
		// if new registration is saved then save also record in registration table
		if($this->TableRegistration->isNewRecord())
		{
			$this->TableRegistration->setValue('reg_org_id', $this->organizationId);
			$this->TableRegistration->setValue('reg_usr_id', $this->getValue('usr_id'));
			$this->TableRegistration->setValue('reg_timestamp', DATETIME_NOW);
			$this->TableRegistration->save();
		}
    }
}
?>