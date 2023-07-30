<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Handle session data of Admidio and is connected to database table adm_sessions
 *
 * This class should be used together with the PHP session handling. If you
 * create a PHP session than you should also create this session object. The
 * class will create a recordset in adm_sessions which stores the PHP session id.
 * With this class it should be easy to add other objects to the session and read
 * them out if you need them elsewhere.
 *
 * **Code examples**
 * ```
 * // add a new object to the session
 * $organization = new Organization($gDb, $organizationId);
 * $session = new Session($gDb, $sessionId);
 * $session->addObject('organization', $organization, true);
 *
 * // read object out of session
 * if($session->hasObject('organization'))
 * {
 *     $organization =& $session->getObject('organization');
 * }
 * ```
 */
class Session extends TableAccess
{
    /**
     * @var array<string,mixed> Array with all objects of this session object.
     */
    protected $mObjectArray = array();
    /**
     * @var AutoLogin|null Object of table auto login that will handle an auto login
     */
    protected $mAutoLogin;
    /**
     * @var string
     */
    protected $cookieAutoLoginId;
    /**
     * @var string a 30 character long CSRF token
     */
    protected $csrfToken = '';

    /**
     * Constructor that will create an object of a recordset of the table adm_sessions.
     * If the id is set than the specific session will be loaded.
     * @param Database   $database     Object of the class Database. This should be the default global object **$gDb**.
     * @param string     $cookiePrefix The prefix that is used for cookies
     */
    public function __construct(Database $database, $cookiePrefix = '')
    {
        parent::__construct($database, TBL_SESSIONS, 'ses');

        // determine session id
        if (array_key_exists(COOKIE_PREFIX . '_SESSION_ID', $_COOKIE)) {
            $sessionId = $_COOKIE[COOKIE_PREFIX . '_SESSION_ID'];
        } else {
            $sessionId = session_id();
        }

        $this->cookieAutoLoginId = $cookiePrefix . '_AUTO_LOGIN_ID';

        if (is_int($sessionId)) {
            $this->readDataById($sessionId);
        } else {
            $this->readDataByColumns(array('ses_session_id' => $sessionId));

            if ($this->newRecord) {
                // if PHP session id was committed then store them in that field
                $this->setValue('ses_session_id', $sessionId);
                $this->setValue('ses_timestamp', DATETIME_NOW);
            }
        }

        // check for a valid auto login
        $this->refreshAutoLogin();
    }

    /**
     * Adds an object to the object array of this class. Objects in this array
     * will be stored in the session and could be read with the method **getObject**.
     * @param string $objectName Internal unique name of the object.
     * @param object $object     The object that should be stored in this class.
     * @return bool Return false if object isn't type object or objectName already exists
     */
    public function addObject(string $objectName, &$object): bool
    {
        if (is_object($object) && !array_key_exists($objectName, $this->mObjectArray)) {
            $this->mObjectArray[$objectName] = &$object;
            return true;
        }
        return false;
    }

    /**
     * clear user data
     */
    protected function clearUserData()
    {
        global $gCurrentUser;

        if (isset($gCurrentUser) && $gCurrentUser instanceof User) {
            $gCurrentUser->clear();
        }
        $this->setValue('ses_usr_id', '');
    }

    /**
     * Returns a CSRF token from the session. If no CSRF token exists a new one will be
     * generated and stored within the session. The next call of the method will than
     * return the existing token. The CSRF token has 30 characters. A new token could
     * be forced by the parameter **$newToken**
     * @param bool $newToken If set to true, always a new token will be generated.
     * @return string Returns the CSRF token
     * @throws AdmException
     * @throws AdmException
     */
    public function getCsrfToken(bool $newToken = false): string
    {
        if ($this->csrfToken === '' || $newToken) {
            $this->csrfToken = SecurityUtils::getRandomString(30);
        }

        return $this->csrfToken;
    }

