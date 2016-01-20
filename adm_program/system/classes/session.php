<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Session
 * @brief Handle session data of Admidio and is connected to database table adm_sessions
 *
 * This class should be used together with the PHP session handling. If you
 * create a PHP session than you should also create this session object. The
 * class will create a recordset in adm_sessions which stores the PHP session id.
 * With this class it should be easy to add other objects to the session and read
 * them out if you need them elsewhere.
 * @par Examples
 * @code script_a.php
 * // add a new object to the session
 * $organization = new Organization($gDb, $organizationId);
 * $session = new Session($gDb, $sessionId);
 * $session->addObject('organization', $organization, true);
 *
 * script_b.php
 * // read object out of session
 * if($session->hasObject('organization'))
 * {
 *     $organization =& $session->getObject('organization');
 * } @endcode
 */
class Session extends TableAccess
{
    protected $mObjectArray = array();  ///< Array with all objects of this session object.
    protected $mAutoLogin;              ///< Object of table auto login that will handle an auto login
    protected $mCookiePrefix;           ///< The prefix that is used for the cookies and identify a cookie for this organization
    protected $mDomain;                 ///< The current domain of this session without any ports

    /**
     * Constructor that will create an object of a recordset of the table adm_sessions.
     * If the id is set than the specific session will be loaded.
     * @param object     $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int|string $session  The recordset of the session with this id will be loaded.
     *                             The session can be the table id or the alphanumeric session id.
     *                             If id isn't set than an empty object of the table is created.
     * @param string     $cookiePrefix The prefix that is used for cookies
     */
    public function __construct(&$database, $session = 0, $cookiePrefix = '')
    {
        parent::__construct($database, TBL_SESSIONS, 'ses');

        $this->mCookiePrefix = $cookiePrefix;
        $this->mDomain       = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));

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
                $this->setValue('ses_timestamp', DATETIME_NOW);
            }
        }

        // if cookie ADMIDIO_DATA is set then there could be an auto login
        // the auto login must be done here because after that the corresponding organization must be set
        if(array_key_exists($cookiePrefix . '_AUTO_LOGIN_ID', $_COOKIE))
        {
            // restore user from auto login session
            $this->mAutoLogin = new AutoLogin($database, $_COOKIE[$cookiePrefix . '_AUTO_LOGIN_ID']);

            // valid AutoLogin found
            if($this->mAutoLogin->getValue('atl_id') > 0)
            {
                $this->mAutoLogin->setValue('atl_session_id', $session);
                $this->mAutoLogin->save();

                $this->setValue('ses_usr_id', $this->mAutoLogin->getValue('atl_usr_id'));
            }
            else
            {
                // an invalid AutoLogin should be executed made the current AutoLogin unusable
                $this->mAutoLogin = null;
                setcookie($this->mCookiePrefix. '_AUTO_LOGIN_ID', $_COOKIE[$cookiePrefix . '_AUTO_LOGIN_ID'], 0, '/', $this->mDomain, 0);

                // now count invalid auto login for this user and delete all auto login of this users if number of wrong logins > 3
                $userId = substr($_COOKIE[$cookiePrefix . '_AUTO_LOGIN_ID'], 0, strpos($_COOKIE[$cookiePrefix . '_AUTO_LOGIN_ID'], ':'));

                $sql = 'UPDATE '.TBL_AUTO_LOGIN.' SET atl_number_invalid = atl_number_invalid + 1
                         WHERE atl_usr_id = '.$userId;
                $this->db->query($sql);

                $sql = 'DELETE FROM '.TBL_AUTO_LOGIN.'
                         WHERE atl_usr_id = '.$userId.'
                           AND atl_number_invalid > 3 ';
                $this->db->query($sql);
            }
        }
    }

    /**
     * Adds an object to the object array of this class. Objects in this array
     * will be stored in the session and could be read with the method @b getObject.
     * @param string $objectName Internal unique name of the object.
     * @param object $object     The object that should be stored in this class.
     * @return bool Return false if object isn't type object or objectName already exists
     */
    public function addObject($objectName, &$object)
    {
        if(is_object($object) && !array_key_exists($objectName, $this->mObjectArray))
        {
            $this->mObjectArray[$objectName] = &$object;
            return true;
        }
        return false;
    }

    /**
     * Returns a reference of an object that is stored in the session. If the stored object
     * has a database object than this could be renewed if the object has a method @b setDatabase.
     * This is necessary because the old database connection is not longer valid.
     * @param string $objectName Internal unique name of the object. The name was set with the method @b addObject
     * @return object|false Returns the reference to the object or false if the object was not found.
     */
    public function &getObject($objectName)
    {
        $returnParam = false;

        if(array_key_exists($objectName, $this->mObjectArray))
        {
            // if object has database connection add database object
            if(method_exists($this->mObjectArray[$objectName], 'setDatabase'))
            {
                $this->mObjectArray[$objectName]->setDatabase($this->db);
            }

            // return reference of object
            return $this->mObjectArray[$objectName];
        }

        // use parameter because we return a reference so only value will return an error
        return $returnParam;
    }

    /**
     * Return the organization id of this session. If AutoLogin is enabled then the
     * organization may not be the organization of the config.php because the
     * user had set the AutoLogin to a different organization.
     * @return int Returns the organization id of this session
     */
    public function getOrganizationId()
    {
        if(is_object($this->mAutoLogin))
        {
            return (int) $this->mAutoLogin->getValue('atl_org_id');
        }
        else
        {
            return (int) $this->getValue('ses_org_id');
        }
    }

    /**
     * Checks if the object with this name exists in the object array of this class.
     * @param string $objectName Internal unique name of the object. The name was set with the method @b addObject
     * @return bool Returns @b true if the object exits otherwise @b false
     */
    public function hasObject($objectName)
    {
        return array_key_exists($objectName, $this->mObjectArray);
    }

    /**
     * Check if the current session has a valid user login. Therefore the user id must be stored
     * within the session and the timestamps must be valid
     * @param int $userId The user id must be stored in this session and will be checked if valid.
     * @return bool Returns @b true if the user has a valid session login otherwise @b false;
     */
    public function isValidLogin($userId)
    {
        global $gPreferences, $gCurrentUser;

        if($userId > 0)
        {
            if($this->getValue('ses_usr_id') === $userId)
            {
                // session has a user assigned -> check if login is still valid
                $time_gap = time() - strtotime($this->getValue('ses_timestamp', 'Y-m-d H:i:s'));

                // Check how long the user was inactive. If time range is to long -> logout
                // if user has auto login than session is also valid
                if($time_gap < $gPreferences['logout_minutes'] * 60 || is_object($this->mAutoLogin))
                {
                    $this->setValue('ses_timestamp', DATETIME_NOW);
                    return true;
                }
                else
                {
                    // user was inactive -> clear user data and remove him from session
                    if (isset($gCurrentUser))
                    {
                        $gCurrentUser->clear();
                    }
                    $this->setValue('ses_usr_id', '');
                }
            }
            else
            {
                // something is wrong -> clear user data
                if (isset($gCurrentUser))
                {
                    $gCurrentUser->clear();
                }
                $this->setValue('ses_usr_id', '');
            }
        }

        return false;
    }

    /**
     * The current user should be removed from the session and auto login.
     * Also the auto login cookie should be removed.
     */
    public function logout()
    {
        $this->db->startTransaction();

        // remove user from current session
        $this->setValue('ses_usr_id', '');
        $this->save();

        if(is_object($this->mAutoLogin))
        {
            // remove auto login cookie from users browser by setting expired timestamp to 0
            setcookie($this->mCookiePrefix. '_AUTO_LOGIN_ID', $this->mAutoLogin->getValue('atl_auto_login_id'), 0, '/', $this->mDomain, 0);

            // delete auto login and remove all data
            $this->mAutoLogin->delete();
            $this->mAutoLogin = null;
        }

        $this->db->endTransaction();
    }

    /**
     * Reload session data from database table adm_sessions. Refresh AutoLogin with
     * new auto_login_id. Check renew flag and reload organization object if necessary.
     */
    public function refreshSession()
    {
        global $gCheckIpAddress, $gDebug;

        // read session data from database to update the renew flag
        $this->readDataById($this->getValue('ses_id'));

        // check if current connection has same ip address as of session initialization
        // if config parameter $gCheckIpAddress = 0 then don't check ip address
        if($this->getValue('ses_ip_address') !== $_SERVER['REMOTE_ADDR']
        && $this->getValue('ses_ip_address') !== ''
        && (!isset($gCheckIpAddress) || $gCheckIpAddress === 1))
        {
            if($gDebug)
            {
                error_log('Admidio stored session ip address: '.$this->getValue('ses_ip_address'). ' :: Remode ip address: '.$_SERVER['REMOTE_ADDR']);
            }

            unset($_SESSION['gCurrentSession']);
            $this->mObjectArray = array();
            $this->clear();

            exit('The IP address doesnot match with the IP address the current session was started! For safety reasons the current session was closed.');
        }

        // if AutoLogin is set then refresh the auto_login_id for security reasons
        if(is_object($this->mAutoLogin))
        {
            $this->mAutoLogin->setValue('atl_auto_login_id', $this->mAutoLogin->generateAutoLoginId($this->getValue('ses_usr_id')));
            $this->mAutoLogin->save();

            // save cookie for autologin
            $timestampExpired = time() + 60*60*24*365;
            setcookie($this->mCookiePrefix. '_AUTO_LOGIN_ID', $this->mAutoLogin->getValue('atl_auto_login_id'), $timestampExpired, '/', $this->mDomain, 0);
        }

        // if flag for reload of organization is set than reload the organization data
        $sesRenew = (int) $this->getValue('ses_renew');
        if($sesRenew === 2 || $sesRenew === 3)
        {
            $organization =& $this->getObject('gCurrentOrganization');
            $organizationId = $organization->getValue('org_id');
            $organization->readDataById($organizationId);
            $this->setValue('ses_renew', 0);
        }
    }

    /**
     * If you call this function than a flag is set so that all other active sessions
     * know that they should renew the organization object. They will renew it when the
     * user perform the next action.
     */
    public function renewOrganizationObject()
    {
        $sql = 'UPDATE ' . TBL_SESSIONS . ' SET ses_renew = 2';
        $this->db->query($sql);
    }

    /**
     * If you call this function than a flag is set so that all other active sessions
     * know that they should renew their user object. They will renew it when the
     * user perform the next action.
     * @param int $userId (optional) if a user id is set then only user objects of this user id will be renewed
     */
    public function renewUserObject($userId = 0)
    {
        $sqlCondition = '';
        if(is_numeric($userId) && $userId > 0)
        {
            $sqlCondition = ' WHERE ses_usr_id = ' . $userId;
        }
        $sql = 'UPDATE ' . TBL_SESSIONS . ' SET ses_renew = 1 ' . $sqlCondition;
        $this->db->query($sql);
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the organization, timestamp, begin date and ip address will be set per default.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool If an update or insert into the database was done then return true, otherwise false.
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
        return parent::save($updateFingerPrint);
    }

    /**
     * Save all data that is necessary for an auto login. Therefore an AutoLogin object
     * will be created with an auto_login_id and this id will be stored in a cookie
     * in the browser of the current user.
     */
    public function setAutoLogin()
    {
        // create object and set current session data to AutoLogin
        $this->mAutoLogin = new AutoLogin($this->db);
        $this->mAutoLogin->setValue('atl_session_id', $this->getValue('ses_session_id'));
        $this->mAutoLogin->setValue('atl_org_id', $this->getValue('ses_org_id'));
        $this->mAutoLogin->setValue('atl_usr_id', $this->getValue('ses_usr_id'));

        // set new auto_login_id and save data
        $this->mAutoLogin->setValue('atl_auto_login_id', $this->mAutoLogin->generateAutoLoginId($this->getValue('ses_usr_id')));
        $this->mAutoLogin->save();

        // save cookie for autologin
        $timestampExpired = time() + 60*60*24*365;
        setcookie($this->mCookiePrefix. '_AUTO_LOGIN_ID', $this->mAutoLogin->getValue('atl_auto_login_id'), $timestampExpired, '/', $this->mDomain, 0);
    }

    /**
     * Set the database object for communication with the database of this class.
     * @param object $database An object of the class Database. This should be the global $gDb object.
     */
    public function setDatabase(&$database)
    {
        parent::setDatabase($database);

        if(is_object($this->mAutoLogin))
        {
            $this->mAutoLogin->setDatabase($database);
        }
    }

    /**
     * Deletes all sessions in table admSessions that are inactive since @b $maxInactiveTime minutes..
     * @param int $maxInactiveMinutes Time in Minutes after that a session will be deleted. Minimum 30 minutes.
     */
    public function tableCleanup($maxInactiveMinutes)
    {
        // determine time when sessions should be deleted (min. 30 minutes)
        if($maxInactiveMinutes > 30)
        {
            $maxInactiveMinutes = 30;
        }

        $now = new DateTime();
        $minutesBack = new DateInterval('PT'.$maxInactiveMinutes.'M');
        $timestamp = $now->sub($minutesBack)->format('Y-m-d H:i:s');

        $sql = 'DELETE FROM '.TBL_SESSIONS.'
                 WHERE ses_timestamp < \''.$timestamp.'\'
                   AND ses_session_id NOT LIKE \''.$this->getValue('ses_session_id').'\'';
        $this->db->query($sql);
    }
}
