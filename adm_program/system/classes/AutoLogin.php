<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Handle auto login with Admidio and manage it in the database
 *
 * The class search in the database table **adm_auto_login** for the session id.
 * If there is an entry for that id then it reads the user id and set this
 * user to the current session. Now the current session has become a valid user
 * that is automatically login.
 *
 * **Code examples**
 * ```
 * // create a valid user login for a Admidio session from auto login
 * $autoLogin = new AutoLogin($gDb, $sessionId);
 * $autoLogin->setValidLogin($gCurrentSession, $_COOKIE['ADMIDIO_ID']);
 *
 * // delete an auto login
 * $autoLogin = new AutoLogin($gDb, $sessionId);
 * $autoLogin->delete();
 * ```
 */
class AutoLogin extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_auto_login.
     * If the id is set than the specific auto login will be loaded.
     * @param Database   $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string|int $session  The recordset of the auto login with this session will be loaded.
     *                             If session isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $session = 0)
    {
        parent::__construct($database, TBL_AUTO_LOGIN, 'atl');

        // if not integer than the auto-login-id is commited
        if (is_int($session)) {
            $this->readDataById($session);
        } else {
            $this->readDataByColumns(array('atl_auto_login_id' => $session));
        }
    }

    /**
     * Creates a new unique auto login id for this user.
     * @param int $userId The id of the current user.
     * @return string Returns the auto login id.
     */
    public function generateAutoLoginId($userId)
    {
        $loginId = '';

        try {
            $loginId = $userId . ':' . SecurityUtils::getRandomString(40);
        } catch (AdmException $e) {
            $e->showText();
            // => EXIT
        }

        return $loginId;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * The current organization, last login and ip address will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset
     *                                if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        // Insert & Update
        $this->setValue('atl_last_login', DATETIME_NOW);

        if ($this->newRecord) {
            // Insert
            $this->setValue('atl_org_id', $GLOBALS['gCurrentOrgId']);

            // Clean up table when a new record is written
            $this->tableCleanup();
        }

        return parent::save($updateFingerPrint);
    }

    /**
     * Method will clean the database table **adm_auto_login**.
     * All login that had their last login one year ago will be deleted.
     * All counted wrong auto login ids from this user will be reset.
     */
    public function tableCleanup()
    {
        // Zeitpunkt bestimmen, ab dem die Auto-Logins geloescht werden, mind. 1 Jahr alt
        $currDateTime = new \DateTime();
        $oneYearDateInterval = new \DateInterval('P1Y');
        $oneYearBeforeDateTime = $currDateTime->sub($oneYearDateInterval);
        $dateSessionDelete = $oneYearBeforeDateTime->format('Y-m-d H:i:s');

        $sql = 'DELETE FROM '.TBL_AUTO_LOGIN.'
                 WHERE atl_last_login < ? -- $dateSessionDelete';
        $this->db->queryPrepared($sql, array($dateSessionDelete));

        // reset all counted wrong auto login ids from this user to prevent
        // a deadlock if user has auto login an several devices and they were
        // set invalid for security reasons
        $sql = 'UPDATE '.TBL_AUTO_LOGIN.'
                   SET atl_number_invalid = 0
                 WHERE atl_usr_id = ? -- $this->getValue(\'atl_usr_id\')';
        $this->db->queryPrepared($sql, array((int) $this->getValue('atl_usr_id')));
    }
}