    /**
     * Returns a reference of an object that is stored in the session.
     * This is necessary because the old database connection is not valid anymore.
     * @param string $objectName Internal unique name of the object. The name was set with the method **addObject**
     * @return object|false Returns the reference to the object or false if the object was not found.
     */
    public function &getObject(string $objectName)
    {
        if (!array_key_exists($objectName, $this->mObjectArray)) {
            // use parameter because we return a reference so only value will return an error
            $returnValue = false;
            return $returnValue;
        }

        // return reference of object
        return $this->mObjectArray[$objectName];
    }

    /**
     * Return the organization id of this session. If AutoLogin is enabled then the
     * organization may not be the organization of the config.php because the
     * user had set the AutoLogin to a different organization.
     * @return int Returns the organization id of this session
     */
    public function getOrganizationId(): int
    {
        if ($this->mAutoLogin instanceof AutoLogin) {
            return (int) $this->mAutoLogin->getValue('atl_org_id');
        }

        return (int) $this->getValue('ses_org_id');
    }

    /**
     * Checks if the object with this name exists in the object array of this class.
     * @param string $objectName Internal unique name of the object. The name was set with the method **addObject**
     * @return bool Returns **true** if the object exits otherwise **false**
     */
    public function hasObject(string $objectName): bool
    {
        return array_key_exists($objectName, $this->mObjectArray);
    }

    /**
     * Initialize the array with all objects except the gNavigation object. If the session got a refresh
     * the existing navigation should still be stored in the refreshed session.
     */
    public function initializeObjects()
    {
        foreach($this->mObjectArray as $key => $element) {
            if($key !== 'gNavigation') {
                unset($this->mObjectArray[$key]);
            }
        }
    }

