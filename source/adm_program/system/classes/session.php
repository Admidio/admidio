<?php
/*****************************************************************************/
/** @class Session
 *  @brief Handle session data of Admidio and is connected to database table adm_sessions
 *
 *  This class should be used together with the PHP session handling. If you
 *  create a PHP session than you should also create this session object. The
 *  class will create a recordset in adm_sessions which stores the PHP session id.
 *  With this class it should be easy to add other objects to the session and read 
 *  them out if you need them elsewhere.
 *  @par Examples
 *  @code script_a.php
 *  // add a new object to the session
 *  $organization = new Organization($gDb, $organizationId);
 *  $session = new Session($gDb, $sessionId);
 *  $session->addObject('organization', $organization, true);
 *  
 *  script_b.php
 *  // read object out of session
 *  if($session->hasObject('organization'))
 *  {
 *  	$organization =& $session->getObject('organization');
 *  }@endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class Session extends TableAccess
{
	private $mObjectArray   = array(); ///< Array with all objects of this session object.

	/** Constuctor that will create an object of a recordset of the table adm_sessions. 
	 *  If the id is set than the specific session will be loaded.
	 *  @param $db Object of the class database. This should be the default object $gDb.
	 *  @param $session The recordset of the session with this id will be loaded. The session can be the table id or the alphanumeric session id. If id isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $session = 0)
    {
        parent::__construct($db, TBL_SESSIONS, 'ses');

		if(is_numeric($session))
		{
			$this->readDataById($session);
		}
		else
		{
			$this->readDataByColumns(array('ses_session_id' => $session));
			
			if($this->new_record)
			{
				// if PHP session id was commited then store them in that field
				$this->setValue('ses_session_id', $session);
			}
		}
    }

	/** Adds an object to the object array of this class. Objects in this array
	 *  will be stored in the session and could be read with the method @b getObject.
	 *  @param $objectName   Internal unique name of the object.
	 *  @param $object       The object that should be stored in this class.
	 */
	public function addObject($objectName, &$object)
	{
		if(is_object($object) && array_key_exists($objectName, $this->mObjectArray) == false)
		{
			$this->mObjectArray[$objectName]   = &$object;
		}
	}
	
	/** Returns a reference of an object that is stored in the session. If the stored object
	 *  has a database object than this could be renewed if the object name of the database
	 *  object is @b db or @b mDb. This is neccessary because the old database connection is
	 *  not longer valid.
	 *  @param $objectName Internal unique name of the object. The name was set with the method @b addObject
	 *  @return Returns the reference to the object
	 */
	public function &getObject($objectName)
	{
		if(array_key_exists($objectName, $this->mObjectArray))
		{
			// if object has database connection add database object
			if(isset($this->mObjectArray[$objectName]->db))
			{
				$this->mObjectArray[$objectName]->db =& $this->db;
			}
			if(isset($this->mObjectArray[$objectName]->mDb))
			{
				$this->mObjectArray[$objectName]->mDb =& $this->db;
			}
			
			// return reference of object
			return $this->mObjectArray[$objectName];
		}
		return $this;
	}
	
	/** Checks if the object with this name exists in the object array of this class. 
	 *  @param $objectName Internal unique name of the object. The name was set with the method @b addObject
	 *  @return Returns @b true if the object exits otherwise @b false
	 */
	public function hasObject($objectName)
	{
		if(array_key_exists($objectName, $this->mObjectArray))
		{
			return true;
		}
		return false;
	}
	
	/** Reload session data from database table adm_sessions. Check renew flag and
	 *  reload organization object if neccessary.
	 */
	public function refreshSession()
	{
		$this->readDataById($this->getValue('ses_id'));
		
		if($this->getValue('ses_renew') == 2 || $this->getValue('ses_renew') == 3)
		{
			// if flag for reload of organization is set than reload the organization data
			$organization =& $this->getObject('gCurrentOrganization');
			$organizationId = $organization->getValue('org_id');
			$organization->readDataById($organizationId);
			$this->setValue('ses_renew', 0);
		}
	}

	/** If you call this function than a flag is set so that all other active sessions
	 *  know that they should renew the organization object. They will renew it when the
	 *  user perform the next action.
	 */
    public function renewOrganizationObject()
    {
        $sql = 'UPDATE '. TBL_SESSIONS. ' SET ses_renew = 2 ';
        $this->db->query($sql);
    }

	/** If you call this function than a flag is set so that all other active sessions
	 *  know that they should renew their user object. They will renew it when the
	 *  user perform the next action.
	 *  @param $userId (optional) if a user id is set then only user objects of this user id will be renewed
	 */
    public function renewUserObject($userId = 0)
    {
        $sqlCondition = '';
        if($userId > 0 && is_numeric($userId))
        {
            $sqlCondition = ' WHERE ses_usr_id = '. $userId;
        }
        $sql    = 'UPDATE '. TBL_SESSIONS. ' SET ses_renew = 1 '. $sqlCondition;
        $this->db->query($sql);
    }

	/** Save all changed columns of the recordset in table of database. Therefore the class remembers if it's 
	 *  a new record or if only an update is neccessary. The update statement will only update
	 *  the changed columns. If the table has columns for creator or editor than these column
	 *  with their timestamp will be updated.
	 *  For new records the organization, timestamp, begin date and ip address will be set per default.
	 *  @param $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
	 */
    public function save($updateFingerPrint = true)
    {
        if($this->new_record)
        {
            // Insert
            global $gCurrentOrganization;
            $this->setValue('ses_org_id', $gCurrentOrganization->getValue('org_id'));
            $this->setValue('ses_begin', DATETIME_NOW);
            $this->setValue('ses_timestamp', DATETIME_NOW);
            $this->setValue('ses_ip_address', $_SERVER['REMOTE_ADDR']);
        }
        else
        {
            // Update
            $this->setValue('ses_timestamp', DATETIME_NOW);
        }
        parent::save($updateFingerPrint);
    }
	
	/** Deletes all sessions in table admSessions that are inactive since @b $maxInactiveTime minutes..
	 *  @param $maxInactiveTime Time in Minutes after that a session will be deleted. Minimun 30 minutes.
	 */
	public function tableCleanup($maxInactiveTime)
    {
		// determine time when sessions should be deleted (min. 30 minutes)
        if($maxInactiveTime > 30)
        {
            $date_session_delete = time() - $maxInactiveTime * 60;
        }
        else
        {
            $date_session_delete = time() - 30 * 60;
        }
            
        $sql    = 'DELETE FROM '. TBL_SESSIONS. ' 
                    WHERE ses_timestamp < \''. date('Y.m.d H:i:s', $date_session_delete). '\'';
        $this->db->query($sql);
    }
}
?>