    /**
     * Check if the current session has a valid user login. Therefore the user id must be stored
     * within the session and the timestamps must be valid
     * @param int $userId The user id must be stored in this session and will be checked if valid.
     * @return bool Returns **true** if the user has a valid session login otherwise **false**;
     */
    public function isValidLogin(int $userId): bool
    {
        global $gSettingsManager;

        if ($userId > 0) {
            if ((int) $this->getValue('ses_usr_id') === $userId) {
                // session has a user assigned -> check if login is still valid
                $timeGap = time() - strtotime($this->getValue('ses_timestamp', 'Y-m-d H:i:s'));

                // Check how long the user was inactive. If time range is too long -> logout
                // if user has auto login than session is also valid
                if ($this->mAutoLogin instanceof AutoLogin || $timeGap < $gSettingsManager->getInt('logout_minutes') * 60) {
                    return true;
                }
            }

            // user was inactive -> clear user data and remove him from session
            // something is wrong -> clear user data
            $this->clearUserData();
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

        if ($this->mAutoLogin instanceof AutoLogin) {
            // remove auto login cookie from users browser by setting expired timestamp to 0
            self::setCookie($this->cookieAutoLoginId, $this->mAutoLogin->getValue('atl_auto_login_id'));

            // delete auto login and remove all data
            $this->mAutoLogin->delete();
            $this->mAutoLogin = null;
        }

        $this->db->endTransaction();

        // remove session object with all data
        session_unset();
        session_destroy();
    }

    /**
     * Reload auto login data from database table adm_auto_login. if cookie PREFIX_AUTO_LOGIN_ID
     * is set then there could be an auto login the auto login must be done here because after
     * that the corresponding organization must be set.
     */
    public function refreshAutoLogin()
    {
        if (array_key_exists($this->cookieAutoLoginId, $_COOKIE)) {
            // restore user from auto login session
            $this->mAutoLogin = new AutoLogin($this->db, $_COOKIE[$this->cookieAutoLoginId]);

            // valid AutoLogin found
            if ($this->mAutoLogin->getValue('atl_id') > 0) {
                $autoLoginId = $this->mAutoLogin->generateAutoLoginId((int) $this->getValue('ses_usr_id'));
                $this->mAutoLogin->setValue('atl_auto_login_id', $autoLoginId);
                $this->mAutoLogin->setValue('atl_session_id', $this->getValue('ses_session_id'));
                $this->mAutoLogin->save();

                $this->setValue('ses_usr_id', (int) $this->mAutoLogin->getValue('atl_usr_id'));

                // save cookie for autologin
                $currDateTime = new DateTime();
                $oneYearDateInterval = new DateInterval('P1Y');
                $oneYearAfterDateTime = $currDateTime->add($oneYearDateInterval);
                $timestampExpired = $oneYearAfterDateTime->getTimestamp();

                self::setCookie($this->cookieAutoLoginId, $this->mAutoLogin->getValue('atl_auto_login_id'), $timestampExpired);
            } else {
                // an invalid AutoLogin should made the current AutoLogin unusable
                $this->mAutoLogin = null;
                self::setCookie($this->cookieAutoLoginId, $_COOKIE[$this->cookieAutoLoginId]);

                // now count invalid auto login for this user and delete all auto login of this users if number of wrong logins > 3
                $autoLoginParts = explode(':', $_COOKIE[$this->cookieAutoLoginId]);
                $userId = $autoLoginParts[0];

                if ($userId > 0) {
                    $sql = 'UPDATE '.TBL_AUTO_LOGIN.'
                               SET atl_number_invalid = atl_number_invalid + 1
                             WHERE atl_usr_id = ? -- $userId';
                    $this->db->queryPrepared($sql, array($userId));

                    $sql = 'DELETE FROM '.TBL_AUTO_LOGIN.'
                             WHERE atl_usr_id = ? -- $userId
                               AND atl_number_invalid > 3 ';
                    $this->db->queryPrepared($sql, array($userId));
                }
            }
        }
    }

    /**
     * Reload session data from database table adm_sessions. If IP address check is activated than check if the IP
     * address has changed. Refresh AutoLogin with new auto_login_id.
     */
    public function refresh()
    {
        // read session data from database to update the reload flag
        if(!$this->readDataById((int) $this->getValue('ses_id'))) {
            // if session was not found than destroy session object
            unset($_SESSION['gCurrentSession']);
            $this->initializeObjects();
            $this->clear();
        }

        // check if current connection has same ip address as of session initialization
        // if config parameter $gCheckIpAddress = 0 then don't check ip address
        $sesIpAddress = $this->getValue('ses_ip_address');
        if (isset($GLOBALS['gCheckIpAddress']) && $GLOBALS['gCheckIpAddress'] && $sesIpAddress !== '' && $sesIpAddress !== $_SERVER['REMOTE_ADDR']) {
            $GLOBALS['gLogger']->warning('Admidio stored session ip address: ' . $sesIpAddress . ' :: Remote ip address: ' . $_SERVER['REMOTE_ADDR']);
            $GLOBALS['gLogger']->warning('The IP address does not match with the IP address the current session was started! For safety reasons the current session was closed.');

            unset($_SESSION['gCurrentSession']);
            $this->initializeObjects();
            $this->clear();

            exit('The IP address does not match with the IP address the current session was started! For safety reasons the current session was closed.');
        }

        // session in database could be deleted if user was some time inactive and another user
        // clears the table. Therefor we must reset the user id
        if ($this->mAutoLogin instanceof AutoLogin) {
            if ((int) $this->getValue('ses_usr_id') === 0) {
                $this->setValue('ses_usr_id', (int) $this->mAutoLogin->getValue('atl_usr_id'));
            }
        } elseif (array_key_exists($this->cookieAutoLoginId, $_COOKIE)) {
            $this->refreshAutoLogin();
        }
    }

    /**
     * This method will replace the current session ID with a new one, and keep the current session information.
     * The new session id will be stored in the database.
     */
    public function regenerateId()
    {
        session_regenerate_id();

        $this->setValue('ses_session_id', session_id());
        $this->save();
    }

    /**
     * This method will reload all stored objects of all active sessions. The session will be
     * reloaded if the user will open a new page.
     */
    public function reloadAllSessions()
    {
        $sql = 'UPDATE ' . TBL_SESSIONS . ' SET ses_reload = true ';
        $this->db->queryPrepared($sql, array(), false); // don't show error because column ses_reload doesn't exist within update from version 3.x
    }

    /**
     * This method will reload the session of a specific user. All stored objects of the session will be initialized
     * and reloaded if the user opens a new page.
     * @param int $userId Id of the user whose session should be relaoded.
     */
    public function reload(int $userId)
    {
        $sql = 'UPDATE ' . TBL_SESSIONS . ' SET ses_reload = true
                 WHERE ses_usr_id = ?  -- $userId';
        $this->db->queryPrepared($sql, array($userId), false); // don't show error because column ses_reload doesn't exist within update from version 3.x
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the organization, timestamp, begin date and ip address will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true): bool
    {
        global $gCurrentOrgId;

        if ($this->newRecord) {
            // Insert
            $this->setValue('ses_org_id', $gCurrentOrgId);
            $this->setValue('ses_begin', DATETIME_NOW);
            // remove the last part of the IP because of privacy (GDPR)
            $ip = preg_replace(array('/\.\d+$/', '/[\da-f]*:[\da-f]*$/'), array('.XXX', 'XXXX:XXXX'), $_SERVER['REMOTE_ADDR']);
            $this->setValue('ses_ip_address', $ip);
        }

        // Insert & Update
        $this->setValue('ses_timestamp', DATETIME_NOW);

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
        $this->mAutoLogin->setValue('atl_org_id', (int) $this->getValue('ses_org_id'));
        $this->mAutoLogin->setValue('atl_usr_id', (int) $this->getValue('ses_usr_id'));

        // set new auto_login_id and save data
        $this->mAutoLogin->setValue('atl_auto_login_id', $this->mAutoLogin->generateAutoLoginId((int) $this->getValue('ses_usr_id')));
        $this->mAutoLogin->save();

        // save cookie for autologin
        $currDateTime = new DateTime();
        $oneYearDateInterval = new DateInterval('P1Y');
        $oneYearAfterDateTime = $currDateTime->add($oneYearDateInterval);
        $timestampExpired = $oneYearAfterDateTime->getTimestamp();

        self::setCookie($this->cookieAutoLoginId, $this->mAutoLogin->getValue('atl_auto_login_id'), $timestampExpired);
    }

    /**
     * @param string $name     Name of the cookie.
     * @param string $value    Value of the cookie. If value is "empty string" or "false",
     *                         the cookie will be set as deleted (Expire is set to 1 year in the past).
     * @param int $expire   The Unix-Timestamp (Seconds) of the Date/Time when the cookie should expire.
     *                         With "0" the cookie will expire if the session ends. (When Browser gets closed)
     * @param string $path     Specify the path where the cookie should be available. (Also in sub-paths)
     * @param string $domain   Specify the domain where the cookie should be available. (Set ".example.org" to allow sub-domains)
     * @param bool|null $secure   If "true" cookie is only set if connection is HTTPS. Default is an auto detection.
     * @param bool $httpOnly If "true" cookie is accessible only via HTTP.
     *                         Set to "false" to allow access for JavaScript. (Possible XSS security leak)
     * @return bool Returns "true" if the cookie is successfully set.
     */
    public static function setCookie(
        string $name,
        string $value = '',
        int $expire = 0,
        string $path = '',
        string $domain = '',
        bool $secure = null,
        bool $httpOnly = true
    ): bool {
        global $gLogger, $gSetCookieForDomain;

        if ($path === '') {
            if ($gSetCookieForDomain) {
                $path = '/';
            } else {
                $path = ADMIDIO_URL_PATH . '/';
            }
        }
        if ($domain === '') {
            $domain = DOMAIN;
            // https://www.php.net/manual/en/function.setcookie.php#73107
            if ($domain === 'localhost') {
                $domain = false;
            }
        }
        if ($secure === null) {
            $secure = HTTPS;
        }

        $gLogger->info('Set Cookie!', array('name' => $name, 'value' => $value, 'expire' => $expire, 'path' => $path, 'domain' => $domain, 'secure' => $secure, 'httpOnly' => $httpOnly, 'sameSite' => 'lax'));

        if (PHP_VERSION_ID < 70300) {
            return setcookie($name, $value, $expire, $path. ';samesite=lax', $domain, $secure, $httpOnly);
        } else {
            return setcookie($name, $value, array(
                'expires'  => $expire,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => $httpOnly,
                'samesite' => 'lax'
            ));
        }
    }

    /**
     * @param string $cookiePrefix The prefix name of the Cookie.
     * @param int $limit        The Lifetime (Seconds) of the cookie when it should expire.
     *                             With "0" the cookie will expire if the session ends. (When Browser gets closed)
     * @param string $path         Specify the path where the cookie should be available. (Also in sub-paths)
     * @param string $domain       Specify the domain where the cookie should be available. (Set ".example.org" to allow sub-domains)
     * @param bool|null $secure       If "true" cookie is only set if connection is HTTPS. Default is an auto detection.
     * @param bool $httpOnly     If "true" cookie is accessible only via HTTP.
     *                             Set to "false" to allow access for JavaScript. (Possible XSS security leak)
     * @throws RuntimeException
     */
    public static function start(string $cookiePrefix, int $limit = 0, string $path = '', string $domain = '', bool $secure = null, bool $httpOnly = true)
    {
        global $gLogger, $gSetCookieForDomain;

        if (headers_sent()) {
            $message = 'HTTP-Headers already sent!';
            $gLogger->alert($message);

            throw new RuntimeException($message);
        }

        $sessionName = $cookiePrefix . '_SESSION_ID';

        // Set the cookie name
        session_name($sessionName);

        if ($path === '') {
            if ($gSetCookieForDomain) {
                $path = '/';
            } else {
                $path = ADMIDIO_URL_PATH . '/';
            }
        }

        if ($domain === '') {
            $domain = DOMAIN;

            // TODO: Test if this is necessary
            // https://www.php.net/manual/en/function.setcookie.php#73107
            if ($domain === 'localhost') {
                $domain = false;
            }
        }
        if ($secure === null) {
            $secure = HTTPS;
        }

        if (PHP_VERSION_ID < 70300) {
            session_set_cookie_params($limit, $path. ';samesite=lax', $domain, $secure, $httpOnly);
        } else {
            session_set_cookie_params(array(
                'lifetime' => $limit,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => $httpOnly,
                'samesite' => 'lax'
            ));
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $gLogger->notice('Session is already started!', array('sessionId' => session_id()));
        }

        // Start session
        session_start();

        $gLogger->info('Session Started!', array('name' => $sessionName, 'limit' => $limit, 'path' => $path, 'domain' => $domain, 'secure' => $secure, 'httpOnly' => $httpOnly, 'sameSite' => 'lax', 'sessionId' => session_id()));
    }

    /**
     * Deletes all sessions in table admSessions that are inactive since **$maxInactiveTime** minutes..
     * @param int $maxInactiveMinutes Time in Minutes after that a session will be deleted.
     * @throws Exception
     * @throws Exception
     */
    public function tableCleanup(int $maxInactiveMinutes = 30)
    {
        $now = new DateTime();
        $minutesBack = new DateInterval('PT' . $maxInactiveMinutes . 'M');
        $timestamp = $now->sub($minutesBack)->format('Y-m-d H:i:s');

        $sql = 'DELETE FROM '.TBL_SESSIONS.'
                 WHERE ses_timestamp < ? -- $timestamp
                   AND ses_session_id <> ? -- $this->getValue(\'ses_session_id\')';
        $this->db->queryPrepared($sql, array($timestamp, $this->getValue('ses_session_id')));
    }
}
